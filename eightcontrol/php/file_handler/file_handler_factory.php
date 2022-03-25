<?php
/**
 *           eightcontrol File Handler Factory
 *
 *           Do not load this factory inside other factories...
 *
 *         @author Jacob (JacobSeated)
 */
namespace eightcontrol\file_handler;

// IMPORTANT
// Please avoid replacing dependencies
// Until you are sure you understand the consequences

/**
 * This factory can be instantiated from a Compositioning Root to use functionality in the file_handler library.
 */
class file_handler_factory
{

    private $base_path;

    public function __construct($base_path)
    {
        $this->base_path = $base_path;
    }

    /**
     * Function to "build" the final object with all of its dependencies
     *
     * @return object The File Handler Object
     */
    public function build()
    {
        $superglobals = new \eightcontrol\php_helpers\superglobals();
        $file_types = new \eightcontrol\file_handler\file_types();

        return new \eightcontrol\file_handler\file_handler($superglobals, $file_types);
    }

}