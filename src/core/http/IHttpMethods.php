<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 17-12-18
 * Time: 16:23
 */

namespace rloris\layer\core\http;


interface IHttpMethods
{
    const CONNECT = "CONNECT";
    const DELETE = "DELETE";
    const GET = "GET";
    const HEAD = "HEAD";
    const OPTIONS = "OPTIONS";
    const PATCH = "PATCH";
    const POST = "POST";
    const PUT = "PUT";
    const TRACE = "TRACE";

    const ALL = [self::CONNECT, self::DELETE, self::GET, self::HEAD, self::OPTIONS, self::PATCH, self::POST, self::PUT, self::TRACE];
}