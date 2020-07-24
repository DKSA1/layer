<?php

namespace rloris\layer\core\mvc\controller;

use rloris\layer\core\error\ELayer;

abstract class ApiErrorBaseController extends ApiBaseController
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