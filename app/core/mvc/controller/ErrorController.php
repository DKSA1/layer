<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 01-09-19
 * Time: 19:35
 */

namespace layer\core\mvc\controller;

use layer\core\error\ELayer;

abstract class ErrorController extends Controller
{
    /**
     * @var ELayer $error
     */
    protected $error;

    public function __construct(ELayer $e)
    {
       $this->error = $e;
    }
}