<?php
//app/Helpers/Envato/User.php
namespace App\Helpers;

use App\Models\MySql\Syslog;
use Illuminate\Support\Facades\DB;
use File;
use Request;

class TrfMandiri {
    private string $csv_data = "";
    private array $csv_rows = [];
    private int $csv_all_total_to_paid = 0;
    private string $mandiri_no_rek = "";

    public function __construct(string $mandiri_no_rek) {
        $this->csv_data                 = "";
        $this->csv_rows                 = [];
        $this->csv_all_total_to_paid    = 0;
        $this->mandiri_no_rek           = $mandiri_no_rek;
    }

    public function addRow(string $rek_no,string $rek_name,string $total_to_paid,string $rek_type,string $bank_code,string $note=""){
        $this->csv_rows[] = [
            "rek_no"=>$rek_no,
            "rek_name"=>$rek_name,
            "total_to_paid"=>$total_to_paid,
            "rek_type"=>$rek_type,
            "bank_code"=>$bank_code,
            "note"=>$note,
        ];
    }

    public function generateBody(){
        $this->csv_data="";
        
        foreach ($this->csv_rows as $key => $val) {
            $this->csv_all_total_to_paid += (int) $val['total_to_paid'];

            $this->csv_data .= "{$val['rek_no']};{$val['rek_name']};;;;IDR;{$val['total_to_paid']};{$val['note']};;{$val['rek_type']};{$val['bank_code']};;;;;;N;;;;;Y;;;;;;;;;;;;;;;;;BEN;1;E";
            if($key<count($this->csv_rows)-1)
            $this->csv_data .= "\r\n";
        }
    }

    public function generateHead(){
        $date = new \DateTime();
        $count = count($this->csv_rows);
        $csv_header = "P;{$date->format('Ymd')};{$this->mandiri_no_rek};{$count};{$this->csv_all_total_to_paid}\r\n";
        $this->csv_data = $csv_header.$this->csv_data;
    }

    public function getCSV(){
        return $this->csv_data;
    }


    
}
