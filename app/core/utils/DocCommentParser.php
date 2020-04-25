<?php

namespace layer\core\utils;

class DocCommentParser
{
    public static function var($docBlock)
    {
        preg_match('/@var\s+([^\s]+)\s*([^\s]*)/', $docBlock, $matches);
        if(isset($matches[1]))
            return $matches[1];
        else
            return [];
    }

    public static function param($docBlock)
    {
        preg_match_all('/@param\s+([^\s]+)\s+\$([^\s]+)/', $docBlock, $matches);
        if(isset($matches[1]) && isset($matches[2]))
            return array_combine($matches[2],$matches[1]);
        else
            return [];
    }
}