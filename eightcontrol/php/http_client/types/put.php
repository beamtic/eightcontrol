<?php

namespace eightcontrol\http_client\types;

class put extends http_request
{

    function __construct()
    {
        parent::__construct();
        $this->type = 'PUT';
    }

}