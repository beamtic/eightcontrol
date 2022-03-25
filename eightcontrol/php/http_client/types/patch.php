<?php

namespace eightcontrol\http_client\types;

class patch extends http_request
{

    function __construct()
    {
        parent::__construct();
        $this->type = 'PATCH';
    }
}
