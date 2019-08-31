<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 14-12-18
 * Time: 20:01
 */

namespace layer\core\persistence\database\ql;


abstract class AbstractQBuilder
{
    /**
     * @var string
     */
    protected $sql;

    //from

    protected function from(){

    }

    //groupby

    protected function groupBy(){

    }

    //having

    protected function having(){

    }

    //join

    protected function innerJoin(){

    }

    protected function leftJoin(){

    }

    protected function rightJoin(){

    }

    protected function join(){

    }

    //limit

    protected function limit(){

    }

    //offset

    protected function offset(){

    }

    //orderBy

    protected function orderBy(){

    }

    //where

    protected function where(){

    }

}