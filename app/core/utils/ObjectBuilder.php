<?php

namespace layer\core\utils;


class ObjectBuilder
{
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
                    if($reflectionClass->hasProperty($key))
                    {
                        if(!$obj)
                        {
                            $obj = $reflectionClass->newInstance();
                        }
                        $reflectionProperty = $reflectionClass->getProperty($key);
                        $docType = DocCommentParser::var($reflectionProperty->getDocComment());
                        $docTypeInfo = DocTypeInfo::getDocType($docType);
                        $reflectionProperty->setAccessible(true);
                        if($docTypeInfo->isInternal)
                        {
                            if($docTypeInfo->type === 'object' && $docTypeInfo->namespace === '\DateTime')
                            {
                                try
                                {
                                    $date = new \DateTime($value);
                                    $reflectionProperty->setValue($obj, $date);
                                }
                                catch(\TypeError $e){}
                            }
                            else
                            {
                                $reflectionProperty->setValue($obj, $value);
                            }
                        }
                        else
                        {
                            $reflectionProperty->setValue($obj, self::build($docTypeInfo->namespace, $value, $docTypeInfo->isArray));
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