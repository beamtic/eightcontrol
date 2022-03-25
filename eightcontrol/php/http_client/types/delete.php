<?php

namespace eightcontrol\http_client\types;

class delete extends http_request
{

    function __construct()
    {
        parent::__construct();

        $this->type = 'DELETE';
    }

}