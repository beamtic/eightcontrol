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
