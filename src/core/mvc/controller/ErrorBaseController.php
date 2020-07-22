<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 01-09-19
 * Time: 19:35
 */

namespace rloris\layer\core\mvc\controller;

use rloris\layer\core\error\ELayer;

abstract class ErrorBaseController extends BaseController
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