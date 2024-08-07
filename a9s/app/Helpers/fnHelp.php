<?php
// if (!function_exists('id_clean_point_format')) {
//   function id_clean_point_format($val)
//   {
//     return rtrim(rtrim((string)number_format($val, 2, ",", "."),"0"),",");
//   }
// }
//
// if (!function_exists('hari_tanggal')) {
//   function hari_tanggal($tanggal)
//   {
//     $day=["Minggu","Senin","Selasa","Rabu","Kamis","Jumat","Sabtu"];
//     return $day[date("w",strtotime($tanggal))].",".date("d-m-Y",strtotime($tanggal));
//   }
// }

if (!function_exists('files_path')) {
    function files_path($x = "")
    {
        return public_path("/../../a9p" . $x);
        // return public_path("/../../../".$x);
        // return public_path("/../public/" . $x);
        // return public_path($x);
    }
}


if (!function_exists('mct')) {

    function mct($filename)
    {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );
        $pfs = explode('.', $filename);
        $ext = strtolower(array_pop($pfs));

        // $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        } else {
            return 'application/octet-stream';
        }
    }
}


if (!function_exists('block_negative')) {
    function block_negative($realnumber,$decimal)
    {
        if($realnumber<0) 
            return "(".number_format($realnumber*-1, $decimal,',','.').")";
        else
            return number_format($realnumber, $decimal,',','.');
    }
}


if (!function_exists('getRealIpAddress')) {
    function getRealIpAddress() {
        $ipAddress = '';

        // Check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        }

        // Check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Check if multiple IPs exist in var
            $iplist = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            foreach ($iplist as $ip) {
                if (validate_ip($ip)) {
                    if ($ipAddress === '') {
                        $ipAddress = $ip;
                    } else {
                        // If there is more than one IP, stop after the first IP is found
                        break;
                    }
                }
            }
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED']) && validate_ip($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        }
        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && validate_ip($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && validate_ip($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        }
        if (!empty($_SERVER['HTTP_FORWARDED']) && validate_ip($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        }

        // Check for IPs passing through Fly proxy
        if (!empty($_SERVER["HTTP_FLY_CLIENT_IP"]) && validate_ip($_SERVER["HTTP_FLY_CLIENT_IP"])) {
            $ipAddress = $_SERVER["HTTP_FLY_CLIENT_IP"];
        }

        if($ipAddress=="" && !empty($_SERVER["REMOTE_ADDR"]) && validate_ip($_SERVER["REMOTE_ADDR"])){
            $ipAddress = $_SERVER["REMOTE_ADDR"];
        }

        // Return validated IP
        if (validate_ip($ipAddress)) {
            return $ipAddress;
        }

        return 'UNKNOWN';
    }
}

if (!function_exists('validate_ip')) {
    function validate_ip($ip) {
        if (strtolower($ip) === 'unknown') {
            return false;
        }

        // Generate ipv4 network address
        $ip = ip2long($ip);

        // If the IP address is not valid, return false
        if ($ip !== false && $ip !== -1) {
            // Make sure the IP does not exceed the maximum for a ipv4 network address
            $ip = sprintf('%u', $ip);
            if ($ip <= 0 || $ip >= 0xffffffff) {
                return false;
            }

            return true;
        }

        return false;
    }
}