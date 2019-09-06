<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 15:41
 */
namespace layer\core\mvc\controller;

use layer\core\config\Configuration;
use layer\core\http\HttpHeaders;
use layer\core\http\IHttpHeaders;
use layer\core\http\Request;
use layer\core\http\Response;
use layer\core\mvc\model\ViewModel;
use layer\core\Router;
use Exception;

abstract class Controller {

    //request
    /**
     * @var Request $request
     */
    protected $request;


    /**
     * @var Response $response
     */
    protected $response;

    /**
     * Controller constructor.
     */
    public function __construct()
    {

    }

    /*
     Méthode abstraite correspondant à l'action par défaut
     Oblige les classes dérivées à implémenter cette action par défaut
    */
    public abstract function index();

    // Génère la vue associée au contrôleur courant
    protected final function genererateView($donneesVue = array(), $usePartials = true){
        // Détermination du nom du fichier vue à partir du nom du contrôleur actuel
        $classeControleur = get_class($this);
        $classeControleur = explode("\\",$classeControleur);
        $classeControleur = $classeControleur[count($classeControleur)-1];
        $classeControleur = str_replace("Controller","",$classeControleur);
        //var_dump($_SERVER);
        // instanciation de la vue
        $vue = new ViewModel(trim(str_replace("Controller","",$classeControleur)),$this->action);

        if($usePartials == true){
            //toutes les vues
            $arrBefore = Configuration::get("defaults/partial/action/before");
            $vue->addIntroViews($arrBefore);
            $arrAfter = Configuration::get("defaults/partial/action/after");
            $vue->addFinalViews($arrAfter);

        }else if(is_array($usePartials) && array_key_exists("before",$usePartials) && array_key_exists("after",$usePartials) ){
            //vues spécifiques
            $vue->addIntroViews($usePartials["before"]);
            $vue->addFinalViews($usePartials["after"]);
        }

        //title page
        if(!defined("APP_VIEW_TITLE")) define("APP_VIEW_TITLE",$classeControleur);
        // lancement de la vue
        return $vue->generer($donneesVue);
    }

    /**
     * @param string $controller
     * @param string $action
     */
    // TODO : move permanently 301
    protected final function forward($controller, $action){
        //check if controller was already instanciated
        $c = Router::getInstance()->getInstancedController($controller);
        if(!$controller){
            $path = APP_SERVICE . basename($controller) . ".php";
            if(file_exists($path)){
                $controller = new \ReflectionClass($controller);
                $c = $controller->newInstance();
            }else throw new Exception("Redirection interne échouée",HttpHeaders::InternalServerError);
        }
    }

    protected final function redirect($url,$timeout = 0) {
        //header('Location: http://www.google.com/');
        header( "refresh:".$timeout.";url=".$url);
    }

}