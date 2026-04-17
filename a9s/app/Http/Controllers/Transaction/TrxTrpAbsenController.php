<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

use App\Http\Requests\MySql\TrxTrpAbsenRequest;
use App\Http\Requests\MySql\TrxTrpAbsenTicketRequest;

use App\Http\Resources\MySql\TrxTrpAbsenResource;
use App\Http\Resources\MySql\IsUserResource;

use App\Exports\MyReport;
use App\Http\Resources\MySql\TrxAbsenResource;
use App\Http\Resources\MySql\TrxTrpResource;
use InvalidArgumentException;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Encoders\AutoEncoder;
use Illuminate\Support\Facades\Storage;

class TrxTrpAbsenController extends Controller
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
                  $q->where("ritase_val2",1)->where("deleted",0)->where("req_deleted",0);
                  // $q->whereNotNull("ritase_leave_at")->whereNotNull("ritase_arrive_at")->whereNotNull("ritase_return_at")->whereNotNull("ritase_till_at");
                }else{
                  $q->where("ritase_val2",0)->where("deleted",0)->where("req_deleted",0);
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

    $model_query = $model_query->with(['ritase_val_by','ritase_val1_by','ritase_val2_by','deleted_by','req_deleted_by','uj','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at','status','is_manual');
    }])->get();

    return response()->json([
      "data" => TrxTrpAbsenResource::collection($model_query),
    ], 200);
  }

  public function showBAbsen(TrxTrpAbsenRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.view','trp_trx.ticket.view']);

    $model_query = TrxTrp::with(['val_by','val1_by','val2_by','val3_by','val4_by','val5_by','val6_by','val_ticket_by','deleted_by','req_deleted_by','payment_method','uj','trx_absens'=>function ($q){
      $q->where("status","B");
    },'potongan'])->find($request->id);
    return response()->json([
      "data" => new TrxTrpResource($model_query),
    ], 200);
  }

  public function show(TrxTrpAbsenRequest $request)
  {
    MyAdmin::checkMultiScope($this->permissions, ['trp_trx.absen.view']);

    $model_query = TrxTrp::with(['ritase_val_by','ritase_val1_by','ritase_val2_by','deleted_by','req_deleted_by','uj','trx_absens'=>function($q) {
      $q->select('id','trx_trp_id','created_at','updated_at','status','is_manual',"gambar","gambar_loc","latitude","longitude");
    }])->find($request->id);

    $data = new TrxTrpAbsenResource($model_query);
   

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

    $att_temp = [
      "B"=>[
        "id"=>0,
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "T"=>[
        "id"=>0,
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "K"=>[
        "id"=>0,
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
      "S"=>[
        "id"=>0,
        "newLoc"=>null,
        "oldLoc"=>null,
        "useNew"=>false
      ],
    ];

    
    DB::beginTransaction();
    try {
      $model_query = TrxTrp::where("id",$request->id)->with(['trx_absens'=>function($q) {
        $q->select('id','trx_trp_id','created_at','updated_at','status','is_manual',"gambar","gambar_loc","latitude","longitude");
      }])->lockForUpdate()->first();

      if($model_query->ritase_val==1 || $model_query->req_deleted==1 || $model_query->deleted==1) 
      throw new \Exception("Data Sudah Divalidasi Dan Tidak Dapat Di Ubah",1);

      $SYSOLD      = clone($model_query);
      $SYSNOTES = [];

      foreach ($model_query->trx_absens as $k => $v) {
        $att_temp[$v->status]['id']=$v->id;        
        $att_temp[$v->status]['oldLoc']=$v->gambar_loc;        
      }


      $img_leave_has_file = $request->hasFile('img_leave');
      $img_leave_ts = $request->img_leave_ts;
  
      if($img_leave_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_leave_ts))
        throw new MyException([ "img_leave_ts" => ["Format Tanggal Salah"] ], 422);
      if($img_leave_ts && (!$img_leave_has_file && !$att_temp['B']['oldLoc']))
        throw new MyException([ "img_leave" => ["Sertakan Bukti Foto"] ], 422);
      if(!$img_leave_ts && ($img_leave_has_file || $att_temp['B']['oldLoc']))
        throw new MyException([ "img_leave_ts" => ["Sertakan Waktu Foto"] ], 422);
      
      if($img_leave_has_file){
        $file = $request->file('img_leave');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "img_leave" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
          
        $file_name = "{$model_query->id}_attB_" . Str::uuid() . '.' . $ext;
        $loc = "trx_trp/absen/{$file_name}";

        // $blob_img_leave = base64_encode(file_get_contents($path));
        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($loc, $compressedImageBinary);
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['B']['newLoc']=$loc;
        $att_temp['B']['useNew']=true;
      }
  
      if (!$img_leave_has_file && in_array($request->img_leave_preview,[null,'null'])) {
        $att_temp['B']['newLoc']=null;
        $att_temp['B']['useNew']=true;
      }

      //special case roker gambar_loc dan ganti sedikit link ny
      if (!$img_leave_has_file && !in_array($request->img_leave_preview,[null,'null']) ) {
        $img_leave_preview = $request->img_leave_preview;
        $ilp_id = explode("/",$img_leave_preview)[4];

        $trx_absen_new = TrxAbsen::where("id",$ilp_id)->first();
        if($ilp_id != $att_temp['B']['id']){
          $file_name = "{$model_query->id}_attB_" . Str::uuid() . '.png';
          $loc = "trx_trp/absen/{$file_name}";

          Storage::disk('public')->put($loc, Storage::disk('public')->get($trx_absen_new->gambar_loc));
          $att_temp['B']['newLoc']=$loc;
          $att_temp['B']['useNew']=true;
        }else{
          $att_temp['B']['newLoc']=$trx_absen_new->gambar_loc;
          $att_temp['B']['useNew']=true;
        }
        
      }



      $img_arrive_has_file = $request->hasFile('img_arrive');
      $img_arrive_ts = $request->img_arrive_ts;
  
      if($img_arrive_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_arrive_ts))
        throw new MyException([ "img_arrive_ts" => ["Format Tanggal Salah"] ], 422);
      if($img_arrive_ts && (!$img_arrive_has_file && !$att_temp['T']['oldLoc']))
        throw new MyException([ "img_arrive" => ["Sertakan Bukti Foto"] ], 422);
      if(!$img_arrive_ts && ($img_arrive_has_file || $att_temp['T']['oldLoc']))
        throw new MyException([ "img_arrive_ts" => ["Sertakan Waktu Foto"] ], 422);
      
      if($img_arrive_has_file){
        $file = $request->file('img_arrive');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "img_arrive" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
          
        $file_name = "{$model_query->id}_attT_" . Str::uuid() . '.' . $ext;
        $loc = "trx_trp/absen/{$file_name}";

        // $blob_img_arrive = base64_encode(file_get_contents($path));
        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($loc, $compressedImageBinary);
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['T']['newLoc']=$loc;
        $att_temp['T']['useNew']=true;
      }
  
      if (!$img_arrive_has_file && in_array($request->img_arrive_preview,[null,'null'])) {
        $att_temp['T']['newLoc']=null;
        $att_temp['T']['useNew']=true;
      }

      $img_return_has_file = $request->hasFile('img_return');
      $img_return_ts = $request->img_return_ts;
  
      if($img_return_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_return_ts))
        throw new MyException([ "img_return_ts" => ["Format Tanggal Salah"] ], 422);
      if($img_return_ts && (!$img_return_has_file && !$att_temp['K']['oldLoc']))
        throw new MyException([ "img_return" => ["Sertakan Bukti Foto"] ], 422);
      if(!$img_return_ts && ($img_return_has_file || $att_temp['K']['oldLoc']))
        throw new MyException([ "img_return_ts" => ["Sertakan Waktu Foto"] ], 422);
      
      if($img_return_has_file){
        $file = $request->file('img_return');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "img_return" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
          
        $file_name = "{$model_query->id}_attK_" . Str::uuid() . '.' . $ext;
        $loc = "trx_trp/absen/{$file_name}";

        // $blob_img_return = base64_encode(file_get_contents($path));
        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($loc, $compressedImageBinary);
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['K']['newLoc']=$loc;
        $att_temp['K']['useNew']=true;
      }
  
      if (!$img_return_has_file && in_array($request->img_return_preview,[null,'null'])) {
        $att_temp['K']['newLoc']=null;
        $att_temp['K']['useNew']=true;
      }

      $img_till_has_file = $request->hasFile('img_till');
      $img_till_ts = $request->img_till_ts;
  
      if($img_till_ts && !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/",$img_till_ts))
        throw new MyException([ "img_till_ts" => ["Format Tanggal Salah"] ], 422);
      if($img_till_ts && (!$img_till_has_file && !$att_temp['S']['oldLoc']))
        throw new MyException([ "img_till" => ["Sertakan Bukti Foto"] ], 422);
      if(!$img_till_ts && ($img_till_has_file || $att_temp['S']['oldLoc']))
        throw new MyException([ "img_till_ts" => ["Sertakan Waktu Foto"] ], 422);
      
      if($img_till_has_file){
        $file = $request->file('img_till');
        $path = $file->getRealPath();
        $fileType = $file->getClientMimeType();
        $ext = $file->extension();

        if(!preg_match("/image\/[jpeg|jpg|png]/",$fileType))
        throw new MyException([ "img_till" => ["Tipe Data Harus berupa jpg,jpeg, atau png"] ], 422);
          
        $file_name = "{$model_query->id}_attS_" . Str::uuid() . '.' . $ext;
        $loc = "trx_trp/absen/{$file_name}";

        // $blob_img_till = base64_encode(file_get_contents($path));
        $image = Image::read($path)->scale(height: $this->height);
        $compressedImageBinary = (string)$image->encode(new AutoEncoder(quality: $this->quality));
        
        try {
          ini_set('memory_limit', '256M');
          Storage::disk('public')->put($loc, $compressedImageBinary);
        } catch (\Exception $e) {
          throw new \Exception("Simpan File Dokumen Gagal");
        }

        $att_temp['S']['newLoc']=$loc;
        $att_temp['S']['useNew']=true;
      }
  
      if (!$img_till_has_file && in_array($request->img_till_preview,[null,'null'])) {
        $att_temp['S']['newLoc']=null;
        $att_temp['S']['useNew']=true;
      }
      
      $b_img_to_delete=[];
      foreach ($att_temp as $k => $v) {
        if($att_temp[$k]['useNew']){
          if($att_temp[$k]['id']==0){
            $insertV=[
              "status" => $k,
              "trx_trp_id" => $model_query->id,
              "gambar"=>null,
              "gambar_loc"=>$att_temp[$k]['newLoc'],
              "created_at"=>$t_stamp,
              "updated_at"=>$t_stamp,
              "created_user"=>$this->admin_id,
              "is_manual"=>1,
            ];
            TrxAbsen::insert($insertV);
            $SYSNOTE = MyLib::logNew($insertV);
            array_push( $SYSNOTES ,"Insert Absen=>\n".$SYSNOTE);            

          }else{
            $trx_absen = TrxAbsen::where("trx_trp_id",$model_query->id)->where('status',$k)->where('id',$att_temp[$k]['id'])->first();
            $OSYSNOTE = clone($trx_absen);

            $trx_absen->gambar_loc = $att_temp[$k]['newLoc'];
            $trx_absen->save();

            $SYSNOTE = MyLib::compareChange($OSYSNOTE,$trx_absen); 
            array_push( $SYSNOTES ,"Update Absen=> ID:".$trx_absen->id."\n".$SYSNOTE);
            // array_push($SYSNOTES,"for checking".$model_query->id."x".$k."x".$att_temp[$k]['id']);            
            if($k=="B"){
              $b_id_to_delete = TrxAbsen::where("trx_trp_id",$model_query->id)->where('status',$k)->where('id',"!=",$att_temp[$k]['id'])->pluck('id')->toArray();
              $b_img_to_delete = TrxAbsen::where("trx_trp_id",$model_query->id)->where('status',$k)->where('id',"!=",$att_temp[$k]['id'])->pluck('gambar_loc')->toArray();
              array_push( $SYSNOTES ,"Hapus Absen=> ID:(".implode(",",$b_id_to_delete).") [Deleted]");
              // array_push( $SYSNOTES ,"check => ID:(".implode(",",$b_img_to_delete).") [Deleted]");
              TrxAbsen::where("trx_trp_id",$model_query->id)->where('status',$k)->where('id',"!=",$att_temp[$k]['id'])->delete();
            }
          }
        }
      }


      $model_query->updated_at        = $t_stamp;
      $model_query->updated_user      = $this->admin_id;
      $model_query->ritase_leave_at   = $img_leave_ts;
      $model_query->ritase_arrive_at  = $img_arrive_ts;
      $model_query->ritase_return_at  = $img_return_ts;
      $model_query->ritase_till_at    = $img_till_ts;
      $model_query->ritase_note       = $request->ritase_note;
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);
      MyLog::sys("trx_trp",$request->id,"update absen",$SYSNOTE."\n".implode("\n",$SYSNOTES));

      DB::commit();

      try {
        ini_set('memory_limit', '256M');
        foreach ($att_temp as $k => $v) {
          if ($att_temp[$k]['useNew'] &&  $att_temp[$k]['oldLoc']!= null && $att_temp[$k]['newLoc'] != $att_temp[$k]['oldLoc'] && Storage::disk('public')->exists($att_temp[$k]['oldLoc'])) {
            Storage::disk('public')->delete($att_temp[$k]['oldLoc']);
          }
        }
        foreach ($b_img_to_delete as $k => $v) {
          if (Storage::disk('public')->exists($v)) {
            Storage::disk('public')->delete($v);
          }
        }
        
      } catch (\Exception $e) {
        
      }

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

      $permit_continue_trx = TrxTrpController::permit_continue_trx($model_query,true);
      // throw new \Exception("ID ".$permit_continue_trx."Absensinya Masih Belum Lengkap",1);
      
      if(count($permit_continue_trx) > 1){
        throw new \Exception("ID ".implode(",",$permit_continue_trx)." Absensinya Masih Belum Lengkap",1);
      }

      // cari trx absen yang belum lengkap diisi apabila ada lebih dari 1 maka tidak bisa validasi
      $SYSOLD                     = clone($model_query);

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val',true) && !$model_query->ritase_val){
        $model_query->ritase_val = 1;
        $model_query->ritase_val_user = $this->admin_id;
        $model_query->ritase_val_at = $t_stamp;
      }
      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val1',true) && !$model_query->ritase_val1){

        if($model_query->ritase_val==0) 
        throw new \Exception("App1 Belum Memvalidasi",1);

        $model_query->ritase_val1 = 1;
        $model_query->ritase_val1_user = $this->admin_id;
        $model_query->ritase_val1_at = $t_stamp;
      }

      if(MyAdmin::checkScope($this->permissions, 'trp_trx.absen.val2',true) && !$model_query->ritase_val2){
        if($model_query->ritase_val==0) 
        throw new \Exception("App1 Belum Memvalidasi",1);

        $model_query->ritase_val2 = 1;
        $model_query->ritase_val2_user = $this->admin_id;
        $model_query->ritase_val2_at = $t_stamp;
      }
      
      $model_query->save();

      $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query); 
      MyLog::sys("trx_trp",$request->id,"approve_absen",$SYSNOTE);

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

    $ids = json_decode($request->ids, true);
    $t_stamp = date("Y-m-d H:i:s");
    DB::beginTransaction();
    try {
      $SYSNOTES = [];
      $model_query = TrxTrp::whereIn("id",$ids)->lockForUpdate()->get();
      $valList = [];
      
      foreach ($model_query as $key => $v) {
        $SYSOLD                     = clone($v);
        if($v->salary_paid_id != null){
          throw new \Exception("Data #".$v->id." Tidak Bisa Di unvalidasi lagi karna sudah memiliki Salary Paid ID",1);
        }
        $change=0;
        if($v->ritase_val != 0){
          $v->ritase_val        = 0;
          $change++;
        }
        if($v->ritase_val1 != 0){
          $v->ritase_val1       = 0;
          $change++;
        }
        if($v->ritase_val2 != 0){
          $v->ritase_val2       = 0;
          $change++;
        }
        if($change > 0){
          $v->updated_at        = $t_stamp;
          $v->updated_user      = $this->admin_id;
  
          $v->save();

          array_push($valList,[
            "id"          => $v->id,
            "ritase_val"  => $v->ritase_val,
            "ritase_val1" => $v->ritase_val1,
            "ritase_val2" => $v->ritase_val2,
            "updated_at"  => $v->updated_at,
          ]);
        }

        $SYSNOTE = MyLib::compareChange($SYSOLD,$model_query);         
        array_push($SYSNOTES,$SYSNOTE);
      }
      MyLog::sys($this->syslog_db,null,"clearValVal1",implode(",",$SYSNOTES));

      $nids = array_map(function($x) {
        return $x['id'];        
      },$valList);
      
      // MyLog::sys("trx_trp",null,"unval_absen",implode(",",$nids));

      DB::commit();
      if(count($nids) == 0 ){
        return response()->json([
          "message"   => "Tidak Ada Data Yang Di proses",
          "val_lists" => $valList
          ], 400);  
      }else{
        return response()->json([
          "message"   => "Proses clear validasi berhasil",
          "val_lists" => $valList
        ], 200);
      }
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


  public function downloadExcel(Request $request){
    MyAdmin::checkScope($this->permissions, 'trp_trx.absen.download_file');

    set_time_limit(0);
    $callGet = $this->index($request, true);
    if ($callGet->getStatusCode() != 200) return $callGet;
    $ori = json_decode(json_encode($callGet), true)["original"];
    

    $newDetails = [];

    foreach ($ori["data"] as $key => $value) {
      $value['tanggal']=date("d-m-Y",strtotime($value["tanggal"]));
      array_push($newDetails,$value);
    }

    $filter_model = json_decode($request->filter_model,true);
    $tanggal = $filter_model['tanggal'];    


    $date_from=date("d-m-Y",strtotime($tanggal['value_1']));
    $date_to=date("d-m-Y",strtotime($tanggal['value_2']));

    $date = new \DateTime();
    $filename=$date->format("YmdHis").'-trx_trp'."[".$date_from."*".$date_to."]";

    $mime=MyLib::mime("xlsx");
    // $bs64=base64_encode(Excel::raw(new TangkiBBMReport($data), $mime["exportType"]));
    $bs64=base64_encode(Excel::raw(new MyReport(["data"=>$newDetails],'excel.trx_trp_absen_raw'), $mime["exportType"]));


    $result = [
      "contentType" => $mime["contentType"],
      "data" => $bs64,
      "dataBase64" => $mime["dataBase64"] . $bs64,
      "filename" => $filename . "." . $mime["ext"],
    ];
    return $result;
  }


  public function getAttachment($id,$n)
  {
    MyAdmin::checkScope($this->permissions, 'trp_trx.absen.view');

    $trx = TrxAbsen::where("trx_trp_id",$id)->where("id",$n)->first();

    abort_unless($trx->gambar_loc, 404,$trx->gambar_loc);

    abort_unless(Storage::disk('public')->exists($trx->gambar_loc), 404,"not exists");

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('public');  
    return $disk->response(
        $trx->gambar_loc,
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
}
