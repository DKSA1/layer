<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 01-09-19
 * Time: 19:35
 */

namespace layer\core\mvc\controller;

abstract class ErrorController extends Controller
{
    /**
     * @var \Exception $error
     */
    protected $error;
}