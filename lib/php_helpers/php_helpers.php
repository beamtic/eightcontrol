<?php
/*
 *      Doorkeeper
 *
 *         Class containing PHP helper methods used to solve common programming problems
 *
 *         @author Jacob Kristensen (JacobSeated)
 */

namespace doorkeeper\lib\php_helpers;

class php_helpers
{

    public function default_arguments(array $arguments_arr, array $default_argument_values_arr)
    {
        // Method to define default arguments using
        // an associative array instead of traditional function arguments.
        // This is needed so arguments may be entered in any order the developer wants!

        // "$default_argument_values_arr"  is filled out by the developer on a per-function basis
        // "$arguments_arr"  contains the provided arguments which are checked against  "$default_argument_values_arr"

        foreach ($default_argument_values_arr as $key => $value) { // Set default values
            if (isset($arguments_arr["{$key}"]) == false) {
                if ($default_argument_values_arr["{$key}"] !== 'REQUIRED') {
                    $arguments_arr["{$key}"] = $value;
                } else { // The error handling may be improved as the project moves forward
                    echo 'Missing required key: ' . $key;
                    exit();
                }
            }
        }
        return $arguments_arr;
    }
}