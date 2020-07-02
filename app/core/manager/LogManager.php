<?php

namespace layer\core\manager;

use layer\core\config\Configuration;
use layer\core\http\Request;

class LogManager
{
    /**
     * @var Request
     */
    private $request;
    /**
     * @var LogManager
     */
    private static $instance;

    public static function getInstance(Request $request) : LogManager
    {
        if(self::$instance == null) self::$instance = new LogManager($request);
        return self::$instance;
    }

    private function __construct($request)
    {
        $this->request = $request;
    }

    function write($message, $template = NULL) {
        if(Configuration::get("environment/".Configuration::$environment."/log", false))
        {
            $date = date('Y-m-d');
            if($this->request)
            {
                    $log = $template ?? Configuration::get("environment/".Configuration::$environment."/logTemplate");
                    $log = str_replace("{request_datetime}", date('Y-m-d H:i:s', $this->request->getRequestTime()), $log);
                    $log = str_replace("{request_time}", date('H:i:s.v', $this->request->getRequestTime()), $log);
                    $log = str_replace("{request_date}", date('Y-m-d', $this->request->getRequestTime()), $log);
                    $log = str_replace("{client_ip}", $this->request->getClientIp(), $log);
                    $log = str_replace("{client_os}", $this->request->getClientOS(), $log);
                    $log = str_replace('{client_browser}', $this->request->getClientBrowser(), $log);
                    $log = str_replace("{client_port}", $this->request->getClientPort(), $log);
                    $log = str_replace("{request_method}", $this->request->getRequestMethod(), $log);
                    $log = str_replace("{request_resource}", $this->request->getFullUrl(), $log);
                    $log = str_replace("{environment}", Configuration::$environment, $log);
                    $log = str_replace("{message}", $message, $log);
                    $log .= PHP_EOL;
            }
            else
            {
                    $log = $message;
                    $log .= PHP_EOL;

            }
            if(!file_exists(Configuration::get("locations/logs")))
            {
                mkdir(Configuration::get("locations/logs"),0777, true);
            }
            file_put_contents(Configuration::get("locations/logs")."/log_$date.txt", $log, FILE_APPEND);
        }
    }
}