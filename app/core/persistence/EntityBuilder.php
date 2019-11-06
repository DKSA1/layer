<?php


namespace layer\core\persistence;


use layer\core\config\Configuration;
use layer\core\http\HttpHeaders;

class EntityBuilder
{
    /**
     * @var EntityBuilder $instance
     */
    private static $instance;

    private $relationalMap = [];
    private $oldRelationalMap = null;

    private $queries = [];

    /**
     * @var \PDO $pdo
     */
    private $pdo;

    private function __construct()
    {
        $this->loadRelationalMap();
        $this->buildObjectRelationalMap();
    }

    public static function getInstance() : EntityBuilder
    {
        if(self::$instance == null) self::$instance = new EntityBuilder();
        return self::$instance;
    }

    private function loadRelationalMap()
    {
        if(file_exists(PATH."app\core\config\\relational_map.json"))
        {
            if($data = file_get_contents(PATH."app\core\config\\relational_map.json"))
            {
                $this->oldRelationalMap = json_decode($data,true);
                return true;
            }
        } else
            return false;
    }

    private function buildObjectRelationalMap() {
        // controleurs
        $path = PATH."app/models";
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
                $this->relationalMap[$entity->unitName][$reflectionClass->getShortName()] = [
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

                $t = & $this->relationalMap[$entity->unitName][$reflectionClass->getShortName()];

                $this->buildCompositeMap($reflectionClass->name, $t['FIELDS'], $t);
            }
        }

        //$this->scanDatabase('unitname2');
        $success = false;

        if($this->oldRelationalMap == null) {
            $success = $this->buildSQLEntities();
        } else {
            $removed = $this->arrayRecursiveDiff($this->oldRelationalMap, $this->relationalMap);
            $added = $this->arrayRecursiveDiff($this->relationalMap, $this->oldRelationalMap);

            if(count($removed) || count($added)) {
                // differences detectes
                //echo 'removed : ';
                //var_dump($removed);
                //echo 'added : ';
                //var_dump($added);
                $success = $this->updateSQLEntities(array_merge($added, $removed));
                //$this->updateSQLEntities($r);
            }
        }

        echo $success.'<br>';

        if($success) {
            $file = fopen("./app/core/config/relational_map.json", "w") or die("cannot write in relational_map.json file");
            $json_string = json_encode($this->relationalMap, JSON_PRETTY_PRINT);
            fwrite($file, $json_string);
            fclose($file);
        }
    }

    private function addQueryToQueue($stmt,$param = []) {
        $this->queries[] = [
            'statement' => $stmt,
            'params' => $param
        ];
    }

    private function updateSQLEntities($u) {
        var_dump($this->relationalMap);
        foreach ($u as $dbName => $db) {
            if($this->connectSQLDatabase($dbName)) {
                foreach ($db as $eName => $entity) {
                    foreach ($entity as $key => $value) {
                        switch ($key) {
                            case('NAME'):
                                // rename table
                                $this->addQueryToQueue('ALTER TABLE '.$this->oldRelationalMap[$dbName][$eName]['NAME'].' RENAME TO '.$this->relationalMap[$dbName][$eName]['NAME'].' ;');
                                break;
                            case('PK'):
                                // TODO : drop PK in FK table
                                // remove and add PK
                                foreach ($this->oldRelationalMap[$dbName][$eName]['PK'] as $pk) {
                                    if($this->oldRelationalMap[$dbName][$eName]['FIELDS'][$pk]['AUTO_INCREMENT'] > -1) {
                                        // remove AI before
                                        $aiField = $this->oldRelationalMap[$dbName][$eName]['FIELDS'][$pk];
                                        $aiField['AUTO_INCREMENT'] = -1;
                                        $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' MODIFY '.$this->buildSQLColumn($aiField).';');
                                    }
                                }
                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' DROP PRIMARY KEY;');
                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' ADD CONSTRAINT PK_'.$this->relationalMap[$dbName][$eName]["NAME"].' PRIMARY KEY ('.implode(',',$this->relationalMap[$dbName][$eName]["PK"]).');');
                                // add auto_increment
                                foreach ($this->relationalMap[$dbName][$eName]['PK'] as $pk) {
                                    if($this->relationalMap[$dbName][$eName]['FIELDS'][$pk]['AUTO_INCREMENT'] > -1) {
                                        // remove AI before
                                        $aiField = $this->relationalMap[$dbName][$eName]['FIELDS'][$pk];
                                        $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' MODIFY '.$this->buildSQLColumn($aiField).' ;');
                                    }
                                }
                                break;
                            case('UNIQUE'):
                                // remove and add UNIQUE constraint
                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' DROP INDEX UC_'.$this->relationalMap[$dbName][$eName]["NAME"].'_'.implode("_",$this->oldRelationalMap[$dbName][$eName]['UNIQUE']).' ;');
                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' ADD CONSTRAINT UC_'.$this->relationalMap[$dbName][$eName]["NAME"].'_'.implode("_",$this->relationalMap[$dbName][$eName]['UNIQUE']).' UNIQUE ('.implode(',',$this->relationalMap[$dbName][$eName]["UNIQUE"]).'); ');
                                break;
                            case('FK'):
                                // update fk
                                break;
                            case('FIELDS'):
                                foreach ($value as $key => $field) {
                                    foreach ($field as $fieldName => $v) {
                                        switch ($fieldName) {
                                            case('NAME'):
                                                // rename column
                                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' CHANGE '.$this->oldRelationalMap[$dbName][$eName]["FIELDS"][$key]['NAME'].' '.$this->buildSQLColumn($this->relationalMap[$dbName][$eName]["FIELDS"][$key]).';');
                                                break;
                                            case('TYPE'):
                                                // change type column
                                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' MODIFY COLUMN '.$this->buildSQLColumn($this->relationalMap[$dbName][$eName]["FIELDS"][$key]).';');
                                                break;
                                            case('UNIQUE'):
                                                // change unique column
                                                if ($this->relationalMap[$dbName][$eName]["FIELDS"][$key]['UNIQUE'] == true) {
                                                    $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' ADD UNIQUE ('.$this->relationalMap[$dbName][$eName]["FIELDS"][$key]['NAME'].') ;');
                                                } else {
                                                    $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' DROP INDEX '.$this->relationalMap[$dbName][$eName]["FIELDS"][$key]['NAME'].' ;');
                                                }
                                                break;
                                            case('NULLABLE'):
                                                // change not null column
                                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' MODIFY '.$this->buildSQLColumn($this->relationalMap[$dbName][$eName]["FIELDS"][$key]).';');
                                                break;
                                            case('DEFAULT'):
                                                // change default column
                                                if($this->relationalMap[$dbName][$eName]["FIELDS"][$key]['DEFAULT'] == null) {
                                                    $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' ALTER '.$this->relationalMap[$dbName][$eName]["FIELDS"][$key]['NAME'].' DROP DEFAULT ;');
                                                } else {
                                                    $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' ALTER '.$this->relationalMap[$dbName][$eName]["FIELDS"][$key]['NAME'].' SET DEFAULT '.$this->relationalMap[$dbName][$eName]["FIELDS"][$key]['DEFAULT'].' ;');
                                                }
                                                break;
                                            case('AUTO_INCREMENT'):
                                                // change AI column
                                                $this->addQueryToQueue('ALTER TABLE '.$this->relationalMap[$dbName][$eName]["NAME"].' MODIFY '.$this->buildSQLColumn($this->relationalMap[$dbName][$eName]["FIELDS"][$key]).';');
                                                break;
                                            case('INDEX'):
                                                // change index column
                                                break;
                                        }
                                    }
                                }
                                break;
                        }
                    }
                }
            }
            var_dump($this->queries);
            if(!$this->executeQueuedQueries()) {
                return false;
            }
        }

        return true;
    }

    private function executeQueuedQueries() {
        $mx = true;
        $this->pdo->beginTransaction();
        try {
            foreach ($this->queries AS $query) {
                $stmt = $this->pdo->prepare($query["statement"]);
                foreach ($query["params"] as $idx => $value) {
                    $stmt->bindValue(($idx+1), $value, \PDO::PARAM_STR);
                }
                $result = $stmt->execute();
                //$result = $stmt->rowCount();
                if($result == 0) {
                    $mx = false;
                    break;
                }
            }
            if($mx == true)
                $this->pdo->commit();
            else
                $this->pdo->rollBack();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $mx = false;
            echo "Failed: " . $e->getMessage();
        }
        return $mx;
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

    private function buildSQLEntities() {
        foreach ($this->relationalMap as $db => $entities) {
            if($this->buildSQLDatabase($db)) {
                if($this->connectSQLDatabase($db)) {
                    foreach ($entities as $modelName => $entity) {
                        $fieldStmt = '';
                        $this->buildSQLCompositeEntities($entity['FIELDS'], $fieldStmt);
                        $pkStmt = '';
                        $this->buildSQLPrimaryKeys($entity, $pkStmt);
                        $uqStmt = '';
                        $this->buildSQLUniqueConstraints($entity, $uqStmt);
                        $this->buildSQLTable($entity, $fieldStmt.' '.$pkStmt.' '.$uqStmt);
                        $this->buildSQLFKConstraints($entity, $entities);
                    }
                }
            }
            //$stmt .= $fkStmt;
        }

        var_dump($this->queries);
        return $this->executeQueuedQueries();
    }


    private function buildSQLDatabase($db) {
        $pdo = null;
        try {
            $pdo = new \PDO("mysql:host=localhost","root","");
            return $pdo->exec("CREATE DATABASE IF NOT EXISTS $db ;");
            // TODO : check if drop and receate enabled
            //$stmt .= "DROP DATABASE `$db` ; ";
        } catch(\Exception $e) {
            // error host
            unset($pdo);
            return false;
        }
    }

    private function connectSQLDatabase($dbName) {
        try {
            //$options = array(
            //For updates where newvalue = oldvalue PDOStatement::rowCount()   returns zero. You can use this:
            //  \PDO::MYSQL_ATTR_FOUND_ROWS => true
            //);
            $this->pdo = new \PDO("mysql:dbname=$dbName;host=localhost", 'root', ''); //$options
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return true;
        } catch (\PDOException $e) {
            echo "Error connecting to SQL Server: " . $e->getMessage();
            return false;
        }
    }

    private function buildSQLCompositeEntities($composite, & $stmt) {
        foreach ($composite as $name => $field) {
            if(array_key_exists('COMPOSITE_FIELDS', $field)) {
                $this->buildSQLCompositeEntities($field['COMPOSITE_FIELDS'], $stmt);
            } else {
                $stmt .= $this->buildSQLColumn($field).", ";
            }
        }
        trim($stmt, ', ');
    }

    private function buildSQLColumn($c): string {
        return " `".$c['NAME']."` ".$c['TYPE'].($c['AUTO_INCREMENT'] > -1 ? ' AUTO_INCREMENT' : '').($c['NULLABLE']==true ? '' : ' NOT NULL' ).($c['UNIQUE']==true ? ' UNIQUE' : '').($c['DEFAULT'] ? ' DEFAULT "'.$c['DEFAULT'].'"' : '');
    }

    private function buildSQLPrimaryKeys($e, & $stmt) {
        if(count($e['PK'])>0) {
            $stmt .= ' CONSTRAINT PK_'.$e['NAME'].' PRIMARY KEY (';
            if(array_key_exists('FIELDS', $e)) {
                $stmt .= $this->implodePrimaryKeys($e);
            }else {
                $stmt .= implode(',', $e['PK']);
            }
            $stmt .= ')';
        }
    }

    private function implodePrimaryKeys($e) {
        $stmt = '';
        foreach ($e['PK'] as $key => $pk) {
            $stmt .= $e['FIELDS'][$pk]['NAME'] ;
            if(count($e['PK'])-1 != $key) {
                $stmt .= ',';
            }
        }
        return $stmt;
    }

    private function buildSQLUniqueConstraints($e, & $stmt) {
        if(count($e['UNIQUE'])>0) {
            foreach ($e['UNIQUE'] as $unique) {
                $stmt .= ', CONSTRAINT UC_'.$e['NAME'].'_'.implode('_',$unique).' UNIQUE ('.implode(',', $unique).')';
            }
        }
    }

    private function buildSQLTable($e, $fields) {
        $this->addQueryToQueue(" CREATE TABLE IF NOT EXISTS ".$e['NAME']." ( $fields ) ;");
    }

    private function buildSQLFKConstraints($e, & $entities) {
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
                            $this->addQueryToQueue('ALTER TABLE '.$e['NAME'].' ADD `'.$name.'` '.$pk['TYPE'].' ;');
                            $fkNames[] = $name;
                        }

                        // alter table add fk
                        $this->addQueryToQueue("ALTER TABLE ".$e['NAME']." ADD CONSTRAINT FK_".$fk['NAME']."_".$relationEntity['NAME']."_".implode('_',$relationEntity['PK'])." FOREIGN KEY (".implode(',',$fkNames).") REFERENCES ".$relationEntity['NAME']." (".implode(',',$relationEntity['PK']).") ;");

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
                                              FOREIGN KEY ('.implode(',',$primaryTablePKNames).') REFERENCES '.$e['NAME'].'('.$this->implodePrimaryKeys($e).');';

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
                                                      FOREIGN KEY ('.implode(',',$secondaryTablePKNames).') REFERENCES '.$relationEntity['NAME'].'('.$this->implodePrimaryKeys($relationEntity).');';

                        $middleTablePks['PK'] = array_merge($primaryTablePKNames,$secondaryTablePKNames);

                        $tableStmt = '';
                        $this->buildSQLCompositeEntities($middleTableFields, $tableStmt);
                        $pkStmt = '';
                        $this->buildSQLPrimaryKeys($middleTablePks,$pkStmt);

                        $this->addQueryToQueue("CREATE TABLE IF NOT EXISTS ".$middleTablePks['NAME']." ($tableStmt $pkStmt) ;");
                        $this->addQueryToQueue($fk1);
                        $this->addQueryToQueue($fk2);

                        break;
                }

            }
        }
    }

    private function arrayRecursiveDiff($aArray1, $aArray2) {
        $aReturn = array();

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }

        return $aReturn;
    }

    private function scanDatabase($db) {
        $dbMap = [];
        $stmt = " SHOW TABLES ;";
        try {
            $dbh = new \PDO("mysql:dbname=$db;host=localhost", 'root', '');
            $p = $dbh->prepare($stmt);
            $p->execute();
            $r = $p->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($r as $idx => $tableName) {
                $p = $dbh->prepare('DESCRIBE '.$tableName);
                $p->execute();
                $description = $p->fetchAll(\PDO::FETCH_ASSOC);
                $dbMap[$tableName] = $description;
            }
            var_dump($dbMap);
        } catch (\PDOException $e) {
            var_dump($e->getMessage());
            return false;
        }
    }
}