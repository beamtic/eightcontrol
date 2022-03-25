<?php
/**
 * 
 *    class to output text to the console
 * 
 *      currently very basic, like highlighting and overwriting output on same line
 *       i will probably add more features later
 * 
 *  @author Jacob (JacobSeated)
 */

namespace eightcontrol\console_writer;

use eightcontrol\php_helpers\php_helpers;
use Exception;

class console_writer
{
    private int $line_symbol_count = 0;
    private php_helpers $helpers;

    public function __construct(php_helpers $php_helpers)
    {
        $this->helpers = $php_helpers;
        // Make sure we are running in the console
        if (!$this->helpers->is_called_from_cli()) {
          throw new Exception("console_writer should only be used in CLI scripts. You can catch and handle this Exception if you know what you are doing; for example, if your script supports both CLI and HTTP.");
        }
        echo "\e[?25l"; // Hide cursor while writing output
    }

    public function __destruct()
    {

        // Add a final newline when the script has finished running to bring the prompt to the next line in some terminals
        // and avoid zsh (Default shell on MacOS) adding a [%] after the script has finished
        echo "\n";
        echo "\e[?25h"; // Show cursor again
    }


    /**
     * Echo'es out $string to the same line in the console. Useful when making progress bars for examble. This function may be ideally called inside a loop.
     * @param string $string 
     *  
     */
    public function overwrite_line(string $string, $delay_milliseconds = 100)
    {
        if((false !== strpos($string, "\n")) || (false !== strpos($string, "\r"))) {
          throw new Exception("Multiple lines are not supported.");
        }

        // We need to pad the string to make sure everything is overwritten
        $string = str_pad($string, $this->line_symbol_count, ' ');

        echo "\033[" . $this->line_symbol_count . "D"; // Delete number of characters equal to last input
        echo "$string";

        $this->line_symbol_count = $this->count_characters($string);

        // Sleep is needed to actually see the changes in console
        usleep($delay_milliseconds * 1000);
    }


    /**
     * Returns a highlighted string for later output
     * @param string $string 
     * @return string 
     */
    public function highlight(string $string)
    {
        return "\033[1;97m" . $string . "\033[1;0m";
    }

    /**
     * Counts the number of characters in a string
     * @param string $string 
     * @return int 
     */
    private function count_characters(string $string)
    {
        return count(preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY));
    }
}
