<?php defined("ALT_PATH") OR die("No direct access allowed.");

class Alt_Websocket_User {
    public $socket;
    public $id;
    public $headers = array();
    public $handshake = false;
    public $handlingPartialPacket = false;
    public $partialBuffer = "";
    public $sendingContinuous = false;
    public $partialMessage = "";

    public $hasSentClose = false;
    function __construct($id, $socket) {
        $this->id = $id;
        $this->socket = $socket;
    }
}