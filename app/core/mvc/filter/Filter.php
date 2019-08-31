<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 28-08-19
 * Time: 19:26
 */

namespace layer\core\mvc\filter;

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

    abstract public function input();
    abstract public function output();
}