<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 11:27
 */

namespace layer\core\http;

use layer\core\utils\File;

class Request
{
    /**
     * @var string
     */
    private $fullUrl;
    /**
     * @var string
     */
    private $baseUrl;
    /**
     * @var string
     */
    private $queryString;
    /**
     * @var string
     */
    private $requestMethod;
    /**
     * @var bool
     */
    private $https;
    /**
     * @var bool
     */
    private $asynchronousRequest;
    /**
     * @var array
     */
    private $requestData;
    /**
     * @var string
     */
    private $clientIp;
    /**
     * @var int
     */
    private $clientPort;
    /**
     * @var string
     */
    private $serverIp;
    /**
     * @var int
     */
    private $serverPort;
    /**
     * @var int
     */
    private $requestTime;
    /**
     * @var string
     */
    private $clientBrowser = '';
    /**
     * @var string
     */
    private $clientOS = '';
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $app;
    /**
     * @var string[] $routeParameters
     */
    private $routeParameters = [];
    /**
     * @var bool $forwarded
     */
    private $forwarded = false;
    /**
     * @var array
     */
    private $_PUT = [];
    /**
     * @var array
     */
    private $_POST = [];
    /**
     * @var string
     */
    private $contentType;
    /**
     * @var Request
     */
    private static $instance;

    public static function getInstance() : Request
    {
        if(self::$instance == null) self::$instance = new Request();
        return self::$instance;
    }

    private function __construct()
    {
        $this->app = trim(dirname($_SERVER['SCRIPT_NAME']),'/');
        $this->host = $_SERVER['HTTP_HOST'];
        $this->baseUrl = $_REQUEST['url'];
        $this->fullUrl = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->queryString = $_SERVER['QUERY_STRING'];
        $this->clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
        $this->clientPort = intval($_SERVER['REMOTE_PORT']);
        $this->serverIp = $_SERVER['SERVER_ADDR'];
        $this->serverPort = intval($_SERVER['SERVER_PORT']);
        $this->requestTime = $_SERVER['REQUEST_TIME_FLOAT'];

        $contentType = $this->getHeader(IHttpHeaders::Content_Type);
        $this->contentType = isset($contentType[0]) ? $contentType[0] : null;

        if(strtolower($this->requestMethod) === 'put')
        {
            if($this->contentType == IHttpContentType::JSON)
            {
                $this->_PUT = json_decode(file_get_contents('php://input'),true);
            }
            else
            {
                parse_str(file_get_contents('php://input'), $this->_PUT);
            }
        }

        if(strtolower($this->requestMethod) === 'post' && $this->contentType == IHttpContentType::JSON)
        {
            $this->_POST = json_decode(file_get_contents('php://input'), true);
        }

        if(is_array($_POST) && is_array($this->_PUT) && is_array($this->_POST))
        {
            $this->requestData = array_merge($_POST, $this->_PUT, $this->_POST);
        }

        $this->https = (((isset($_SERVER['HTTPS'])) && (strtolower($_SERVER['HTTPS']) == 'on')) || ((isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) && (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')));
        $this->asynchronousRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

        $ua = " ".strtolower($_SERVER['HTTP_USER_AGENT']);
        if(strpos($ua, 'chrome/')) {
            if(strpos($ua, 'safari/')) {
                if(strpos($ua, 'opr/')) {
                    $this->clientBrowser = 'Opera';
                } elseif(strpos($ua, 'edge/')) {
                    $this->clientBrowser = 'Edge';
                } else {
                    $this->clientBrowser = 'Chrome';
                }
            }
        } elseif(strpos($ua, 'safari/')) {
            $this->clientBrowser = 'Safari';
        } elseif(strpos($ua, 'firefox/')) {
            $this->clientBrowser = 'Firefox';
        } elseif(strpos($ua, 'msie/')) {
            $this->clientBrowser = 'Internet Explorer';
        }

        if(preg_match('/windows|win32|win98|win95|win16/',$ua)) {
            $this->clientOS = 'Windows';
        } else if(strpos($ua, 'android')) {
            $this->clientOS = 'Android';
        } else if(preg_match('/macintosh|mac os x|mac_powerpc/', $ua))  {
            $this->clientOS = 'Macintosh';
        } else if(strpos($ua, 'linux')) {
            $this->clientOS = 'Linux';
        } elseif (strpos($ua, 'ubuntu')) {
            $this->clientOS = 'Ubuntu';
        } elseif (strpos($ua, 'iphone')) {
            $this->clientOS = 'IPhone';
        } elseif (strpos($ua, 'ipod')) {
            $this->clientOS = 'IPod';
        } elseif (strpos($ua, 'ipad')) {
            $this->clientOS = 'IPad';
        } elseif (strpos($ua, 'blackberry')) {
            $this->clientOS = 'Blackberry';
        } elseif (strpos($ua, 'webos')) {
            $this->clientOS = 'Mobile';
        }
    }

    /**
     * @return string
     */
    public function getFullUrl()
    {
        return $this->fullUrl;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
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
     * @return File[]
     */
    public function getUploadedFiles() : array
    {
        $files = [];
        foreach ($_FILES as $key => $file) {
            if(is_array($file['name'])) {
                $files[$key] = [];
                foreach ($file['name'] as $idx => $value) {
                    $f = File::getInstance($file['tmp_name'][$idx], $file['name'][$idx], $file['type'][$idx]);
                    array_push($files[$key], $f);
                }
            } else {
                $files[$key] = File::getInstance($file['tmp_name'], $file['name'], $file['type']);
            }
        }
        return $files;
    }

    /**
     * @param $name string
     * @return File|File[]
     */
    public function getUploadedFile($name)
    {
        if(array_key_exists($name, $_FILES)) {
            if(is_array($_FILES[$name]['name'])) {
                $files = [];
                foreach ($_FILES[$name]['name'] as $idx => $value) {
                    $f = File::getInstance($_FILES[$name]['tmp_name'][$idx], $_FILES[$name]['name'][$idx], $_FILES[$name]['type'][$idx]);
                    array_push($files, $f);
                }
                return $files;
            } else {
                return File::getInstance($_FILES[$name]['tmp_name'],$_FILES[$name]['name'], $_FILES[$name]['type']);
            }
        } else {
            return null;
        }
    }

    public function getHeader($name): array
    {
        $name = strtoupper(str_replace('-', '_', $name));
        if(array_key_exists($name, $_SERVER))
        {
            return explode(',', $_SERVER[$name]);
        }
        else if(array_key_exists("HTTP_".$name, $_SERVER))
        {
            return explode(',', $_SERVER["HTTP_".$name]);
        }
        return [];
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
            if(array_key_exists($key, $this->requestData))
                return $this->requestData[$key];
            else
                return null;
        }else{
            return $this->requestData;
        }
    }

    /**
     * @return string
     */
    public function getQueryString()
    {
        return $this->queryString;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * @return bool
     */
    public function isHttps(): bool
    {
        return $this->https;
    }

    /**
     * @return bool
     */
    public function isAsynchronousRequest(): bool
    {
        return $this->asynchronousRequest;
    }

    /**
     * @return string
     */
    public function getClientIp()
    {
        return $this->clientIp;
    }

    /**
     * @return int
     */
    public function getClientPort()
    {
        return $this->clientPort;
    }

    /**
     * @return string
     */
    public function getServerIp()
    {
        return $this->serverIp;
    }

    /**
     * @return int
     */
    public function getServerPort()
    {
        return $this->serverPort;
    }

    /**
     * @return int
     */
    public function getRequestTime()
    {
        return $this->requestTime;
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
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @return string
     */
    public function getClientBrowser()
    {
        return $this->clientBrowser;
    }

    /**
     * @return string
     */
    public function getClientOS()
    {
        return $this->clientOS;
    }

    /**
     * @return string[]
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * @return bool
     */
    public function isForwarded(): bool
    {
        return $this->forwarded;
    }

    public function getPut($key = null) {
        if($key){
            if(array_key_exists($key,$this->_PUT))
                return $this->_PUT[$key];
            else
                return null;
        }else{
            return $this->_PUT;
        }
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

}