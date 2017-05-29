<?php defined('ALT_PATH') OR die('No direct access allowed.');

class Alt_Component {

    public static $folder = "component";
    public $_data = array();
    public $_html = "";

    public function __construct($location, $data = array())
    {
        $this->_data = $data;
        foreach($data as $key => $value){
            $this->{$key} = $value;
        }

        ob_start();

        /** @noinspection PhpIncludeInspection */
        /** @noinspection PhpUndefinedConstantInspection */
        include ALT_PATH . self::$folder . DIRECTORY_SEPARATOR . $location . ".php";

        $this->_html = ob_get_contents() ? ob_get_contents() : "";
        ob_end_clean();
    }

    public function __toString()
    {
        return $this->_html;
    }

    public static function load($location, $data = array())
    {
        return new self($location, $data);
    }
}