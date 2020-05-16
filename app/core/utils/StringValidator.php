<?php


namespace layer\core\utils;


class StringValidator
{
    public static function isDateTime(string $data, string $format = 'Y-m-d hh:mm:ss')
    {
        try
        {
            return \DateTime::createFromFormat($format, $data) != false;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    public static function isDate(string $data, string $format = 'Y-m-d')
    {
        try
        {
            return \DateTime::createFromFormat($format, $data) != false;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    public static function isTime(string $data, string $format = 'hh:mm:ss')
    {
        try
        {
            return \DateTime::createFromFormat($format, $data) != false;
        }
        catch (\Exception $e)
        {
            return false;
        }
    }

    public static function isPath(string $data)
    {
        return preg_match('/^((\.?\.)?\/)*([A-z0-9-_+]+\/)*(\.?[A-z0-9]+(\.(.*))?)$/', $data) != false;
    }

    public static function isRegexp(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_REGEXP) == $data);
    }

    public static function isDomain(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_DOMAIN) == $data);
    }

    public static function isUrl(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_URL) == $data && preg_match('/^(?:http(s)?:\/\/)?[\w.-]+(?:\.[\w.-]+)+[\w\-._~:\/?#\[\]@!$&\'()*+,;=]+$/', $data) != false );
    }

    public static function isEmail(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_EMAIL) == $data && preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $data) != false ) ;
    }

    public static function isPhoneNum(string $data)
    {
        return preg_match('/((?:\+|00)[17](?: |\-)?|(?:\+|00)[1-9]\d{0,2}(?: |\-)?|(?:\+|00)1\-\d{3}(?: |\-)?)?(0\d|\([0-9]{3}\)|[1-9]{0,3})(?:((?: |\-)[0-9]{2}){4}|((?:[0-9]{2}){4})|((?: |\-)[0-9]{3}(?: |\-)[0-9]{4})|([0-9]{7}))/', $data) != false;
    }

    public static function isWord(string $data)
    {
        return preg_match('/^[\p{L}-]+$/', $data) != false;
    }

    public static function isIpv4(string $data)
    {
        if($data === '::1')
            return true;
        return (filter_var($data, FILTER_VALIDATE_IP) == $data && preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $data)) != false;
    }

    public static function isIpv6(string $data)
    {
        return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public static function isMac(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_MAC) == $data);
    }

    public static function info(string $data) : array
    {
        $length = strlen($data);

        $uc = 0; $lc = 0; $num = 0; $sym = 0; $spa = 0;

        for ($i = 0; $i < $length; $i++)
        {
            $char = $data[$i];
            if(ctype_upper($char)) $uc++;
            else if(ctype_lower($char)) $lc++;
            else if(ctype_space($char)) $spa++;
            else if(ctype_digit($char)) $num++;
            else $sym++;
        }

        return [
            'uppercase' => $uc,
            'lowercase' => $lc,
            'number' => $num,
            'symbol' => $sym,
            'space' => $spa,
            'word' => str_word_count($data),
            'length' => $length
        ];
    }

}