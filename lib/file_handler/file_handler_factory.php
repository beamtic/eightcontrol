<?php
/**
 *           Doorkeeper File Handler Factory
 *
 *           Do not load this factory inside other factories...
 *
 *         @author Jacob (JacobSeated)
 */
namespace doorkeeper\lib\file_handler;

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
        $superglobals = new \doorkeeper\lib\php_helpers\superglobals();
        $file_types = new \doorkeeper\lib\file_handler\file_types();

        return new \doorkeeper\lib\file_handler\file_handler($superglobals, $file_types);
    }

}