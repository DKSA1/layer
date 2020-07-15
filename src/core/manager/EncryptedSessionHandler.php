<?php

namespace rloris\layer\core\manager;

use rloris\layer\utils\Security;

class EncryptedSessionHandler extends \SessionHandler
{

    /**
     * @var EncryptedSessionHandler
     */
    private static $instance;
    /**
     * @var string
     */
    private $secret;

    public static function getInstance(string $secret) : EncryptedSessionHandler
    {
        if(self::$instance == null) self::$instance = new EncryptedSessionHandler($secret);
        return self::$instance;
    }

    private function __construct(string $secret)
    {

    }
    public function read($id)
    {
        $data = parent::read($id);

        if (!$data) {
            return "";
        } else {
            return Security::decrypt($data, $this->secret);
        }
    }

    public function write($id, $data)
    {
        $data = Security::encrypt($data, $this->secret);

        return parent::write($id, $data);
    }

}