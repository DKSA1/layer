<?php


namespace rloris\layer\utils;


class StringSanitizer
{

    public static function htmlStrong(string $unsafe)
    {
        return htmlspecialchars(strip_tags($unsafe));
    }

    public static function htmlLight(string $unsafe)
    {
        return htmlspecialchars($unsafe);
    }

    public static function htmlText(string $unsafe)
    {
        return strip_tags($unsafe);
    }
}