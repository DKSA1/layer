<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 11:28
 */

namespace layer\core\http;

class Response
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
    private static $instance;

    public static function getInstance() : Response
    {
        if(self::$instance == null) self::$instance = new Response();
        return self::$instance;
    }

    private function __construct(){
        $this->setHeader(IHttpHeaders::X_Powered_By, 'Hello there');
    }

    public function setHeader($key, $value)
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
            HttpHeaders::responseHeader($this->getResponseCode());
            if($this->contentType)
                $this->setHeader(IHttpHeaders::Content_Type, $this->contentType);
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
            $this->setHeader(IHttpHeaders::Content_Encoding, 'gzip');
            $this->setHeader(IHttpHeaders::Content_Length, strlen($content));
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
        if($compression && strpos(Request::getInstance()->getHeader(IHttpHeaders::Accept_Encoding), 'gzip') !== false)
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
}