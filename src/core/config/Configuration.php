<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:44
 */
namespace rloris\layer\core\config;

use rloris\layer\core\error\EConfiguration;
use rloris\layer\core\http\HttpHeaders;

abstract class Configuration
{
    /**
     * @var array
     */
    private static $configuration;
    /**
     * @var bool
     */
    private static $loaded = false;

    //Read the JSON config file
    static function load(string $config)
    {
        if(file_exists($config))
        {
            if(!self::$loaded)
            {
                if($data = file_get_contents($config))
                {
                    self::$configuration = json_decode($data,true);
                    self::validate();
                    self::$loaded = true;
                }
            }
        } else
            echo "An error occured during the configuration process: File {$config} not found\n";

    }

    private static function validate() {
        try
        {
            // layouts
            self::get('layouts');
            // locations
            self::get('locations');
            self::get('locations/controllers');
            self::get('locations/shared');
            self::get('locations/build');
            // env
            $currentEnv = self::get('environment/current');
            self::get('environment/'.$currentEnv);
            self::get('environment/'.$currentEnv.'/routeTemplate');
            self::get('environment/'.$currentEnv.'/apiRouteTemplate');
            self::get('environment/'.$currentEnv.'/build');
            define("APP_ENV", $currentEnv);
        }
        catch(EConfiguration $e)
        {
            echo "An error occurred during the configuration loading process: {$e->getMessage()}\n";
        }
    }

    static function environment($keys, $throwException = false)
    {
        return self::get('environment/'.APP_ENV.'/'.trim($keys, '/'), $throwException);
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