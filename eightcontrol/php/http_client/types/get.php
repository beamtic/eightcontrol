<?php

namespace eightcontrol\http_client\types;

class get extends http_request
{

    function __construct()
    {
        parent::__construct();

        $this->type = 'GET';
    }

}