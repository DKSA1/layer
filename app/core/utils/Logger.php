<?php

namespace layer\core\utils;

use layer\core\config\Configuration;
use layer\core\http\Request;

class Logger
{
    /**
     * @var Request
     */
    private static $request;

    static function write(string $message) {
        if(Configuration::get("environment/".Configuration::$environment."/log", false))
        {
            $log = Configuration::get("environment/".Configuration::$environment."/logTemplate", false);
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
                    $log = str_replace("{environment}", Configuration::$environment, $log);
                    $log = str_replace("{message}", $message, $log);
                    $log .= PHP_EOL;
            }
            else
            {
                    $log = "[".date('Y-m-d H:i:s')."] ".$message;
                    $log .= PHP_EOL;

            }
            if(!file_exists(Configuration::get("locations/logs")))
            {
                mkdir(Configuration::get("locations/logs"),0777, true);
            }
            $date = date('Y-m-d');
            file_put_contents(Configuration::get("locations/logs")."/log_$date.txt", $log, FILE_APPEND);
        }
    }
}