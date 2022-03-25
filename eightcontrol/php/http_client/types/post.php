<?php

namespace eightcontrol\http_client\types;

class post extends http_request
{
    protected array $postParameters; // Array of parameters to be sent in the entity body


    function __construct()
    {
        parent::__construct();
        $this->defaultReqHeaders['content-type'] = 'application/x-www-form-urlencoded';
        $this->type = 'POST';
    }

    /**
     * Sets or returns the POST parameters (FYI. POST parameters are sent in the Entity Body).
     * @param array|null $postParameters
     * @return $this|array
     */
    public function postParameters(array $postParameters = null, $array = false) {
        if ($postParameters === null) {
            return $this->postParameters;
        }
        $this->postParameters = $postParameters;
        $this->entityBody = urlencode($this->buildPostString($postParameters));
        return $this;
    }

}