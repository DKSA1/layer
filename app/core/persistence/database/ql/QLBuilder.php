<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 14-12-18
 * Time: 00:26
 */

namespace layer\core\persistence\database\ql;

class QLBuilder
{
    public static function createSelectQuery(){

        $qb = new QBuilder();

        $sql = "SELECT :distinct :fields FROM :table :joins :where :groupby :having :orderby :limit :offset ";

        $sql = str_replace(":fields","username,password,account",$sql);
        $sql = str_replace(":table","users",$sql);
        $sql = str_replace(":where","WHERE username=loris AND password=123",$sql);
        return $sql;
    }

    public static function createUpdateQuery(){
        $sql = "UPDATE ";
    }

    public static function createDeleteQuery(){
        $sql = "DELETE ";
    }

    public static function createInsertQuery(){
        $sql = "INSERT ";
    }

    public static function createDatabaseQuery(){
        $sql = "CREATE DATABASE :db ";
    }

    public static function createTableQuery(){
        $sql = "CREATE TABLE :table :columns";
    }

    public static function createIndexQuery(){
        $sql = "CREATE :unique INDEX :name ON :table :columns";
    }

    public static function createDropIndexQuery(){
        $sql = "ALTER TABLE :table DROP INDEX :name";
    }

    public static function createAlterTableQuery(){
        $sql = "ALTER TABLE :table :add :drop :modify";
    }

    public static function createDropTableQuery(){
        $sql = "DROP TABLE :table";
    }

    public static function createDropDatabaseQuery(){
        $sql = "DROP DATABASE :db ";
    }
}