<?php


namespace rloris\layer\utils;


class Security
{
    public static function token($length = 16)
    {
        try
        {
            return bin2hex(random_bytes($length));
        }
        catch(\Exception $e)
        {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
    }


    public static function decrypt($b64data, $secret) {
        $dData = base64_decode($b64data);
        $salt = substr($dData, 0, 16);
        $ct = substr($dData, 16);
        $rounds = 3;
        $data = $secret.$salt;
        $hash = array();
        $hash[0] = hash('sha256', $data, true);
        $result = $hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $hash[$i] = hash('sha256', $hash[$i - 1].$data, true);
            $result .= $hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32,16);
        return openssl_decrypt($ct, 'AES-256-CBC', $key, true, $iv);
    }

    public static function encrypt($data, $secret) {
        $salt = openssl_random_pseudo_bytes(16);
        $salted = '';
        $dx = '';
        while (strlen($salted) < 48) {
            $dx = hash('sha256', $dx.$secret.$salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv  = substr($salted, 32,16);
        $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $key, true, $iv);
        return base64_encode($salt . $encrypted_data);
    }
}