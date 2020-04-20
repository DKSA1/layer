<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 11:28
 */

namespace layer\core\http;

use layer\core\utils\Logger;

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
    private $minified = true;
    /**
     * @var string $content
     */
    private $content;

    private $headers = [];

    private $data = [];

    private $responseTime;

    private $headersSent = false;

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

    public function putHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setResponseCode(int $code) {
        $this->responseCode = $code;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }
    /**
     * @param string $key
     * @param $value
     */
    public function putData(string $key, $value) {
        $this->data[$key] = $value;
    }
    /**
     * @param array|object $array
     */
    public function setDataArray($array) {
        $this->data = $array;
    }
    /**
     * @return array|object
     */
    public function getData() {
        return $this->data;
    }
    /**
     * @param string $content
     */
    public function setContent(string $content) {
        $this->content = $content;
    }
    /**
     * @return string
     */
    public function getContent() : string {
        return $this->content;
    }

    public function getResponseTime()
    {
        return $this->responseTime;
    }

    public function sendHeaders() {
        if(!headers_sent()) {
            HttpHeaders::responseHeader($this->getResponseCode());
            $this->putHeader(IHttpHeaders::X_Powered_By, 'Hello there');
            if($this->contentType)
                $this->putHeader(IHttpHeaders::Content_Type, $this->contentType);
            foreach ($this->getHeaders() as $h => $v) {
                header($h.": ".$v, true, $this->getResponseCode());
            }
            $this->headersSent = true;
        }
    }

    public function sendResponse() {
        if(!$this->responseSent)
        {
            $content = $this->getContent();
            if($this->compression)
            {
                $content = gzencode($content, 1);
                $this->putHeader(IHttpHeaders::Content_Encoding, 'gzip');
                $this->putHeader(IHttpHeaders::Content_Length, strlen($content));
            }
            if(!headers_sent())
            {
                $this->sendHeaders();
            }
            if($this->minified)
            {
                echo $this->minify($content);
            }
            else
            {
                echo $content;
            }
            $this->responseTime = microtime(true);
            $this->responseSent = true;
        }
    }

    private function minify($data) {
        return preg_replace('/^[[:blank:]]+/m', '', $data);
    }

    /**
     * @return string
     */
    public function getContentType(): string
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
    public function setMinified(bool $minified): void
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