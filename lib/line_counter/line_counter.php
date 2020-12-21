<?php

/**
 *           Doorkeeper Line Counter
 *
 *              Class to count number of lines in a project
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\line_counter;

use doorkeeper\lib\file_handler\file_handler;

class line_counter
{

    private file_handler $fh;

    // Total lines counted
    public $total_lines_counted;
    // Will contain file names with line counts
    public array $files_with_line_count = [];
    // List of files and directories to ignore, absolute paths only
    // Array structure example: [
    //  '/var/www/some-directory-to-ignore',
    //  '/bar/www/some-file.php'
    //  ] 
    private array $ignore_list = [];


    public function __construct(file_handler $file_handler)
    {
        $this->fh = $file_handler;
    }

    /**
     * Scans a project-directory for files and attempts to count the lines in each source code file.
     * This method requires write-access. If an error occurs, an exception is thrown by the file handler.
     * @return true
     */
    public function simple_scan(string $file_or_dir, int $number_of_lines = 0)
    {
        $number_of_lines = $number_of_lines;
        $subdirs = array();
        if (is_dir($file_or_dir)) { // Do this if we are dealing with a directory
            $objects = scandir($file_or_dir); // Handle subdirectories (if any)
            $objects = array_diff($objects, array('..', '.')); // Removes "." and ".." from the array, since they are not needed.
            foreach ($objects as $object) {
                $full_path = $file_or_dir . $object;
                if ($this->is_ignored($full_path)) {
                    continue;
                }
                if (is_dir($full_path)) {
                    $dirId = md5($full_path);
                    if (!isset($subdirs["$dirId"])) {
                        $subdirs["$dirId"] = $full_path;
                        $this->simple_scan($full_path . '/', $number_of_lines); // If dealing with a subdirectory, perform another simple_scan()
                    }
                } else {
                    // Read the file and record the number of lines
                    $this->read_file($full_path);
                }
            }
        } else {
            // Read the file and record the number of lines
            $this->read_file($file_or_dir);
        }
        // print_r($subdirs) . "\n\n";
        return true; // Return true on success
    }

    /**
     * Method to check if an object is in the ignore list
     * 
     */
    private function is_ignored($path) : bool
    {
        // Check if the ignored object matches the $path variable
        foreach ($this->ignore_list as $ignored_object) {
            if (preg_match('|^' . preg_quote($ignored_object) . '/?.*$|', $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Method to read a file.
     */
    public function read_file(string $file)
    {
        if (preg_match('/\.php$|\.scss$|\.js$|\.py$/', $file)) {
            $response = $this->fh->read_file_lines($file);
            $line_numbers = $this->line_numbers($response);
            // echo $file . ": $line_numbers -- Total: $this->total_lines_counted \n";
            $this->files_with_line_count["$file"] = $line_numbers;
        }
    }

    /**
     * Method to count the number of lines in a file.
     */
    public function line_numbers(string $string) : int
    {
        $lines = preg_split('/\n|\r/', $string);
        $line_numbers = count($lines);
        $this->total_lines_counted = $this->total_lines_counted + $line_numbers;
        return $line_numbers;
    }

    /**
     * Method to add an item to the ignore list.
     * If the path is a directory, the entire sub-contents will also be ignored.
     */
    public function ignore(string $path) {
      $this->ignore_list[] = $path;
    }
}
