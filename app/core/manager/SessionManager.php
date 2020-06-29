<?php


namespace layer\core\manager;


use layer\core\config\Configuration;

class SessionManager
{
    private $customSessionHandler;

    /**
     * @var SessionManager
     */
    private static $instance;

    public static function getInstance() : SessionManager
    {
        if(self::$instance == null) self::$instance = new SessionManager();
        return self::$instance;
    }

    private function __construct(){}

    public function start() {
        if(session_status() === PHP_SESSION_NONE) {
            if(Configuration::get("environment/".Configuration::$environment."/encryptedSession")) {
                $key = Configuration::get("environment/".Configuration::$environment."/encryptedKey");
                if($key && (ini_set('session.save_handler', 'files') !== false)) {
                    $this->customSessionHandler = EncryptedSessionHandler::getInstance($key);
                    session_set_save_handler($this->customSessionHandler, true);
                }
            }
            session_start();
        }
    }

    public function save(string $key, $value) {
        if($this->isActive()) {
            $_SESSION[$key] = $value;
            return true;
        }
        return false;
    }

    public function get(string $key, $default = NULL) {
        if($this->isActive() && isset($_SESSION[$key]))
            return $_SESSION[$key];
        return $default;
    }

    public function has(string $key) {
        if($this->isActive() && isset($_SESSION[$key]))
            return true;
        return false;
    }

    public function delete(string $key) {
        if($this->isActive() && isset($_SESSION[$key]))
            unset($_SESSION[$key]);
    }

    public function destroy() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function isActive(): bool {
        return (session_status() === PHP_SESSION_ACTIVE);
    }

    public function isEncrypted(): bool {
        return ($this->customSessionHandler !== null);
    }
}