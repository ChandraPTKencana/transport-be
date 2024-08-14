<?php
//app/Helpers/Envato/User.php
namespace App\PS;

use App\Helpers\MyLib;
use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganMst;
use App\Models\MySql\PotonganTrx;
use App\Models\MySql\TrxTrp;

class PSPotonganTrx
{

  public static function insertData($arrs=[]){
    /*
        $arrs =[
            "employee_id"=>0,
            "user_id"=>0,
            "trx_trp_id"=>0,
            "_source"=>"", // TRX_TRP
        ];
    */

    if(gettype($arrs)!='array'){
        throw new \Exception("Tipe data tidak sesuai",1);
    }
    
    if(count($arrs)>0){
        foreach ($arrs as $k => $v) {
        
            $employee_id = $v['employee_id'];
          
            // ambil dari method potongan karna perlu filter nya
            $employee = Employee::where("id",$employee_id)->first();
            if(!$employee){
              throw new \Exception("Tidak ditemukan data pekerja",1);
            }

            if($v["_source"]=='TRX_TRP' && isset($v['trx_trp_id'])){
              $trx_trp = TrxTrp::where("id",$v['trx_trp_id'])->first();
              if(!$trx_trp) 
              throw new \Exception("Data Trx Trp Tidak Ditemukan",1);
            }else{
              throw new \Exception("Source Tidak Dikenali",1);
            }
            self::loopCut($v,$employee,date("Y-m-d H:i:s"));
        }
    }

  }

  public static function loopCut($v,$emp,$t_stamp,$sisa_cut=0){
    $potongan_mst   = PotonganMst::where('employee_id',$emp->id)->where('val1',1)
    ->where('deleted',0)->where('status','Open')->where('remaining_cut',">",0)->orderBy('created_at','asc')
    ->lockForUpdate()
    ->first();

    if($potongan_mst){
      $SYSOLD         = clone($potongan_mst);
      $nominal_cut    =  $sisa_cut > 0 ? $sisa_cut : $potongan_mst->nominal_cut;
      $sisa_cut       =  $nominal_cut - $potongan_mst->remaining_cut;
  
      if($potongan_mst->remaining_cut < $nominal_cut)
      { $nominal_cut  = $potongan_mst->remaining_cut; }
  
      $potongan_trx                   = new PotonganTrx();
      $potongan_trx->potongan_mst_id  = $potongan_mst->id;
      $potongan_trx->nominal_cut      = $nominal_cut;
      $potongan_trx->created_at       = $t_stamp;
      $potongan_trx->created_user     = $v['user_id'];
      $potongan_trx->updated_at       = $t_stamp;
      $potongan_trx->updated_user     = $v['user_id'];
      $potongan_trx->val              = 1;
      $potongan_trx->val_user         = $v['user_id'];
      $potongan_trx->val_at           = $t_stamp;

      if($v["_source"]=='TRX_TRP' && isset($v['trx_trp_id']))
      $potongan_trx->trx_trp_id       = $v['trx_trp_id'];
  
      $potongan_trx->save();
  
      MyLog::sys("potongan_trx",$potongan_trx->id,"insert");
      
      $potongan_mst->remaining_cut    = $potongan_mst->remaining_cut - $nominal_cut;
      $potongan_mst->updated_at       = $t_stamp;
      $potongan_mst->save();
  
      $SYSNOTE = MyLib::compareChange($SYSOLD,$potongan_mst);
      MyLog::sys("potongan_mst",$potongan_mst->id,"update",$SYSNOTE);
  
      if($sisa_cut>0) self::loopCut($v,$emp,$t_stamp,$sisa_cut);
    }
    
  }


  public static function deletePotongan($arr=[]) {


    if(gettype($arr)!='array'){
      throw new \Exception("Tipe data tidak sesuai",1);
    }

    if($arr["_source"]!='TRX_TRP'){
      throw new \Exception("Tipe data tidak sesuai",1);
    }


    $potongan_trx = PotonganTrx::where('deleted',0)->where("trx_trp_id",$arr['trx_trp_id'])->lockForUpdate()->get();

    foreach ($potongan_trx as $k => $v) {
      $SYSOLD             = clone($v);
      $v->deleted         = 1;
      $v->deleted_user    = $arr['deleted_user'];
      $v->deleted_at      = $arr['deleted_at'];
      $v->deleted_reason  = $arr['deleted_reason'];
      $v->save();
      $SYSNOTE            = MyLib::compareChange($SYSOLD,$v);
      MyLog::sys("potongan_trx",$v->id,"update",$SYSNOTE);

      $pm                 = PotonganMst::where("id",$v->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD             = clone($pm);
      $pm->remaining_cut  = $pm->remaining_cut + $v->nominal_cut;
      $pm->save();
      $SYSNOTE            = MyLib::compareChange($SYSOLD,$pm);
      MyLog::sys("potongan_mst",$pm->id,"update",$SYSNOTE);
    }
    
  }
  
}
