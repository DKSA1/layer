<?php


namespace layer\core\utils;


class DocTypeInfo
{
    /**
     * @var string
     */
    public $type;
    /**
     * @var bool
     */
    public $isArray;
    /**
     * @var bool
     */
    public $isInternal;
    /**
     * @var string
     */
    public $namespace;

    public function __construct($type, $isArray, $isInternal, $namespace = null)
    {
        $this->isArray = $isArray;
        $this->type = $type;
        $this->isInternal = $isInternal;
        $this->namespace = $namespace;
    }

    public static function getDocType(string $string)
    {
        $isArray = false;
        if(preg_match("/\/?([A-Za-z0-9_\\\])\\[]/", $string))
        {
            $isArray = true;
            $string = str_replace('[]','', $string);
        }
        switch (strtolower($string))
        {
            case("integer"):
            case("int"):
                return new DocTypeInfo("int", $isArray, true);
                break;
            case("boolean"):
            case("bool"):
                return new DocTypeInfo("bool", $isArray, true);
                break;
            case("string"):
                return new DocTypeInfo("string", $isArray, true);
                break;
            case("float"):
                return new DocTypeInfo("float", $isArray, true);
                break;
            case("double"):
                return new DocTypeInfo("double", $isArray, true);
                break;
            case("array"):
                return new DocTypeInfo("array", $isArray, true);
                break;
            case("null"):
                return new DocTypeInfo("null", $isArray, true);
                break;
            case("numeric"):
                return new DocTypeInfo("numeric", $isArray, true);
                break;
            case("resource"):
                return new DocTypeInfo("resource", $isArray, true);
                break;
            case("callable"):
                return new DocTypeInfo("callable", $isArray, true);
                break;
            case("mixed"):
                return new DocTypeInfo("unknown", $isArray, true);
                break;
            default:
                $isInternal = false;
                try
                {
                    $class = new \ReflectionClass($string);
                    $isInternal = $class->isInternal();
                }
                catch (\ReflectionException $e) {}
                return new DocTypeInfo("object", $isArray, $isInternal, $string);
        }
    }

}
