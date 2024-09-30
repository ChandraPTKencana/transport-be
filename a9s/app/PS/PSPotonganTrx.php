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

  public static function trpTrxInsert($id,$arrs=[]){
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
      $trx_trp = TrxTrp::where("id",$id)->with('uj_details2')->first();
      if(!$trx_trp) 
      throw new \Exception("Data Trx Trp Tidak Ditemukan",1);

      $supir_money_to_tf = 0;
      $kernet_money_to_tf = 0;
      foreach ($trx_trp->uj_details2 as $ujdv) {
        if($ujdv->xfor=='Kernet'){
          $kernet_money_to_tf+=$ujdv->amount*$ujdv->qty;
        }else{
          $supir_money_to_tf+=$ujdv->amount*$ujdv->qty;
        }
      }
      
      foreach ($arrs as $k => $v) {
        
        $employee_id = $v['employee_id'];
      
        // ambil dari method potongan karna perlu filter nya
        $employee = Employee::exclude(['attachment_1','attachment_2'])->where("id",$employee_id)->first();
        if(!$employee){
          throw new \Exception("Tidak ditemukan data pekerja",1);
        }

        if($trx_trp->supir_id != $employee->id && $trx_trp->kernet_id != $employee->id )
        throw new \Exception("Pekerja tidak cocok dengan data di Trx Trp",1);

        $potongan_mst   = PotonganMst::exclude(['attachment_1','attachment_2'])->where('employee_id',$employee->id)->where('val1',1)
        ->where('deleted',0)->where('status','Open')->where('remaining_cut',">",0)->orderBy('created_at','asc')
        ->lockForUpdate()
        ->first();

        if($trx_trp->supir_id == $employee->id && $supir_money_to_tf - $potongan_mst->nominal_cut < 10000)
        throw new \Exception("Dana Untuk Transfer Kesupir Minimal 10.000",1);

        if($trx_trp->kernet_id == $employee->id && $kernet_money_to_tf - $potongan_mst->nominal_cut < 10000)
        throw new \Exception("Dana Untuk Transfer Kekernet Minimal 10.000",1);

      }

      foreach ($arrs as $k => $v) {
      
          $employee_id = $v['employee_id'];
        
          // ambil dari method potongan karna perlu filter nya
          $employee = Employee::exclude(['attachment_1','attachment_2'])->where("id",$employee_id)->first();
          // if(!$employee){
          //   throw new \Exception("Tidak ditemukan data pekerja",1);
          // }

          // if($v["_source"]=='TRX_TRP' && isset($v['trx_trp_id'])){
          //   $trx_trp = TrxTrp::where("id",$v['trx_trp_id'])->first();
          //   if(!$trx_trp) 
          //   throw new \Exception("Data Trx Trp Tidak Ditemukan",1);
          // }else{
          //   throw new \Exception("Source Tidak Dikenali",1);
          // }
          self::trpTrxloopCut($id,$v,$employee,date("Y-m-d H:i:s"));
      }
    }

  }

  public static function trpTrxloopCut($id,$v,$emp,$t_stamp,$sisa_cut=0){
    $potongan_mst   = PotonganMst::exclude(['attachment_1','attachment_2'])->where('employee_id',$emp->id)->where('val1',1)
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
      $potongan_trx->trx_trp_id       = $id;
  
      $potongan_trx->save();
  
      MyLog::sys("potongan_trx",$potongan_trx->id,"insert");
      
      $potongan_mst->remaining_cut    = $potongan_mst->remaining_cut - $nominal_cut;
      $potongan_mst->updated_at       = $t_stamp;
      $potongan_mst->save();
  
      $SYSNOTE = MyLib::compareChange($SYSOLD,$potongan_mst);
      MyLog::sys("potongan_mst",$potongan_mst->id,"update",$SYSNOTE);
  
      if($sisa_cut>0) self::trpTrxloopCut($id,$v,$emp,$t_stamp,$sisa_cut);
    }
    
  }


  public static function trpTrxDelete($id,$arr=[]) {
    if(gettype($arr)!='array'){
      throw new \Exception("Tipe data tidak sesuai",1);
    }

    $potongan_trx = PotonganTrx::where('deleted',0)->where("trx_trp_id",$id)->lockForUpdate()->get();

    foreach ($potongan_trx as $k => $v) {
      $SYSOLD             = clone($v);
      $v->deleted         = 1;
      $v->deleted_user    = $arr['deleted_user'];
      $v->deleted_at      = $arr['deleted_at'];
      $v->deleted_reason  = $arr['deleted_reason'];
      $v->save();
      $SYSNOTE            = MyLib::compareChange($SYSOLD,$v);
      MyLog::sys("potongan_trx",$v->id,"update",$SYSNOTE);

      $pm                 = PotonganMst::exclude(['attachment_1','attachment_2'])->where("id",$v->potongan_mst_id)->lockForUpdate()->first();
      $SYSOLD             = clone($pm);
      $pm->remaining_cut  = $pm->remaining_cut + $v->nominal_cut;
      $pm->save();
      $SYSNOTE            = MyLib::compareChange($SYSOLD,$pm);
      MyLog::sys("potongan_mst",$pm->id,"update",$SYSNOTE);
    }
    
  }
  
}
