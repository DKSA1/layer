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

    // Action à réaliser
    protected $action = "index";

    // Parametres Requête entrante
    /**
     * @var array
     */
    protected $params;

    // Parametres GET|POST
    protected $data;

    //methode utilisée GET ou POST
    protected $method;

    //api call
    /**
     * @var bool
     */
    protected $isApiCall = false;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
    }

    // Action à réaliser.
    public final function executeAction(){

        $requestedAction = $this->action;
        if($this->actionExists($requestedAction)){
            $this->$requestedAction();
        } else throw new Exception("Aucune action ne correspond à votre requète !",HttpHeaders::NotFound);
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
        $this->response->setContent($vue->generer($donneesVue));
        foreach ($this->response->getHeaders() as $h => $v) {
            header($h.":".$v, true, $this->response->getResponseCode());
        }
        echo $this->response->getContent();
    }

    /**
     * @param string $controller
     * @param string $action
     */
    // TODO : move permanently 301
    protected final function forward($controller,$action){
        //check if controller was already instanciated
        $c = Router::getInstance()->getInstancedController($controller);
        if(!$controller){
            $path = PATH."app\service\\" . basename($controller) . ".php";
            if(file_exists($path)){
                $controller = new \ReflectionClass($controller);
                $c = $controller->newInstance();
            }else throw new Exception("Redirection interne échouée",HttpHeaders::InternalServerError);
        }
        $c->setAction($action);
        $c->setMethod($this->method);
        $c->setParams($this->params);
        $c->setData($this->data);
        $c->setIsApiCall($this->isApiCall);
        $c->executeAction();
    }

    protected final function redirect($url,$timeout = 0) {
        //header('Location: http://www.google.com/');
        header( "refresh:".$timeout.";url=".$url);
    }

    /**
     * @param string $action
     * @return bool
     */
    public final function actionExists($action){

        $c = new \ReflectionClass($this);

        if($c->hasMethod($action)) return true;
        else return false;
    }

    // methode demandée
    public function setMethod($method){
        $this->method = $method;
    }

    // Mémorisation des param requête entrante
    public function setParams($requete){
        $this->params = $requete;
    }

    // action a effectuer
    public function setAction($action)
    {
        $this->action = $action;
    }

    // parametres GET|POST
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return bool
     */
    public function isApiCall(): bool
    {
        return $this->isApiCall;
    }

    /**
     * @param bool $isApiCall
     */
    public function setIsApiCall(bool $isApiCall)
    {
        $this->isApiCall = $isApiCall;
    }
}