<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 28-08-19
 * Time: 19:26
 */

namespace layer\core\mvc\filter;

use layer\core\mvc\controller\CoreController;

abstract class Filter extends CoreController
{
    abstract public function beforeAction();
    abstract public function afterAction();
}