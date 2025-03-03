<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QuickRpt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quick_rpt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Serve the application from a custom public folder';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $account_id = 552;
        // $start_date = '2025-01-01';
        // $end_date = '2025-01-31';
        // $myData = DB::connection('sqlsrv')->select("exec USP_AC_Accounts_QueryActivities @AccountID=:account_id,@DateStart =:start_date,
        // @DateEnd=:end_date",[
        //   ":account_id"=>$account_id,
        //   ":start_date"=>$start_date,
        //   ":end_date"=>$end_date,
        // ]);

        // // $myData= $myData->map(function ($item) {
        // //     return array_map('utf8_encode', (array)$item);
        // // })->toArray();
        // // $this->info(json_encode($myData)."\n ");

        // foreach ($myData as $key => $value) {   
        //     $this->info(json_encode($value)."\n ");
        // }


        // $list_ticket = DB::connection('sqlsrv')->select("select *,amountpaid-nilai as selisih from (
        // select voucherno,AMOUNTPAID, (SELECT SUM(AMOUNT) FROM FI_ARAPExtraItems WHERE VOUCHERID = FI_ARAP.VOUCHERID ) AS NILAI
        // from fi_arap where VOUCHERTYPE = 'TRP' and voucherdate >= '2025-01-01' and voucherdate <= '2025-01-31' and isAR = 0 
        // ) PV ");
        //     // where amountpaid-nilai > 0 OR amountpaid-nilai < 0

        // // $this->info(json_encode($list_ticket)."\n ");

        // foreach ($list_ticket as $key => $value) {
        //     $this->info(json_encode($value)."\n ");
        // }


//         $source_local = DB::connection('mysql')->select("select a.id,b.id,a.pv_no,b.amount from (SELECT * FROM trx_trp WHERE pv_id is NOT NULL AND req_deleted = '0' AND deleted ='0' AND val='1' AND val1='1' 
// AND val2='1' 
// and ( (payment_method_id = '1' AND received_payment = '0' AND tanggal >='2025-01-01' AND tanggal <= '2025-01-31') 
// OR (payment_method_id='2' AND ((rp_supir_at>='2025-01-01 00:00:00' AND rp_supir_at<='2025-01-31 23:59:59') OR 
// (rp_kernet_at>='2025-01-01 00:00:00' AND rp_kernet_at<='2025-01-31 23:59:59')) )  ))  a
// join
// is_ujdetails2 b on a.id_uj = b.id_uj where (b.xfor ='Kernet' or b.xfor='Supir') and b.ac_account_code ='01.510.001'");


// $source_local = DB::connection('mysql')->select("select a.id,b.id,a.pv_no,b.amount from (SELECT * FROM trx_trp WHERE pv_id is NOT NULL AND req_deleted = '0' AND deleted ='0' AND val='1' AND val1='1' 
// AND val2='1' 
// and ( (payment_method_id = '1' AND received_payment = '0' AND tanggal >='2025-01-01' AND tanggal <= '2025-01-31') 
// OR (payment_method_id='2' AND ((rp_supir_at>='2025-01-01 00:00:00' AND rp_supir_at<='2025-01-31 23:59:59') OR 
// (rp_kernet_at>='2025-01-01 00:00:00' AND rp_kernet_at<='2025-01-31 23:59:59')) )  ))  a
// join
// is_ujdetails2 b on a.id_uj = b.id_uj where (b.xfor ='Kernet' or b.xfor='Supir') and b.ac_account_code ='01.510.001'");


// $this->info(json_encode($source_local)."\n ");

// foreach ($source_local as $key => $value) {
//             $this->info(json_encode($value)."\n ");
//         }
//     }
$client = new \GuzzleHttp\Client([
    'headers' => [ 'Content-Type' => 'application/json' ]
]);

$endpoint = "http://127.0.0.1:5000/compare_face";
$id = 5;
$value = "ABC";
try {
    
    $response = $client->request('GET', $endpoint, ['body' => json_encode([
      'emp_data' => [
        'id' => $id,
        'name' => $value,
      ], 
      'key2' => $value,
  ])]);
  $data = json_decode($response->getBody(), true);
} catch (\Exception $e) {
    echo $e->getMessage();

  }
  echo $response->getBody();

}
}