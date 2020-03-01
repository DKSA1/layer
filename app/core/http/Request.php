<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 11:27
 */

namespace layer\core\http;

class Request
{
    /**
     * @var string
     */
    private $full_url;
    /**
     * @var string
     */
    private $base_url;
    /**
     * @var string
     */
    private $query_string;
    /**
     * @var string
     */
    private $request_method;
    /**
     * @var bool
     */
    private $isHttps;
    /**
     * @var bool
     */
    private $isAsynchronousRequest;
    /**
     * @var array
     */
    private $request_data;
    /**
     * @var string
     */
    private $client_ip;
    /**
     * @var int
     */
    private $client_port;
    /**
     * @var string
     */
    private $server_ip;
    /**
     * @var int
     */
    private $server_port;
    /**
     * @var int
     */
    private $request_time;
    /**
     * @var string
     */
    private $clientBrowser;
    /**
     * @var string
     */
    private $clientOS;
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $app;

    public function __construct()
    {
        $this->app = trim(dirname($_SERVER['SCRIPT_NAME']),'/');
        $this->host = $_SERVER['HTTP_HOST'];
        $this->base_url = $_REQUEST['url'];
        $this->full_url = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $this->request_method = $_SERVER['REQUEST_METHOD'];
        $this->query_string = $_SERVER['QUERY_STRING'];
        $this->client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
        $this->client_port = intval($_SERVER['REMOTE_PORT']);
        $this->server_ip = $_SERVER['SERVER_ADDR'];
        $this->server_port = intval($_SERVER['SERVER_PORT']);
        $this->request_time = $_SERVER['REQUEST_TIME_FLOAT'];
        $this->request_data = array_merge($_GET, $_POST);
        $this->isHttps = (((isset($_SERVER['HTTPS'])) && (strtolower($_SERVER['HTTPS']) == 'on')) || ((isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')));
        $this->isAsynchronousRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        if(strpos($ua, 'chrome/')) {
            if(strpos($ua, 'safari/')) {
                if(strpos($ua, 'opr/')) {
                    $this->clientBrowser = 'Opera';
                } else if(strpos($ua, 'edge/')) {
                    $this->clientBrowser = 'Edge';
                } else {
                    $this->clientBrowser = 'Chrome';
                }
            }
        } else if(strpos($ua, 'safari/')) {
            $this->clientBrowser = 'Safari';
        } else if(strpos($ua, 'firefox/')) {
            $this->clientBrowser = 'Firefox';
        } else if(strpos($ua, 'msie/')) {
            $this->clientBrowser = 'Internet Explorer';
        }
    }

    /**
     * @return string
     */
    public function getFullUrl()
    {
        return $this->full_url;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    /**
     * @return null|string|array
     */
    public function getCookie($key = null)
    {
        if($key){
            if(array_key_exists($key,$_COOKIE))
                return $_COOKIE[$key];
            else
                return null;
        }else{
            return $_COOKIE;
        }

    }

    /**
     * @param $key
     * @param $value
     * @param int $time
     * @param string $path
     * @return bool
     */
    public function putCookie($key,$value,$time = null,$path = null)
    {
        if($time == null) $time = time() + (86400*30);
        if($path == null) $path = "/";

        return setcookie($key, $value, $time, $path);
    }

    /**
     * @param $key string
     */
    public function removeCookie($key)
    {
        if(isset($_COOKIE[$key]))
        {
            unset($_COOKIE[$key]);
            setcookie($key,null,time() - (86400*30),"/");
        }
    }

    /**
     * @return null|string|array
     */
    public function getGet($key = null)
    {
        if($key){
            if(array_key_exists($key,$_GET))
                return $_GET[$key];
            else
                return null;
        }else{
            return $_GET;
        }
    }

    /**
     * @return null|string|array
     */
    public function getPost($key = null)
    {
        if($key){
            if(array_key_exists($key,$_POST))
                return $_POST[$key];
            else
                return null;
        }else{
            return $_POST;
        }
    }

    /**
     * @return null|string|array
     */
    public function getFiles($key = null)
    {
        if($key){
            if(array_key_exists($key,$_FILES))
                return $_FILES[$key];
            else
                return null;
        }else{
            return $_FILES;
        }
    }

    /**
     * @return null|string|array
     */
    public function getServer($key = null)
    {
        if($key){
            if(array_key_exists($key,$_SERVER))
                return $_SERVER[$key];
            else
                return null;
        }else{
            return $_SERVER;
        }
    }

    /**
     * @return null|string|array
     */
    public function getSession($key = null)
    {
        if($key){
            if(array_key_exists($key,$_SESSION))
                return $_SESSION[$key];
            else
                return null;
        }else{
            return $_SESSION;
        }
    }

    /**
     * @param $key string
     * @param $value
     */
    public function putSession($key,$value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * @param $key string
     */
    public function removeSession($key){

        if(array_key_exists($key,$_SESSION))
            unset($_SESSION[$key]);
    }

    /**
     * @return null|array|string
     */
    public function getRequestData($key = null)
    {
        if($key){
            if(array_key_exists($key,$this->request_data))
                return $this->request_data[$key];
            else
                return null;
        }else{
            return $this->request_data;
        }
    }

    /**
     * @return string
     */
    public function getQueryString()
    {
        return $this->query_string;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->request_method;
    }

    /**
     * @return bool
     */
    public function isHttps(): bool
    {
        return $this->isHttps;
    }

    /**
     * @return bool
     */
    public function isAsynchronousRequest(): bool
    {
        return $this->isAsynchronousRequest;
    }

    /**
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->client_ip;
    }

    /**
     * @return int
     */
    public function getClientPort(): int
    {
        return $this->client_port;
    }

    /**
     * @return string
     */
    public function getServerIp(): string
    {
        return $this->server_ip;
    }

    /**
     * @return int
     */
    public function getServerPort(): int
    {
        return $this->server_port;
    }

    /**
     * @return int
     */
    public function getRequestTime(): int
    {
        return $this->request_time;
    }

    /**
     * @return mixed
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getApp(): string
    {
        return $this->app;
    }

    /**
     * @return string
     */
    public function getClientBrowser(): string
    {
        return $this->clientBrowser;
    }

}