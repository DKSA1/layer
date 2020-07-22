<?php

namespace rloris\layer\utils;


class Builder
{
    /**
     * @param $obj
     * @param bool $copyOnlyPublicProperties
     * @param bool $copyPropertiesWithGetters
     * @param Callable|null $keyTransform
     * @return array
     * @throws \ReflectionException
     */
    public static function object2Array($obj, $copyOnlyPublicProperties = true, $copyPropertiesWithGetters = true, $keyTransform = null): array {
        if(is_array($obj)) {
            $arr = [];
            foreach ($obj as $k => $v) {
                $arr[$k] = is_object($v) || is_array($v) ? self::object2Array($v, $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransform) : $v;
            }
            return $arr;
        }
        $res = [];
        $reflectionClass = new \ReflectionClass(get_class($obj));
        foreach ($reflectionClass->getProperties() as $p) {
            $keyName = $p->name;
            if($keyTransform && is_callable($keyTransform)) $keyName = call_user_func_array($keyTransform, [$p->name, $obj]);
            if($keyName === null) $keyName = $p->name;
            if($p->isPublic()) {
                $res[$keyName] = is_object($p->getValue($obj)) ? self::object2Array($p->getValue($obj), $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransform) : $p->getValue($obj);
            } else {
                if($copyOnlyPublicProperties === true && $copyPropertiesWithGetters === false)
                    continue;
                if($copyOnlyPublicProperties === true) {
                    $getter = 'get'.implode("", array_map('ucfirst', explode("_", $p->name)));
                    if(!($reflectionClass->hasMethod($getter) && $reflectionClass->getMethod($getter)->isPublic()))
                        continue;
                }
                $p->setAccessible(true);
                $res[$keyName] = is_object($p->getValue($obj)) ? self::object2Array($p->getValue($obj), $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransform) : $p->getValue($obj);
                $p->setAccessible(false);
            }
        }
        return $res;
    }
    /**
     * @param string $namespace
     * @param array $data
     * @param bool $isArray
     * @return array|object|null
     * @throws \Exception
     */
    public static function array2Object($namespace, $data = [], $isArray = false)
    {
        if($isArray && is_array($data))
        {
            $arr = [];
            foreach ($data as $value)
            {
                $res = self::array2Object($namespace, $value);
                if($res)
                    array_push($arr, $res);
            }
            return $arr;
        }
        else if(!$isArray && is_array($data))
        {
            try
            {
                $reflectionClass = new \ReflectionClass($namespace);
                $obj = null;
                foreach ($data as $key => $value)
                {
                    $setter = 'set'.ucfirst($key);
                    $reflectionSetter = $reflectionClass->hasMethod($setter) ? $reflectionClass->getMethod($setter) : null;
                    if($reflectionClass->hasProperty($key) && $reflectionClass->isInstantiable() && $reflectionSetter && $reflectionSetter->isPublic())
                    {
                        if(!$obj)
                        {
                            $obj = $reflectionClass->newInstance();
                        }
                        $reflectionProperty = $reflectionClass->getProperty($key);
                        $docType = DocCommentParser::var($reflectionProperty->getDocComment());
                        $docTypeInfo = DocTypeInfo::getDocType($docType);
                        if($docTypeInfo->isInternal)
                        {
                            if($docTypeInfo->type === 'object' && $docTypeInfo->namespace === '\DateTime')
                            {
                                try
                                {
                                    $date = new \DateTime($value);
                                    $reflectionSetter->invokeArgs($obj, [$date]);
                                }
                                catch(\TypeError $e){}
                            }
                            else
                            {
                                $reflectionSetter->invokeArgs($obj, [$value]);
                            }
                        }
                        else
                        {
                            $reflectionSetter->invokeArgs($obj, [self::array2Object($docTypeInfo->namespace, $value, $docTypeInfo->isArray)]);
                        }
                    }
                }
                return $obj;
            }
            catch (\ReflectionException $e)
            {
                return null;
            }
        }
        else
        {
            return null;
        }

    }
}