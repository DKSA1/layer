<?php
/**
 * Created by IntelliJ IDEA.
 * User: Home
 * Date: 16-12-18
 * Time: 13:40
 */

namespace layer\core\persistence\database\ql\delete;


class Delete implements IDelete1, IDelete2
{

    function from(): IDelete2
    {
        return $this;
    }

    function where()
    {
        return $this;
    }
}