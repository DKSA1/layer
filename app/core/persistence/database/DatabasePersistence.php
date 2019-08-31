<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 13-12-18
 * Time: 19:24
 */

namespace layer\core\persistence\database;

use layer\core\persistence\IPersistence;
use layer\core\persistence\Persistence;
use \PDO;
use \PDOException;

class DatabasePersistence extends Persistence
{
    public $type = IPersistence::DATABASE;

    private $sgbd;
    private $host;
    private $user;
    private $pass;
    private $schema;

    /**
     * @var PDO $pdo
     */
    private $pdo;

    public $last_transaction_success = false;

    /**
     * MysqlPersistence constructor.
     * @param array $options
     */
    public function __construct($options)
    {
        array_key_exists("sgbd",$options) ? $this->sgbd=$options['sgbd'] : $this->sgbd="mysql";
        array_key_exists("host",$options) ? $this->host=$options['host'] : $this->host="localhost";
        array_key_exists("user",$options) ? $this->user=$options['user'] : $this->host="root";
        array_key_exists("pass",$options) ? $this->pass=$options['pass'] : $this->pass="";
        array_key_exists("schema",$options) ? $this->schema=$options['schema'] : $this->schema="public";

    }

    protected function open()
    {
        $this->pdo = new PDO("$this->sgbd:dbname=$this->schema;host=$this->host",$this->user,$this->pass);
        $this->pdo->beginTransaction();
    }

    function create($params)
    {
        $status = false;
        try{
            $this->open();
            $stmt = $this->pdo->prepare($params[IPersistence::DATABASE_STATEMENT]);
            foreach ($params[IPersistence::DATABASE_ATTRIBUTE] as $param => $value){
                $dt = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$param",$value,$dt);
            }
            $status = $stmt->execute();
            $stmt->closeCursor();
            $this->close();
        }catch(PDOException $e){
            if($this->pdo!=null) $this->pdo->rollBack();
        }

        return $status;
    }

    function delete($params)
    {
        $result = 0;
        try{
            $this->open();
            $stmt = $this->pdo->prepare($params[IPersistence::DATABASE_STATEMENT]);
            foreach ($params[IPersistence::DATABASE_ATTRIBUTE] as $param => $value){
                $dt = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$param",$value,$dt);
            }
            $stmt->execute();
            $result = $stmt->rowCount();
            $stmt->closeCursor();
            $this->close();
        }catch(PDOException $e){
            if($this->pdo!=null) $this->pdo->rollBack();
        }

        return $result;
    }

    function update($params)
    {
        $result = 0;
        try{
            $this->open();
            $stmt = $this->pdo->prepare($params[IPersistence::DATABASE_STATEMENT]);
            foreach ($params[IPersistence::DATABASE_ATTRIBUTE] as $param => $value){
                $dt = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$param",$value,$dt);
            }
            $stmt->execute();
            $result = $stmt->rowCount();
            $stmt->closeCursor();
            $this->close();
        }catch(PDOException $e){
            if($this->pdo!=null) $this->pdo->rollBack();
        }

        return $result;
    }

    function read($params)
    {
        $result = null;
        try{
            $this->open();
            $stmt = $this->pdo->prepare($params[IPersistence::DATABASE_STATEMENT]);
            foreach ($params[IPersistence::DATABASE_ATTRIBUTE] as $param => $value){
                $dt = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue(":$param",$value,$dt);
            }
            $stmt->execute();
            if($params[IPersistence::DATABASE_FETCH_MODE]==IPersistence::FETCH_MODE_OBJECT)
                $result = $stmt->fetchObject($params[IPersistence::DATABASE_FETCH_CLASS]);
            else
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $this->close();
        }catch(PDOException $e){
            if($this->pdo!=null) $this->pdo->rollBack();
        }

        return $result;
    }

    protected function close()
    {
        $this->last_transaction_success = $this->pdo->commit();
        $this->pdo = null;
    }
}