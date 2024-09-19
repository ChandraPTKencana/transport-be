<?php
//app/Helpers/Envato/User.php
namespace App\Helpers;

use App\Models\MySql\IsUser;
use App\Models\MySql\Syslog;
use Illuminate\Support\Facades\DB;
use File;
use Request;

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

class GAuthor {
    public static function return_qr($username){

        $ga = new GoogleAuthenticator();
        $secret = $ga->generateSecret();

        IsUser::where("username",$username)->update(["ga_secret_key"=>$secret]);
        
        // $qrCodeUrl = GoogleQrUrl::generate($username, $secret, 'Genk.'.env("app_name"));
        $qrCodeUrl = GoogleQrUrl::generate($username, $secret, 'Genk.Logistik');
        
        // echo "<img src='https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl={$qrCodeUrl}'>";

        // echo "<img src='$qrCodeUrl'>";
        return $qrCodeUrl;
    }

    public static function validate_pin($secret,$pin){
        $ga = new GoogleAuthenticator();
        return $ga->checkCode($secret, $pin);
        // $secret = 'your_secret_here'; // Replace with the user's secret
        // $pin = '123456'; // Replace with the PIN entered by the user
        
        // if ($ga->checkCode($secret, $pin)) {
        //     echo "PIN is valid";
        // } else {
        //     echo "PIN is invalid";
        // }
    }
}
