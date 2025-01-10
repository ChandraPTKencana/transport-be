<?php

namespace App\Http\Controllers\Standby;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

use App\Exceptions\MyException;

use App\Helpers\MyLib;
use App\Helpers\MyAdmin;
use App\Helpers\MyLog;

use App\Models\MySql\StandbyMst;
use App\Models\MySql\StandbyDtl;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\StandbyMstRequest;

use App\Http\Resources\MySql\StandbyMstResource;
use App\Http\Resources\IsUserResource;
use App\Models\MySql\StandbyTrx;
use Exception;
use Illuminate\Support\Facades\DB;

class StandbyMstController extends Controller
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

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_mst.views');
 
    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 50; // Limit +> Much Data
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
    $model_query = StandbyMst::offset($offset)->limit($limit);

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

      $list_to_like = ["id","name","amount"];

      // $list_to_like_user = [
      //   ["val_name","val_user"],
      //   ["val1_name","val1_user"],
      //   ["val2_name","val2_user"],
      //   ["req_deleted_name","req_deleted_user"],
      //   ["deleted_name","deleted_user"],
      // ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          // foreach ($list_to_like_user as $key => $v) {
          //   if (isset($like_lists[$v[0]])) {
          //     $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
          //       $q2->from('is_users')
          //       ->select('id')->where("username",'like',$like_lists[$v[0]]);          
          //     });
          //   }
          // }

        });        
      }     
    }

    // ==============
    // Model Filter
    // ==============

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
      $model_query = $model_query->orderBy('name', 'asc')->orderBy('id','DESC');
    }
    
    $filter_status = $request->filter_status;
    
    if($filter_status=="available"){
      $model_query = $model_query->where("deleted",0)->where("val",1)->where("val1",1);
    }

    if($filter_status=="unapprove"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val",0)->orWhere("val1",0); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    // ==============
    // Model Filter
    // ==============

    $model_query = $model_query->with('deleted_by')->get();

    return response()->json([
      "data" => StandbyMstResource::collection($model_query),
    ], 200);
  }

  public function show(StandbyMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_mst.view');

    $model_query = StandbyMst::with([
    'details'=>function($q){
      $q->orderBy("ordinal","asc");
    },
    ])->with(['val_by','val1_by','deleted_by'])->where("deleted",0)->find($request->id);

    return response()->json([
      "data" => new StandbyMstResource($model_query),
    ], 200);
  }

  public function validateItems($details_in){
    $rules = [
      
      'details'                          => 'required|array',
      // 'details.*.id_uj'               => 'required|exists:\App\Models\MySql\StandbyMst',
      'details.*.p_status'               => 'required|in:Remove,Add,Edit',
      'details.*.ac_account_id'          => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_code'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.ac_account_name'        => 'required_if:details.*.p_status,Add,Edit',
      'details.*.amount'                 => 'required_if:details.*.p_status,Add,Edit|numeric',
      'details.*.description'            => 'required_if:details.*.p_status,Add,Edit',
      'details.*.xfor'                   => 'nullable|in:Supir,Kernet',
      // 'details.*.status'              => 'required|in:Y,N',
    ];

    $messages = [
      'details.required' => 'List Item harus di isi',
      'details.array' => 'Format Pengambilan Barang Salah',
    ];

    // // Replace :index with the actual index value in the custom error messages
    foreach ($details_in as $index => $msg) {
      // $messages["details.{$index}.id_uj.required"]            = "Baris #" . ($index + 1) . ". ID StandbyMst yang diminta tidak boleh kosong.";
      // $messages["details.{$index}.id_uj.exists"]              = "Baris #" . ($index + 1) . ". ID StandbyMst yang diminta harus dipilih";

      $messages["details.{$index}.ac_account_id.required_if"]    = "Baris #" . ($index + 1) . ". Acc ID yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.required_if"]  = "Baris #" . ($index + 1) . ". Acc Code yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_name.required_if"]  = "Baris #" . ($index + 1) . ". Acc Name yang diminta tidak boleh kosong.";
      $messages["details.{$index}.ac_account_code.max"]          = "Baris #" . ($index + 1) . ". Acc Code Maksimal 255 Karakter";

      // $messages["details.{$index}.qty.required_if"]           = "Baris #" . ($index + 1) . ". Qty harus di isi";
      // $messages["details.{$index}.qty.numeric"]               = "Baris #" . ($index + 1) . ". Qty harus berupa angka";

      $messages["details.{$index}.amount.required_if"]           = "Baris #" . ($index + 1) . ". Amount harus di isi";
      $messages["details.{$index}.amount.numeric"]               = "Baris #" . ($index + 1) . ". Amount harus berupa angka";

      $messages["details.{$index}.description.required_if"]      = "Baris #" . ($index + 1) . ". Description harus di isi";
      $messages["details.{$index}.xfor.in"]                      = "Baris #" . ($index + 1) . ". harus di pilih";
      
    }

    $validator = Validator::make(['details' => $details_in], $rules, $messages);

    // Check if validation fails
    if ($validator->fails()) {
      foreach ($validator->messages()->all() as $k => $v) {
        throw new MyException(["message" => $v], 400);
      }
    }
  }

  public function store(StandbyMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_mst.create');

    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);

    if(count($details_in)>0){
      MyAdmin::checkScope($this->permissions, 'standby_mst.detail.insert');
    }

    $rollback_id = -1;
    DB::beginTransaction();
    try {
      //start for details2
      $unique_items = [];
      $unique_acc_code=[];
      foreach ($details_in as $key => $value) {
        $unique_data = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data, $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, $unique_data);
        if($value["p_status"]!="Remove")
        array_push($unique_acc_code,$value['ac_account_code']);
      }
      $unique_acc_code = array_unique($unique_acc_code);
  
      $temp_ac_accounts = [];
      $listToCheck = [];
      if(count($unique_acc_code)>0){
        $connectionDB = DB::connection('sqlsrv');
        $temp_ac_accounts = $connectionDB->table("AC_Accounts")
        ->select('AccountID','AccountCode','AccountName')
        ->whereIn('AccountCode',$unique_acc_code)
        ->get();
  
        $temp_ac_accounts= MyLib::objsToArray($temp_ac_accounts);
  
        $listToCheck = array_map(function($x){
          return $x["AccountCode"];
        },$temp_ac_accounts);

        if(count($temp_ac_accounts)!=count($unique_acc_code)){
  
          $diff = array_diff($unique_acc_code,$listToCheck);
          throw new \Exception(implode(",",$diff)."tidak terdaftar",1);
        }
      }

      $t_stamp = date("Y-m-d H:i:s");

      $model_query                  = new StandbyMst();      
      $model_query->name            = $request->name;
      $model_query->tipe            = $request->tipe;
      $model_query->amount          = 0;

      $model_query->created_at      = $t_stamp;
      $model_query->created_user    = $this->admin_id;
      $model_query->is_transition   = $request->is_transition=='true' ? 1 : 0;
      $model_query->is_trip         = $request->is_trip=='true' ? 1 : 0;

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      $model_query->save();
      $rollback_id = $model_query->id - 1;

      $ordinal=0;
      $there_supir=0;
      $there_kernet=0;

      foreach ($details_in as $key => $value) {
        $ordinal = $key + 1;
        $detail                     = new StandbyDtl();
        $detail->standby_mst_id     = $model_query->id;
        $detail->ordinal            = $ordinal;
        
        $index_item = array_search($value['ac_account_code'], $listToCheck);
        
        if ($index_item !== false){
          $detail->ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
          $detail->ac_account_name  = $temp_ac_accounts[$index_item]['AccountName'];
          $detail->ac_account_code  = $temp_ac_accounts[$index_item]['AccountCode'];
        }

        $detail->amount             = $value['amount'];
        $detail->description        = $value['description'];
        $detail->xfor               = $value['xfor'];
        $model_query->amount        +=  $value["amount"];
        $detail->created_at         = $t_stamp;
        $detail->created_user       = $this->admin_id;
  
        $detail->updated_at         = $t_stamp;
        $detail->updated_user       = $this->admin_id;  
        $detail->save();

        if($value['xfor']=="Kernet") $there_kernet=1;
        if($value['xfor']=="Supir") $there_supir=1;
      }
      //end for details2


      if($there_kernet && $there_supir){
        $model_query->driver_asst_opt = "SUPIR KERNET";
      } elseif ($there_kernet && !$there_supir) {
        $model_query->driver_asst_opt = "KERNET";      
      } elseif (!$there_kernet && $there_supir) {
        $model_query->driver_asst_opt = "SUPIR";
      }

      $model_query->save();
      MyLog::sys("standby_mst",$model_query->id,"insert");

      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if($rollback_id>-1)
      DB::statement("ALTER TABLE standby_mst AUTO_INCREMENT = $rollback_id");
      
      return response()->json([
        "message" => $e->getMessage(),
      ], 400);
      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      return response()->json([
        "message" => "Proses tambah data gagal",
      ], 400);
    }
  }

  public function update(StandbyMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_mst.modify');
    $t_stamp = date("Y-m-d H:i:s");

    //start for details2
    $details_in = json_decode($request->details, true);
    $this->validateItems($details_in);
    //end for details2

    DB::beginTransaction();
    try {
      $SYSNOTES=[];
      $model_query = StandbyMst::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      array_push( $SYSNOTES ,"Details: \n");
      if($model_query->deleted==1)
      throw new \Exception("Data Sudah Dihapus",1);
      
      if(
        ($model_query->val==1 && $model_query->val1==1) || 
        (MyAdmin::checkScope($this->permissions, 'standby_mst.val',true) && $model_query->val == 1) ||
        (MyAdmin::checkScope($this->permissions, 'standby_mst.val1',true) && $model_query->val1 == 1)
      ) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);
      
      //start for details2
      $unique_items = [];
      $unique_acc_code=[];
      foreach ($details_in as $key => $value) {
        $unique_data = strtolower(trim($value['ac_account_code']).trim($value['description']));
        if (in_array($unique_data, $unique_items) == 1) {
          throw new \Exception("Maaf terdapat Item yang sama",1);
        }
        array_push($unique_items, $unique_data);
        if($value["p_status"]!="Remove")
        array_push($unique_acc_code,$value['ac_account_code']);
      }
      $unique_acc_code = array_unique($unique_acc_code);
  
      $temp_ac_accounts = [];
      $listToCheck = [];
      if(count($unique_acc_code)>0){
        $connectionDB = DB::connection('sqlsrv');
        $temp_ac_accounts = $connectionDB->table("AC_Accounts")
        ->select('AccountID','AccountCode','AccountName')
        ->whereIn('AccountCode',$unique_acc_code) // RTBS & MTBS untuk armada TBS CPO & PK untuk armada cpo pk
        ->get();
  
        $temp_ac_accounts= MyLib::objsToArray($temp_ac_accounts);
  
        $listToCheck = array_map(function($x){
          return $x["AccountCode"];
        },$temp_ac_accounts);

        if(count($temp_ac_accounts)!=count($unique_acc_code)){  
          $diff = array_diff($unique_acc_code,$listToCheck);
          throw new \Exception(implode(",",$diff)."tidak terdaftar",1);
        }
      }
      //end for details2

      $model_query->name            = $request->name;
      $model_query->tipe            = $request->tipe;
      $model_query->amount          = 0;
      $model_query->is_transition   = $request->is_transition=='true' ? 1 : 0; 
      $model_query->is_trip         = $request->is_trip=='true' ? 1 : 0; 

      $model_query->updated_at      = $t_stamp;
      $model_query->updated_user    = $this->admin_id;

      //start for details2
      $data_from_db = StandbyDtl::where('standby_mst_id', $model_query->id)
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
          $unique = strtolower(trim($v['ac_account_code']).trim($v['description']));
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
        MyAdmin::checkScope($this->permissions, 'standby_mst.detail.insert');
      }

      if(count($for_edits) > 0){
        MyAdmin::checkScope($this->permissions, 'standby_mst.detail.modify');
      }

      if(count($for_deletes) > 0){
        MyAdmin::checkScope($this->permissions, 'standby_mst.detail.remove');
      }

      $data_to_processes = array_merge($for_deletes, $for_edits, $for_adds);
      
      if(count($for_edits)==0 && count($for_adds)==0){
        throw new \Exception("Data List Harus Diisi",1);
      }

      $there_supir=0;
      $there_kernet=0;

      foreach ($data_to_processes as $k => $v) {
        $index = false;

        if (isset($v["key"])) {
            $index = array_search($v["key"], $am_ordinal_db);
        }

        if ($v["p_status"] == "Remove") {

            if ($index === false) {
                throw new \Exception("Data yang ingin dihapus tidak ditemukan",1);
            } else {
                $dt = $data_from_db[$index];
                array_push( $SYSNOTES ,"Ordinal ".$dt["ordinal"]." [Deleted]");
                StandbyDtl::where("standby_mst_id",$model_query->id)->where("ordinal",$dt["ordinal"])->delete();
            }
        } else if ($v["p_status"] == "Edit") {

            if ($index === false) {
                throw new \Exception("Data yang ingin diubah tidak ditemukan" . $k,1);
            } else {
             
              $model_query->amount  += $v["amount"];

              $index_item = array_search($v['ac_account_code'], $listToCheck);
              $ac_account_id        = null;
              $ac_account_name      = null;
              $ac_account_code      = null;
              if ($index_item !== false){
                $ac_account_id      = $temp_ac_accounts[$index_item]['AccountID'];
                $ac_account_name    = $temp_ac_accounts[$index_item]['AccountName'];
                $ac_account_code    = $temp_ac_accounts[$index_item]['AccountCode'];
              }

              
              $mq=StandbyDtl::where("standby_mst_id", $model_query->id)
              ->where("ordinal", $v["key"])->where("p_change",false)->lockForUpdate()->first();

              $mqToCom = clone($mq);

              $mq->ordinal         = $v["ordinal"];
              $mq->amount          = $v["amount"];
              $mq->ac_account_id   = $ac_account_id;
              $mq->ac_account_name = $ac_account_name;
              $mq->ac_account_code = $ac_account_code;
              $mq->description     = $v["description"];
              $mq->xfor            = $v["xfor"];
              $mq->p_change        = true;
              $mq->updated_at      = $t_stamp;
              $mq->updated_user    = $this->admin_id;
              $mq->save();

              if($v['xfor']=="Kernet") $there_kernet=1;
              if($v['xfor']=="Supir") $there_supir=1;
              
              $SYSNOTE = MyLib::compareChange($mqToCom,$mq); 
              array_push( $SYSNOTES ,"Ordinal ".$v["key"]."\n".$SYSNOTE);

              // StandbyDtl::where("standby_mst_id", $model_query->id)
              // ->where("ordinal", $v["key"])->where("p_change",false)->update([
              //     "ordinal"         => $v["ordinal"],
              //     "amount"          => $v["amount"],
              //     "ac_account_id"   => $ac_account_id,
              //     "ac_account_name" => $ac_account_name,
              //     "ac_account_code" => $ac_account_code,
              //     "description"     => $v["description"],
              //     // "status" => $v["status"],
              //     "p_change"        => true,
              //     "updated_at"      => $t_stamp,
              //     "updated_user"    => $this->admin_id,
              // ]);

            }
        } else if ($v["p_status"] == "Add") {
            $model_query->amount += $v["amount"];

            $index_item = array_search($v['ac_account_code'], $listToCheck);
            $ac_account_id   = null;
            $ac_account_name = null;
            $ac_account_code = null;
            if ($index_item !== false){
              $ac_account_id    = $temp_ac_accounts[$index_item]['AccountID'];
              $ac_account_name  = $temp_ac_accounts[$index_item]['AccountName'];
              $ac_account_code  = $temp_ac_accounts[$index_item]['AccountCode'];
            }

            array_push( $SYSNOTES ,"Ordinal ".$v["ordinal"]." [Insert]");

            StandbyDtl::insert([
                'standby_mst_id'  => $model_query->id,
                'ordinal'         => $v["ordinal"],
                "amount"          => $v["amount"],
                "ac_account_id"   => $ac_account_id,
                "ac_account_name" => $ac_account_name,
                "ac_account_code" => $ac_account_code,
                "description"     => $v["description"],
                "xfor"            => $v["xfor"],
                // 'status'            => $v['status'],
                "p_change"        => true,
                'created_at'      => $t_stamp,
                'created_user'    => $this->admin_id,
                'updated_at'      => $t_stamp,
                'updated_user'    => $this->admin_id,
            ]);
            if($v['xfor']=="Kernet") $there_kernet=1;
            if($v['xfor']=="Supir") $there_supir=1;
        }
      }

      if($there_kernet && $there_supir){
        $model_query->driver_asst_opt = "SUPIR KERNET";
      } elseif ($there_kernet && !$there_supir) {
        $model_query->driver_asst_opt = "KERNET";      
      } elseif (!$there_kernet && $there_supir) {
        $model_query->driver_asst_opt = "SUPIR";
      }

      $model_query->save();
        //start for details2
      StandbyDtl::where('standby_mst_id',$model_query->id)->update(["p_change"=>false]);

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      array_unshift( $SYSNOTES , $SYSNOTE );            
      MyLog::sys("standby_mst",$request->id,"update",implode("\n",$SYSNOTES));

    //end for details2
      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at"=>$t_stamp
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
      
      return response()->json([
        "message" => "Proses ubah data gagal",
      ], 400);
    }
  }

  public function delete(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'standby_mst.remove');

    DB::beginTransaction();

    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);
    
      $model_query = StandbyMst::where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
  
      $model_query->deleted         = 1;
      $model_query->deleted_user    = $this->admin_id;
      $model_query->deleted_at      = date("Y-m-d H:i:s");
      $model_query->deleted_reason  = $deleted_reason;
      $model_query->save();

      MyLog::sys("standby_mst",$request->id,"delete");

      DB::commit();
      return response()->json([
        "message"       => "Proses Hapus data berhasil",
        "deleted"       => $model_query->deleted,
        "deleted_user"  => $model_query->deleted_user,
        "deleted_by"    => $model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->deleted_user)) : null,
        "deleted_at"    => $model_query->deleted_at,
        "deleted_reason"=> $model_query->deleted_reason,
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

      return response()->json([
        "message" => "Proses hapus data gagal",
      ], 400);
      //throw $th;
    }
  }

  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['standby_mst.val','standby_mst.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\StandbyMst,id",
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
      $model_query = StandbyMst::where("id",$request->id)->lockForUpdate()->first();
      
      
      if(MyAdmin::checkScope($this->permissions, 'standby_mst.val',true) &&  $model_query->val){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      if(MyAdmin::checkScope($this->permissions, 'standby_mst.val1',true) && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi",1);
      }

      
      if(MyAdmin::checkScope($this->permissions, 'standby_mst.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'standby_mst.val1',true) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }
      $model_query->save();

      MyLog::sys("standby_mst",$request->id,"approve");

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

  public function unvalidasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['standby_mst.unval','standby_mst.unval1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\StandbyMst,id",
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
      $model_query = StandbyMst::where("id",$request->id)->lockForUpdate()->first();
      if(StandbyTrx::where("standby_mst_id",$model_query->id)->first()){
        throw new \Exception("Data Sudah Digunakan",1);
      }
      
      if(MyAdmin::checkScope($this->permissions, 'standby_mst.unval',true) && $model_query->val){
        $model_query->val = 0;
        // $model_query->val_user = $this->admin_id;
        // $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'standby_mst.unval1',true) && $model_query->val1){
        $model_query->val1 = 0;
        // $model_query->val1_user = $this->admin_id;
        // $model_query->val1_at = $t_stamp;
      }
      $model_query->save();

      MyLog::sys("standby_mst",$request->id,"unapprove");

      DB::commit();
      return response()->json([
        "message" => "Proses unvalidasi data berhasil",
        "val"=>$model_query->val,
        "val_user"=>$model_query->val_user,
        "val_at"=>$model_query->val_at,
        "val_by"=>$model_query->val_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
        "val1"=>$model_query->val1,
        "val1_user"=>$model_query->val1_user,
        "val1_at"=>$model_query->val1_at,
        "val1_by"=>$model_query->val1_user ? new IsUserResource(IsUser::find($model_query->val1_user)) : null, 
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
        "message" => "Proses unvalidasi data gagal",
      ], 400);
    }

  }
  
}
