<?php

namespace layer\core\utils;

use layer\core\config\Configuration;
use layer\core\http\Request;
use layer\core\http\Response;

class Logger
{

    /**
     * @var Request
     */
    public static $request;
    /**
     * @var Response
     */
    public static $response;

    static function write($message, $template = NULL) {
        if(self::$response && self::$request) {
            try {
                $log = $template ?? Configuration::get("layer/logTemplate");
                $date = date('Y-m-d');
                $log = str_replace("{request_datetime}", date('Y-m-d H:i:s', self::$request->getRequestTime()), $log);
                $log = str_replace("{request_time}", date('H:i:s.v', self::$request->getRequestTime()), $log);
                $log = str_replace("{request_date}", date('Y-m-d', self::$request->getRequestTime()), $log);
                $log = str_replace("{client_ip}", self::$request->getClientIp(), $log);
                $log = str_replace('{client_browser}', self::$request->getBrowser(), $log);
                $log = str_replace("{client_port}", self::$request->getClientPort(), $log);
                $log = str_replace("{request_method}", self::$request->getRequestMethod(), $log);
                $log = str_replace("{request_resource}", self::$request->getFullUrl(), $log);
                $log = str_replace("{message}", $message, $log);
                $log .= PHP_EOL;
                if(!file_exists(Configuration::get("layer/logFolder"))) {
                    mkdir(Configuration::get("layer/logFolder"),0777, true);
                }
                file_put_contents("logs/log_$date.txt", $log, FILE_APPEND);
            } catch (\Exception $e) {
                // echo $e->getMessage();
            }
        }
    }
}