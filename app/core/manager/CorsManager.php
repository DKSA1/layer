<?php

namespace layer\core\manager;

use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;

class CorsManager
{
    /**
     * @var CorsManager
     */
    private static $instance;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;

    // TODO :cors rule key with values
    private $rules;

    private $allowedOrigins;

    public static function getInstance(Request $req, Response $res) : CorsManager
    {
        if(self::$instance == null) self::$instance = new CorsManager($req, $res);
        return self::$instance;
    }

    private function __construct($req, $res){
        $this->request = $req;
        $this->response = $res;
    }

    // all origins allowed
    public function allowAnyOrigins(): CorsManager {
        $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Origin, "*");
        return $this;
    }

    // all methods allowed
    public function allowAnyMethods(): CorsManager {
        $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Methods, "*");
        return $this;
    }

    // all headers allowed
    public function allowAnyHeaders(): CorsManager {
        $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Headers, "*");
        return $this;
    }

    // checks in the allowed origins for the current origin and adds the header or not
    public function allowOrigins(string ...$origins): CorsManager {
        if(($origin = $this->request->getHeader(IHttpHeaders::Origin)) && in_array($origin, $origins)) {
            $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Origin, $origin);
        }
        return $this;
    }

    // allow specific headers in the request
    public function allowHeaders(string ...$headers): CorsManager {
        if($this->request->getHeader(IHttpHeaders::Access_Control_Request_Headers))
            $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Headers, implode(", ", $headers) );
        return $this;
    }

    // allow specific methods to be called
    public function allowMethods(string ...$methods): CorsManager {
        if ($this->request->getHeader(IHttpHeaders::Access_Control_Request_Method))
            $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Methods, implode(", ", $methods) );
        return $this;
    }

    // works only if origin is set and not '*'
    public function supportsCredentials(bool $v) {
        $origin = $this->request->getHeader(IHttpHeaders::Origin);
        $this->response->putHeader(IHttpHeaders::Access_Control_Allow_Credentials, ($v && $origin) ? "true" : "false");
    }

    public function maxAge(int $time) {
        if($this->request->getHeader(IHttpHeaders::Origin))
            $this->response->putHeader(IHttpHeaders::Access_Control_Max_Age, $time);
    }

    private function sendCORS($actionMetaData) {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // origin you want to allow, and if so:
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        } else {
            // No HTTP_ORIGIN set, so we allow any. You can disallow if needed here
            header("Access-Control-Allow-Origin: *");
        }
        // Access-Control headers are received during OPTIONS requests
        if (strtoupper($this->request->getRequestMethod()) == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                header("Access-Control-Allow-Methods: " . implode(", ", $actionMetaData['request_methods']));
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            exit(0);
        }
    }
}