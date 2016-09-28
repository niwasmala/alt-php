<?php defined("ALT_PATH") OR die("No direct access allowed.");

class Alt_Log {
    const LEVEL_LOG         = 5;
    const LEVEL_DEBUG       = 4;
    const LEVEL_INFO        = 3;
    const LEVEL_WARN        = 2;
    const LEVEL_ERROR       = 1;
    const LEVEL_SILENT      = 0;

    public static $level    = self::LEVEL_ERROR;

    public static function write($level, $message){
        $time = time();
        $dir  = "log" . DIRECTORY_SEPARATOR;
        $file = $dir . date("Ymd", $time) . ".txt";

        if(!file_exists($dir))
            mkdir($dir, 1777, true);

        if(self::$level >= $level) {
            $type = "[";
            switch($level){
                case self::LEVEL_LOG:
                    $type .= "LOG";
                    break;
                case self::LEVEL_DEBUG:
                    $type .= "DEBUG";
                    break;
                case self::LEVEL_INFO:
                    $type .= "INFO";
                    break;
                case self::LEVEL_WARN:
                    $type .= "WARN";
                    break;
                case self::LEVEL_ERROR:
                    $type .= "ERROR";
                    break;
                default:
                    $type .= "";
                    break;
            }
            $type .= "] " . date("H:m:s") . "\n";

            file_put_contents($file, $type . (gettype($message) == "string" || gettype($message) == "number" ? $message : json_encode($message)) . "\n\n", FILE_APPEND | LOCK_EX);
        }
    }

    public static function log($message){
        Alt_Log::write(Alt_Log::LEVEL_LOG, $message);
    }

    public static function debug($message){
        Alt_Log::write(Alt_Log::LEVEL_DEBUG, $message);
    }

    public static function info($message){
        Alt_Log::write(Alt_Log::LEVEL_INFO, $message);
    }

    public static function warn($message){
        Alt_Log::write(Alt_Log::LEVEL_WARN, $message);
    }

    public static function error($message){
        Alt_Log::write(Alt_Log::LEVEL_ERROR, $message);
    }
}

