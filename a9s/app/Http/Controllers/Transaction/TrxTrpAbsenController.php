<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

use App\Http\Requests\MySql\TrxTrpAbsenRequest;
use App\Http\Requests\MySql\TrxTrpAbsenTicketRequest;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Http\Resources\MySql\TrxAbsenResource;
use InvalidArgumentException;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
class TrxTrpAbsenController extends Controller
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

  public function index(Request $request, $download = false)
  {
    if(!$download)
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.absen.views']);

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
                  $q->where("ritase_val2",1)->where("deleted",0)->where("req_deleted",0);
                  // $q->whereNotNull("ritase_leave_at")->whereNotNull("ritase_arrive_at")->whereNotNull("ritase_return_at")->whereNotNull("ritase_till_at");
                }else{
                  $q->where("ritase_val2",0)->where("deleted",0)->where("req_deleted",0);
                  // $q->whereNull("ritase_leave_at")->orWhereNull("ritase_arrive_at")->orWhereNull("ritase_return_at")->orWhereNull("ritase_till_at");
                }
              }
            }
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
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where("ritase_val2",1);
      // ->where(function ($q) {
      //   $q->whereNotNull("ritase_leave_at")->whereNotNull("ritase_arrive_at")->whereNotNull("ritase_return_at")->whereNotNull("ritase_till_at");
      // });

    }elseif ($filter_status=="Undone") {
      $model_query = $model_query->where("deleted",0)->where("req_deleted",0)->where("ritase_val2",0);
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

    $model_query = $model_query->with(['ritase_val_by','ritase_val1_by','ritase_val2_by','deleted_by','req_deleted_by','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at','status','is_manual');
    }])->get();

    return response()->json([
      "data" => TrxTrpAbsenResource::collection($model_query),
    ], 200);
  }

  public function show(TrxTrpAbsenRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.absen.view']);

    $model_query = TrxTrp::with(['ritase_val_by','ritase_val1_by','ritase_val2_by','deleted_by','req_deleted_by','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at','status','is_manual',"gambar");
    }])->find($request->id);


    // $data = new TrxTrpAbsenResource($model_query);

    $data = [
      'id'                => $model_query->id,
      'tanggal'           => $model_query->tanggal,

      'id_uj'             => $model_query->id_uj,
      'jenis'             => $model_query->jenis,
      'xto'               => $model_query->xto,
      'tipe'              => $model_query->tipe,

      'supir_id'          => $model_query->supir_id ?? "",
      'supir'             => $model_query->supir,
      // 'supir_rek_no'      => $model_query->supir_rek_no ?? "",
      // 'supir_rek_name'    => $model_query->supir_rek_name ?? "",
      'kernet_id'         => $model_query->kernet_id ?? "",
      'kernet'            => $model_query->kernet ?? "",
      // 'kernet_rek_no'     => $model_query->kernet_rek_no ?? "",
      // 'kernet_rek_name'   => $model_query->kernet_rek_name ?? "",
      'no_pol'            => $model_query->no_pol,
      
      'created_user'      => $model_query->created_user,
      'updated_user'      => $model_query->updated_user,
      'created_at'        => $model_query->created_at,
      'updated_at'        => $model_query->updated_at,

      'deleted'           => $model_query->deleted,
      'deleted_user'      => $model_query->deleted_user ?? "",
      'deleted_at'        => $model_query->deleted_at ?? "",
      'deleted_by'        => $model_query->deleted_by ? new IsUserResource($model_query->deleted_by) : "",
      'deleted_reason'    => $model_query->deleted_reason ?? "",

      'req_deleted'       => $model_query->req_deleted,
      'req_deleted_user'  => $model_query->req_deleted_user ?? "",
      'req_deleted_at'    => $model_query->req_deleted_at ?? "",
      'req_deleted_by'    => $model_query->req_deleted_by ? new IsUserResource($model_query->req_deleted_by) : "",
      'req_deleted_reason'=> $model_query->req_deleted_reason ?? "",

      'transition_target' => $model_query->transition_target ?? "",
      'transition_type'   => $model_query->transition_type ?? "",
      // 'trx_absens'        => $model_query->trx_absens,

      'ritase_leave_at'   => $model_query->ritase_leave_at ?? "",
      'ritase_arrive_at'  => $model_query->ritase_arrive_at ?? "",
      'ritase_return_at'  => $model_query->ritase_return_at ?? "",
      'ritase_till_at'    => $model_query->ritase_till_at ?? "",
      
      'ritase_note'       => $model_query->ritase_note ?? "",
      
      'ritase_val'        => $model_query->ritase_val,
      'ritase_val_user'   => $model_query->ritase_val_user ?? "",
      'ritase_val_by'     => $model_query->ritase_val_by ? new IsUserResource($model_query->ritase_val_by) : "",
      'ritase_val_at'     => $model_query->ritase_val_at ?? "",

      'ritase_val1'       => $model_query->ritase_val1,
      'ritase_val1_user'  => $model_query->ritase_val1_user ?? "",
      'ritase_val1_by'    => $model_query->ritase_val1_by ? new IsUserResource($model_query->ritase_val1_by) : "",
      'ritase_val1_at'    => $model_query->ritase_val1_at ?? "",

      'ritase_val2'       => $model_query->ritase_val2,
      'ritase_val2_user'  => $model_query->ritase_val2_user ?? "",
      'ritase_val2_by'    => $model_query->ritase_val2_by ? new IsUserResource($model_query->ritase_val2_by) : "",
      'ritase_val2_at'    => $model_query->ritase_val2_at ?? "",

      "img_leave_ts"      => $model_query->ritase_leave_at,
      "img_arrive_ts"     => $model_query->ritase_arrive_at,
      "img_return_ts"     => $model_query->ritase_return_at,
      "img_till_ts"       => $model_query->ritase_till_at,
    ];

    $data['img_leaves']=[];
    foreach ($model_query->trx_absens as $k => $v) {
      // mb_convert_encoding($img, 'UTF-8', 'UTF-8')
      $img = "data:image/png;base64,";
      if(mb_detect_encoding($v->gambar)===false){
        $img.=base64_encode($v->gambar);
      }else{
        $img.=$v->gambar;        
      }
      
      if($v['status']=="B") {
        $data["img_leave"]   = $img;
        array_push($data['img_leaves'],[
          "id"=>$v["id"],
          "gambar"=>$img,
        ]);
      }

      if($v['status']=="T") 
      $data["img_arrive"]   = $img;

      if($v['status']=="K") 
      $data["img_return"]   = $img;

      if($v['status']=="S") 
      $data["img_till"]   = $img;
    }

    // $data['trx_absens']= array_filter($data['trx_absens']->toArray(),function($x){
    //   $x['status']=="B";
    // });

    return response()->json([
      "data" => $data,
    ], 200);
  }

  private $height = 500;
  private $quality = 100;

  public function update(TrxTrpAbsenRequest $request)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.absen.modify');
    $t_stamp = date("Y-m-d H:i:s");

    $img_leave = $request->img_leave;
    $img_leave_ts = $request->img_leave_ts;

    if($img_leave_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_leave_ts))
      throw new MyException([ "img_leave_ts" => ["Format Tanggal Salah"] ], 422);
    if($img_leave_ts && !$img_leave)
      throw new MyException([ "img_leave" => ["Sertakan Bukti Foto"] ], 422);
    if(!$img_leave_ts && $img_leave)
      throw new MyException([ "img_leave_ts" => ["Sertakan Waktu Foto"] ], 422);

    if($request->hasFile('img_leave_file')){
      $file = $request->file('img_leave_file');
      $path = $file->getRealPath();
      $fileType = $file->getClientMimeType();
      if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      throw new MyException([ "img_leave" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);

      // $blob_img_leave = base64_encode(file_get_contents($path));
      $image = Image::read($path)->scale(height: $this->height);
      $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
      $blob_img_leave = base64_encode($compressedImageBinary);      
    }

    if (!$request->hasFile('img_leave_file') && $img_leave == null) {
      $blob_img_leave = null;
    }

    $img_arrive = $request->img_arrive;
    $img_arrive_ts = $request->img_arrive_ts;    

    if($img_arrive_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_arrive_ts))
      throw new MyException([ "img_arrive_ts" => ["Format Tanggal Salah"] ], 422);
    if($img_arrive_ts && !$img_arrive)
      throw new MyException([ "img_arrive" => ["Sertakan Bukti Foto"] ], 422);
    if(!$img_arrive_ts && $img_arrive)
      throw new MyException([ "img_arrive_ts" => ["Sertakan Waktu Foto"] ], 422);

    if($request->hasFile('img_arrive_file')){
      $file = $request->file('img_arrive_file');
      $path = $file->getRealPath();
      $fileType = $file->getClientMimeType();
      if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      throw new MyException([ "img_arrive" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
      // $blob_img_arrive = base64_encode(file_get_contents($path));
    
      $image = Image::read($path)->scale(height: $this->height);
      $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
      $blob_img_arrive = base64_encode($compressedImageBinary);
    }

    if (!$request->hasFile('img_arrive_file') && $img_arrive == null) {
      $blob_img_arrive = null;
    }

    $img_return = $request->img_return;
    $img_return_ts = $request->img_return_ts;

    if($img_return_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_return_ts))
      throw new MyException([ "img_return_ts" => ["Format Tanggal Salah"] ], 422);
    if($img_return_ts && !$img_return)
      throw new MyException([ "img_return" => ["Sertakan Bukti Foto"] ], 422);
    if(!$img_return_ts && $img_return)
      throw new MyException([ "img_return_ts" => ["Sertakan Waktu Foto"] ], 422);  

    if($request->hasFile('img_return_file')){
      $file = $request->file('img_return_file');
      $path = $file->getRealPath();
      $fileType = $file->getClientMimeType();
      if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      throw new MyException([ "img_return" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
      // $blob_img_return = base64_encode(file_get_contents($path));
    
      $image = Image::read($path)->scale(height: $this->height);
      $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
      $blob_img_return = base64_encode($compressedImageBinary);
    }

    if (!$request->hasFile('img_return_file') && $img_return == null) {
      $blob_img_return = null;
    }


    $img_till = $request->img_till;
    $img_till_ts = $request->img_till_ts;    

    if($img_till_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_till_ts))
      throw new MyException([ "img_till_ts" => ["Format Tanggal Salah"] ], 422);
    if($img_till_ts && !$img_till)
      throw new MyException([ "img_till" => ["Sertakan Bukti Foto"] ], 422);
    if(!$img_till_ts && $img_till)
      throw new MyException([ "img_till_ts" => ["Sertakan Waktu Foto"] ], 422);

    if($request->hasFile('img_till_file')){
      $file = $request->file('img_till_file');
      $path = $file->getRealPath();
      $fileType = $file->getClientMimeType();
      if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
      throw new MyException([ "img_till" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
      // $blob_img_till = base64_encode(file_get_contents($path));
    
      $image = Image::read($path)->scale(height: $this->height);
      $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
      $blob_img_till = base64_encode($compressedImageBinary);

    }

    if (!$request->hasFile('img_till_file') && $img_till == null) {
      $blob_img_till = null;
    }

    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)->lockForUpdate()->first();
      $SYSOLD      = clone($model_query);
      if($model_query->ritase_val==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;
      $model_query->ritase_leave_at   = $img_leave_ts;
      $model_query->ritase_arrive_at  = $img_arrive_ts;
      $model_query->ritase_return_at  = $img_return_ts;
      $model_query->ritase_till_at    = $img_till_ts;
      $model_query->ritase_note       = $request->ritase_note;
      $model_query->save();


      $OSYSNOTE="";
      if((int)$img_leave!=0){
        $OSYSNOTE.="Gambar yang tidak dipilih untuk gambar berangkat dihapuskan \n";
        TrxAbsen::where("status","B")->where("trx_trp_id",$model_query->id)->where("id","!=",$img_leave)->delete();
      }elseif ($img_leave=="ada" && isset($blob_img_leave)) {
        $OSYSNOTE.="Tambah Gambar Berangkat \n";

        TrxAbsen::insert([
          "status" => "B",
          "trx_trp_id" => $model_query->id,
          "gambar"=>$blob_img_leave,
          "created_at"=>$t_stamp,
          "updated_at"=>$t_stamp,
          "created_user"=>$this->admin_id,
          "is_manual"=>1,
        ]);
      }
      // elseif(!$img_leave){
      //   $OSYSNOTE.="Hapus Gambar Berangkat \n";
      //   TrxAbsen::where("status","B")->where('trx_trp_id',$model_query->id)->delete();
      // }

      if($img_arrive && isset($blob_img_arrive)){
        $OSYSNOTE.="Tambah Gambar Tiba \n";
        TrxAbsen::insert([
          "status" => "T",
          "trx_trp_id" => $model_query->id,
          "gambar"=>$blob_img_arrive,
          "created_at"=>$t_stamp,
          "updated_at"=>$t_stamp,
          "created_user"=>$this->admin_id,
          "is_manual"=>1,
        ]);
      }elseif(!$img_arrive){
        $OSYSNOTE.="Hapus Gambar Tiba \n";
        TrxAbsen::where("status","T")->where('trx_trp_id',$model_query->id)->delete();
      }

      if($img_return && isset($blob_img_return)){
        $OSYSNOTE.="Tambah Gambar Kembali \n";
        TrxAbsen::insert([
          "status" => "K",
          "trx_trp_id" => $model_query->id,
          "gambar"=>$blob_img_return,
          "created_at"=>$t_stamp,
          "updated_at"=>$t_stamp,
          "created_user"=>$this->admin_id,
          "is_manual"=>1,
        ]);
      }elseif(!$img_return){
        $OSYSNOTE.="Hapus Gambar Kembali \n";
        TrxAbsen::where("status","K")->where('trx_trp_id',$model_query->id)->delete();
      }

      if($img_till && isset($blob_img_till)){
        $OSYSNOTE.="Tambah Gambar Sampai \n";
        TrxAbsen::insert([
          "status" => "S",
          "trx_trp_id" => $model_query->id,
          "gambar"=>$blob_img_till,
          "created_at"=>$t_stamp,
          "updated_at"=>$t_stamp,
          "created_user"=>$this->admin_id,
          "is_manual"=>1,
        ]);
      }elseif(!$img_till){
        $OSYSNOTE.="Hapus Gambar Sampai \n";
        TrxAbsen::where("status","S")->where('trx_trp_id',$model_query->id)->delete();
      }

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("trx_trp",$request->id,"update absen",$SYSNOTE."\n".$OSYSNOTE);

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


  public function validasi(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.absen.val','trp_trx.absen.val1','trp_trx.absen.val2']);

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
      if($model_query->ritase_val && $model_query->ritase_val1 && $model_query->ritase_val2){
        throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      }

      if(!$model_query->ritase_note && (!$model_query->ritase_leave_at || !$model_query->ritase_arrive_at || !$model_query->ritase_return_at || !$model_query->ritase_till_at) )
      throw new \Exception("Gambar Belum Lengkap dan tidak disertai Catatan",1);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val',true) && !$model_query->ritase_val){
        $model_query->ritase_val = 1;
        $model_query->ritase_val_user = $this->admin_id;
        $model_query->ritase_val_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val1',true) && !$model_query->ritase_val1){
        $model_query->ritase_val1 = 1;
        $model_query->ritase_val1_user = $this->admin_id;
        $model_query->ritase_val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val2',true) && !$model_query->ritase_val2){
        $model_query->ritase_val2 = 1;
        $model_query->ritase_val2_user = $this->admin_id;
        $model_query->ritase_val2_at = $t_stamp;
      }
      
      $model_query->save();

      MyLog::sys("trx_trp",$request->id,"approve_absen");

      DB::commit();
      return response()->json([
        "message"         => "Proses validasi data berhasil",
        "ritase_val"      => $model_query->ritase_val,
        "ritase_val_user" => $model_query->ritase_val_user,
        "ritase_val_at"   => $model_query->ritase_val_at,
        "ritase_val_by"   => $model_query->ritase_val_user ? new IsUserResource(IsUser::find($model_query->ritase_val_user)) : null,
        "ritase_val1"     => $model_query->ritase_val1,
        "ritase_val1_user"=> $model_query->ritase_val1_user,
        "ritase_val1_at"  => $model_query->ritase_val1_at,
        "ritase_val1_by"  => $model_query->ritase_val1_user ? new IsUserResource(IsUser::find($model_query->ritase_val1_user)) : null,
        "ritase_val2"     => $model_query->ritase_val2,
        "ritase_val2_user"=> $model_query->ritase_val2_user,
        "ritase_val2_at"  => $model_query->ritase_val2_at,
        "ritase_val2_by"  => $model_query->ritase_val2_user ? new IsUserResource(IsUser::find($model_query->ritase_val2_user)) : null,
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

  public function clearValVal1(Request $request){
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.absen.clear_valval1']);

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
      // if($model_query->ritase_val2){
      //   throw new \Exception("Data Sudah Tervalidasi Sepenuhnya",1);
      // }
      
      $model_query->ritase_val        = 0;
      $model_query->ritase_val1       = 0;
      $model_query->ritase_val2       = 0;
      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;

      $model_query->save();

      MyLog::sys("trx_trp",$request->id,"unval_absen");

      DB::commit();
      return response()->json([
        "message"         => "Proses clear validasi berhasil",
        "ritase_val"      => 0,
        "ritase_val1"     => 0,
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
        "message" => "Proses clear validasi gagal",
      ], 400);
    }

  }
}
