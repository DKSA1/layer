<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:44
 */
namespace layer\core\config;

use Exception;
use layer\core\error\EConfiguration;
use layer\core\http\HttpHeaders;

abstract class Configuration
{
    const ConfigurationFile = "configuration.json";
    private static $configuration;
    /**
     * @var bool
     */
    private static $loaded = false;
    // TODO : replace by GLOBAL
    public static $environment;

    //Read the JSON config file
    static function load()
    {
        if(!self::$loaded && file_exists(PATH."app\core\config\\".Configuration::ConfigurationFile))
        {
            if($data = file_get_contents(PATH."app\core\config\\".Configuration::ConfigurationFile))
            {
                self::$configuration = json_decode($data,true);
                self::validate();
                self::$loaded = true;
            }
        }
    }

    private static function validate() {
        self::get('layouts');
        self::get('environment/current');
        self::$environment = self::get("environment/current");
        define("APP_ENV", self::$environment);
        self::get('environment/'.self::$environment);
        self::get('locations');
    }

    static function get($keys = "", $throwException = true)
    {
        $subArr = self::$configuration;
        if(self::$configuration != null)
        {
            $arrKey = explode("/",trim($keys,"/"));
            foreach ($arrKey as $k)
            {
                if(array_key_exists($k,$subArr))
                {
                    $subArr = $subArr[$k];
                }
                else
                {
                    if ($throwException)
                    {
                        throw new EConfiguration("Key [$keys] missing in configuration file", HttpHeaders::InternalServerError);
                    }
                    else
                    {
                        return null;
                    }
                }
            }
        }
        return $subArr;
    }

}