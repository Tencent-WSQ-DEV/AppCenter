<?php

class HTTP_Request_Exception extends Exception {

    public function __construct($message, $code = 0) {
        if (is_a($message, 'Exception')) {
            parent::__construct($message->getMessage(), $message->getCode());
        } else {
            parent::__construct($message, intval($code));
        }
    }

}
