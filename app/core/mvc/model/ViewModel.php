<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 15:42
 */
namespace layer\core\mvc\model;

use layer\core\http\HttpHeaders;
use \Exception;

class ViewModel {
    private $intro = [];
    // Nom du fichier associé à la vue
    private $main;
    private $final = [];

    // Constructeur avec contenu principal
    public function __construct($viewDir,$viewFile = "index")
    {
        $servicePath = PATH."app/service/";
        $this->main = $servicePath.$viewDir."/view/".$viewFile.".php" ;
    }

    // affichage du contenu
    public function generer($donnees)
    {
        // Génération de la partie spécifique de la vue
        $vue = $this->genererFichier($donnees);
        // il est possible de répéter la temporisation ...

        // Renvoi de la vue générée au navigateur
        return $vue;
    }

    // temporisation
    private function genererFichier($donnees)
    {
        if (file_exists($this->main)) {
            // Rend les éléments du tableau $donnees accessibles dans la vue
            extract($donnees);
            // Démarrage de la temporisation de sortie
            ob_start();
            // Inclut le fichier vue
            // Son résultat est placé dans le tampon de sortie
            //adding intro partial views
            if(count($this->intro)>0){
                foreach ($this->intro as $i){
                    require_once (PATH . "app/service/#shared/view/" . $i . ".php");
                }
            }
            //main part
            require_once $this->main;
            //adding final partial views
            if(count($this->final)>0){
                foreach ($this->final as $f){
                    require_once (PATH . "app/service/#shared/view/" . $f . ".php");
                }
            }

            // Arrêt de la temporisation et renvoi du tampon de sortie
            return ob_get_clean();
        }
        else {
            throw new Exception("La ressource demandée est introuvable ! ", HttpHeaders::NotImplemented);
        }
    }

    //adding introduction views
    public function addIntroViews($partials = array())
    {
        foreach ($partials as $partial) {
            if (file_exists(PATH . "app/service/#shared/view/" . $partial . ".php")) {
                $this->intro[] = $partial;
            }
        }

    }

    //adding final views
    public function addFinalViews($partials = array())
    {
        foreach ($partials as $partial) {
            if (file_exists(PATH . "app/service/#shared/view/" . $partial . ".php")) {
                $this->final[] = $partial;
            }
        }
    }

}