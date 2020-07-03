<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 24-09-18
 * Time: 23:44
 */
namespace layer\core\config;

use layer\core\error\EConfiguration;
use layer\core\http\HttpHeaders;

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
    public static $environment;

    //Read the JSON config file
    static function load(string $config)
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
    }

    private static function validate() {
        self::get('layouts');
        self::get('environment/current');
        // TODO load full environment
        self::$environment = self::get("environment/current");
        self::get('environment/'.self::$environment);
        define("APP_ENV", self::$environment);
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