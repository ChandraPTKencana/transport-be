<?php

namespace App\Http\Controllers\Potongan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Exceptions\MyException;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Helpers\MyAdmin;
use App\Helpers\MyLog;
use App\Helpers\MyLib;

use App\Models\MySql\PotonganMst;
use App\Models\MySql\IsUser;

use App\Http\Requests\MySql\PotonganMstRequest;

use App\Http\Resources\MySql\PotonganMstResource;
use App\Http\Resources\MySql\IsUserResource;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganTrx;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PotonganMstController extends Controller
{
  private $admin;
  private $admin_id;
  private $permissions;
  private $syslog_db = 'potongan_mst';

  public function __construct(Request $request)
  {
    $this->admin = MyAdmin::user();
    $this->admin_id = $this->admin->the_user->id;
    $this->permissions = $this->admin->the_user->listPermissions();
  }

  public function loadLocal()
  {
    $list_vehicle=[];
    $list_employee=[];
    if(MyAdmin::checkMultiScope($this->permissions, ['potongan_mst.create','potongan_mst.modify'],true)){
      $list_vehicle = \App\Models\MySql\Vehicle::where("deleted",0)->get();
      $list_employee = \App\Models\MySql\Employee::exclude(['attachment_1','attachment_2'])->available()->verified()->whereIn("role",['Supir','Kernet'])->get();  
    }
    
    return response()->json([
      "list_vehicle" => $list_vehicle,
      "list_employee" => $list_employee,
    ], 200);
  }

  public function index(Request $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.views');

    //======================================================================================================
    // Pembatasan Data hanya memerlukan limit dan offset
    //======================================================================================================

    $limit = 250; // Limit +> Much Data
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
    $model_query = PotonganMst::offset($offset)->limit($limit);

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

      $list_to_like = ["id"];

      $list_to_like_employee = [
        ["employee_name","employee_id"],
      ];

      

      if(count($like_lists) > 0){
        $model_query = $model_query->where(function ($q)use($like_lists,$list_to_like,$list_to_like_employee){
          foreach ($list_to_like as $key => $v) {
            if (isset($like_lists[$v])) {
              $q->orWhere($v, "like", $like_lists[$v]);
            }
          }

          foreach ($list_to_like_employee as $key => $v) {
            if (isset($like_lists[$v[0]])) {
              $q->orWhereIn($v[1], function($q2)use($like_lists,$v) {
                $q2->from('employee_mst')
                ->select('id')->where("name",'like',$like_lists[$v[0]]);          
              });
            }
          }

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
          if(array_search($value['key'],['employee_name'])!==false){
            $model_query = MyLib::queryOrderP1($model_query,"employee","employee_id",$value['key'],$filter_model[$value['key']]["sort_type"],"employee_mst");
          }else{
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
          }else if(array_search($key,['employee_name'])!==false){
            MyLib::queryCheckP1("employee",$value,$key,$q,'employee_mst');
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
      $model_query = $model_query->orderBy('id', 'desc');
    }
    
    $filter_status = $request->filter_status;
    
    if($filter_status=="done"){
      $model_query = $model_query->where("deleted",0)->where("val",1)->where("val1",1)->where("remaining_cut",0);
    }

    if($filter_status=="undone"){
      $model_query = $model_query->where("deleted",0)->where(function($q){
       $q->where("val",0)->orWhere("val1",0)->orWhere("remaining_cut",">",0); 
      });
    }

    if($filter_status=="deleted"){
      $model_query = $model_query->where("deleted",1);
    }

    $model_query = $model_query->exclude(['attachment_1','attachment_2']);
    $model_query = $model_query->with('employee')->get();

    return response()->json([
      // "data"=>PotonganMstResource::collection($potongan_msts->keyBy->id),
      "data" => PotonganMstResource::collection($model_query),
    ], 200);
  }

  public function show(PotonganMstRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['potongan_mst.view']);
    
    $model_query = PotonganMst::with(['val_by','val1_by','employee'])
    ->find($request->id);
    return response()->json([
      "data" => new PotonganMstResource($model_query),
    ], 200);
  }

  public function store(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.create');

    $att_1_loc = null;
    $att_2_loc = null;
    DB::beginTransaction();
    $t_stamp = date("Y-m-d H:i:s");
    try {
      $model_query                = new PotonganMst();

      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        $file_name = "att1_".Str::uuid() . '.' . $ext;
        $att_1_loc = "potongan_msts/$file_name";
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($att_1_loc, file_get_contents($path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }
        $model_query->attachment_1_loc = $att_1_loc;
        $model_query->attachment_1_type = $fileType;
      }

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        $file_name = "att2_".Str::uuid() . '.' . $ext;
        $att_2_loc = "potongan_msts/$file_name";
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($att_2_loc, file_get_contents($path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }
        $model_query->attachment_2_loc = $att_2_loc;
        $model_query->attachment_2_type = $fileType;
      }

      $model_query->kejadian      = $request->kejadian;
      $model_query->employee_id   = $request->employee_id;
      $model_query->no_pol        = $request->no_pol;
      $model_query->nominal       = $request->nominal;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->remaining_cut = $request->nominal;

      $model_query->status        = $request->status;

      $model_query->created_at    = $t_stamp;
      $model_query->created_user  = $this->admin_id;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;
      $model_query->save();

      MyLog::sys($this->syslog_db,$model_query->id,"insert");
      DB::commit();
      return response()->json([
        "message" => "Proses tambah data berhasil",
        "id"=>$model_query->id,
        "created_at" => $t_stamp,
        "updated_at" => $t_stamp,
        "remaining_cut"=>$model_query->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      if ($att_1_loc && Storage::disk('public')->exists($att_1_loc)) {
        Storage::disk('public')->delete($att_1_loc);
      }

      if ($att_2_loc && Storage::disk('public')->exists($att_2_loc)) {
        Storage::disk('public')->delete($att_2_loc);
      }

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }

      return response()->json([
        "message"=>$e->getMessage(),
      ],400);

      return response()->json([
        "message" => "Proses tambah data gagal"
      ], 400);
    }
  }

  public function update(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.modify');

    $t_stamp = date("Y-m-d H:i:s");
    $attachment_1_preview = $request->attachment_1_preview;
    $attachment_2_preview = $request->attachment_2_preview;
    // $fileType_1 = null;
    // $fileType_2 = null;
    // $blobFile_1 = null;
    // $blobFile_2 = null;
    // $change_1 = 0;
    // $change_2 = 0;

    $att_temp = [
      "att_1"=>[
        "newLoc"=>null,"oldLoc"=>null,"newType"=>null,"oldType"=>null,"useNew"=>false
      ],
      "att_2"=>[
        "newLoc"=>null,"oldLoc"=>null,"newType"=>null,"oldType"=>null,"useNew"=>false
      ],
    ];

    DB::beginTransaction();
    try {
      
      $model_query                = PotonganMst::where("id",$request->id)->lockForUpdate()->first();
      
      // if($model_query->id==1){
      //   throw new \Exception("Izin Ubah Ditolak",1);
      // }

      if($model_query->val==1)
      throw new \Exception("Data sudah tervalidasi",1);

      $SYSOLD                     = clone($model_query);
      
      $att_temp["att_1"]["oldLoc"]     = $model_query->attachment_1_loc;
      $att_temp["att_1"]["oldType"]    = $model_query->attachment_1_type;
      $att_temp["att_2"]["oldLoc"]     = $model_query->attachment_2_loc;
      $att_temp["att_2"]["oldType"]    = $model_query->attachment_2_type;
      
      $model_query->kejadian      = $request->kejadian;
      $model_query->employee_id   = $request->employee_id;
      $model_query->no_pol        = $request->no_pol;
      $model_query->nominal       = $request->nominal;
      $model_query->nominal_cut   = $request->nominal_cut;
      $model_query->status        = $request->status;
      $model_query->updated_at    = $t_stamp;
      $model_query->updated_user  = $this->admin_id;

      $model_query1 = PotonganTrx::selectRaw('sum(nominal_cut) as paid')->where("potongan_mst_id",$request->id)->where("deleted",0)->lockForUpdate()->first();
      $paid = 0; 
      if($model_query1){
        $paid = $model_query1->paid;
      }
      $model_query->remaining_cut = $model_query->nominal - $paid;
      
      if($model_query->remaining_cut < 0)
      throw new \Exception("Nominal yang sudah terpotong Melebihi Nominal yang ada",1);
      
      if($request->hasFile('attachment_1')){
        $file = $request->file('attachment_1');
        $path = $file->getRealPath();
        $att_1_type = $file->getClientMimeType();
        $ext = $file->extension();

        $file_name = $model_query->id."_att1_".Str::uuid() . '.' . $ext;
        $att_1_loc = "potongan_msts/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($att_1_loc, file_get_contents($path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['att_1']['newLoc']=$att_1_loc;
        $att_temp['att_1']['newType']=$att_1_type;
        $att_temp['att_1']['useNew']=true;
      }

      if (!$request->hasFile('attachment_1') && in_array($attachment_1_preview,[null,'null'])) {
        $att_temp['att_1']['newLoc']=null;
        $att_temp['att_1']['newType']=null;
        $att_temp['att_1']['useNew']=true;
      }

      if($request->hasFile('attachment_2')){
        $file = $request->file('attachment_2');
        $path = $file->getRealPath();
        $att_2_type = $file->getClientMimeType();
        $ext = $file->extension();

        $file_name = $model_query->id."_att2_".Str::uuid() . '.' . $ext;
        $att_2_loc = "potongan_msts/$file_name";

        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($att_2_loc, file_get_contents($path));
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['att_2']['newLoc']=$att_2_loc;
        $att_temp['att_2']['newType']=$att_2_type;
        $att_temp['att_2']['useNew']=true;
      }

      if (!$request->hasFile('attachment_2') && in_array($attachment_2_preview,[null,'null'])) {
        $att_temp['att_2']['newLoc']=null;
        $att_temp['att_2']['newType']=null;
        $att_temp['att_2']['useNew']=true;
      }

      $model_query->attachment_1_type = $att_temp['att_1']['useNew']?$att_temp['att_1']['newType']:$att_temp['att_1']['oldType'];
      $model_query->attachment_1_loc = $att_temp['att_1']['useNew']?$att_temp['att_1']['newLoc']:$att_temp['att_1']['oldLoc'];

      $model_query->attachment_2_type = $att_temp['att_2']['useNew']?$att_temp['att_2']['newType']:$att_temp['att_2']['oldType'];
      $model_query->attachment_2_loc = $att_temp['att_2']['useNew']?$att_temp['att_2']['newLoc']:$att_temp['att_2']['oldLoc'];
      // $model_query->attachment_1_type = $fileType_1;
      // $model_query->attachment_2_type = $fileType_2;

      $model_query->save();

      // $update=[];

      // if($change_1){
      //   $update["attachment_1"] = $blobFile_1;
      // }

      // if($change_2){
      //   $update["attachment_2"] = $blobFile_2;
      // }

      // if($change_1 || $change_2){
      //   PotonganMst::where("id",$request->id)->update($update);
      // }
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"update",$SYSNOTE);

      DB::commit();

      try {
        ini_set('memory_limit', '256M');
        if ($att_temp['att_1']['useNew'] &&  $att_temp['att_1']['oldLoc']!= null && Storage::disk('public')->exists($att_temp['att_1']['oldLoc'])) {
          Storage::disk('public')->delete($att_temp['att_1']['oldLoc']);
        }

        if ($att_temp['att_2']['useNew'] &&  $att_temp['att_2']['oldLoc']!= null && Storage::disk('public')->exists($att_temp['att_2']['oldLoc'])) {
          Storage::disk('public')->delete($att_temp['att_2']['oldLoc']);
        }
      } catch (\Exception $e) {
        
      }
      return response()->json([
        "message" => "Proses ubah data berhasil",
        "updated_at" => $t_stamp,
        "remaining_cut"=>$model_query->remaining_cut
      ], 200);
    } catch (\Exception $e) {
      DB::rollback();

      try {
        ini_set('memory_limit', '256M');
        if ($att_temp['att_1']['useNew'] &&  $att_temp['att_1']['newLoc']!= null && Storage::disk('public')->exists($att_temp['att_1']['newLoc'])) {
          Storage::disk('public')->delete($att_temp['att_1']['newLoc']);
        }

        if ($att_temp['att_2']['useNew'] &&  $att_temp['att_2']['newLoc']!= null && Storage::disk('public')->exists($att_temp['att_2']['newLoc'])) {
          Storage::disk('public')->delete($att_temp['att_2']['newLoc']);
        }
      } catch (\Exception $e) {
        
      }

      if ($e->getCode() == 1) {
        return response()->json([
          "message" => $e->getMessage(),
        ], 400);
      }
      return response()->json([
        "message" => $e->getMessage(),
      ], 400);
      return response()->json([
        "message" => "Proses ubah data gagal"
      ], 400);
    }
  }

  public function delete(PotonganMstRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.remove');

    DB::beginTransaction();
    try {
      $deleted_reason = $request->deleted_reason;
      if(!$deleted_reason)
      throw new \Exception("Sertakan Alasan Penghapusan",1);

      $model_query = PotonganMst::exclude(['attachment_1','attachment_2'])->where("id",$request->id)->lockForUpdate()->first();

      if (!$model_query) {
        throw new \Exception("Data tidak terdaftar", 1);
      }
      $SYSOLD                     = clone($model_query);
      // if($model_query->id==1){
      //   throw new \Exception("Izin Hapus Ditolak",1);
      // }
  
      $model_query->deleted = 1;
      $model_query->deleted_user = $this->admin_id;
      $model_query->deleted_at = date("Y-m-d H:i:s");
      $model_query->deleted_reason = $deleted_reason;
      $model_query->save();
      
      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"delete",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses ubah data berhasil",
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
    // if ($model_query->delete()) {
    //     return response()->json([
    //         "message"=>"Proses ubah data berhasil",
    //     ],200);
    // }

    // return response()->json([
    //     "message"=>"Proses ubah data gagal",
    // ],400);
  }

  public function undelete(Request $request){
    MyAdmin::checkScope($this->permissions, 'potongan_mst.unremove');

    $rules = [
      'id' => "required|exists:\App\Models\MySql\PotonganMst,id",
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
      $model_query = PotonganMst::lockForUpdate()->find($request->id);
      $SYSOLD                     = clone($model_query);
      if($model_query->deleted==0){
        throw new \Exception("Data Sudah Di Aktifkan",1);
      }

      if(MyAdmin::checkScope($this->permissions, 'potongan_mst.unremove',true) && $model_query->deleted){
        $model_query->deleted = 0;
        // $model_query->val1_user = $this->admin_id;
        // $model_query->val1_at = $t_stamp;
      }
      
      // $model_query->val = 0;
      // if(!$model_query->val){
      //   $model_query->val = 1;
      //   $model_query->val_user = $this->admin_id;
      //   $model_query->val_at = $t_stamp;
      // }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"unremove",$SYSNOTE);

      DB::commit();
      return response()->json([
        "message" => "Proses unremove data berhasil",
        "deleted"=>$model_query->deleted,
        "deleted_user"=>$model_query->deleted_user,
        "deleted_at"=>$model_query->deleted_at,
        "deleted_by"=>$model_query->deleted_user ? new IsUserResource(IsUser::find($model_query->val_user)) : null,
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
        "message" => "Proses unremove data gagal",
      ], 400);
    }
  }


  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['potongan_mst.val','potongan_mst.val1']);

    $rules = [
      'id' => "required|exists:\App\Models\MySql\PotonganMst,id",
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
      $model_query = PotonganMst::exclude(['attachment_1','attachment_2'])->lockForUpdate()->find($request->id);
      if($model_query->val && $model_query->val1){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }

      $SYSOLD                     = clone($model_query);
      
      if(MyAdmin::checkScope($this->permissions, 'potongan_mst.val',true) && !$model_query->val){
        $model_query->val = 1;
        $model_query->val_user = $this->admin_id;
        $model_query->val_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'potongan_mst.val1',true) && !$model_query->val1){
        $model_query->val1 = 1;
        $model_query->val1_user = $this->admin_id;
        $model_query->val1_at = $t_stamp;
      }

      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys($this->syslog_db,$request->id,"approve",$SYSNOTE);

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

  public function getAttachment($id,$n)
  {
    MyAdmin::checkScope($this->permissions, 'potongan_mst.view');

    $trx = PotonganMst::findOrFail($id);

    $locField  = "attachment_{$n}_loc";
    $typeField = "attachment_{$n}_type";

    abort_unless($trx->$locField, 404);

    abort_unless(Storage::disk('public')->exists($trx->$locField), 404);

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('public');  
    return $disk->response(
        $trx->$locField,
        null,
        [
            'Cache-Control' => 'no-store, private',
            'Content-Type'  => $trx->$typeField,
            'X-Attachment'  => $n,
        ]
    );
  }

}
