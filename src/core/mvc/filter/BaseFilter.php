<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 28-08-19
 * Time: 19:26
 */

namespace rloris\layer\core\mvc\filter;

use rloris\layer\core\mvc\controller\CoreController;

abstract class BaseFilter extends CoreController
{
    abstract public function in();
    abstract public function out();
}