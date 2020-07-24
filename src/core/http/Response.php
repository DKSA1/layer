<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 11:28
 */

namespace rloris\layer\core\http;

use rloris\layer\core\error\ERedirect;
use rloris\layer\utils\File;

class Response implements IHttpCodes
{
    /**
     * @var int $responseCode
     */
    private $responseCode = IHttpCodes::OK;
    /**
     * @var string $contentType
     */
    private $contentType = IHttpContentType::HTML;
    /**
     * @var bool $compression
     */
    private $compression = false;
    /**
     * @var bool $minified
     */
    private $minified = false;
    /**
     * @var string[] $headers
     */
    private $headers = [];
    /**
     * @var string $messageBody
     */
    private $messageBody;
    /**
     * @var float
     */
    private $responseTime;
    /**
     * @var bool
     */
    private $headersSent = false;
    /**
     * @var bool
     */
    private $responseSent = false;
    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->putHeader(IHttpHeaders::X_Powered_By, 'Hello there');
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

    public function putHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setResponseCode(int $code) {
        if(preg_match('/^[1-5][0-9][0-9]$/', strval($code)))
        {
            $this->responseCode = $code;
        }
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }
    /**
     * @param string $messageBody
     */
    public function setMessageBody(string $messageBody) {
        $this->messageBody = $messageBody;
    }
    /**
     * @return string
     */
    public function getMessageBody()
    {
        return $this->messageBody;
    }

    public function getResponseTime()
    {
        return $this->responseTime;
    }

    public function sendHeaders() {
        if(!headers_sent())
        {
            http_response_code($this->getResponseCode());
            if($this->contentType && !isset($this->getHeaders()[IHttpHeaders::Content_Type]))
                $this->putHeader(IHttpHeaders::Content_Type, $this->contentType);
            foreach ($this->getHeaders() as $h => $v)
            {
                header($h.": ".$v, true, $this->getResponseCode());
            }
            $this->headersSent = true;
        }
    }

    public function sendResponse() {
        if(!$this->responseSent)
        {
            $content = $this->compress($this->minify($this->getMessageBody()));

            if(!headers_sent())
            {
                $this->sendHeaders();
            }

            echo $content;

            $this->responseTime = microtime(true);
            $this->responseSent = true;
        }
    }

    public function sendFile(File $file): bool
    {
        $filename = $file->getDownloadName() ? $file->getDownloadName() : $file->getFullname();

        if(file_exists($file->getAbsolutePath()))
        {
            // send specific headers for file download
            $this->putHeader(IHttpHeaders::Content_Type, $file->getMimeType());
            $this->putHeader(IHttpHeaders::Pragma, 'public');
            $this->putHeader(IHttpHeaders::Expires, '0');
            $this->putHeader(IHttpHeaders::Cache_Control, 'must-revalidate, post-check=0, pre-check=0');
            $this->putHeader(IHttpHeaders::Content_Disposition, 'attachment; filename="'.$filename.'"');
            $this->putHeader(IHttpHeaders::Content_Length, $file->getSize());
            $this->sendHeaders();
            flush();
            @readfile($file->getAbsolutePath());
            return true;
        }
        else
            return false;
    }

    public function redirect($location, $httpCode = IHttpCodes::MovedTemporarily)
    {
        throw new ERedirect($location, $httpCode);
    }

    /**
     * @param $data
     * @return string
     */
    private function minify($data) {
        if($this->minified) {
            return str_replace(array("\r\n","\r","\n"),"", preg_replace('/^[[:blank:]]+/m', '', $data));
        }
        return $data;
    }

    /**
     * @param $data
     * @return string
     */
    private function compress($data) {
        if($this->compression)
        {
            $content = gzencode($data, 1);
            $this->putHeader(IHttpHeaders::Content_Encoding, 'gzip');
            $this->putHeader(IHttpHeaders::Content_Length, strlen($content));
            return $content;
        }
        return $data;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType(string $contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return bool
     */
    public function isCompression(): bool
    {
        return $this->compression;
    }

    /**
     * @param bool $compression
     */
    public function setCompression(bool $compression)
    {
        if($compression && strpos($this->request->getHeader(IHttpHeaders::Accept_Encoding), 'gzip') !== false)
            $this->compression = $compression;
    }

    /**
     * @return bool
     */
    public function isMinified(): bool
    {
        return $this->minified;
    }

    /**
     * @param bool $minified
     */
    public function setMinified(bool $minified)
    {
        $this->minified = $minified;
    }

    /**
     * @return bool
     */
    public function isResponseSent(): bool
    {
        return $this->responseSent;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}