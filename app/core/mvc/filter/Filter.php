<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 28-08-19
 * Time: 19:26
 */

namespace layer\core\mvc\filter;

use layer\core\exception\ForwardException;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;

abstract class Filter
{
    /**
     * @var Request $request
     */
    protected $request;
    /**
     * @var Response $response
     */
    protected $response;

    /**
     * @param $internalUrl
     * @param int $httpCode
     * @throws ForwardException
     */
    protected final function forward($internalUrl, $httpCode = HttpHeaders::MovedTemporarily)
    {
        throw new ForwardException($httpCode, $internalUrl);
    }

    abstract public function enter();
    abstract public function leave();
}