<?php

namespace eightcontrol\http_client\types;

class head extends http_request
{

    function __construct()
    {
        parent::__construct();

        $this->type = 'HEAD';
    }

}