<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 14-12-18
 * Time: 19:26
 */

namespace layer\core\persistence\database\ql;

use layer\core\persistence\database\ql\create\Create;
use layer\core\persistence\database\ql\create\ICreate1;
use layer\core\persistence\database\ql\delete\Delete;
use layer\core\persistence\database\ql\delete\IDelete1;
use layer\core\persistence\database\ql\select\ISelect1;
use layer\core\persistence\database\ql\select\Select;
use layer\core\persistence\database\ql\update\IUpdate1;
use layer\core\persistence\database\ql\update\Update;

class QBuilder
{
    public function __construct()
    {
        $this->sql = "";
    }

    /**
     * @return ISelect1
     */
    public function select() : ISelect1
    {
        return new Select();
    }

    public function selectDistinct() : ISelect1
    {
        return $this->select();
    }

    public function update() : IUpdate1
    {
        return new Update();
    }

    public function delete() : IDelete1{
        return new Delete();
    }

    public function create() : ICreate1
    {
        return new Create();
    }

    public function drop()
    {

    }

    //TODO : alter table

}

