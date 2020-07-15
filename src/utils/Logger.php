<?php

namespace rloris\layer\utils;

use rloris\layer\core\config\Configuration;
use rloris\layer\core\http\Request;

class Logger
{
    /**
     * @var Request
     */
    private static $request;

    static function write(string $message) {
        if(Configuration::environment("log", false) && Configuration::get("locations/log"))
        {
            $log = Configuration::environment("logTemplate", false);
            if(self::$request)
            {
                    $log = str_replace("{request_datetime}", date('Y-m-d H:i:s', self::$request->getRequestTime()), $log);
                    $log = str_replace("{request_time}", date('H:i:s.v', self::$request->getRequestTime()), $log);
                    $log = str_replace("{request_date}", date('Y-m-d', self::$request->getRequestTime()), $log);
                    $log = str_replace("{client_ip}", self::$request->getClientIp(), $log);
                    $log = str_replace("{client_os}", self::$request->getClientOS(), $log);
                    $log = str_replace('{client_browser}', self::$request->getClientBrowser(), $log);
                    $log = str_replace("{client_port}", self::$request->getClientPort(), $log);
                    $log = str_replace("{request_method}", self::$request->getRequestMethod(), $log);
                    $log = str_replace("{request_resource}", self::$request->getFullUrl(), $log);
                    $log = str_replace("{environment}", APP_ENV, $log);
                    $log = str_replace("{message}", $message, $log);
                    $log .= PHP_EOL;
            }
            else
            {
                    $log = "[".date('Y-m-d H:i:s')."] ".$message;
                    $log .= PHP_EOL;

            }
            $logFolder = realpath(Configuration::get("locations/log")."/".APP_ENV);
            if(!file_exists($logFolder))
            {
                mkdir($logFolder,0777, true);
            }
            $date = date('Y-m-d');
            file_put_contents($logFolder."/log_$date.txt", $log, FILE_APPEND);
        }
    }
}