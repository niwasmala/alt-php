<?php defined("ALT_PATH") or die("No direct script access.");

class Alt_Security {

    public static function encrypt($text, $options = array()){
        if(!function_exists("mcrypt_encrypt"))
            throw new Alt_Exception("PHP Mcrypt extension is not enabled");

        $options = array_union($options, array(
            "algorithm" => MCRYPT_RIJNDAEL_128,
            "mode"      => MCRYPT_MODE_CBC,
            "key"       => Alt::$config["app"]["id"],
            "iv"        => Alt::$config["app"]["id"],
        ));

        $str = Alt_Security::pkcs5_pad($text);
        $td = mcrypt_module_open($options["algorithm"], '', $options["mode"], $options["iv"]);
        mcrypt_generic_init($td, $options["key"], $options["iv"]);
        $encrypted = mcrypt_generic($td, $str);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        return bin2hex($encrypted);
    }

    public static function decrypt($text, $options = array()){
        if(!function_exists("mcrypt_decrypt"))
            throw new Alt_Exception("PHP Mcrypt extension is not enabled");

        $options = array_union($options, array(
            "algorithm" => MCRYPT_RIJNDAEL_128,
            "mode"      => MCRYPT_MODE_CBC,
            "key"       => Alt::$config["app"]["id"],
            "iv"        => Alt::$config["app"]["id"],
        ));

        $code = hex2bin($text);
        $td = mcrypt_module_open($options["algorithm"], '', $options["mode"], $options["iv"]);
        mcrypt_generic_init($td, $options["key"], $options["iv"]);
        $decrypted = mdecrypt_generic($td, $code);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);
        $ut =  utf8_encode(trim($decrypted));
        return Alt_Security::pkcs5_unpad($ut);
    }

    public static function pkcs5_pad($text) {
        $blocksize = 16;
        $pad = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    public static function pkcs5_unpad($text) {
        $pad = ord($text{strlen($text)-1});
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
            return false;
        }
        return substr($text, 0, -1 * $pad);
    }
}