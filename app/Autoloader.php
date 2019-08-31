<?php 

namespace layer;

Class Autoloader {
    
    static function register(){

        spl_autoload_register(array(__CLASS__,'autoload'));
        
    }
    
    static function autoload($classname){

        if(strpos($classname,__NAMESPACE__) == 0){
            $classname = str_replace(__NAMESPACE__,'',$classname);
            $classname.='.php';
            $required = ltrim(str_replace('\\','/',$classname),"/");
            require_once $required;
        }    
    }
}

Autoloader::register();


?>