<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 15:41
 */
namespace layer\core\mvc\controller;

use layer\core\config\Configuration;
use layer\core\exception\ForwardException;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\model\ViewModel;
use layer\core\Router;
use Exception;
use layer\core\utils\Logger;

abstract class Controller extends CoreController {

    /**
     * Controller constructor.
     */
    public function __construct()
    {

    }

    public abstract function index();
}