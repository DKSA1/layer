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

    public function __construct()
    {
        $this->response_code = IHttpCodes::OK;
        $this->headers = [];
        $this->data = [];
    }


    public function putHeader(IHttpHeaders $key, $value)
    {
        $headers[$key] = $value;
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

    public function setData($key, $value) {
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

}