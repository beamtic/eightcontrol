<?php
/**
 *      Doorkeeper
 *
 *         Class containing PHP helper methods used to solve common programming problems
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\php_helpers;

class php_helpers
{
    /**
     * Method to define default arguments using
     * an associative array instead of traditional function arguments.
     * This allows the developer to supply arguments in any order desired.
     *
     * $default_argument_values_arr
     *   is filled out by the developer on a per-function basis
     * $arguments_arr
     *   contains the provided arguments which are checked against  "$default_argument_values_arr"
     */
    public function default_arguments(array $arguments_arr, array $default_argument_values_arr)
    {

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
    /**
     * Reordering method to move an element in an array up or down.
     *
     *  @param array $array the array that must be worked on
     *  @param $selected_key string or int of the key that should be moved.
     *  @param string $direction the direction the element should be moved- either "up" or "down".
     *
     *
     */
    public function array_shove(array $array, $selected_key, string $direction)
    {
        $new_array = array();

        foreach ($array as $key => $value) {
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
 * UFT-8 safe method to count the number of characters in a string.
 *
 * @param string $string
 * @return integeer
 */
    public function count_characters(string $string)
    {
        return count(preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY));
    }
}