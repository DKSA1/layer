<?php

namespace layer\core\utils;


class ObjectBuilder
{
    /**
     * @param string $namespace
     * @param array $data
     * @param bool $isArray
     * @return array|object|null
     * @throws \Exception
     */
    public static function build($namespace, $data = [], $isArray = false)
    {
        if($isArray && is_array($data))
        {
            $arr = [];
            foreach ($data as $value)
            {
                $res = self::build($namespace, $value);
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
                            $reflectionSetter->invokeArgs($obj, [self::build($docTypeInfo->namespace, $value, $docTypeInfo->isArray)]);
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