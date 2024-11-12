<?php

namespace App\Http\Controllers\Standby;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Exceptions\MyException;
use Exception;

use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\StandbyTrx;
use App\Models\MySql\IsUser;
use App\Models\MySql\StandbyDtl;
use App\Models\MySql\StandbyMst;
use App\Models\MySql\StandbyTrxDtl;

use App\Http\Requests\MySql\StandbyTrxRequest;

use App\Http\Resources\MySql\StandbyTrxResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\TrxTrp;

class StandbyTrxController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function loadLocal()
  {
    MyAdmin::checkMultiScope($this->permissions, ['standby_trx.create','standby_trx.modify']);

    $list_standby_mst = \App\Models\MySql\StandbyMst::where("deleted",0)->where('val',1)->where('val1',1)->get();
    $list_xto = \App\Models\MySql\Ujalan::select('xto')->where("deleted",0)->where('val',1)->where('val1',1)->groupBy('xto')->get()->pluck('xto');

    $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->get();
    $list_employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->available()->verified()->whereIn("role",['Supir','Kernet','BLANK'])->get();
    
    return response()->json([
      "list_standby_mst" => $list_standby_mst,
      "list_vehicle" => $list_vehicle,
      "list_employee" => $list_employee,
      "list_xto" => $list_xto,
    ], 200);
  }

  public function loadSqlSrv(Request $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['standby_trx.create','standby_trx.modify']);

    $online_status = $request->online_status;

    $list_cost_center=[];
    $connectionDB = DB::connection('sqlsrv');
    try {
      if($online_status=="true"){
        
        $list_cost_center = $connectionDB->table("AC_CostCenterNames")
        ->select('CostCenter','Description')
        ->where('CostCenter','like', '112%')
        ->get();
  
        $list_cost_center= MyLib::objsToArray($list_cost_center); 
      }
    } catch (\Exception $e) {
      // return response()->json($e->getMessage(), 400);
    }
    
    return response()->json([
      "list_cost_center" => $list_cost_center,
    ], 200);
  }

  public function index(Request $request, $download = false)
  {
    MyAdmin::checkScope($this->permissions, 'standby_trx.views');
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 100; // Limit +> Much Data
    if (isset($request->limit)) {
      if ($request->limit <= 250) {
        $limit = $request->limit;
      } else {
        throw new MyException(["message" => "Max Limit 250"]);
      }
    }

    $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

    //======================================================================================================
    // Jika Halaman Ditentutkan maka $offset akan disesuaikan
    //======================================================================================================
    if (isset($request->page)) {
      $page =  (int) $request->page;
      $offset = ($page * $limit) - $limit;
    }


    //======================================================================================================
    // Init Model
    //======================================================================================================
    $model_query = new StandbyTrx();
    if (!$download) {
      $model_query = $model_query->offset($offset)->limit($limit);
    }

    $first_row=[];
    if($request->first_row){
      $first_row 	= json_decode($request->first_row, true);
    }

    //======================================================================================================
    // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
    //======================================================================================================

    if ($request->like) {
      $like_lists = [];

      $likes = explode(",", $request->like);
      foreach ($likes as $key => $like) {
        $side = explode(":", $like);
        $side[1] = isset($side[1]) ? $side[1] : '';
        $like_lists[$side[0]] = $side[1];
      }

      $list_to_like = ["id","transition_target","transition_type",
      "supir","kernet","no_pol","xto","pvr_id","pvr_no","pv_id","pv_no",
      "rv_id","rv_no","cost_center_code","cost_center_desc"];

      $list_to_like_user = [
        ["val_name","val_user"],
        ["val1_name","val1_user"],
        ["val2_name","val2_user"],
        ["req_deleted_name","req_deleted_user"],
        ["deleted_name","deleted_user"],
      ];

      $list_to_like_standby_mst = [
        ["standby_mst_name","name"],
        ["standby_mst_type","tipe"]
      ];

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_user,$list_to_like_standby_mst){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          foreach ($list_to_like_user as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
                $q2->from('is_users')
                ->select('id')->where("username",'like',$like_lists[$v[0]]);          
              });
            }
          }

          foreach ($list_to_like_standby_mst as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('standby_mst_id', function($q2)use($like_lists,$v) {
                $q2->from('standby_mst')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
              });
            }
          }
        });        
      }     
    }
    

    //======================================================================================================
    // Model Sorting And Filtering
    //======================================================================================================

    $fm_sorts=[];
    if($request->filter_model){
      $filter_model = json_decode($request->filter_model,true);
  
      foreach ($filter_model as $key => $value) {
        if($value["sort_priority"] && $value["sort_type"]){
          array_push($fm_sorts,[
            "key"    =>$key,
            "priority"=>$value["sort_priority"],
          ]);
        }
      }

      if(count($fm_sorts)>0){
        usort($fm_sorts, function($a, $b) {return (int)$a['priority'] - (int)$b['priority'];});
        foreach ($fm_sorts as $key => $value) {
          $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
          if (count($first_row) > 0) {
            $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
            $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
          }
        }
      }

      $model_query = $model_query->where(function ($q)use($filter_model,$request){

        foreach ($filter_model as $key => $value) {
          if(!isset($value['type'])) continue;

          if(array_search($key,['status'])!==false){
          }else if(array_search($key,['standby_trx_dtl_tanggal'])!==false){
            MyLib::queryCheckC1("standby_trx_dtl","standby_trx",$value,$key,$q);
          } else{
            MyLib::queryCheck($value,$key,$q);
          }
        }
        
         
       
        // if (isset($like_lists["requested_name"])) {
        //   $q->orWhereIn("requested_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['requested_name']);          
        //   });
        // }
  
        // if (isset($like_lists["confirmed_name"])) {
        //   $q->orWhereIn("confirmed_by", function($q2)use($like_lists) {
        //     $q2->from('is_users')
        //     ->select('id_user')->where("username",'like',$like_lists['confirmed_name']);          
        //   });
        // }
      });  
    }
    
    if(!$request->filter_model || count($fm_sorts)==0){
      $model_query = $model_query->orderBy('updated_at', 'DESC')->orderBy('id','DESC');
    }
    
    $filter_status = $request->filter_status;

    if($filter_status=="trx_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)
      ->where(function ($q){
        $q->whereNotNull("pv_no");
        $q->orWhereNotNull("salary_paid_id");
      });
    }

    if($filter_status=="trx_not_done"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)
      ->where(function ($q){
        $q->whereNull("pv_no");
        $q->WhereNull("salary_paid_id");
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
    }

    $model_query = $model_query->with(['val_by','val1_by','val2_by','deleted_by','req_deleted_by','standby_mst','salary_paid','details'=>function($q) {
      $q->orderBy("tanggal","asc");
      $q->select("id", "tanggal","standby_trx_id","attachment_1_type","be_paid");
    }])
    ->withCount('details')->get();

    return response()->json([
      "data" => StandbyTrxResource::collection($model_query),
    ], 200);
  }

  public function show(StandbyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_trx.view');

    $model_query = StandbyTrx::with(['val_by','val1_by','val2_by','deleted_by','req_deleted_by','details'=>function($q){
      $q->orderby("ordinal","asc");      
    },'standby_mst'])->find($request->id);
    return response()->json([
      "data" => new StandbyTrxResource($model_query),
    ], 200);
  }

  public function validateItems($details_in){
    $rules = [      
      'details'                          => 'required|array',
      'details.*.p_status'               => 'required|in:Remove,Add,Edit',
      'details.*.tanggal'                => 'required_if:details.*.p_status,Add,Edit|date_format:Y-m-d',
    ];

    $messages = [
      'details.required' => 'List Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      $messages["details.{$index}.tanggal.required_if"] = "Baris #" . ($index + 1) . ". Tanggal tidak boleh kosong.";
      $messages["details.{$index}.tanggal.date_format"] = "Baris #" . ($index + 1) . ". Format Tanggal Salah.";
    }

    $validator = Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  private $height = 500;
  private $quality = 100;

  public function store(StandbyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_trx.create');

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    if(count($details_in)>0){
      MyAdmin::checkScope($this->permissions, 'standby_trx.detail.insert');
    }

    $t_stamp = date("Y-m-d H:i:s");
    // $online_status=$request->online_status;
    
    $rollback_id = -1;
    DB::beginTransaction();
    try {

      if($request->supir_id){
        $supir_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->supir_id)->available()->verified()->first();
        if(!$supir_dt)
        throw new \Exception("Supir tidak terdaftar",1);
      }

      if($request->kernet_id){
        $kernet_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->kernet_id)->available()->verified()->first();
        if(!$kernet_dt)
        throw new \Exception("Kernet tidak terdaftar",1);
      }

      if($request->supir_id && $request->kernet_id &&  $request->supir_id == $request->kernet_id && $request->supir_id != 1)
      throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

      $unique_items = [];
      foreach ($details_in as $key => $value) {
        $unique_data = strtolower(trim($value['tanggal']));
        if (in_array($unique_data, $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, $unique_data);
      }

      // if(StandbyTrx::where("xto",$request->xto)->where("tipe",$request->tipe)->where("jenis",$request->jenis)->first())
      // throw new \Exception("List sudah terdaftar");

      $model_query                      = new StandbyTrx();      
      $standby_mst = \App\Models\MySql\StandbyMst::where("id",$request->standby_mst_id)
      ->where("deleted",0)
      ->lockForUpdate()
      ->first();

      if(!$standby_mst) 
      throw new \Exception("Silahkan Isi Data Standby Dengan Benar",1);

      if($standby_mst->is_transition && ($request->transition_target=='' || $request->transition_type=='')) 
        throw new \Exception("Info Peralihan Harap Diisi",1);

      if($standby_mst->is_trip && !$standby_mst->is_transition){
        if(!$request->trx_trp_id)
        throw new \Exception("Harap Trx Trp ID Diisi",1);

        $trx_trp = TrxTrp::where("id",$request->trx_trp_id)->first();
        if(!$trx_trp) 
        throw new \Exception("Trx Trp Tidak Ditemukan",1);

        $model_query->trx_trp_id = $trx_trp->id;
      }

      $model_query->standby_mst_id      = $standby_mst->id;
      if($standby_mst->is_transition){
        $model_query->transition_target   = $request->transition_target;
        $model_query->transition_type     = $request->transition_type;
      }else{
        $model_query->transition_target   = null;
        $model_query->transition_type     = null;
      }
      // $model_query->standby_mst_name    = $standby_mst->name;
      // $model_query->standby_mst_type    = $standby_mst->tipe;
      // $model_query->standby_mst_amount  = $standby_mst->amount;

      if(isset($supir_dt)){
        $model_query->supir_id          = $supir_dt->id;
        $model_query->supir             = $supir_dt->name;
        $model_query->supir_rek_no      = $supir_dt->rek_no;
        $model_query->supir_rek_name    = $supir_dt->rek_name;
      }

      if(isset($kernet_dt)){
        $model_query->kernet_id       = $kernet_dt->id;
        $model_query->kernet          = $kernet_dt->name;
        $model_query->kernet_rek_no   = $kernet_dt->rek_no;
        $model_query->kernet_rek_name = $kernet_dt->rek_name;  
      }
      $model_query->no_pol              = $request->no_pol;

      $model_query->xto                 = $request->xto;
      $model_query->note_for_remarks    = $request->note_for_remarks;
      // $model_query->ref                 = $request->ref;
      
      // if($online_status=="true"){
      //   if($request->cost_center_code){
      //     $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
      //     ->select('CostCenter','Description')
      //     ->where('CostCenter',$request->cost_center_code)
      //     ->first();
      //     if(!$list_cost_center)
      //     throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
        
      //     $model_query->cost_center_code = $list_cost_center->CostCenter;
      //     $model_query->cost_center_desc = $list_cost_center->Description;
      //   }
      // }
      
      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $supir_id = (isset($supir_dt->id) ? $supir_dt->id : 0);
      $kernet_id = (isset($kernet_dt->id) ? $kernet_dt->id : 0);

      foreach ($details_in as $key => $value) {

        $checks = StandbyTrxDtl::where('tanggal',$value['tanggal'])
        ->whereHas('standby_trx',function ($q) use ($supir_id,$kernet_id) {
          $q->where('deleted',0)->where('req_deleted',0)->where(function ($q1) use ($supir_id,$kernet_id) {            
            if($supir_id>0) $q1->where('supir_id',$supir_id);
            if($kernet_id>0) $q1->orwhere('kernet_id',$kernet_id);
          });
        })->get();

        if(count($checks)>0)
        throw new \Exception("Data telah ada di:".($checks->pluck('standby_trx_id')), 1);
        
      }

      $model_query->save();

      $rollback_id = $model_query->id - 1;

      $ordinal=0;
      
      $blobFiles = [];
      $fileTypes = [];
      $attachments =$request->all()['attachments'];
      foreach ($attachments as $key => $attachment) {
        $blobFile=null;
        $fileType=null;
        
        if($attachment instanceof \Illuminate\Http\UploadedFile){
          
          $path     = $attachment->getRealPath();
          $fileType = $attachment->getClientMimeType();
          $fileSize = $attachment->getSize();

          if($fileSize > 2048000)
          throw new Exception("Baris #" . ($key + 1) . ".Size File max 2048 kb", 1);

          if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
          throw new Exception("Baris #" . ($key + 1) . ".Tipe File Harus berupa jpg,jpeg, atau png", 1);

          // $blobFile = base64_encode(file_get_contents($path));
          $image = Image::read($path)->scale(height: $this->height);
          $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));    
          $blobFile = base64_encode($compressedImageBinary);      

        }

        $blobFiles[$key]=$blobFile;
        $fileTypes[$key]=$fileType;

        // $filename = $attachment->getClientOriginalName();
        // $attachment->storeAs('attachments', $filename);
      }

      foreach ($details_in as $key => $value) {
        $ordinal                    = $key + 1;
        $detail                     = new StandbyTrxDtl();
        $detail->standby_trx_id     = $model_query->id;
        $detail->ordinal            = $ordinal;
        $detail->attachment_1       = $blobFiles[$key];
        $detail->attachment_1_type  = $fileTypes[$key];

        $detail->tanggal            = $value['tanggal'];
        $detail->note               = $value['note'];

        if(MyAdmin::checkScope($this->permissions, 'standby_trx.detail.decide_paid',true)){
          $detail->be_paid          = $value['be_paid'];
        }

        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
        $detail->save();
      }

      MyLog::sys("standby_trx",$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message"     => "Proses tambah data berhasil",
        "id"          => $model_query->id,
        "created_at"  => $t_stamp,
        "updated_at"  => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE standby_trx AUTO_INCREMENT = $rollback_id");

      // return response()->json([
      //   "message" => $e->getMessage(),
      //   "code" => $e->getCode(),
      //   "line" => $e->getLine(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(StandbyTrxRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_trx.modify');
    
    $t_stamp        = date("Y-m-d H:i:s");
    // $online_status  = $request->online_status;

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    DB::beginTransaction();
    try {

      if($request->supir_id){
        $supir_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->supir_id)->available()->verified()->first();
        if(!$supir_dt)
        throw new \Exception("Supir tidak terdaftar",1);
      }

      if($request->kernet_id){
        $kernet_dt =\App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->where('id',$request->kernet_id)->available()->verified()->first();
        if(!$kernet_dt)
        throw new \Exception("Kernet tidak terdaftar",1);
      }

      if($request->supir_id && $request->kernet_id &&  $request->supir_id == $request->kernet_id && $request->supir_id != 1)
      throw new \Exception("Supir Dan Kernet Tidak Boleh Orang Yang Sama",1);

      $SYSNOTES=[];
      $model_query = StandbyTrx::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      array_push( $SYSNOTES ,"Details: \n");

      if($model_query->val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);
      
      if($model_query->val==0){
        $standby_mst = \App\Models\MySql\StandbyMst::where("id",$request->standby_mst_id)
        ->where("deleted",0)
        ->lockForUpdate()
        ->first();
  
        if(!$standby_mst) 
        throw new \Exception("Silahkan Isi Data Standby Dengan Benar",1);
        
        if($standby_mst->is_transition && ($request->transition_target=='' || $request->transition_type=='')) 
        throw new \Exception("Info Peralihan Harap Diisi",1);

        if($standby_mst->is_trip && !$standby_mst->is_transition){
          if(!$request->trx_trp_id)
          throw new \Exception("Harap Trx Trp ID Diisi",1);
  
          $trx_trp = TrxTrp::where("id",$request->trx_trp_id)->first();
          if(!$trx_trp) 
          throw new \Exception("Trx Trp Tidak Ditemukan",1);
  
          $model_query->trx_trp_id = $trx_trp->id;
        }
        
        $model_query->standby_mst_id      = $standby_mst->id;

        if($standby_mst->is_transition){
          $model_query->transition_target   = $request->transition_target;
          $model_query->transition_type     = $request->transition_type;
        }else{
          $model_query->transition_target   = null;
          $model_query->transition_type     = null;
        }

        // $model_query->standby_mst_name    = $standby_mst->name;
        // $model_query->standby_mst_type    = $standby_mst->tipe;
        // $model_query->standby_mst_amount  = $standby_mst->amount;
        if(isset($supir_dt)){
          $model_query->supir_id          = $supir_dt->id;
          $model_query->supir             = $supir_dt->name;
          $model_query->supir_rek_no      = $supir_dt->rek_no;
          $model_query->supir_rek_name    = $supir_dt->rek_name;
        }
        else{
          $model_query->supir_id       = null;
          $model_query->supir          = null;
          $model_query->supir_rek_no   = null;
          $model_query->supir_rek_name = null;  
        }

        if(isset($kernet_dt)){
          $model_query->kernet_id       = $kernet_dt->id;
          $model_query->kernet          = $kernet_dt->name;
          $model_query->kernet_rek_no   = $kernet_dt->rek_no;
          $model_query->kernet_rek_name = $kernet_dt->rek_name;  
        }else{
          $model_query->kernet_id       = null;
          $model_query->kernet          = null;
          $model_query->kernet_rek_no   = null;
          $model_query->kernet_rek_name = null;  
        }
        $model_query->no_pol              = $request->no_pol;
  
        $model_query->xto                 = $request->xto;
        $model_query->note_for_remarks    = $request->note_for_remarks;
        // $model_query->ref              = $request->ref;
      }

      // if($request->cost_center_code!=$model_query->cost_center_code){

      //   if($online_status!="true" && $request->cost_center_code)
      //   throw new \Exception("Pengisian cost center harus dalam mode online", 1);

      //   if($request->cost_center_code==""){
      //     $model_query->cost_center_code =null;
      //     $model_query->cost_center_desc =null;
      //   }else{
      //     if($model_query->pvr_id==null){
      //       $list_cost_center = DB::connection('sqlsrv')->table("AC_CostCenterNames")
      //       ->select('CostCenter','Description')
      //       ->where('CostCenter',$request->cost_center_code)
      //       ->first();
      //       if(!$list_cost_center)
      //       throw new \Exception(json_encode(["cost_center_code"=>["Cost Center Code Tidak Ditemukan"]]), 422);
          
      //       $model_query->cost_center_code = $list_cost_center->CostCenter;
      //       $model_query->cost_center_desc = $list_cost_center->Description;
      //     }
      //   }
      // }
      

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;
      
      $blobFiles = [];
      $fileTypes = [];
      $attachments =$request->all()['attachments'];
      foreach ($attachments as $key => $attachment) {
        $blobFile=null;
        $fileType=null;
        
        if($attachment instanceof \Illuminate\Http\UploadedFile){
          
          $path     = $attachment->getRealPath();
          $fileType = $attachment->getClientMimeType();
          $fileSize = $attachment->getSize();

          if($fileSize > 2048000)
          throw new Exception("Baris #" . ($key + 1) . ".Size File max 2048 kb", 1);

          if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
          throw new Exception("Baris #" . ($key + 1) . ".Tipe File Harus berupa jpg,jpeg, atau png", 1);

          // $blobFile = base64_encode(file_get_contents($path));
          $image = Image::read($path)->scale(height: $this->height);
          $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
          $blobFile = base64_encode($compressedImageBinary);      
    
        }

        $blobFiles[$key]=$blobFile;
        $fileTypes[$key]=$fileType;

        // $filename = $attachment->getClientOriginalName();
        // $attachment->storeAs('attachments', $filename);
      }

      if($model_query->val==0){
        //start for details2
        $data_from_db = StandbyTrxDtl::where('standby_trx_id', $model_query->id)
        ->orderBy("ordinal", "asc")->lockForUpdate()
        ->get()->toArray();
  
        $in_keys = array_filter($details_in, function ($x) {
          return isset($x["key"]);
        });
  
        $in_keys = array_map(function ($x) {
          return $x["key"];
        }, $in_keys);
  
        $am_ordinal_db = array_map(function ($x) {
          return $x["ordinal"];
        }, $data_from_db);
  
        if (count(array_diff($in_keys, $am_ordinal_db)) > 0 || count(array_diff($am_ordinal_db, $in_keys)) > 0) {
          throw new Exception('Ada ketidak sesuaian data, harap hubungi staff IT atau refresh browser anda',1);
        }
  
        $id_items           = [];
        $ordinal            = 0;
        $for_deletes        = [];
        $for_edits          = [];
        $for_adds           = [];
        $data_to_processes  = [];
        foreach ($details_in as $k => $v) {        
          if (in_array($v["p_status"], ["Add", "Edit"])) {
            $unique = strtolower(trim($v['tanggal']));
            if (in_array($unique, $id_items) == 1) {
              throw new \Exception("Maaf terdapat Nama Item yang sama",1);
            }
            array_push($id_items, $unique);
          }
  
          if ($v["p_status"] !== "Remove") {
            $ordinal++;
            $details_in[$k]["ordinal"] = $ordinal;
            if ($v["p_status"] == "Edit")
              array_unshift($for_edits, $details_in[$k]);
            elseif ($v["p_status"] == "Add")
              array_push($for_adds, $details_in[$k]);
          } else
              array_push($for_deletes, $details_in[$k]);
        }
  

        if(count($for_adds) > 0){
          MyAdmin::checkScope($this->permissions, 'standby_trx.detail.insert');
        }
  
        if(count($for_edits) > 0){
          MyAdmin::checkScope($this->permissions, 'standby_trx.detail.modify');
        }
  
        if(count($for_deletes) > 0){
          MyAdmin::checkScope($this->permissions, 'standby_trx.detail.remove');
        }

        $data_to_processes = array_merge($for_deletes, $for_edits, $for_adds);
        
        if(count($for_edits)==0 && count($for_adds)==0){
          throw new \Exception("Data List Harus Diisi",1);
        }
        
        $supir_id = (isset($supir_dt->id) ? $supir_dt->id : 0);
        $kernet_id = (isset($kernet_dt->id) ? $kernet_dt->id : 0);

        foreach ($data_to_processes as $k => $v) {
          $index = false;
  
          if (isset($v["key"])) {
            $index = array_search($v["key"], $am_ordinal_db);
          }
  
          if ($v["p_status"] == "Remove") {
            // !!! NOT ALLOWED TO DELETE
            if ($index === false) {
              throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
            } else {
              $dt = $data_from_db[$index];
              array_push( $SYSNOTES ,"Ordinal ".$dt["ordinal"]." [Deleted]");
              StandbyTrxDtl::where("standby_trx_id",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
            }
          } else if ($v["p_status"] == "Edit") {
            if ($index === false) {
              throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
            } else {
              $checks = StandbyTrxDtl::where('tanggal',$v['tanggal'])
              ->whereHas('standby_trx',function ($q)use($supir_id,$kernet_id) {
                $q->where('deleted',0)->where('req_deleted',0)->where(function ($q1) use ($supir_id,$kernet_id) {            
                  if($supir_id>0) $q1->where('supir_id',$supir_id);
                  if($kernet_id>0) $q1->orwhere('kernet_id',$kernet_id);
                });
              })->where("standby_trx_id","!=",$model_query->id)->get();
      
              if(count($checks)>0)
              throw new \Exception("Data telah ada di:".($checks->pluck('standby_trx_id')), 1);
  

              $mq=StandbyTrxDtl::where("standby_trx_id", $model_query->id)
              ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();

              $mqToCom = clone($mq);
              
              $change=0;
              if($blobFiles[$v["ordinal"]-1]){
                $change++;
              }else if($v["attachment_1_preview"]== null){
                $change++;
              }

              $mq->ordinal            = $v["ordinal"];
              // !!! IZIN EDIT TANGGAL TIDAK LAGI DIPERBOLEHKAN
              $mq->tanggal            = $v["tanggal"]; 
              $mq->note               = $v["note"];

              if(MyAdmin::checkScope($this->permissions, 'standby_trx.detail.decide_paid',true)){
                $mq->be_paid          = $v['be_paid'];
              }

              if($change)
              $mq->attachment_1_type  = $fileTypes[$v["ordinal"]-1];

              $mq->p_change           = true;
              $mq->updated_at         = $t_stamp;
              $mq->updated_user       = $this->admin_id;
              $mq->save();

              if($change){
                StandbyTrxDtl::where("id",$mq->id)->update([
                  "attachment_1"      => $blobFiles[$v["ordinal"]-1]
                ]);
              }

              $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
              array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);

              // StandbyTrxDtl::where("standby_trx_id", $model_query->id)
              // ->where("ordinal", $v["key"])->where("p_change",false)->update([
              //     "ordinal"         => $v["ordinal"],
              //     "tanggal"         => $v["tanggal"],
              //     "note"            => $v["note"],
              //     "p_change"        => true,
              //     "updated_at"      => $t_stamp,
              //     "updated_user"    => $this->admin_id,
              // ]);
            }
          } else if ($v["p_status"] == "Add") {     
            array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");

            $checks = StandbyTrxDtl::where('tanggal',$v['tanggal'])
            ->whereHas('standby_trx',function ($q)use($supir_id,$kernet_id) {
              $q->where('deleted',0)->where('req_deleted',0)->where(function ($q1) use ($supir_id,$kernet_id) {            
                if($supir_id>0) $q1->where('supir_id',$supir_id);
                if($kernet_id>0) $q1->orwhere('kernet_id',$kernet_id);
              });
            })->get();
    
            if(count($checks)>0)
            throw new \Exception("Data telah ada di:".($checks->pluck('standby_trx_id')), 1);


            $mq_data = [
              'standby_trx_id'    => $model_query->id,
              'ordinal'           => $v["ordinal"],
              "tanggal"           => $v["tanggal"],
              "note"              => $v["note"],
              "attachment_1"      => $blobFiles[$v["ordinal"]-1],
              "attachment_1_type" => $fileTypes[$v["ordinal"]-1],
              "p_change"          => true,
              'created_at'        => $t_stamp,
              'created_user'      => $this->admin_id,
              'updated_at'        => $t_stamp,
              'updated_user'      => $this->admin_id,
            ];

            if(MyAdmin::checkScope($this->permissions, 'standby_trx.detail.decide_paid',true)){
              $mq_data['be_paid']  = $v['be_paid'];
            }

            StandbyTrxDtl::insert($mq_data);
          }
        }
      }
      
      $model_query->save();
      StandbyTrxDtl::where('standby_trx_id',$model_query->id)->update(["p_change"=>false]);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE);            
      MyLog::sys("standby_trx",$request->id,"update",implode("\n",$SYSNOTES));

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      if ($e->getCode() == 422) {
        return response()->json(json_decode($e->getMessage()), 422);
      }
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }
  }

  public function delete(Request $request)
  {
    // !!! NOT ALLOWED TO DELETE
    MyAdmin::checkScope($this->permissions, 'standby_trx.remove');

    DB::beginTransaction();

    try {
      $model_query = StandbyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);

      if($model_query->val || $model_query->req_deleted==1  || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->pvr_id!="" || $model_query->pvr_id!=null)
      throw new \Exception("Harap Lakukan Permintaan Penghapusan Terlebih Dahulu",1);

      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();

      MyLog::sys("standby_trx",$request->id,"delete");

      DB::commit();
      return response()->json([
        "message" => "Proses Hapus data berhasil",
        "deleted"=>$model_query->deleted,
        "deleted_user"=>$model_query->deleted_user,
        "deleted_by"=>$model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"=>$model_query->deleted_at,
        "deleted_reason"=>$model_query->deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function reqDelete(Request $request)
  {
    // !!! NOT ALLOWED TO DELETE

    MyAdmin::checkScope($this->permissions, 'standby_trx.request_remove');

    DB::beginTransaction();

    try {
      $model_query = StandbyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }

      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);

      
      if($model_query->val2)
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 || $model_query->req_deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $req_deleted_reason = $request->req_deleted_reason;
      if(!$req_deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->req_deleted = 1;
      $model_query->req_deleted_user = $this->admin_id;
      $model_query->req_deleted_at = date("Y-m-d H:i:s");
      $model_query->req_deleted_reason = $req_deleted_reason;
      $model_query->save();

      MyLog::sys("standby_trx",$request->id,"delete","Request Delete (Void)");


      DB::commit();
      return response()->json([
        "message" => "Proses Permintaan Hapus data berhasil",
        "req_deleted"=>$model_query->req_deleted,
        "req_deleted_user"=>$model_query->req_deleted_user,
        "req_deleted_by"=>$model_query->req_deleted_user ? new IsUserResource(IsUser::find($model_query->req_deleted_user)) : null,
        "req_deleted_at"=>$model_query->req_deleted_at,
        "req_deleted_reason"=>$model_query->req_deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function approveReqDelete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_trx.approve_request_remove');

    $time = microtime(true);
    $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
    $t_stamp_ms = date("Y-m-d H:i:s").".".$mSecs;

    DB::beginTransaction();

    try {
      $model_query = StandbyTrx::where("id",$request->id)->lockForUpdate()->first();
      // if($model_query->requested_by != $this->admin_id){
      //   throw new \Exception("Hanya yang membuat transaksi yang boleh melakukan penghapusan data",1);
      // }
      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      
      if($model_query->salary_paid_id) 
      throw new \Exception("Data Sudah Digunakan Dan Tidak Dapat Di Ubah",1);

      if($model_query->val2)
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Hapus",1);

      if($model_query->deleted==1 )
      throw new \Exception("Data Tidak Dapat Di Hapus Lagi",1);

      if($model_query->pvr_id=="" || $model_query->pvr_id==null)
      throw new \Exception("Harap Lakukan Penghapusan",1);

      $deleted_reason = $model_query->req_deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;

      // if($model_query->pvr_no){
      //   DB::connection('sqlsrv')->table('FI_APRequest')
      //   ->where("VoucherNo",$model_query->pvr_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }

      // if($model_query->pv_no){
      //   DB::connection('sqlsrv')->table('FI_Arap')
      //   ->where("VoucherNo",$model_query->pv_no)->update([
      //     "Void" => 1,
      //     "VoidBy" => $this->admin->the_user->username,
      //     "VoidDateTime" => $t_stamp_ms,
      //     "VoidReason" => $deleted_reason
      //   ]);
      // }
      
      $model_query->save();

      MyLog::sys("standby_trx",$request->id,"delete","Approve Request Delete (Void)");

      DB::commit();
      return response()->json([
        "message" => "Proses Hapus data berhasil",
        "deleted"=>$model_query->deleted,
        "deleted_user"=>$model_query->deleted_user,
        "deleted_by"=>$model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"=>$model_query->deleted_at,
        "deleted_reason"=>$model_query->deleted_reason,
      ], 200);
    } catch (\Exception  $e) {
      DB::rollback();
      if ($e->getCode() == "23000")
        return response()->json([
          "message" => "Data tidak dapat dihapus, data terkait dengan data yang lain nya",
        ], 400);

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  } 

  public function previewFile(Request $request){
    MyAdmin::checkScope($this->permissions, 'standby_trx.preview_file');

    set_time_limit(0);

    $standby_trx = StandbyTrx::find($request->id);

    if($standby_trx->val==0)
    return response()->json([
      "message" => "Harap Di Validasi Terlebih Dahulu",
    ], 400);
    $standby_trx_details = \App\Models\MySql\StandbyTrxDtl::where("standby_trx_id",$standby_trx->id)->orderBy("ordinal","asc")->get();

    $standby_mst = \App\Models\MySql\StandbyMst::where("id",$standby_trx->standby_mst_id)->first();
    $standby_details = \App\Models\MySql\StandbyDtl::where("standby_mst_id",$standby_trx->standby_mst_id)->orderBy("ordinal","asc")->get();

    $sendData = [
      "created_at"=>$standby_trx->created_at,
      "id"=>$standby_trx->id,
      "standby_mst_id"=>$standby_trx->standby_mst_id,
      // "standby_mst_name"=>$standby_trx->standby_mst_name,
      // "standby_mst_type"=>$standby_trx->standby_mst_type,
      // "standby_mst_amount"=>$standby_trx->standby_mst_amount,
      "no_pol"=>$standby_trx->no_pol,
      "supir"=>$standby_trx->supir,
      "kernet"=>$standby_trx->kernet,
      "xto"=>$standby_trx->xto,
      "asal"=>env("app_name"),
      "standby_details"=>$standby_details,
      "standby_mst"=>$standby_mst,
      "standby_trx_details"=>$standby_trx_details,
      "is_transition"=>$standby_trx->transition_type,
      "user_1"=>$this->admin->the_user->username,
    ];   
    
    
    $html = view("html.standby_trx",$sendData);
    
    $result = [
      "html"=>$html->render()
    ];
    return $result;
  }

  // public function previewFiles(Request $request){

  //   // set_time_limit(0);

  //   // $rules = [
  //   //   'date_from' => "required|date_format:Y-m-d H:i:s",
  //   // ];

  //   // $messages = [
  //   //   'date_from.required' => 'Date From is required',
  //   //   'date_from.date_format' => 'Please Select Date From',
  //   // ];

  //   // $validator = Validator::make($request->all(), $rules, $messages);

  //   // if ($validator->fails()) {
  //   //   throw new ValidationException($validator);
  //   // }


  //   // // Change some request value
  //   // $request['period'] = "Daily";

  //   // $date_from = $request->date_from;
  //   // $d_from = date("Y-m", MyLib::manualMillis($date_from) / 1000) . "-01 00:00:00";
  //   // $date_f = new \DateTime($d_from);

  //   // $start = clone $date_f;
  //   // $start->add(new \DateInterval('P1M'));
  //   // $start->sub(new \DateInterval('P1D'));
  //   // $x = $start->format("Y-m-d H:i:s");

  //   // $request['date_from'] = $d_from;
  //   // $request['date_to'] = $x;
  //   // return response()->json(["data"=>[$d_from,$x]],200);

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   // $additional = $ori["additional"];


  //   // $date = new \DateTime();
  //   // $filename = $date->format("YmdHis") . "-" . $additional["company_name"] . "[" . $additional["date_from"] . "-" . $additional["date_to"] . "]";
  //   // // $filename=$date->format("YmdHis");

  //   // // return response()->json(["message"=>$filename],200);

  //   // $mime = MyLib::mime("csv");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.sensor_get_data_by_location'), $mime["exportType"]));
  //   // $mime = MyLib::mime("xlsx");
  //   // $bs64 = base64_encode(Excel::raw(new MyReport($data, 'report.tracking_info2'), $mime["exportType"]));

    

  //   // $sendData = [
  //   //   'pag_no'  => $pag->no,
  //   //   'created_at'    => $pag->created_at,
  //   //   'updated_at'    => $pag->updated_at,
  //   //   'proyek'  => $pag->project ?? "",
  //   //   'need'    => $pag->need,
  //   //   'part'    => $pag->part,
  //   //   'datas'   => $pag->pag_details,
  //   //   'title'   => "PENGAMBILAN BARANG GUDANG (PAG)"
  //   // ];
  //   // dd($sendData);

  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];
  //   $newDetails = [];
  //   $total_a_bruto = 0;
  //   $total_a_tara = 0;
  //   $total_a_netto = 0;
  //   $total_b_bruto = 0;
  //   $total_b_tara = 0;
  //   $total_b_netto = 0;
  //   $total_b_a_bruto = 0;
  //   $total_b_a_tara = 0;
  //   $total_b_a_netto = 0;
  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $total_a_bruto+=$ticket_a_bruto;
  //     $total_a_tara+=$ticket_a_tara;
  //     $total_a_netto+=$ticket_a_netto;

  //     $total_b_bruto+=$ticket_b_bruto;
  //     $total_b_tara+=$ticket_b_tara;
  //     $total_b_netto+=$ticket_b_netto;

  //     $limitSusut = 0.4;

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=number_format($ticket_a_bruto, 0,',','.');
  //     $value['ticket_b_bruto']=number_format($ticket_b_bruto, 0,',','.');
  //     $value['ticket_b_a_bruto']=block_negative($ticket_b_a_bruto, 0);
  //     $value['ticket_b_a_bruto_persen_red']=abs($ticket_b_a_bruto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_bruto_persen']=block_negative($ticket_b_a_bruto_persen, 2);
  //     $value['ticket_a_tara']=number_format($ticket_a_tara, 0,',','.');
  //     $value['ticket_b_tara']=number_format($ticket_b_tara, 0,',','.');
  //     $value['ticket_b_a_tara']=block_negative($ticket_b_a_tara, 0);
  //     $value['ticket_b_a_tara_persen_red']=abs($ticket_b_a_tara_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_tara_persen']=block_negative($ticket_b_a_tara_persen, 2);
  //     $value['ticket_a_netto']=number_format($ticket_a_netto, 0,',','.');
  //     $value['ticket_b_netto']=number_format($ticket_b_netto, 0,',','.');
  //     $value['ticket_b_a_netto']=block_negative($ticket_b_a_netto, 0);
  //     $value['ticket_b_a_netto_persen_red']=abs($ticket_b_a_netto_persen) >= $limitSusut ? 'color:red;' : '';
  //     $value['ticket_b_a_netto_persen']=block_negative($ticket_b_a_netto_persen, 2);
  //     $value['amount']=number_format((float)$value["amount"], 0,',','.');
  //     $value['pv_total']=number_format((float)$value["pv_total"], 0,',','.');
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   list($total_b_a_bruto, $total_b_a_bruto_persen) =  $this->genPersen($total_a_bruto,$total_b_bruto);
  //   list($total_b_a_tara, $total_b_a_tara_persen) =  $this->genPersen($total_a_tara,$total_b_tara);
  //   list($total_b_a_netto, $total_b_a_netto_persen) =  $this->genPersen($total_a_netto,$total_b_netto);
    

  //   $ttl_a_tara=number_format($total_a_tara, 0,',','.');
  //   $ttl_a_bruto=number_format($total_a_bruto, 0,',','.');
  //   $ttl_a_netto=number_format($total_a_netto, 0,',','.');

  //   $ttl_b_tara=number_format($total_b_tara, 0,',','.');
  //   $ttl_b_bruto=number_format($total_b_bruto, 0,',','.');
  //   $ttl_b_netto=number_format($total_b_netto, 0,',','.');


  //   $ttl_b_a_tara=block_negative($total_b_a_tara, 0);
  //   $ttl_b_a_bruto=block_negative($total_b_a_bruto, 0);
  //   $ttl_b_a_netto=block_negative($total_b_a_netto, 0);
    
  //   $ttl_b_a_bruto_persen=block_negative($total_b_a_bruto_persen, 2);
  //   $ttl_b_a_tara_persen=block_negative($total_b_a_tara_persen, 2);
  //   $ttl_b_a_netto_persen=block_negative($total_b_a_netto_persen, 2);

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename = $date->format("YmdHis");
  //   Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
  //   $pdf = PDF::loadView('pdf.standby_trx', ["data"=>$newDetails,"shows"=>$shows,"info"=>[
  //     "from"=>date("d-m-Y",strtotime($request->date_from)),
  //     "to"=>date("d-m-Y",strtotime($request->date_to)),
  //     "now"=>date("d-m-Y H:i:s"),
  //     "ttl_a_bruto"=>$ttl_a_bruto,
  //     "ttl_a_tara"=>$ttl_a_tara,
  //     "ttl_a_netto"=>$ttl_a_netto,
  //     "ttl_b_bruto"=>$ttl_b_bruto,
  //     "ttl_b_tara"=>$ttl_b_tara,
  //     "ttl_b_netto"=>$ttl_b_netto,
  //     "ttl_b_a_bruto"=>$ttl_b_a_bruto,
  //     "ttl_b_a_tara"=>$ttl_b_a_tara,
  //     "ttl_b_a_netto"=>$ttl_b_a_netto,
  //     // "ttl_b_a_bruto_persen"=>$ttl_b_a_bruto_persen,
  //     // "ttl_b_a_tara_persen"=>$ttl_b_a_tara_persen,
  //     // "ttl_b_a_netto_persen"=>$ttl_b_a_netto_persen,
  //   ]])->setPaper('a4', 'landscape');


  //   $mime = MyLib::mime("pdf");
  //   $bs64 = base64_encode($pdf->download($filename . "." . $mime["ext"]));

  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  // public function downloadExcel(Request $request){

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
  //   $data = $ori["data"];
    
  //   $shows=["id","tanggal","no_pol","jenis","xto","amount"];

  //   $newDetails = [];

  //   foreach ($ori["data"] as $key => $value) {
  //     $ticket_a_bruto = (float)$value["ticket_a_bruto"];
  //     $ticket_b_bruto = (float)$value["ticket_b_bruto"];
  //     list($ticket_b_a_bruto, $ticket_b_a_bruto_persen) =  $this->genPersen($value["ticket_a_bruto"],$value["ticket_b_bruto"]);
  //     $ticket_a_tara = (float)$value["ticket_a_tara"];
  //     $ticket_b_tara = (float)$value["ticket_b_tara"];
  //     list($ticket_b_a_tara, $ticket_b_a_tara_persen) =  $this->genPersen($value["ticket_a_tara"],$value["ticket_b_tara"]);
  //     $ticket_a_netto = (float)$value["ticket_a_netto"];
  //     $ticket_b_netto = (float)$value["ticket_b_netto"];
  //     list($ticket_b_a_netto, $ticket_b_a_netto_persen) =  $this->genPersen($value["ticket_a_netto"],$value["ticket_b_netto"]);

  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     $value['ticket_a_out_at']=$value["ticket_a_out_at"] ? date("d-m-Y H:i",strtotime($value["ticket_a_out_at"])) : "";
  //     $value['ticket_b_in_at']=$value["ticket_b_in_at"] ? date("d-m-Y H:i",strtotime($value["ticket_b_in_at"])) : "";
  //     $value['ticket_a_bruto']=$ticket_a_bruto;
  //     $value['ticket_b_bruto']=$ticket_b_bruto;
  //     $value['ticket_b_a_bruto']=$ticket_b_a_bruto;
  //     $value['ticket_b_a_bruto_persen']=$ticket_b_a_bruto_persen;
  //     $value['ticket_a_tara']=$ticket_a_tara;
  //     $value['ticket_b_tara']=$ticket_b_tara;
  //     $value['ticket_b_a_tara']=$ticket_b_a_tara;
  //     $value['ticket_b_a_tara_persen']=$ticket_b_a_tara_persen;
  //     $value['ticket_a_netto']=$ticket_a_netto;
  //     $value['ticket_b_netto']=$ticket_b_netto;
  //     $value['ticket_b_a_netto']=$ticket_b_a_netto;
  //     $value['ticket_b_a_netto_persen']=$ticket_b_a_netto_persen;
  //     $value['amount']=$value["amount"];
  //     $value['pv_total']=$value["pv_total"];
  //     $value['pv_datetime']=$value["pv_datetime"] ? date("d-m-Y",strtotime($value["pv_datetime"])) : "";
  //     array_push($newDetails,$value);
  //   }

  //   // <td>{{ number_format($v["ticket_a_bruto"] ?( ((float)$v["ticket_b_netto"] - (float)$v["ticket_a_netto"])/(float)$v["ticket_a_bruto"] * 100):0, 2,',','.') }}</td>

  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-standby_trx'."[".$request["date_from"]."-".$request["date_to"]."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails,"shows"=>$shows],'excel.standby_trx'), $mime["exportType"]));


  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['standby_trx.val','standby_trx.val1','standby_trx.val2']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\StandbyTrx,id",
    ];

    $messages = [
      'id.required' => 'ID tidak boleh kosong',
      'id.exists' => 'ID tidak terdaftar',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      throw new ValidationException($validator);
    }

    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $model_query = StandbyTrx::find($request->id);
      if($model_query->salary_paid_id){
        throw new \Exception("Standby Sudah Generate Salary Paid Tidak Dapat Divalidasi lagi",1);
      }
      // if($model_query->cost_center_code=="")
      // throw new \Exception("Harap Mengisi Cost Center Code Sebelum validasi",1);

      if($model_query->val && $model_query->val1 && $model_query->val2){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }

    if(MyAdmin::checkScope($this->permissions, 'standby_trx.val',true) && !$model_query->val){
      $model_query->val = 1;
      $model_query->val_user = $this->admin_id;
      $model_query->val_at = $t_stamp;
    }
  
    if(MyAdmin::checkScope($this->permissions, 'standby_trx.val1',true) && !$model_query->val1){
        if($model_query->val==0){
          throw new \Exception("Kasir Harus Memvalidasi Terlebih Dahulu",1);
        }
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'standby_trx.val2',true) && !$model_query->val2){
        if($model_query->val1==0){
          throw new \Exception("Mandor Harus Memvalidasi Terlebih Dahulu",1);
        }
        if(MyAdmin::checkScope($this->permissions, 'standby_trx.detail.decide_paid',true)){
          $details_in = json_decode($request->details, true);
          // $this->validateItems($details_in);

          foreach ($details_in as $key => $value) {
            StandbyTrxDtl::where("standby_trx_id",$model_query->id)
            ->where("ordinal",$value['ordinal'])
            ->update([
              "be_paid"=>$value['be_paid']
            ]);
          }
        }

        $model_query->val2 = 1;
        $model_query->val2_user = $this->admin_id;
        $model_query->val2_at = $t_stamp;
      }

      $model_query->save();

      MyLog::sys("standby_trx",$request->id,"approve");

      DB::commit();
      return response()->json([
        "message" => "Proses validasi data berhasil",
        "val"=>$model_query->val,
        "val_user"=>$model_query->val_user,
        "val_at"=>$model_query->val_at,
        "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null,
        "val2"=>$model_query->val2,
        "val2_user"=>$model_query->val2_user,
        "val2_at"=>$model_query->val2_at,
        "val2_by"=>$model_query->val2_user ? new IsUserResource(IsUser::find($model_query->val2_user)) : null,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      // return response()->json([
      //   "getCode" => $e->getCode(),
      //   "line" => $e->getLine(),
      //   "message" => $e->getMessage(),
      // ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }

  }

  // public function doGenPVR(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'standby_trx.generate_pvr');
  //   $rules = [
  //     // 'id' => "required|exists:\App\Models\MySql\StandbyTrx,id",
  //     'online_status' => "required",
  //   ];

  //   $messages = [
  //     // 'id.required' => 'ID tidak boleh kosong',
  //     // 'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }
  //   $online_status=$request->online_status;
  //   if($online_status!="true")
  //   return response()->json([
  //     "message" => "Mode Harus Online",
  //   ], 400);

  //   $miniError="";
  //   $id="";
  //   try {
  //     $standby_trxs = StandbyTrx::where(function($q1){$q1->where('pvr_had_detail',0)->orWhereNull("pvr_id");})->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->where('val1',1)->get();
  //     if(count($standby_trxs)==0){
  //       throw new \Exception("Semua PVR sudah terisi",1);
  //     }
  //     $changes=[];
  //     foreach ($standby_trxs as $key => $tt) {
  //       $id=$tt->id;
  //       $callGet = $this->genPVR($id);
  //       array_push($changes,$callGet);
  //     }
  //     if(count($changes)>0){
  //       $ids = array_map(function ($x) {
  //         return $x["id"];
  //       },$changes);
  //       MyLog::sys("standby_trx",null,"generate_pvr",implode(",",$ids));
  //     }
  //     return response()->json($changes, 200);
  //   } catch (\Exception $e) {
  //     if(isset($changes) && count($changes)>0){
  //       $ids = array_map(function ($x) {
  //         return $x["id"];
  //       },$changes);
  //       MyLog::sys("standby_trx",null,"generate_pvr",implode(",",$ids));
  //     }

  //     if ($e->getCode() == 1) {
  //       if($id!=""){
  //         $miniError.="Trx-".$id.".";
  //       }
  //       $miniError.="PVR Batal Dibuat: ".$e->getMessage();
  //     }else{
  //       if($id!=""){
  //         $miniError.="Trx-".$id.".";
  //       }
  //       $miniError.="PVR Batal Dibuat. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //     ], 400);
  //   }
  // }

  // public function genPVR($standby_trx_id){

  //   $t_stamp = date("Y-m-d H:i:s");

  //   $time = microtime(true);
  //   $mSecs = sprintf('%03d', ($time - floor($time)) * 1000);
  //   $t_stamp_ms = date("Y-m-d H:i:s",strtotime($t_stamp)).".".$mSecs;

  //   $standby_trx = StandbyTrx::where("id",$standby_trx_id)->first();
  //   if(!$standby_trx){
  //     throw new \Exception("Karna Transaksi tidak ditemukan",1);
  //   }

  //   if($standby_trx->pvr_had_detail==1) throw new \Exception("Karna PVR sudah selesai dibuat",1);
  //   if($standby_trx->cost_center_code==null) throw new \Exception("Cost Center Code belum diisi",1);
  //   if($standby_trx->pv_id!=null) throw new \Exception("Karna PV sudah diisi",1);
      
  //   $supir = $standby_trx->supir;
  //   $no_pol = $standby_trx->no_pol;
  //   $kernet = $standby_trx->kernet;
  //   $associate_name=($supir?"(S) ".$supir." ":"(Tanpa Supir) ").($kernet?"(K) ".$kernet." ":"(Tanpa Kernet) ").$no_pol; // max 80char

  //   $standby_mst = StandbyMst::where("id",$standby_trx->standby_mst_id)->first();
  //   $standby_mst_dtl = StandbyDtl::where("standby_mst_id",$standby_mst->id)->get();
  //   if(count($standby_mst_dtl)==0)
  //   throw new \Exception("Master Standby Detail Harus diisi terlebih dahulu",1);
    
  //   $standby_trx_dtl = StandbyTrxDtl::where("standby_trx_id",$standby_trx->id)->get();
  //   if(count($standby_trx_dtl)==0)
  //   throw new \Exception("Transaksi Standby Detail Harus diisi terlebih dahulu",1);

  //   $arrRemarks = [];
  //   array_push($arrRemarks,"#".$standby_trx->id.($standby_trx->transition_type!=''?" (P) " : " ").$associate_name.".");
  //   array_push($arrRemarks,$standby_mst->name." ".($standby_trx->xto ? env("app_name")."-".$standby_trx->xto : "")).".";
  //   $pertanggal = "";
  //   foreach ($standby_trx_dtl as $key => $value) {
  //     if($key > 0) $pertanggal .= ",";

  //     $pertanggal .= " P/".date("d-m-y",strtotime($value->tanggal));
  //   }
  //   array_push($arrRemarks,$pertanggal);

  //   if($standby_trx->note_for_remarks!=null){
  //     $note_for_remarks_arr = preg_split('/\r\n|\r|\n/', $standby_trx->note_for_remarks);
  //     $arrRemarks = array_merge($arrRemarks,$note_for_remarks_arr);
  //   }
    
  //   $remarks = implode(chr(10),$arrRemarks);
  //   array_push($arrRemarks,";");

  //   if(strlen($associate_name)>80){
  //     $associate_name = substr($associate_name,0,80);
  //   }

  //   $bank_account_code=env("PVR_BANK_ACCOUNT_CODE");
    
  //   $bank_acccount_db = DB::connection('sqlsrv')->table('FI_BankAccounts')
  //   ->select('BankAccountID')
  //   ->where("bankaccountcode",$bank_account_code)
  //   ->first();
  //   if(!$bank_acccount_db) throw new \Exception("Bank account code tidak terdaftar ,segera infokan ke tim IT",1);

  //   $bank_account_id = $bank_acccount_db->BankAccountID;
    
  //   // @VoucherID INT = 0,
  //   $voucher_no = "(AUTO)";
  //   $voucher_type = "TRP";
  //   $voucher_date = date("Y-m-d");

  //   $income_or_expense = 1;
  //   $currency_id = 1;
  //   $payment_method="Cash";
  //   $check_no=$bank_name=$account_no= '';
  //   $check_due_date= null;

  //   $amount_paid = $standby_mst->amount * count($standby_trx_dtl); // call from child
  //   $exclude_in_ARAP = 0;
  //   $login_name = $this->admin->the_user->username;
  //   $expense_or_revenue_type_id=0;
  //   $confidential=1;
  //   $PVR_source = 'gt_standby'; // digenerate melalui program
  //   $PVR_source_id = $standby_trx_id; //ambil id trx
  //     // DB::select("exec USP_FI_APRequest_Update(0,'(AUTO)','TRP',1,1,1,0,)",array($ts,$param2));
  //   $VoucherID = -1;

  //   $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
  //   ->select('VoucherID','VoucherNo','AmountPaid')
  //   ->where("PVRSource",$PVR_source)
  //   ->where("PVRSourceID",$standby_trx->id)
  //   ->where("Void",0)
  //   ->first();

  //   if(!$pvr){
  //     // $myData = DB::connection('sqlsrv')->update("SET NOCOUNT ON;exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
  //     $myData = DB::connection('sqlsrv')->update("exec USP_FI_APRequest_Update @VoucherNo=:voucher_no,@VoucherType=:voucher_type,
  //     @VoucherDate=:voucher_date,@IncomeOrExpense=:income_or_expense,@CurrencyID=:currency_id,@AssociateName=:associate_name,
  //     @BankAccountID=:bank_account_id,@PaymentMethod=:payment_method,@CheckNo=:check_no,@CheckDueDate=:check_due_date,
  //     @BankName=:bank_name,@AmountPaid=:amount_paid,@AccountNo=:account_no,@Remarks=:remarks,@ExcludeInARAP=:exclude_in_ARAP,
  //     @LoginName=:login_name,@ExpenseOrRevenueTypeID=:expense_or_revenue_type_id,@Confidential=:confidential,
  //     @PVRSource=:PVR_source,@PVRSourceID=:PVR_source_id",[
  //       ":voucher_no"=>$voucher_no,
  //       ":voucher_type"=>$voucher_type,
  //       ":voucher_date"=>$voucher_date,
  //       ":income_or_expense"=>$income_or_expense,
  //       ":currency_id"=>$currency_id,
  //       ":associate_name"=>$associate_name,
  //       ":bank_account_id"=>$bank_account_id,
  //       ":payment_method"=>$payment_method,
  //       ":check_no"=>$check_no,
  //       ":check_due_date"=>$check_due_date,
  //       ":bank_name"=>$bank_name,
  //       ":amount_paid"=>$amount_paid,
  //       ":account_no"=>$account_no,
  //       ":remarks"=>$remarks,
  //       ":exclude_in_ARAP"=>$exclude_in_ARAP,
  //       ":login_name"=>$login_name,
  //       ":expense_or_revenue_type_id"=>$expense_or_revenue_type_id,
  //       ":confidential"=>$confidential,
  //       ":PVR_source"=>$PVR_source,
  //       ":PVR_source_id"=>$PVR_source_id,
  //     ]);
  //     if(!$myData)
  //     throw new \Exception("Data yang diperlukan tidak terpenuhi",1);
    
  //     $pvr= DB::connection('sqlsrv')->table('FI_APRequest')
  //     ->select('VoucherID','VoucherNo','AmountPaid')
  //     ->where("PVRSource",$PVR_source)
  //     ->where("PVRSourceID",$standby_trx->id)
  //     ->where("Void",0)
  //     ->first();
  //     if(!$pvr)
  //     throw new \Exception("Akses Ke Jaringan Gagal",1);
  //   }

  //   $standby_trx->pvr_id = $pvr->VoucherID;
  //   $standby_trx->pvr_no = $pvr->VoucherNo;
  //   $standby_trx->pvr_total = $pvr->AmountPaid;
  //   $standby_trx->save();
    
  //   $d_voucher_id = $pvr->VoucherID;
  //   $d_voucher_extra_item_id = 0;
  //   $d_type = 0;

  //   $pvr_detail= DB::connection('sqlsrv')->table('FI_APRequestExtraItems')
  //   ->select('VoucherID')
  //   ->where("VoucherID",$d_voucher_id)
  //   ->get();

  //   if(count($pvr_detail)==0 || count($pvr_detail) < count($standby_mst_dtl)){
  //     $start = count($pvr_detail);
  //     foreach ($standby_mst_dtl as $key => $v) {
  //       if($key < $start){ continue; }
  //       $d_description = $v->description;
  //       $d_amount = count($standby_trx_dtl) * $v->amount;
  //       $d_account_id = $v->ac_account_id;
  //       $d_dept = $standby_trx->cost_center_code;
  //       $d_qty=count($standby_trx_dtl);
  //       $d_unit_price=$v->amount;
  //       $details = DB::connection('sqlsrv')->update("exec 
  //       USP_FI_APRequestExtraItems_Update @VoucherID=:d_voucher_id,
  //       @VoucherExtraItemID=:d_voucher_extra_item_id,
  //       @Description=:d_description,@Amount=:d_amount,
  //       @AccountID=:d_account_id,@TypeID=:d_type,
  //       @Department=:d_dept,@LoginName=:login_name,
  //       @Qty=:d_qty,@UnitPrice=:d_unit_price",[
  //         ":d_voucher_id"=>$d_voucher_id,
  //         ":d_voucher_extra_item_id"=>$d_voucher_extra_item_id,
  //         ":d_description"=>$d_description,
  //         ":d_amount"=>$d_amount,
  //         ":d_account_id"=>$d_account_id,
  //         ":d_type"=>$d_type,
  //         ":d_dept"=>$d_dept,
  //         ":login_name"=>$login_name,
  //         ":d_qty"=>$d_qty,
  //         ":d_unit_price"=>$d_unit_price
  //       ]);
  //     }
  //   }

  //   $tocheck = DB::connection('sqlsrv')->table('FI_APRequest')->where("VoucherID",$d_voucher_id)->first();

  //   if(!$tocheck)
  //   throw new \Exception("Voucher Tidak terdaftar",1);

  //   $checked2 = IsUser::where("id",$standby_trx->val1_user)->first();
  //   if(!$checked2)
  //   throw new \Exception("User Tidak terdaftar",1);
  //   if($tocheck->Checked==0){
  //     DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoCheck @VoucherID=:voucher_no,
  //     @CheckedBy=:login_name",[
  //       ":voucher_no"=>$d_voucher_id,
  //       ":login_name"=>$login_name,
  //     ]);
  //   }

  //   if($tocheck->Approved==0){
  //     DB::connection('sqlsrv')->update("exec USP_FI_APRequest_DoApprove @VoucherID=:voucher_no,
  //     @ApprovedBy=:login_name",[
  //       ":voucher_no"=>$d_voucher_id,
  //       ":login_name"=>$checked2->username,
  //     ]);
  //   }

  //   $standby_trx->pvr_had_detail = 1;
  //   $standby_trx->save();

  //   return [
  //     "message" => "PVR berhasil dibuat",
  //     "id"=>$standby_trx->id,
  //     "pvr_id" => $standby_trx->pvr_id,
  //     "pvr_no" => $standby_trx->pvr_no,
  //     "pvr_total" => $standby_trx->pvr_total,
  //     "pvr_had_detail" => $standby_trx->pvr_had_detail,
  //     "updated_at"=>$t_stamp
  //   ];
  // }

  // public function doUpdatePV(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'standby_trx.get_pv');
  //   $rules = [
  //     'online_status' => "required",
  //   ];

  //   $messages = [
  //     'id.exists' => 'ID tidak terdaftar',
  //   ];

  //   $validator = Validator::make($request->all(), $rules, $messages);

  //   if ($validator->fails()) {
  //     throw new ValidationException($validator);
  //   }
  //   $online_status=$request->online_status;
  //   if($online_status!="true")
  //   return response()->json([
  //     "message" => "Mode Harus Online",
  //   ], 400);

  //   $miniError="";
  //   try {
  //     $t_stamp = date("Y-m-d H:i:s");
  //     $standby_trxs = StandbyTrx::whereNotNull("pvr_id")->whereNull("pv_id")->where("req_deleted",0)->where("deleted",0)->get();
  //     if(count($standby_trxs)==0){
  //       throw new \Exception("Semua PVR yang ada ,PV ny sudah terisi",1);
  //     }

  //     $pvr_nos=$standby_trxs->pluck('pvr_no');
  //     // $pvr_nos=['KPN/PV-R/2404/0951','KPN/PV-R/2404/1000'];
  //     $get_data_pvs = DB::connection('sqlsrv')->table('FI_ARAPINFO')
  //     ->selectRaw('fi_arap.VoucherID,fi_arap.VoucherDate,Sources,fi_arap.VoucherNo,FI_APRequest.PVRSourceID,fi_arap.AmountPaid')
  //     ->join('fi_arap',function ($join){
  //       $join->on("fi_arap.VoucherID","FI_ARAPINFO.VoucherID");        
  //     })
  //     ->join('FI_APRequest',function ($join){
  //       $join->on("FI_APRequest.VoucherNo","FI_ARAPINFO.Sources");        
  //     })
  //     ->whereIn("sources",$pvr_nos)
  //     ->get();

  //     $get_data_pvs=MyLib::objsToArray($get_data_pvs);
  //     $changes=[];
  //     foreach ($get_data_pvs as $key => $v) {
  //       $ud_standby_trx=StandbyTrx::where("id", $v["PVRSourceID"])->where("pvr_no", $v["Sources"])->first();
  //       if(!$ud_standby_trx) continue;
  //       $ud_standby_trx->pv_id=$v["VoucherID"];
  //       $ud_standby_trx->pv_no=$v["VoucherNo"];
  //       $ud_standby_trx->pv_total=$v["AmountPaid"];
  //       $ud_standby_trx->pv_datetime=$v["VoucherDate"];
  //       $ud_standby_trx->updated_at=$t_stamp;
  //       $ud_standby_trx->save();
  //       array_push($changes,[
  //         "id"=>$ud_standby_trx->id,
  //         "pv_id"=>$ud_standby_trx->pv_id,
  //         "pv_no"=>$ud_standby_trx->pv_no,
  //         "pv_total"=>$ud_standby_trx->pv_total,
  //         "pv_datetime"=>$ud_standby_trx->pv_datetime,
  //         "updated_at"=>$t_stamp
  //       ]);
  //     }

  //     if(count($changes)==0)
  //     throw new \Exception("PV Tidak ada yang di Update",1);


  //     $ids = array_map(function ($x) {
  //       return $x["id"];
  //     }, $changes);

  //     MyLog::sys("standby_trx",null,"update_pv",implode(",",$ids));

  //     return response()->json([
  //       "message" => "PV Berhasil di Update",
  //       "data" => $changes,
  //     ], 200);
      
  //   } catch (\Exception $e) {
  //     if ($e->getCode() == 1) {
  //       $miniError="PV Batal Update: ".$e->getMessage();
  //     }else{
  //       $miniError="PV Batal Update. Akses Jaringan Gagal";
  //     }
  //     return response()->json([
  //       "message" => $miniError,
  //     ], 400);
  //   }
  // }


  // public function indexold(Request $request, $download = false)
  // {
 
  //   //======================================================================================================
  //   // Pembatasan Data hanya memerlukan limit dan offset
  //   //======================================================================================================

  //   $limit = 100; // Limit +> Much Data
  //   if (isset($request->limit)) {
  //     if ($request->limit <= 250) {
  //       $limit = $request->limit;
  //     } else {
  //       throw new MyException(["message" => "Max Limit 250"]);
  //     }
  //   }

  //   $offset = isset($request->offset) ? (int) $request->offset : 0; // example offset 400 start from 401

  //   //======================================================================================================
  //   // Jika Halaman Ditentutkan maka $offset akan disesuaikan
  //   //======================================================================================================
  //   if (isset($request->page)) {
  //     $page =  (int) $request->page;
  //     $offset = ($page * $limit) - $limit;
  //   }


  //   //======================================================================================================
  //   // Init Model
  //   //======================================================================================================
  //   $model_query = new StandbyTrx();
  //   if (!$download) {
  //     $model_query = $model_query->offset($offset)->limit($limit);
  //   }

  //   $first_row=[];
  //   if($request->first_row){
  //     $first_row 	= json_decode($request->first_row, true);
  //   }

  //   //======================================================================================================
  //   // Model Sorting | Example $request->sort = "username:desc,role:desc";
  //   //======================================================================================================
    

  //   if ($request->sort) {
  //     $sort_lists = [];

  //     $sorts = explode(",", $request->sort);
  //     foreach ($sorts as $key => $sort) {
  //       $side = explode(":", $sort);
  //       $side[1] = isset($side[1]) ? $side[1] : 'ASC';
  //       $sort_symbol = $side[1] == "desc" ? "<=" : ">=";
  //       $sort_lists[$side[0]] = $side[1];
  //     }

  //     $list_to_sort = ["id","created_at","updated_at"];
  //     foreach ($list_to_sort as $key => $v) {
  //       if (isset($sort_lists[$v])) {
  //         $model_query = $model_query->orderBy($v, $sort_lists[$v]);
  //         if (count($first_row) > 0) {
  //           $model_query = $model_query->where($v,$sort_symbol,$first_row[$v]);
  //         }
  //       }
  //     }

  //     // if (isset($sort_lists["tanggal"])) {
  //     //   $model_query = $model_query->orderBy("tanggal", $sort_lists["tanggal"])->orderBy('id','DESC');
  //     //   if (count($first_row) > 0) {
  //     //     $model_query = $model_query->where("tanggal",$sort_symbol,$first_row["tanggal"])->orderBy('id','DESC');
  //     //   }
  //     // }
      

  //   } else {
  //     $model_query = $model_query->orderBy('updated_at', 'DESC');
  //   }
  //   //======================================================================================================
  //   // Model Filter | Example $request->like = "username:%username,role:%role%,name:role%,";
  //   //======================================================================================================

  //   if ($request->like) {
  //     $like_lists = [];

  //     $likes = explode(",", $request->like);
  //     foreach ($likes as $key => $like) {
  //       $side = explode(":", $like);
  //       $side[1] = isset($side[1]) ? $side[1] : '';
  //       $like_lists[$side[0]] = $side[1];
  //     }

  //     $list_to_like = ["id","transition_target","transition_type",
  //     "supir","kernet","no_pol","xto","pvr_id","pvr_no","pv_id","pv_no",
  //     "rv_id","rv_no","cost_center_code","cost_center_desc"];

  //     $list_to_like_user = [
  //       ["val_name","val_user"],
  //       ["val1_name","val1_user"],
  //       ["val2_name","val2_user"],
  //       ["req_deleted_name","req_deleted_user"],
  //       ["deleted_name","deleted_user"],
  //     ];

  //     $list_to_like_standby_mst = [
  //       ["standby_mst_name","name"],
  //       ["standby_mst_type","tipe"]
  //     ];

  //     if(count($like_lists) > 0){
  //       $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_user,$list_to_like_standby_mst){
  //         foreach ($list_to_like as $key => $v) {
  //           if (isset($like_lists[$v])) {
  //             $q->orWhere($v, "like", $like_lists[$v]);
  //           }
  //         }

  //         foreach ($list_to_like_user as $key => $v) {
  //           if (isset($like_lists[$v[0]])) {
  //             $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
  //               $q2->from('is_users')
  //               ->select('id')->where("username",'like',$like_lists[$v[0]]);          
  //             });
  //           }
  //         }

  //         foreach ($list_to_like_standby_mst as $key => $v) {
  //           if (isset($like_lists[$v[0]])) {
  //             $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
  //               $q2->from('standby_msts')
  //               ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
  //             });
  //           }
  //         }
  //       });        
  //     }     
  //   }

  //   // ==============
  //   // Model Filter
  //   // ==============
  //   if($request->date_from || $request->date_to){
  //     $date_from = $request->date_from;
  //     if(!$date_from)
  //     throw new MyException([ "date_from" => ["Date From harus diisi"] ], 422);

  //     if(!strtotime($date_from))
  //     throw new MyException(["date_from"=>["Format Date From Tidak Cocok"]], 422);
      
  //     $date_to = $request->date_to;
  //     if(!$date_to)
  //     throw new MyException([ "date_to" => ["Date To harus diisi"] ], 422);

  //     if(!strtotime($date_to))
  //     throw new MyException(["date_to"=>["Format Date To Tidak Cocok"]], 422);

  //     $date_from = date("Y-m-d",strtotime($date_from))." 00:00:00";
  //     $date_to = date("Y-m-d",strtotime($date_to))." 23:59:59";
      
  //     $model_query = $model_query->where("created_at",">=",$date_from);
  //     $model_query = $model_query->where("created_at","<=",$date_to);
  //   }
  //   $filter_status = $request->filter_status;
    

  //   if($filter_status=="trx_done"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->whereNotNull("pv_no");
  //   }

  //   if($filter_status=="trx_not_done"){
  //     $model_query = $model_query->where("deleted",0)->whereNull("pv_no")->where("req_deleted",0);
  //   }

  //   if($filter_status=="deleted"){
  //     $model_query = $model_query->where("deleted",1);
  //   }

  //   if($filter_status=="req_deleted"){
  //     $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
  //   }

  //   $model_query = $model_query->with(['val_by','val1_by','val2_by','deleted_by','req_deleted_by','standby_mst'])->get();

  //   return response()->json([
  //     "data" => StandbyTrxResource::collection($model_query),
  //   ], 200);
  // }

}
