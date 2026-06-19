<?php
//app/Helpers/Envato/User.php
namespace App\PS;

use App\Helpers\MyLib;
use App\Helpers\MyLog;
use App\Models\MySql\Employee;
use App\Models\MySql\PotonganMst;
use App\Models\MySql\PotonganTrx;
use App\Models\MySql\TrxTrp;

class PSGroupAcAccount
{

  public static function fn_ret($uj_details2){

    if(gettype($uj_details2)!='array')
    $uj_details2 = json_decode(json_encode($uj_details2), true);

    $supir_gaji = 0;
    $supir_makan = 0;
    $supir_dinas = 0;
    
    $kernet_gaji = 0;
    $kernet_makan = 0;
    $kernet_dinas = 0;

    foreach($uj_details2 as $k1=>$v1){
      if($v1['xfor']=='Supir' && $v1['ac_account_code']=='01.510.001') $supir_gaji += $v1['amount'] * $v1['qty'];          
      if($v1['xfor']=='Supir' && $v1['ac_account_code']=='01.510.005') $supir_makan += $v1['amount'] * $v1['qty'];          
      if($v1['xfor']=='Supir' && $v1['ac_account_code']=='01.575.002') $supir_dinas += $v1['amount'] * $v1['qty'];          

      if($v1['xfor']=='Kernet' && $v1['ac_account_code']=='01.510.001') $kernet_gaji += $v1['amount'] * $v1['qty'];          
      if($v1['xfor']=='Kernet' && $v1['ac_account_code']=='01.510.005') $kernet_makan += $v1['amount'] * $v1['qty'];          
      if($v1['xfor']=='Kernet' && $v1['ac_account_code']=='01.575.002') $kernet_dinas += $v1['amount'] * $v1['qty'];          
    }

    return [
      "supir_gaji"  =>$supir_gaji,
      "supir_makan" =>$supir_makan,
      "supir_dinas" =>$supir_dinas,
      "kernet_gaji" =>$kernet_gaji,
      "kernet_makan"=>$kernet_makan,
      "kernet_dinas"=>$kernet_dinas,
    ];
  }
}
