<?php

/**
 *           Doorkeeper Globals
 *
 *               Problem
 *                Relying on globals can cause confusion and unpredictable object states,
 *                therefor we best provide the globals in an object, and prevent editing the
 *                globals directly.
 *
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\php_helpers;

class superglobals
{
    private $server, $post, $files, $get, $session, $cookie;

    public function __construct()
    {
        $this->define_superglobals();
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_SERVER($key = null)
    {
        if (null !== $key) {
            return (isset($this->server["$key"])) ? $this->server["$key"] : null;
        } else {
            return $this->server;
        }
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_POST($key = null)
    {
        if (null !== $key) {
            return (isset($this->post["$key"])) ? $this->post["$key"] : null;
        } else {
            return $this->post;
        }
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_FILES($key = null)
    {
        if (null !== $key) {
            return (isset($this->files["$key"])) ? $this->files["$key"] : null;
        } else {
            return $this->files;
        }
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_GET($key = null)
    {
        if (null !== $key) {
            return (isset($this->get["$key"])) ? $this->get["$key"] : null;
        } else {
            return $this->get;
        }
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_SESSION($key = null)
    {
        if (null !== $key) {
            return (isset($this->session["$key"])) ? $this->session["$key"] : null;
        } else {
            return $this->session;
        }
    }
    /**
     * Returns a key from the superglobal,
     * as it was at the time of instantiation.
     *
     * @param $key
     * @return mixed
     */
    public function get_COOKIE($key = null)
    {
        if (null !== $key) {
            return (isset($this->cookie["$key"])) ? $this->cookie["$key"] : null;
        } else {
            return $this->cookie;
        }
    }
    /**
     * Function to define superglobals for use locally.
     * We do not automatically unset the superglobals after
     * defining them, since they might be used by other code.
     *
     * @return mixed
     */
    private function define_superglobals()
    {

        // Store a local copy of the PHP superglobals
        // This should avoid dealing with the global scope directly
        // $this->_SERVER = $_SERVER;
        $this->server = (isset($_SERVER)) ? $_SERVER : null;
        $this->post = (isset($_POST)) ? $_POST : null;
        $this->files = (isset($_FILES)) ? $_FILES : null;
        $this->get = (isset($_GET)) ? $_GET : null;
        $this->session = (isset($_SESSION)) ? $_SESSION : null;
        $this->cookie = (isset($_COOKIE)) ? $_COOKIE : null;
    }
    /**
     * You may call this function from your compositioning root
     * if you are sure superglobals will not be needed by
     * dependencies or outside of your own code.
     *
     * @return void
     */
    public function unset_superglobals()
    {
        unset($_SERVER);
        unset($_POST);
        unset($_FILES);
        unset($_GET);
        unset($_SESSION);
        unset($_COOKIE);
    }
    /**
     * Compares used GET or POST parameters with allowed parameters.
     * Returns false if all parameters was valid, and an array of unknown parameters otherwhise.
     * @return mixed
     */
    public function get_unknown_parms(array $allowed_parms, array $used_parms, $flip_allowed_array=true) {
        $invalid_parms = false;

        // Allows the shortest usage syntax: ['some_parm', 'other_parm'...]
        if (true == $flip_allowed_array) {
            $allowed_parms = array_flip($allowed_parms);
        }
        // Only allow specific post|get variables
        foreach ($used_parms as $key => &$value) {
            if (!isset($allowed_parms["$key"])) {
                $invalid_parms[] = $key;
            }
        }
        if (is_array($invalid_parms)) {
            return $invalid_parms;
        }
        return false;
    }
}
