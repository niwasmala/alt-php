<?php defined("ALT_PATH") OR die("No direct access allowed.");

class Alt_Exception extends Exception {

    public $code;
    public $message;

    public function __construct($message, $code = null) {
        $this->message = $message;
        $this->code = $code ? $code : Alt::STATUS_ERROR;

        Alt_Log::error($this->jTraceEx($this));
    }

    public function __toString() {
        return "$this->message [Code: $this->code]";
    }

    function jTraceEx($e, $seen = null) {
        $result     = array();
        $seen       = !$seen ? array() : array();
        $trace      = $e->getTrace();
        $prev       = $e->getPrevious();
        $file       = $e->getFile();
        $line       = $e->getLine();

        $result[]   = sprintf("%s: %s", get_class($e), $e->getMessage() . " (" . $this->getCode() . ")");
        while (true) {
            $current = "$file:$line";
            if (is_array($seen) && in_array($current, $seen)) {
                $result[] = sprintf("   ... %d more", count($trace)+1);
                break;
            }
            $result[] = sprintf("   at %s%s%s(%s%s%s)",
                count($trace) && array_key_exists("class", $trace[0]) ? str_replace("\\", ".", $trace[0]["class"]) : "",
                count($trace) && array_key_exists("class", $trace[0]) && array_key_exists("function", $trace[0]) ? "." : "",
                count($trace) && array_key_exists("function", $trace[0]) ? str_replace("\\", ".", $trace[0]["function"]) : "(main)",
                str_replace(APP_PATH, "", $file),
                $line === null ? "" : ":",
                $line === null ? "" : $line);
            if (is_array($seen))
                $seen[] = "$file:$line";
            if (!count($trace))
                break;
            $file = array_key_exists("file", $trace[0]) ? $trace[0]["file"] : "Unknown Source";
            $line = array_key_exists("file", $trace[0]) && array_key_exists("line", $trace[0]) && $trace[0]["line"] ? $trace[0]["line"] : null;
            array_shift($trace);
        }
        $result = join("\n", $result);
        if ($prev)
            $result  .= "\n" . jTraceEx($prev, $seen);

        return $result;
    }
}