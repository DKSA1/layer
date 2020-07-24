<?php

namespace rloris\layer\core\mvc\filter;

use rloris\layer\core\mvc\controller\CoreController;

abstract class BaseFilter extends CoreController
{
    abstract public function in();
    abstract public function out();
}