<?php

namespace eightcontrol\dev;

use eightcontrol\console_writer\console_writer;
use eightcontrol\php_helpers\php_helpers;

/**
 * Various methods to help test things doing development
 * @package 
 */
class dev
{

    private php_helpers $helpers;
    private console_writer $console;

    private int $start_execution_time;

    public function __construct(php_helpers $php_helpers, console_writer $console_writer, int $start_execution_time = null)
    {
      $this->helpers = $php_helpers;
      $this->console = $console_writer;
      // If no start execution time is provided, assume the dev class is initialized early and set our own
      $this->start_execution_time = (isset($start_execution_time)) ? $start_execution_time : time();
    }

    /**
     * Outputs plain text content to the console or the browser
     *
     * @param string $input
     * @return void
     */
    function dump($input)
    {
        if (!is_string($input)) {
            $input = print_r($input, true);
        }

        $execution_time = (microtime(true) - $this->start_execution_time);

        // If in CLI mode
        // Some tips on console formatting: https://gist.github.com/vratiu/9780109
        // Note. We may need to test this on Windows Terminal as well!
        //       Formatting should work for both Mac and GNU/Linux systems now!
        if ($this->helpers->is_called_from_cli()) {
            $input .= "\n\n";
            $input .= (!empty($execution_time)) ? "\n\n  " . $execution_time . "\n\n" : '';
        } else {
            // Assume PHP is called via a web browser and use HTML formatting instead
            $output_start = '<p><b>';
            $output_end = '</b></p>';
            $input = '<pre>' . $input . '</pre>';
            $input .= (!empty($execution_time)) ? '<p>' . $execution_time . '</p>' : '';
        }

        // Attempt to clean output buffers before sending output
        while (ob_get_level() !== 0) {
            ob_clean();
        }
        // Attempt to send output
        echo "\n\n  " . $this->console->highlight("OUTPUT:") . "\n";

        echo $input;
        exit();
    }


    /**
     * Outputs plain text content to the console or the browser (preferably the console)
     *
     * @param $input
     * @param string $file_path
     * @return void
     */
    function dump_to_file($input, string $file_path)
    {

        if (false === is_string($input)) {
            $input = print_r($input, true);
        }

        // If in CLI mode
        // Some tips on console formatting: https://gist.github.com/vratiu/9780109
        // Note. We may need to test this on Windows Terminal as well!
        //       Formatting should work for both Mac and GNU/Linux systems now!
        if (substr(php_sapi_name(), 0, 3) !== 'cgi') {
            $input .= "\n\n";
        } else {
            // Assume PHP is called via a web browser and use HTML formatting instead
            $output_start = '<p><b>';
            $output_end = '</b></p>';
            $string = '<pre>' . $input . '</pre>';
        }

        // Attempt to clean output buffers before sending stuff
        while (ob_get_level() !== 0) {
            ob_clean();
        }
        // Attempt to send output
        echo "\n\n  ". $this->console->highlight('Dumped to file: ') . $file_path . "\n\n";
        file_put_contents($file_path, $input);
        exit();
    }
}
