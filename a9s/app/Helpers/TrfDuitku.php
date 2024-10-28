<?php
//app/Helpers/Envato/User.php
namespace App\Helpers;

use App\Models\MySql\Syslog;
use Illuminate\Support\Facades\DB;
use File;
use Request;

class TrfDuitku {
    // private static $userId = 48271;
    // private static $secretKey = '26c52e376ebe13a216175e8c2998de582b984d2c765a0f5f7700765ce6208653';
    // private static $email = 'miausion@gmail.com';
    private static $type = 'BIFAST';


    public static function generate_invoice($bankCode,$bankAccount,$amountTransfer,$custRefNumber,$purpose=''){
        $userId     = env("DK_I");
        $secretKey  = env("DK_S");
        $email      = env("DK_E");
        $timestamp  = round(microtime(true) * 1000);

        // $bankCode          = '014'; 
        // $bankAccount       = '8760673466';
        // $amountTransfer    =  12000; 
        // $custRefNumber     = '12345789';
        // // $senderName        = 'John Doe';
        // // $senderId          =  12345789; 
        // $purpose           = 'Test BIFAST Inquiry with duitku';
        $paramSignature    = $email . $timestamp . $bankCode . self::$type . $bankAccount . $amountTransfer . $purpose . $secretKey; 

        $signature = hash('sha256', $paramSignature);

        $params = array(
            'userId'         => $userId,
            'email'          => $email,
            'bankCode'       => $bankCode,
            'bankAccount'    => $bankAccount,
            'amountTransfer' => $amountTransfer,
            'custRefNumber'  => $custRefNumber,
            // 'senderId'       => $senderId,
            // 'senderName'     => $senderName,
            'purpose'        => $purpose,
            'type'           => self::$type,
            'timestamp'      => $timestamp,
            'signature'      => $signature
        );

        $params_string = json_encode($params);
        // MyLog::logging($params,"discheck");
        $url = 'https://passport.duitku.com/webapi/api/disbursement/inquiryclearing';
        // $url = 'https://sandbox.duitku.com/webapi/api/disbursement/inquiryclearingsandbox';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($params_string))                                                                       
        );   
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        //execute post
        $request = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        $result = [];
        if($httpCode == 200)
        {
            $result = json_decode($request, true);
            if($result['responseCode']=="-142"){
                $result['responseCode']=="00";
            }
            return $result;
            //header('location: '. $result['paymentUrl']);
            // echo "email          :". $result['email']          . "<br />";
            // echo "bankCode       :". $result['bankCode']       . "<br />";
            // echo "bankAccount    :". $result['bankAccount']    . "<br />";
            // echo "amountTransfer :". $result['amountTransfer'] . "<br />";
            // echo "accountName    :". $result['accountName']    . "<br />";
            // echo "custRefNumber  :". $result['custRefNumber']  . "<br />";
            // echo "disburseId     :". $result['disburseId']     . "<br />";
            // echo "type           :". $result['type']           . "<br />";
            // echo "responseCode   :". $result['responseCode']   . "<br />";
            // echo "responseDesc   :". $result['responseDesc']   . "<br />";
        }
        // else
        //     echo $httpCode;

            return $result;

    }

    public static function generate_transfer($disburseId,$bankCode,$bankAccount,$amountTransfer,$custRefNumber,$purpose=''){
        $userId     = env("DK_I");
        $secretKey  = env("DK_S");
        $email      = env("DK_E");
        // $bankCode       = '014'; 
        // $bankAccount    = '8760673466';
        // $amountTransfer =  11000;
        // $disburseId    = 596865;
        // $accountName    = 'Test Account';
        $accountName    = '';
        // $custRefNumber  = '11';
        // $purpose        = 'Test Clearing Inquiry with duitku';
        $timestamp      = round(microtime(true) * 1000);
        $paramSignature = $email . $timestamp . $bankCode . self::$type . $bankAccount . $accountName . $custRefNumber . $amountTransfer . $purpose . $disburseId . $secretKey; 

        $signature = hash('sha256', $paramSignature);

        $params = array(
            'disburseId' => $disburseId,
            'userId'         => $userId,
            'email'          => $email,
            'bankCode'       => $bankCode,
            'bankAccount'    => $bankAccount,
            'amountTransfer' => $amountTransfer,
            'accountName'    => $accountName,
            'custRefNumber'  => $custRefNumber,
            'type'           => self::$type,
            'purpose'        => $purpose,
            'timestamp'      => $timestamp,
            'signature'      => $signature
        );

        $params_string = json_encode($params);
        $url = 'https://passport.duitku.com/webapi/api/disbursement/transferclearing';
        // $url = 'https://sandbox.duitku.com/webapi/api/disbursement/transferclearingsandbox';
    
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);                                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
            'Content-Type: application/json',                                                                                
            'Content-Length: ' . strlen($params_string))                                                                       
        );   
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        //execute post
        $request = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = [];

        if($httpCode == 200)
        {
            $result = json_decode($request, true);
                     
            if($result['responseCode']=="-142" || trim($result['responseDesc'])=="In Progress"){
                $result['responseCode']=="00";
            }
            // //header('location: '. $result['paymentUrl']);
            // echo "email          :". $result['email'] . "<br />";
            // echo "bankCode       :". $result['bankCode'] . "<br />";
            // echo "bankAccount    :". $result['bankAccount'] . "<br />";
            // echo "amountTransfer :". $result['amountTransfer'] . "<br />";
            // echo "accountName    :". $result['accountName'] . "<br />";
            // echo "custRefNumber  :". $result['custRefNumber'] . "<br />";
            // echo "type           :". $result['type'] . "<br />";
            // echo "responseCode   :". $result['responseCode'] . "<br />";
            // echo "responseDesc   :". $result['responseDesc'] . "<br />";
            
   

            // if(!empty($email) && !empty($bankCode) && !empty($bankAccount) && !empty($accountName) && !empty($custRefNumber) && !empty($amountTransfer) && !empty($disburseId) && !empty($signature))
            // {
            //         $params = $email . $bankCode . $bankAccount . $accountName . $custRefNumber .  $amountTransfer . $disburseId . $secretKey;
            //         $calcSignature = hash('sha256', $params);
            //         if($signature == $calcSignature)
            //         {
            //             //Your code here
            //             echo "SUCCESS"; // Please response with success

            //         }
            //         else
            //         {
            //             //throw new Exception('Bad Signature');
            //             //mo cek signature nanti
            //         }
            //     }else
            //     {
            //         // throw new Exception('Bad Parameter');
            //     }
        }
        // else
        //     echo $httpCode;

        return $result;

    }


    public static function info_inv($status_code){
        $status_codes =[
            ["id"=>"EE","msg"=>"General Error."],
            ["id"=>"TO","msg"=>"Response Time Out dari Jaringan ATM Bersama (Jangan diulang)."],
            ["id"=>"LD","msg"=>"Masalah link antara Duitku dan jaringan ATM Bersama."],
            ["id"=>"NF","msg"=>"Transaksi belum tercatat pada gateway Remittance."],
            ["id"=>"76","msg"=>"Nomor rekening tujuan tidak valid."],
            ["id"=>"80","msg"=>"Sedang menunggu callback."],
            ["id"=>"-100","msg"=>"Kesalahan lainnya (Jangan di ulang)."],
            ["id"=>"-120","msg"=>"User ID tidak ditemukan/ tidak memiliki akses ke API ini."],
            ["id"=>"-123","msg"=>"User telah diblokir."],
            ["id"=>"-141","msg"=>"Nominal transfer tidak valid."],
            ["id"=>"-142","msg"=>"Transaksi sudah selesai."],
            ["id"=>"-148","msg"=>"Bank tidak mendukung tipe H2H."],
            ["id"=>"-149","msg"=>"Bank tidak terdaftar."],
            ["id"=>"-161","msg"=>"URL callback tidak ditemukan."],
            ["id"=>"-191","msg"=>"Signature tidak valid."],
            ["id"=>"-192","msg"=>"Nomor rekening masuk blacklist."],
            ["id"=>"-213","msg"=>"Alamat email salah."],
            ["id"=>"-420","msg"=>"Transaksi tidak ditemukan."],
            ["id"=>"-510","msg"=>"Dana tidak cukup."],
            ["id"=>"-920","msg"=>"Batas terlampaui."],
            ["id"=>"-930","msg"=>"IP tidak terdaftar dalam whitelist."],
            ["id"=>"-951","msg"=>"Waktu telah habis."],
            ["id"=>"-952","msg"=>"Parameter tidak valid."],
            ["id"=>"-960","msg"=>"Timestamp sudah tidak berlaku (5 menit)."],
        ];

        $am = array_map(function($x){return $x["id"];},$status_codes);
        $idx = array_search($status_code,$am);

        if($idx===false){
            return "";
        }else{
            return $status_codes[$idx]["msg"];
        }
    }
}
