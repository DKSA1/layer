<?php


namespace layer\core\mvc\controller;


use layer\core\error\ELayer;

class ApiErrorController extends ApiController
{
    /**
     * @var ELayer $error
     */
    protected $error;

    public function __construct(ELayer $e)
    {
        parent::__construct();
        $this->error = $e;
    }
}