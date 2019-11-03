<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 10-11-18
 * Time: 13:51
 */

namespace layer\core;

use layer\core\config\Configuration;
use layer\core\manager\ModuleManager;
use layer\core\manager\PersistenceManager;
use layer\core\manager\ResourceManager;


class App
{
    /**
     * @var App $instance
     */
    private static $instance;
    /**
     * @var Router $router
     */
    private $router;


    public static function getInstance() : App
    {
        if(self::$instance == null) self::$instance = new App();
        return self::$instance;
    }

    private function __construct()
    {
        define("PATH",rtrim($_SERVER['SCRIPT_FILENAME'],"index.php"));

        Configuration::load();

        $this->router = Router::getInstance();

        $this->registerGlobals();

        $this->buildObjectRelationalMap();
        //process
        $this->router->handleRequest();
    }

    private function registerGlobals(){

        $appContext = Configuration::get("globals");
        foreach ($appContext as $c => $v){
            if(is_array($v)) $v = json_encode($v);
            define(strtoupper($c),$v);
        }
        define("APP_ROOT",rtrim($_SERVER["PHP_SELF"],"index.php"));
        define("APP_PUBLIC",APP_ROOT."public");
        define("APP_SERVICE",APP_ROOT."app/service");
        define("APP_CORE",APP_ROOT."app/core");
        define("APP_LIB",APP_ROOT."app/lib");
    }

    private function buildObjectRelationalMap() {
        $relationalMap = [];
        // controleurs
        $path = dirname(__DIR__)."\models";

        //check annotations on controller & action
        require_once PATH."app/core/persistence/lormAnnotations.php";

        $allFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $phpFiles = new \RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            require_once $phpFile;
            $reflectionClass = new \ReflectionAnnotatedClass("layer\models\\".(str_replace(".php", "", basename($phpFile))));
            if($reflectionClass->hasAnnotation('Entity')) {
                /**
                 * @var \Entity $entity
                 */
                $entity = $reflectionClass->getAnnotation('Entity');
                $relationalMap[$entity->unitName][$reflectionClass->getShortName()] = [
                    'NAME' => $entity->name ? $entity->name : $reflectionClass->getShortName(),
                    'NAMESPACE' => $reflectionClass->name,
                    'PATH' => trim($phpFile),
                    'PK' => $entity->primaryKey,
                    'UNIQUE' => $entity->unique,
                    'FK' => [],
                    'INDEX' => null,
                    'DROP_IF_EXITS' => $entity->dropIfExists,
                    'FIELDS' => []
                ];

                $t = & $relationalMap[$entity->unitName][$reflectionClass->getShortName()];

                $this->buildCompositeMap($reflectionClass->name, $t['FIELDS'], $t);
            }
        }

        $file = fopen("./app/core/config/relational_map.json", "w") or die("cannot write in relational_map.json file");
        $json_string = json_encode($relationalMap, JSON_PRETTY_PRINT);
        fwrite($file, $json_string);
        fclose($file);

        $this->buildSQLEntities($relationalMap);

    }

    private function buildCompositeMap($namespace, & $f, & $e) {
        $reflectionClass = new \ReflectionAnnotatedClass($namespace);

        foreach($reflectionClass->getProperties() as $property) {
            /**
             * @var \Field $field
             */
            $field = ($reflectionClass->getProperty($property->getName()))->getAnnotation('Field');
            if($field) {
                if($field->relationType) {
                    $e['FK'][$property->name] = [
                        'NAME' => $field->name ? $field->name : $property->name,
                        'REFERENCES' => basename($field->type),
                        'RELATION_TYPE' => $field->relationType,
                        'ON_DELETE' => $field->onDelete,
                        'ON_UPDATE' => $field->onUpdate
                    ];
                } else if($field->isComposite) {
                    $this->buildCompositeMap($field->type, $f[$property->name]['COMPOSITE_FIELDS'], $e);
                } else {
                    $f[$property->name] = [
                        'NAME' => $field->name ? $field->name : $property->name,
                        'TYPE' => $field->type,
                        'UNIQUE' => $field->unique,
                        'NULLABLE' => $field->nullable,
                        'DEFAULT' => $field->default,
                        'AUTO_INCREMENT' => $field->autoIncrement,
                        'INDEX' => null
                    ];
                }
            }
        }
    }

    private function buildSQLEntities($relationalMap) {
        $stmt = '';
        $fkStmt = '';
        foreach ($relationalMap as $db => $entities) {
            $stmt .= "DROP DATABASE `$db` ; ";
            $stmt .= "CREATE DATABASE IF NOT EXISTS `$db` ; USE `$db` ; ";
            foreach ($entities as $modelName => $entity) {
                $fieldStmt = '';
                $this->buildSQLCompositeEntities($entity['FIELDS'], $fieldStmt);
                $fieldStmt = trim($fieldStmt, ', ');
                $pkStmt = '';
                $this->buildSQLPrimaryKeys($entity, $pkStmt);
                $uqStmt = '';
                $this->buildSQLUniqueConstraints($entity, $uqStmt);
                $this->buildSQLFKConstraints($entity, $fkStmt, $entities);
                $stmt .= " CREATE TABLE IF NOT EXISTS ".$entity['NAME']." ( $fieldStmt $pkStmt $uqStmt ) ;";
            }
            $stmt .= $fkStmt;
        }

        try {
            $dbh = new \PDO("mysql:host=localhost", 'root', '');
            $dbh->exec($stmt) or die(print_r($dbh->errorInfo(), true));
        } catch (\PDOException $e) {
            die("DB ERROR: ". $e->getMessage());
        }
    }

    private function buildSQLCompositeEntities($composite, & $stmt) {
        foreach ($composite as $name => $field) {
            if(array_key_exists('COMPOSITE_FIELDS', $field)) {
                $this->buildSQLCompositeEntities($field['COMPOSITE_FIELDS'], $stmt);
            } else {
                $stmt .= $field['NAME']." ".$field['TYPE']." ".($field['AUTO_INCREMENT'] > -1 ? 'AUTO_INCREMENT' : '')." ".($field['NULLABLE']==true ? '' : 'NOT NULL' )." ".($field['UNIQUE']==true ? 'UNIQUE' : '')." ".($field['DEFAULT'] ? 'DEFAULT "'.$field['DEFAULT'].'"' : '').", ";
            }
        }
    }

    private function buildSQLPrimaryKeys($e, & $stmt) {
        if(count($e['PK'])>0) {
            $stmt .= ', CONSTRAINT PK_'.$e['NAME'].' PRIMARY KEY ('.implode(',', $e['PK']).')';
        }
    }

    private function buildSQLUniqueConstraints($e, & $stmt) {
        if(count($e['UNIQUE'])>0) {
            foreach ($e['UNIQUE'] as $unique) {
                $stmt .= ', CONSTRAINT UC_'.$e['NAME'].'_'.implode('_',$unique).' UNIQUE ('.implode(',', $unique).')';
            }
        }
    }

    private function buildSQLFKConstraints($e, & $stmt, & $entities) {
        if(count($e['FK'])>0) {
            foreach ($e['FK'] as $name => $fk) {
                $relationEntity = $entities[$fk['REFERENCES']];
                // check relation type
                switch ($fk['RELATION_TYPE']) {
                    case('N-O'):
                        // the relationEntity own the FK
                    case('O-N'):
                        // the current entity own the FK
                                $fkNames = [];

                                foreach ($relationEntity['PK'] as $primaryKeyName) {
                                    $pk = $relationEntity['FIELDS'][$primaryKeyName];
                                    $name = $fk['NAME'].'_'.$pk['NAME'];
                                    $stmt .= 'ALTER TABLE '.$e['NAME'].'
                                      ADD '.$name.' '.$pk['TYPE'].';';
                                    $fkNames[] = $name;
                                }

                                // alter table add fk
                                $stmt .= 'ALTER TABLE '.$e['NAME'].'
                                      ADD CONSTRAINT FK_'.$fk['NAME']."_".$relationEntity['NAME']."_".implode('_',$relationEntity['PK']).'
                                      FOREIGN KEY ('.implode(',',$fkNames).') REFERENCES '.$relationEntity['NAME'].'('.implode(',',$relationEntity['PK']).');';
                                break;
                    case('O-O'):
                        // both entities own the FK
                        break;
                    case('N-N'):
                        // middle table with FK to the other 2 tables
                                // create middle table
                                $middleTableFields = [];
                                $middleTablePks = [ 'NAME' => $fk['NAME'].'_'.$relationEntity['NAME'].'_'.$e['NAME'] ];

                                $primaryTablePKNames = [];
                                foreach ($e['PK'] as $pkName) {
                                     $tableField = $e['FIELDS'][$pkName];
                                     $tableField['NAME'] = $e['NAME'].'_'.$tableField['NAME'];
                                     $primaryTablePKNames[] = $tableField['NAME'];
                                     $tableField['UNIQUE'] = false;
                                     $tableField['DEFAULT'] = null;
                                     $tableField['AUTO_INCREMENT'] = -1;
                                     $middleTableFields[] = $tableField;
                                }

                                $fk1 = 'ALTER TABLE '.$middleTablePks['NAME'].'
                                              ADD CONSTRAINT FK_'.$middleTablePks['NAME']."_".implode('_',$e['PK']).'_1
                                              FOREIGN KEY ('.implode(',',$primaryTablePKNames).') REFERENCES '.$e['NAME'].'('.implode(',',$e['PK']).');';

                                $secondaryTablePKNames = [];
                                foreach ($relationEntity['PK'] as $pkName) {
                                    $tableField = $relationEntity['FIELDS'][$pkName];
                                    $tableField['NAME'] = $relationEntity['NAME'].'_'.$tableField['NAME'];
                                    $secondaryTablePKNames[] = $tableField['NAME'];
                                    $tableField['UNIQUE'] = false;
                                    $tableField['DEFAULT'] = null;
                                    $tableField['AUTO_INCREMENT'] = -1;
                                    $middleTableFields[] = $tableField;
                                }

                                $fk2 = 'ALTER TABLE '.$middleTablePks['NAME'].'
                                                      ADD CONSTRAINT FK_'.$middleTablePks['NAME']."_".implode('_',$relationEntity['PK']).'_2
                                                      FOREIGN KEY ('.implode(',',$secondaryTablePKNames).') REFERENCES '.$relationEntity['NAME'].'('.implode(',',$relationEntity['PK']).');';

                                $middleTablePks['PK'] = array_merge($primaryTablePKNames,$secondaryTablePKNames);

                                $tableStmt = '';
                                $this->buildSQLCompositeEntities($middleTableFields, $tableStmt);
                                $pkStmt = '';
                                $this->buildSQLPrimaryKeys($middleTablePks,$pkStmt);

                                $stmt .= " CREATE TABLE IF NOT EXISTS ".$middleTablePks['NAME']." ( ".trim($tableStmt,', ')." $pkStmt ) ;";

                                $stmt .= $fk1.$fk2;
                        break;
                }

            }
        }
    }

}