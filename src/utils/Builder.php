<?php

namespace rloris\layer\utils;


class Builder
{
    /**
     * @param $obj
     * @param bool $copyOnlyPublicProperties
     * @param bool $copyPropertiesWithGetters
     * @param Callable|null $keyTransformFunc
     * @return array
     * @throws \ReflectionException
     */
    // TODO: call getter instead of prop
    public static function object2Array($obj, $copyOnlyPublicProperties = true, $copyPropertiesWithGetters = true, $keyTransformFunc = null): array {
        if(is_array($obj))
        {
            $arr = [];
            foreach ($obj as $k => $v)
            {
                $arr[$k] = is_object($v) || is_array($v) ? self::object2Array($v, $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransformFunc) : $v;
            }
            return $arr;
        }
        $res = [];
        $reflectionClass = new \ReflectionClass(get_class($obj));
        foreach ($reflectionClass->getProperties() as $p)
        {
            $keyName = null;
            if($keyTransformFunc && is_callable($keyTransformFunc)) $keyName = call_user_func_array($keyTransformFunc, [$p->name, $obj]);
            if($keyName === null) $keyName = $p->name;

            if($p->isPublic())
            {
                $res[$keyName] = is_object($p->getValue($obj)) ? self::object2Array($p->getValue($obj), $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransformFunc) : $p->getValue($obj);
            } else
            {
                if($copyOnlyPublicProperties === true && $copyPropertiesWithGetters === false)
                    continue;
                if($copyOnlyPublicProperties === true)
                {
                    $getter = implode("", array_map('ucfirst', explode("_", $p->name)));
                    if( !(($reflectionClass->hasMethod('get'.$getter) && $reflectionClass->getMethod('get'.$getter)->isPublic()) || ($reflectionClass->hasMethod('is'.$getter) && $reflectionClass->getMethod('is'.$getter)->isPublic())) )
                        continue;
                }
                $p->setAccessible(true);
                $res[$keyName] = is_object($p->getValue($obj)) ? self::object2Array($p->getValue($obj), $copyOnlyPublicProperties, $copyPropertiesWithGetters, $keyTransformFunc) : $p->getValue($obj);
                $p->setAccessible(false);
            }
        }
        return $res;
    }
    /**
     * @param string $namespace
     * @param array $data
     * @param bool $isArray
     * @param callable|null $keyTransformFunc
     * @return array|object|null
     * @throws \Exception
     */
    public static function array2Object($namespace, $data = [], $isArray = false, $keyTransformFunc = null)
    {
        if($isArray && is_array($data))
        {
            $arr = [];
            foreach ($data as $value)
            {
                $res = self::array2Object($namespace, $value, (count(array_filter(array_keys($value), 'is_string')) === 0), $keyTransformFunc);
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
                    $keyName = null;
                    if($keyTransformFunc && is_callable($keyTransformFunc))
                    {
                        $keyName = call_user_func_array($keyTransformFunc, [$key]);
                    }
                    if($keyName !== null) $key = $keyName;

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
                            $reflectionSetter->invokeArgs($obj, [self::array2Object($docTypeInfo->namespace, $value, $docTypeInfo->isArray, $keyTransformFunc)]);
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