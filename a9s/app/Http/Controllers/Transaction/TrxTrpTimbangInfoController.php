<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Barryvdh\DomPDF\Facade\PDF;
use Maatwebsite\Excel\Facades\Excel;

use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\TrxTrp;
use App\Models\MySql\IsUser;
use App\Models\MySql\TrxAbsen;
use App\Models\MySql\Ujalan;
use App\Models\MySql\UjalanDetail;

use App\Http\Requests\MySql\TrxTrpTimbangInfoRequest;
use App\Http\Requests\MySql\TrxTrpAbsenTicketRequest;

use App\Http\Resources\MySql\TrxTrpTimbangInfoResource;
use App\Http\Resources\MySql\TrxTrpTimbangInfoResourceShow;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Http\Resources\MySql\TrxAbsenResource;
use App\Http\Resources\MySql\TrxTrpResource;
use InvalidArgumentException;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;

class TrxTrpTimbangInfoController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'trx_trp';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();

  }

  public function index(Request $request, $download = false)
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.timbang_info.views']);

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
    $model_query = new TrxTrp();
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

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists){
          
          $list_to_like = ["id","xto","tipe","jenis","supir","kernet","no_pol","tanggal"];
    
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }
          
          $list_to_like_uj = [
            ["uj_asst_opt","asst_opt"],
          ];
          foreach ($list_to_like_uj as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn('id_uj', function($q2)use($like_lists,$v) {
                $q2->from('is_uj')
                ->select('id')->where($v[1],'like',$like_lists[$v[0]]);          
              });
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
          if(array_search($value['key'],['uj_asst_opt'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"uj","id_uj",$value['key'],$filter_model[$value['key']]["sort_type"],"is_uj");
          } else{
            $model_query = $model_query->orderBy($value['key'], $filter_model[$value['key']]["sort_type"]);
            if (count($first_row) > 0) {
              $sort_symbol = $filter_model[$value['key']]["sort_type"] == "desc" ? "<=" : ">=";
              $model_query = $model_query->where($value['key'],$sort_symbol,$first_row[$value['key']]);
            }
          }
        }
      }

      $model_query = $model_query->where(function ($q)use($filter_model,$request){

        foreach ($filter_model as $key => $value) {
          if(!isset($value['type'])) continue;

          if(array_search($key,['status'])!==false){
            if(array_search($value['type'],['select'])!==false && $value['value_1']){

              if(array_search($key,['status'])!==false){
                $r_val = $value['value_1'];
                if($value["operator"]=='exactly_same'){
                }else {
                  if($r_val=='Undone'){
                    $r_val='Done';
                  }else{
                    $r_val='Undone';
                  };
                }

                if($r_val=='Done'){
                  $q->where("timbang_val1",1)->where("deleted",0)->where("req_deleted",0);
                  // $q->whereNotNull("ritase_leave_at")->whereNotNull("ritase_arrive_at")->whereNotNull("ritase_return_at")->whereNotNull("ritase_till_at");
                }else{
                  $q->where("timbang_val1",0)->where("deleted",0)->where("req_deleted",0);
                  // $q->whereNull("ritase_leave_at")->orWhereNull("ritase_arrive_at")->orWhereNull("ritase_return_at")->orWhereNull("ritase_till_at");
                }
              }
            }
          }else if(array_search($key,['uj_asst_opt'])!==false){
            MyLib::queryCheckP1Dif("uj",$value,$key,$q,'is_uj',"id_uj");
          }else{
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
      $model_query = $model_query->orderBy('tanggal', 'DESC')->orderBy('id','DESC');
    }

    $filter_status = $request->filter_status;
    
    if($filter_status=='Done'){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where("timbang_val1",1);
      // ->where(function ($q) {
      //   $q->whereNotNull("ritase_leave_at")->whereNotNull("ritase_arrive_at")->whereNotNull("ritase_return_at")->whereNotNull("ritase_till_at");
      // });

    }elseif ($filter_status=="Undone") {
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where("timbang_val1",0);
      // ->where(function ($q) {
      //   $q->whereNull("ritase_leave_at")->orWhereNull("ritase_arrive_at")->orWhereNull("ritase_return_at")->orWhereNull("ritase_till_at");
      // });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    if($filter_status=="req_deleted"){
      $model_query = $model_query->where("deleted",0)->where("req_deleted",1);
    }

    $model_query = $model_query->with(['timbang_val1_by','deleted_by','req_deleted_by','uj'])->get();

    return response()->json([
      "data" => TrxTrpTimbangInfoResource::collection($model_query),
    ], 200);
  }

  // public function showBAbsen(TrxTrpTimbangInfoRequest $request)
  // {
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

  //   $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','trx_absens'=>function ($q){
  //     $q->where("status","B");
  //   },'potongan'])->find($request->id);
  //   return response()->json([
  //     "data" => new TrxTrpResource($model_query),
  //   ], 200);
  // }

  public function show(TrxTrpTimbangInfoRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.timbang_info.view']);

    $model_query = TrxTrp::with(['timbang_val1_by','deleted_by','req_deleted_by','uj'])->find($request->id);

    return response()->json([
      "data" => new TrxTrpTimbangInfoResourceShow($model_query),
    ], 200);
  }

  private $height = 500;
  private $quality = 100;

  public function update(TrxTrpTimbangInfoRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.timbang_info.modify');
    $t_stamp = date("Y-m-d H:i:s");

    $att_temp = [
      "a_in"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "a_out"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "b_in"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "b_out"=>[
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
    ];

    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      if($model_query->timbang_val1==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $att_temp['a_in']['oldLoc']=$model_query->timbang_a_img_in_loc;
      if($request->hasFile('timbang_a_img_in')){
        $file = $request->file('timbang_a_img_in');
        $doc_path = $file->getRealPath();
        $doc_type = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$doc_type))
        throw new MyException([ "timbang_a_img_in" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);       

        $file_name = $model_query->id."_timbang_a_img_in_".Str::uuid() . '.' . $ext;
        $doc_loc = "trx_trp/timbang_info/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($doc_loc, file_get_contents($doc_path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['a_in']['newLoc']=$doc_loc;
        $att_temp['a_in']['useNew']=true;
      }
      if (!$request->hasFile('timbang_a_img_in') && in_array($request->timbang_a_img_in_preview,[null,'null'])) {
        $att_temp['a_in']['newLoc']=null;
        $att_temp['a_in']['useNew']=true;
      }
      $model_query->timbang_a_img_in_loc = $att_temp['a_in']['useNew']?$att_temp['a_in']['newLoc']:$att_temp['a_in']['oldLoc'];

      $att_temp['a_out']['oldLoc']=$model_query->timbang_a_img_out_loc;
      if($request->hasFile('timbang_a_img_out')){
        $file = $request->file('timbang_a_img_out');
        $doc_path = $file->getRealPath();
        $doc_type = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$doc_type))
        throw new MyException([ "timbang_a_img_out" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);       

        $file_name = $model_query->id."_timbang_a_img_out_".Str::uuid() . '.' . $ext;
        $doc_loc = "trx_trp/timbang_info/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($doc_loc, file_get_contents($doc_path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['a_out']['newLoc']=$doc_loc;
        $att_temp['a_out']['useNew']=true;
      }
      if (!$request->hasFile('timbang_a_img_out') && in_array($request->timbang_a_img_out_preview,[null,'null'])) {
        $att_temp['a_out']['newLoc']=null;
        $att_temp['a_out']['useNew']=true;
      }
      $model_query->timbang_a_img_out_loc = $att_temp['a_out']['useNew']?$att_temp['a_out']['newLoc']:$att_temp['a_out']['oldLoc'];

      $att_temp['b_in']['oldLoc']=$model_query->timbang_b_img_in_loc;
      if($request->hasFile('timbang_b_img_in')){
        $file = $request->file('timbang_b_img_in');
        $doc_path = $file->getRealPath();
        $doc_type = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$doc_type))
        throw new MyException([ "timbang_b_img_in" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);       

        $file_name = $model_query->id."_timbang_b_img_in_".Str::uuid() . '.' . $ext;
        $doc_loc = "trx_trp/timbang_info/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($doc_loc, file_get_contents($doc_path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['b_in']['newLoc']=$doc_loc;
        $att_temp['b_in']['useNew']=true;
      }
      if (!$request->hasFile('timbang_b_img_in') && in_array($request->timbang_b_img_in_preview,[null,'null'])) {
        $att_temp['b_in']['newLoc']=null;
        $att_temp['b_in']['useNew']=true;
      }
      $model_query->timbang_b_img_in_loc = $att_temp['b_in']['useNew']?$att_temp['b_in']['newLoc']:$att_temp['b_in']['oldLoc'];

      $att_temp['b_out']['oldLoc']=$model_query->timbang_b_img_out_loc;
      if($request->hasFile('timbang_b_img_out')){
        $file = $request->file('timbang_b_img_out');
        $doc_path = $file->getRealPath();
        $doc_type = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$doc_type))
        throw new MyException([ "timbang_b_img_out" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);       

        $file_name = $model_query->id."_timbang_b_img_out_".Str::uuid() . '.' . $ext;
        $doc_loc = "trx_trp/timbang_info/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($doc_loc, file_get_contents($doc_path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['b_out']['newLoc']=$doc_loc;
        $att_temp['b_out']['useNew']=true;
      }
      if (!$request->hasFile('timbang_b_img_out') && in_array($request->timbang_b_img_out_preview,[null,'null'])) {
        $att_temp['b_out']['newLoc']=null;
        $att_temp['b_out']['useNew']=true;
      }
      $model_query->timbang_b_img_out_loc = $att_temp['b_out']['useNew']?$att_temp['b_out']['newLoc']:$att_temp['b_out']['oldLoc'];


      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;
      $model_query->timbang_note       = $request->timbang_note;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("trx_trp",$request->id,"update absen",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();
      return response()->json([
        "getCode" => $e->getCode(),
        "line" => $e->getLine(),
        "message" => $e->getMessage(),
      ], 400);
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

  public function getAttachment($id,$n)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.timbang_info.view');

    $trx = TrxTrp::findOrFail($id);
    $locField = 'timbang_'.$n."_loc";
   
    abort_unless($trx->$locField, 404,$trx->$locField);

    abort_unless(Storage::disk('public')->exists($trx->$locField), 404,"not exists");

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('public');  
    return $disk->response(
        $trx->$locField,
        null,
        [
            'Cache-Control' => 'no-store, private',
            'Content-Type'  => "image/png",
            'X-Attachment'  => $n,
        ]
    );

    // return response()->file($path, [
    //   'Cache-Control'=> 'no-store, private',
    //   'Content-Type'  => $trx->$typeField,
    //   'X-Attachment' => $n,
    // ]);
  }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.timbang_info.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\TrxTrp,id",
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
      $model_query = TrxTrp::find($request->id);
      if($model_query->timbang_val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }

      if(!$model_query->timbang_note && (!$model_query->timbang_a_img_in_loc || !$model_query->timbang_a_img_out_loc || !$model_query->timbang_b_img_in_loc || !$model_query->timbang_b_img_out_loc) )
      throw new \Exception("Gambar Belum Lengkap dan tidak disertai Catatan",1);

      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.timbang_info.val1',true) && !$model_query->timbang_val1){
        $model_query->timbang_val1 = 1;
        $model_query->timbang_val1_user = $this->admin_id;
        $model_query->timbang_val1_at = $t_stamp;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"approve_absen",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message"         => "Proses validasi data berhasil",
        "timbang_val1"     => $model_query->timbang_val1,
        "timbang_val1_user"=> $model_query->timbang_val1_user,
        "timbang_val1_at"  => $model_query->timbang_val1_at,
        "timbang_val1_by"  => $model_query->timbang_val1_user ? new IsUserResource(IsUser::find($model_query->timbang_val1_user)) : null,
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

  // public function clearValVal1(Request $request){
  //   MyAdmin::checkMultiScope($this->permissions, ['trp_trx.timbang_info.clear_valval1']);

  //   $ids = json_decode($request->ids, true);
  //   $t_stamp = date("Y-m-d H:i:s");
  //   DB::beginTransaction();
  //   try {
  //     $SYSNOTES = [];
  //     $model_query = TrxTrp::whereIn("id",$ids)->lockForUpdate()->get();
  //     $valList = [];
      
  //     foreach ($model_query as $key => $v) {
  //       $SYSOLD                     = clone($v);
  //       if($v->salary_paid_id != null){
  //         throw new \Exception("Data #".$v->id." Tidak Bisa Di unvalidasi lagi karna sudah memiliki Salary Paid ID",1);
  //       }
  //       $change=0;
  //       if($v->ritase_val != 0){
  //         $v->ritase_val        = 0;
  //         $change++;
  //       }
  //       if($v->ritase_val1 != 0){
  //         $v->ritase_val1       = 0;
  //         $change++;
  //       }
  //       if($v->ritase_val2 != 0){
  //         $v->ritase_val2       = 0;
  //         $change++;
  //       }
  //       if($change > 0){
  //         $v->updated_at        = $t_stamp;
  //         $v->updated_user      = $this->admin_id;
  
  //         $v->save();

  //         array_push($valList,[
  //           "id"          => $v->id,
  //           "ritase_val"  => $v->ritase_val,
  //           "ritase_val1" => $v->ritase_val1,
  //           "ritase_val2" => $v->ritase_val2,
  //           "updated_at"  => $v->updated_at,
  //         ]);
  //       }

  //       $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);         
  //       array_push($SYSNOTES,$SYSNOTE);
  //     }
  //     MyLog::sys($this->syslog_db,null,"clearValVal1",implode(",",$SYSNOTES));

  //     $nids = array_map(function($x) {
  //       return $x['id'];        
  //     },$valList);
      
  //     // MyLog::sys("trx_trp",null,"unval_absen",implode(",",$nids));

  //     DB::commit();
  //     if(count($nids) == 0 ){
  //       return response()->json([
  //         "message"   => "Tidak Ada Data Yang Di proses",
  //         "val_lists" => $valList
  //         ], 400);  
  //     }else{
  //       return response()->json([
  //         "message"   => "Proses clear validasi berhasil",
  //         "val_lists" => $valList
  //       ], 200);
  //     }
  //   } catch (\Exception $e) {
  //     DB::rollback();
  //     if ($e->getCode() == 1) {
  //       return response()->json([
  //         "message" => $e->getMessage(),
  //       ], 400);
  //     }
  //     // return response()->json([
  //     //   "getCode" => $e->getCode(),
  //     //   "line" => $e->getLine(),
  //     //   "message" => $e->getMessage(),
  //     // ], 400);
  //     return response()->json([
  //       "message" => "Proses clear validasi gagal",
  //     ], 400);
  //   }

  // }


  // public function downloadExcel(Request $request){
  //   MyAdmin::checkScope($this->permissions, 'trp_trx.timbang_info.download_file');

  //   set_time_limit(0);
  //   $callGet = $this->index($request, true);
  //   if ($callGet->getStatusCode() != 200) return $callGet;
  //   $ori = json_decode(json_encode($callGet), true)["original"];
    

  //   $newDetails = [];

  //   foreach ($ori["data"] as $key => $value) {
  //     $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
  //     array_push($newDetails,$value);
  //   }

  //   $filter_model = json_decode($request->filter_model,true);
  //   $tanggal = $filter_model['tanggal'];    


  //   $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
  //   $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

  //   $date = new \DateTime();
  //   $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."*".$date_to."]";

  //   $mime=MyLib::mime("xlsx");
  //   // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
  //   $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_absen_raw'), $mime["exportType"]));


  //   $result = [
  //     "contentType" => $mime["contentType"],
  //     "data" => $bs64,
  //     "dataBase64" => $mime["dataBase64"] . $bs64,
  //     "filename" => $filename . "." . $mime["ext"],
  //   ];
  //   return $result;
  // }
}
