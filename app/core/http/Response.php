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

    //IHttpCodes
    /***
     * @var int $response_code
     */
    private $response_code;
    /***
     * @var mixed $content
     */
    private $content;

    private $headers;

    public function __construct()
    {
        $this->response_code = IHttpCodes::OK;
        $this->headers = [];
    }


    public function putHeader(IHttpHeaders $key, $value)
    {
        $headers[$key] = $value;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getResponseCode()
    {
        return $this->response_code;
    }

    public function getContent()
    {
        return $this->content;
    }

}