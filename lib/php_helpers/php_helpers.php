<?php

/**
 *      Doorkeeper
 *
 *         Class containing PHP helper methods used to solve common programming problems
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\php_helpers;

use Exception;

class php_helpers
{

    /**
     * Method to validate $input_arguments, and compare with $defined_arguments.
     * The input arguments are valid if the function is not interrupted.
     *
     * @param array $defined_arguments is filled out by the developer on a per-function basis
     * @param array $input_arguments contains the provided arguments which are checked against  "$default_argument_values_arr"
     * @return array Returns an array on success exits with an error on failure.
     * @throws Exception on failure.
     */
    public function handle_arguments($input_arguments, $defined_arguments)
    {
        trigger_error('PHP8.0 added support for named parameters, so this is no longer needed, and will be removed soon.', E_USER_DEPRECATED);
        // Check if parameter is defined, and validate the type (if provided)
        foreach ($input_arguments as $key => $value) {
            if (!isset($defined_arguments["$key"])) {
                throw new \Exception('Unknown function parameter: ' . $key);
            }
            // Validate the type, if defined. A type is always defined in an array.
            // I.e.: array('required' => true, 'type' => 'object')
            if (is_array($defined_arguments["$key"])) {
                // Check if the developer remembered to define both "required" and "type"
                if ((!isset($defined_arguments["$key"]['required'])) || (!isset($defined_arguments["$key"]['type']))) {
                    throw new \Exception('Missing argument definition "required" or "type".');
                    exit();
                }
                if (!$this->type_check($value, $defined_arguments["$key"]['type'])) {
                    throw new \Exception(
                        'Invalid input type for: "' . $key
                            . '" Expected ' . $defined_arguments["$key"]['type'] . ', ' . gettype($value) . ' given.'
                    );
                }
                // In case value was an array, make sure to add possible default elements.
                if ((isset($defined_arguments["$key"]['default'])) && (is_array($defined_arguments["$key"]['default']))) {
                    $input_arguments["$key"] += $defined_arguments["$key"]['default'];
                }
            }
        }
        // --------------------------------------------------
        // Check for missing required parameters-------------
        // The "required" setting only needs to be checked when the parameter is missing
        // --------------------------------------------------
        $missing_parms = array_diff_key($defined_arguments, $input_arguments);
        foreach ($missing_parms as $key => $value) {
            if (!is_array($defined_arguments["$key"])) {
                // If no type validation
                if (true === $defined_arguments["$key"]) {
                    throw new \Exception('Missing required parameter: ' . $key);
                }
            } else {
                // Check if the developer remembered to define both "required" and "type"
                if ((!isset($defined_arguments["$key"]['required'])) || (!isset($defined_arguments["$key"]['type']))) {
                    throw new \Exception('Missing argument definition "required" or "type".');
                }
                // If type was to be validated, check the "required" key
                if (true === $defined_arguments["$key"]['required']) {
                    throw new \Exception('Missing required parameter: ' . $key);
                }
                // Check if the parameter has a default value
                if (isset($defined_arguments["$key"]['default'])) {
                    $input_arguments["$key"] = $defined_arguments["$key"]['default'];
                }
            }
        }
        return $input_arguments;
    }

    /**
     * Checks if a value is of the supplied type.
     *
     * @param mixed $input_value The value to validate.
     * @param string $defined_type The type to check for.
     * @return boolean true when input type matched defined type, false otherwise.
     */
    public function type_check($input_value, string $defined_type)
    {
        switch ($defined_type) {
            case 'string':
                return is_string($input_value);
                break;
            case 'str':
                return is_string($input_value);
                break;
            case 'integeer':
                return is_int($input_value);
                break;
            case 'int':
                return is_int($input_value);
                break;
            case 'object':
                return is_object($input_value);
                break;
            case 'array':
                return is_array($input_value);
                break;
            case 'bool':
                return is_bool($input_value);
                break;
            case 'resource':
                return is_resource($input_value);
                break;
            case 'null':
                return is_null($input_value);
                break;
                // Mixed type will allow anything
            case 'mixed':
                return true;
                break;
                // For unknown types we return false
            default:
                return false;
                break;
        }
    }

    /**
     * Reordering method to move an element in an array up or down.
     *
     *  @param array $array the array that must be worked on
     *  @param mixed $selected_key string or int of the key that should be moved.
     *  @param string $direction the direction the element should be moved- either "up" or "down".
     *
     *  @return array A new array with the key shoved in the requested direction.
     */
    public function array_shove(array $array, $selected_key, string $direction)
    {
        $new_array = array();

        foreach ($array as $key => $value) {
            $last = false;
            if ($key !== $selected_key) {
                $new_array["$key"] = $value;
                $last = array('key' => $key, 'value' => $value);
                unset($array["$key"]);
            } else {
                if ($direction !== 'up') {
                    // Value of next, moves pointer
                    $next_value = next($array);

                    // Key of next
                    $next_key = key($array);

                    // We can not rely on $next_key !== false,
                    // since it might break some arrays containing boolean values
                    // Instead we check if $next_key is null,
                    // indicating there is no more elements in the array
                    if ($next_key !== null) {
                        // Add -next- to $new_array, keeping -current- in $array
                        $new_array["$next_key"] = $next_value;
                        unset($array["$next_key"]);
                    }
                } else {
                    if (isset($last['key'])) {
                        unset($new_array["{$last['key']}"]);
                    }
                    // Add current $array element
                    $new_array["$key"] = $value;
                    // Re-add $last element
                    $new_array["{$last['key']}"] = $last['value'];
                }
                // Merge new and old array
                return $new_array + $array;
            }
        }
    }
    /**
     * Checks if an array is associative. Return value of 'False' indicates a sequential array.
     * @param array $inptArry 
     * @return bool 
     */
    public function is_associative(array $inptArry): bool
    {
        // An empty array is in theory a valid associative array
        // so we return 'true' for empty.
        if ([] === $inptArry) {
            return true;
        }

        for ($i = 0; $i < count($inptArry); $i++) {
            if (!array_key_exists($i, $inptArry)) {
                return true;
            }
        }
        // Dealing with a Sequential array
        return false;
    }

    /**
     * UTF-8 safe method to count the number of characters in a string.
     *
     * @param string $string
     * @return integeer
     */
    public function count_characters(string $string)
    {
        return count(preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Method to replace the first occurance of a string within another string
     * 
     */
    public function replace_first_str(string $search_str, string $replacement_str, string $src_str): string
    {
        return (false !== ($pos = strpos($src_str, $search_str))) ? substr_replace($src_str, $replacement_str, $pos, strlen($search_str)) : $src_str;
    }

    /**
     * Checks if a command exist. Works for both Windows and Linux systems
     * @param mixed $command_name 
     * @return bool 
     */
    public function command_exists($command_name)
    {
        // If on Windows, use "where", else use "command -v"
        // Command -v is the recommended way to check if a command exists.
        // This is also how it is done in project humanize:
        //  https://github.com/beamtic/humanize/blob/master/helpers/is-command-available.sh
        $command_name = escapeshellcmd($command_name);
        $test_method = (false === stripos(PHP_OS, 'win')) ? 'command -v' : 'where';
        return (null === shell_exec("$test_method $command_name")) ? false : true;
    }
    use \doorkeeper\lib\class_traits\no_set;
}
