<?php
/**
 *           Doorkeeper File Handler Factory
 *
 *           Do not load this factory inside other factories...
 *           Instead, go to the relevant create_method and create the dependencies as required.
 *
 *         @author Jacob (JacobSeated)
 */
namespace doorkeeper\lib\file_handler;

// IMPORTANT
// Please avoid replacing dependencies
// Until you are sure you understand the consequences

/**
 * This factory can be instantiated from a Compositioning Root to use functionality in the file_handler library.
 * The File Handler object is available via the public "p" property.
 */
class file_handler_factory
{

    public static function build()
    {
        $superglobals = new \doorkeeper\lib\superglobals\superglobals();
        $helpers = new \doorkeeper\lib\php_helpers\php_helpers();
        $file_types = new \doorkeeper\lib\file_handler\file_types();

        return new \doorkeeper\lib\file_handler\file_handler($helpers, $superglobals, $file_types);
    }

}