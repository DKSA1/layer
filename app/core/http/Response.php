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
    /***
     * @var int $response_code
     */
    private $response_code;
    /***
     * @var mixed $content
     */
    private $content;

    private $headers;

    private $data;

    private $responseTime;

    public function __construct()
    {
        $this->response_code = IHttpCodes::OK;
        $this->headers = [];
        $this->data = [];
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
        $this->response_code = $code;
    }

    public function getResponseCode()
    {
        return $this->response_code;
    }

    public function putData($key, $value) {
        $this->data[$key] = $value;
    }

    public function setDataArray($array) {
        $this->data = $array;
    }

    public function getData() {
        return $this->data;
    }

    public function setContent($content) {
        $this->content = $content;
    }

    public function getContent() {
        return $this->content;
    }

    public function getResponseTime()
    {
        return $this->responseTime;
    }

    public function sendResponse() {
        HttpHeaders::ResponseHeader($this->getResponseCode());
        header("X-Powered-By: Hello there");
        foreach ($this->getHeaders() as $h => $v) {
            header($h.":".$v, true, $this->getResponseCode());
        }
        echo $this->getContent();
        $this->responseTime = microtime(true);
    }

}