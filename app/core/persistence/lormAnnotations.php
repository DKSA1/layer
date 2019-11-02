<?php

require_once(PATH . 'app/lib/addendum/annotations.php');

// Table
/** @Target("class") */
class Entity extends Annotation {

    public $name;

    public $unitName;

    public $dropIfExists = false;

    public $primaryKey = [];

    public $unique = [];

}

// Field
/** @Target("property") */
class Field extends Annotation {

    public $name;

    public $type;

    public $autoIncrement = -1;

    public $nullable = false;

    public $unique = false;

    public $default;

    public $isComposite = false;

    public $relationType;

    public $onUpdate;

    public $onDelete;

    // index

    // check
}

?>