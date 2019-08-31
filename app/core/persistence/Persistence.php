<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 13-12-18
 * Time: 21:04
 */

namespace layer\core\persistence;

//abstract class
abstract class Persistence implements IPersistence
{
    //open resource
    protected abstract function open();
    //close resource
    protected abstract function close();
}