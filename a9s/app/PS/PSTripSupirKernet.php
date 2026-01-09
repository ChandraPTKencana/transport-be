<?php
//app/Helpers/Envato/User.php
namespace App\PS;

use App\Helpers\MyLib;
use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganMst;
use App\Models\MySql\PotonganTrx;
use App\Models\MySql\TrxTrp;

class PSTripSupirKernet
{

  public static function fn_supir_kernet_for_transfer($trx_trp){
    $result = [
      "supir_money"=>0,
      "supir_money"=>0,
      // "kernet_money_ids"=>null,
      // "kernet_money_ids"=>null,

      "supir_potongan_trx_money"=>0,
      "kernet_potongan_trx_money"=>0,
      "supir_potongan_trx_ids"=>"",
      "kernet_potongan_trx_ids"=>"",

      "supir_extra_money_trx_money"=>0,
      "kernet_extra_money_trx_money"=>0,
      "supir_extra_money_trx_ids"=>"",
      "kernet_extra_money_trx_ids"=>"",

    ];

    foreach ($trx_trp->uj->details2 as $k1 => $v1) {
      if($v1->xfor=='Kernet'){
        $result['kernet_money']+= $v1->qty * $v1->amount;
      }else{
        $result['supir_money']+= $v1->qty * $v1->amount;
      }
    }
  
    foreach ($trx_trp->potongan as $kx => $v2) {
      if($v2->potongan_mst->employee_id == $trx_trp->supir_id){
        $result["supir_potongan_trx_money"]+=$v2->nominal_cut;
        if($result["supir_potongan_trx_ids"]==""){
          $result["supir_potongan_trx_ids"].="PTG#".$v2->potongan_mst->id;
        }else{
          $result["supir_potongan_trx_ids"].=",".$v2->potongan_mst->id;
        }
      }

      if($v2->potongan_mst->employee_id == $trx_trp->kernet_id){
        $result["kernet_potongan_trx_money"]+=$v2->nominal_cut;
        if($result["kernet_potongan_trx_ids"]==""){
          $result["kernet_potongan_trx_ids"].="PTG#".$v2->potongan_mst->id;
        }else{
          $result["kernet_potongan_trx_ids"].=",".$v2->potongan_mst->id;
        }
      }
    }
    
    foreach ($trx_trp->extra_money_trxs as $k => $emt) {
      if($emt->employee_id == $trx_trp->supir_id){
        $result['supir_extra_money_trx_money']+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
        if($result['supir_extra_money_trx_ids']==""){
          $result['supir_extra_money_trx_ids'].="EM#".$emt->id." ";
        }else{
          $result['supir_extra_money_trx_ids'].=",".$emt->id." ";
        }
      }

      if($emt->employee_id == $trx_trp->kernet_id){
        $result['kernet_extra_money_trx_money']+=($emt->extra_money->nominal * $emt->extra_money->qty) ;
        if($result['kernet_extra_money_trx_ids']==""){
          $result['kernet_extra_money_trx_ids'].="EM#".$emt->id." ";
        }else{
          $result['kernet_extra_money_trx_ids'].=",".$emt->id." ";
        }
      }
    }
    return $result;
  }
}
