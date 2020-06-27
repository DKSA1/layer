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
        return (filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == $data && preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $data)) != false;
    }

    public static function isIpv6(string $data)
    {
        if($data === '::1') return true;
        return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public static function isMac(string $data)
    {
        return (filter_var($data, FILTER_VALIDATE_MAC) == $data);
    }

    public static function info(string $data, bool $word = false, bool $occurrence = false) : array
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

        $words = $word ? str_word_count($data, 2) : null;

        return [
            'uppercase' => $uc,
            'lowercase' => $lc,
            'number' => $num,
            'symbol' => $sym,
            'space' => $spa,
            'word' => $word ? count($words) : -1,
            'words' => $word ? $words : null,
            'occurrences' => $occurrence ? count_chars($data,1) : null,
            'occurrence_ratio' => $occurrence ? (strlen(count_chars($data,3)) / $length) : -1,
            'length' => $length
        ];
    }

    public static function levenshtein(string $data, string $similar): int {
        if($data == $similar) return 0;
        return levenshtein($data, $similar);
    }

    public static function similarityPercent(string $main, string $data): float {
        if($main == $data) return 100.0;
        similar_text($main, $data, $percent);
        return $percent;
    }

}