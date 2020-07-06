<?php

/**
 *           Doorkeeper File Handler
 *
 *                Class to handle creating, editing, and deleting files and directories with support for locking.
 *
 *              write_file() should automatically handle locking, allowing use in some concurrency siturations.
 *              This probably does not work for all applications, but should be enough in many cases.
 *              You may want to use a database for "heavy" load applications.
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

use doorkeeper\lib\php_helpers\{php_helpers, superglobals};
use doorkeeper\lib\file_handler\exception; // Must be lowercase for the autoloader to work

class file_handler
{

    private $additional_error_data = array();
    // private $helpers; // The $helpers object

    private $f_args;
    private $lock_max_time = 20; // File-lock maximum time in seconds.

    private $helpers;
    private $ft; // The $file_types object, used in http_stream_file()
    private $sg;

    public function __construct(php_helpers $helpers, superglobals $superglobals, file_types $file_types)
    {
        $this->helpers = $helpers; // Helper methods to solve common programming problems
        $this->ft = $file_types;
        $this->sg = $superglobals;
    }
    /**
     *  A standard method to delete both directories and files,
     *  if the permissions allow it, a directory will be deleted, including subdirectories.
     *  @param string $file_or_dir Path to file or directory
     *  @return true
     *  @throws Exception on failure.
     */
    public function simple_delete(string $file_or_dir)
    {
        $this->additional_error_data = array('source' => __METHOD__); // Source = class and method name where the error occured

        if (!is_writable($this->f_args['path'])) {
            throw new Exception(['code' => 5, 'path' => $this->f_args['path']]);
        }
        // If not dealing with a directory
        if (!is_dir($file_or_dir)) {
            // If unlink failed, possibly due to a race condition, return an error
            if (!unlink($file_or_dir)) {
                throw new Exception(['code' => 8, 'path' => $file_or_dir]);
            }
        }

        // Handle subdirectories (if any)
        $objects = scandir($file_or_dir);
        // Removes "." and ".." from the array, since they are not needed.
        $objects = array_diff($objects, array('..', '.'));

        foreach ($objects as $object) {
            if (is_dir($file_or_dir . '/' . $object)) {
                // If dealing with a subdirectory, perform another simple_delete()
                $this->simple_delete($file_or_dir . '/' . $object);
            } else {
                // Check for write permissions
                if (!is_writable($file_or_dir . '/' . $object)) {
                    throw new Exception(['code' => 5, 'path' => $file_or_dir . '/' . $object]);
                }
                // If unlink failed, possibly due to a race condition, return an error
                if (!unlink($file_or_dir . '/' . $object)) {
                    throw new Exception(['code' => 8, 'path' => $file_or_dir . '/' . $object]);
                }
            }
        }
        if (!rmdir($file_or_dir)) {
            throw new Exception(['code' => 10, 'path' => $file_or_dir . '/' . $object]);
        } // Delete directory
        return true; // Return true on success
    }
    /**
     *  Method to delete all files in a directory
     *  @param string $directory Path to directory
     *  @param string $pattern I.e.: *.
     *  @return true
     *  @throws Exception on failure.
     */
    public function delete_files(string $directory, string $pattern)
    {
        // Remove slashes at end if needed
        $directory = rtrim($directory, '/');
        // Re-add slash manually followed by $pattern
        $files = glob($directory . '/' . $pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    // If unlink failed, possibly due to a race condition, return an error
                    throw new Exception(['code' => 8, 'path' => $directory . '/' . $file]);
                }
            }
        }
        return true;
    }
    /**
     *  Method to read a file or the specified parts of it. The default max_line_length is 4096.
     *  Note. This function does not call clearstatcache(); do that from the outside if needed!
     *  @param array $arguments_arr path=REQUIRED (string), start_line=0 (int), max_line_length=4096 (int), lines_to_read=false (int)
     *  @return string
     *  @throws Exception on failure.
     */
    public function read_file_lines(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'path' => ['type' => 'string', 'required' => true],
            'start_line' => ['default' => 0, 'type' => 'int', 'required' => false],
            'max_line_length' => ['default' => 4096, 'type' => 'int', 'required' => false],
            'lines_to_read' => ['default' => false, 'type' => 'int', 'required' => false],
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        if (false === (file_exists($this->f_args['path']))) {
            throw new Exception(['code' => 11, 'path' => $this->f_args['path']]);
        }
        // Only files should be opened for reading
        if (false === (is_file($this->f_args['path']))) {
            throw new Exception(['code' => 15, 'path' => $this->f_args['path']]);
        }
        // Note on is_readable(): If a path is a directory, it will result in a false positive, so we need to use is_file() first.
        // Note on fopen(): In Theory, fopen can fail even if a file is readable;
        // this might happen if a system has too many open file handles.
        if (
            false === (is_readable($this->f_args['path'])) || // Check if the file is readable
            false === ($fp = fopen($this->f_args['path'], "r")) // Attempt to open the file
        ) {
            throw new Exception(['code' => 1, 'path' => $this->f_args['path']]);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp);



        // Reads entire file until End Of File has been reached
        if ($this->f_args['lines_to_read'] === false) {
            $lc = 1;
            $file_content = '';
            while (($buffer = fgets($fp, $this->f_args['max_line_length'])) !== false) {
                if ($lc >= $this->f_args['start_line']) {
                    $file_content .= $buffer;
                }
                ++$lc;
            }
        } else { // Only read the specified number of lines, current line is stored in $lc
            $file_content = '';
            $i = 0;
            $lc = 1;
            $lines_to_read = $this->f_args['lines_to_read'] + $this->f_args['start_line'];
            while ($i < $lines_to_read) {
                if ($lc > $this->f_args['start_line']) {
                    // fgets (read a single line)
                    if (($file_content .= fgets($fp, $this->f_args['max_line_length'])) === false) {
                        throw new Exception(['code' => 14, 'path' => $this->f_args['path']]);
                    }
                } else {
                    if (fgets($fp, $this->f_args['max_line_length']) === false) {
                        throw new Exception(['code' => 14, 'path' => $this->f_args['path']]);
                    }
                } // Just move the position indicator without saving the read data
                ++$i;
                ++$lc;
            }
        }
        // If End Of File was not reached (unexpected at this point)...
        // Note. This chould happen if something interrupts the read process.
        // Note. 2. If "lines_to_read" is used, EOF (feof) is not relevant, since
        //          it will probably never be reached anyway.
        if ((!feof($fp)) && ($this->f_args['lines_to_read'] === false)) {
            fclose($fp);
            throw new Exception(['code' => 4, 'path' => $this->f_args['path']]);
        }
        fclose($fp); // Closing after successful operation
        return $file_content;
    }
    /**
     *  Method to count the lines in files, useful if you want to read a file x lines at a time
     *  within a loop. Also useful to count the lines in your source code.
     *
     *  @param array $arguments_arr path=REQUIRED (string), start_line=0 (int), max_line_length=4096 (int), lines_to_read=false (int)
     *  @return int
     *  @throws Exception on failure.
     */
    public function count_lines(array $arguments_arr)
    {
        // It may be nessecery to count the lines in very large files, so we can read the file, say, 100 lines at a time.
        // Note. Any file-lock should be obtained outside this method, to prevent writing to a file while we are counting the lines in it
        $default_argument_values_arr = array(
            'path' => ['type' => 'string', 'required' => true],
            'start_line' => ['default' => 0, 'type' => 'int', 'required' => false],
            'max_line_length' => ['default' => 4096, 'type' => 'int', 'required' => false],
            'lines_to_read' => ['default' => false, 'type' => 'int', 'required' => false],
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        // Attempt to open the file for reading
        if (($fp = @fopen($this->f_args['path'], "r")) === false) {
            throw new Exception(['code' => 1, 'path' => $this->f_args['path']]);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp);


        $lc = 0;
        while (fgets($fp, $this->f_args['max_line_length']) !== false) {
            ++$lc;
        }

        // If End Of File was not reached (unexpected at this point)...
        // Note. This chould happen if something interrupts the read process.
        if ((!feof($fp))) {
            fclose($fp);
            throw new Exception(['code' => 4, 'path' => $this->f_args['path']]);
        }
        fclose($fp); // Closing after a successful execution
        return $lc; // Return the Line Number
    }
    /**
     *  Method to write to the filesystem.
     *
     *  A file lock should automatically be obtained, ideal in concurrency siturations.
     *
     *  @param array $arguments_arr path=REQUIRED (string), content='' (string), mode=w (string)
     *  @return true
     *  @throws Exception on failure.
     */
    public function write_file(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'path' => ['required' => true, 'type' => 'string'],
            'content' => ['required' => false, 'type' => 'string', 'default' => ''],
            'permissions' => ['required' => 'false', 'type' => 'int', 'default' => 0775],
            'mode' => ['required' => false, 'type' => 'string', 'default' => 'w'], // w = open for writing, truncates the file, and attempts to create the file if it does not exist.
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        $this->additional_error_data = array(
            'source' => __METHOD__, // The class and method name where the error occured
        );

        // Attempt to open the file
        if (($fp = @fopen($this->f_args['path'], $this->f_args['mode'])) === false) {
            throw new Exception(['action' => 'fopen', 'path' => $this->f_args['path']]);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp);


        // If 0 bytes is written (!fwrite(...)) will cause an error, hence (false === fwrite(...))
        if (false === fwrite($fp, $this->f_args['content'])) {
            throw new Exception(['code' => 2, 'path' => $this->f_args['path']]);
        } else {
            fclose($fp);
            // We should also update file permissions after creating the file
            chmod($this->f_args['path'], $this->f_args['permissions']);
            return true;
        } // fclose also releases the file lock
    }
    /**
     *  Method to create recursively create directories - the way you expect :-)
     *
     *   @param array $arguments_arr path=REQUIRED (string), permissions=0775 (int)
     *   @return true
     *   @throws Exception on failure.
     */
    public function create_directory(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'path' => ['required' => true, 'type' => 'string'],
            'base_path' => ['required' => false, 'type' => 'string'],
            'permissions' => ['required' => 'false', 'type' => 'int', 'default' => 0775], // This should be an int value!
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        // If base_path is defined, subtract base_path from the path array
        // To get a list of directories we need to make
        // Note. This step is important in order to correctly set permissions recursively on the directories
        if (isset($this->f_args['base_path'])) {
            // Create an array containing directories found in the path and base_path
            $path_arr = explode('/', trim($this->f_args['path'], '/'));
            $base_path_arr = explode('/', trim($this->f_args['base_path'], '/'));
            // Find out how many directories should be created
            $dirs_from_base_arr = array_diff_key($path_arr, $base_path_arr);
            $dirs_to_make_arr = array();

            // Add the base_path to the $dir_path string
            $dir_path = $this->f_args['base_path'];
            foreach ($dirs_from_base_arr as $dir) {
                $dir_path .= $dir . '/';
                // If the $dir_path did not exist, queue it for creation
                if (!file_exists($dir_path)) {
                    $dirs_to_make_arr[] = $dir_path;
                }
            }
        } else {
            // If base_path was not defined, we compromise by
            // only setting permissions on the last directory found in the path—
            // this will often be sufficient anyway!
            $dirs_to_make_arr[] = $this->f_args['path'];
        }

        // Check if there's anything to create
        if (count($dirs_to_make_arr) < 1) {
            throw new Exception(['code' => 9, 'path' => $this->f_args['path']]);
        }


        // Finally attempt to create the directories with the desired permissions
        foreach ($dirs_to_make_arr as $dir) {
            // Note. Error supression is on purpose,
            // since we only care if the action failed or not at this point.
            // If the action failed, it will most likely be due to permissions.
            // Subdirectories will be created recursively if present.
            if (!@mkdir($dir, $this->f_args['permissions'], false)) { // Bug? Perform chmod after!
                throw new Exception(['code' => 3, 'path' => $this->f_args['path']]);
            }
            // An apparent bug in PHPs mkdir() is causing directories to be made with
            // wrong permissions. Performing a chmod after creating the directory seems to solve this problem
            // - has this issue been reported to developers of PHP?
            chmod($dir, $this->f_args['permissions']);
        }
        // If all directories was created successfully return true
        return true;
    }
    /**
     *  Method to obtain a file lock before reading (shared) or writing (single) from/to files.
     *
     *   @param $fp file pointer.
     *   @param boolean $LOCK_SH The type of lock to be obtained.
     *   @return true
     *   @throws Exception on failure.
     */
    public function obtain_lock($fp, $LOCK_SH = false)
    {
        // Add the right bitmask for use with flock
        if ($LOCK_SH === true) {
            $lock_type = LOCK_SH | LOCK_NB;
        } else {
            $lock_type = LOCK_EX | LOCK_NB;
        }
        if (!is_writable($this->f_args['path'])) {
            throw new Exception(['code' => 5, 'path' => $this->f_args['path']]);
        }
        $i = 0;
        while (!flock($fp, $lock_type)) {
            ++$i;
            if (($i == $this->lock_max_time)) {
                throw new Exception(['code' => 7, 'path' => $this->f_args['path']]);
            }
            $rand = rand(100, 1000);
            usleep($rand * 1000); // nanoseconds->milliseconds
        }
        return true; // Return true if file-lock was successfully obtained

    }
    private function read_file_settings(array $arg_arr)
    {
        trigger_error('This method will probably be removed soon.', E_USER_DEPRECATED);
        $read_setting = array();
        $read_setting['start_line'] = 0; // The line to start reading from, use this if you want to resume reading a file at a certain point (or use ftell)
        $read_setting['max_line_length'] = 4096; // Max length of line In bytes (This should be large enough to hold the longest line)
        $read_setting['lines_to_read'] = false; // False indicates the entire file should be read

        foreach ($arg_arr as $key => $value) {
            $read_setting["$key"] = $value; // Overwrite defaults if nessecery
        }
        return $read_setting;
    }

    /**
     * Method to "stream" a file over HTTP in response to a client request.
     * "range" requests are supported, making it possible to stream audio and video files from PHP.
     * This function exits() on success, and outputs an error array on failure.
     * error 1 = failed to open file. error 11 = file did not exist
     * @param array $arguments_arr
     * @throws Exception on failure.
     * 
     */
    public function http_stream_file(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'path' => ['required' => true, 'type' => 'string'],
            'chunk_size' => ['required' => false, 'type' => 'int', 'default' => 8192],
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        // If an error occurs...
        $this->additional_error_data = array(
            'source' => __METHOD__,
            'chunk_size' => $this->f_args['chunk_size'],
        );

        // Variables
        $response_headers = array();

        

        // -----------------------
        // Handle the file--------
        // -----------------------
        if (!file_exists($this->f_args['path'])) {
            // The file did not exist, handle the error elsewhere
            throw new Exception(['code' => 11, 'path' => $this->f_args['path']]);
        }
        if (($file_size = filesize($this->f_args['path'])) === false) {
            throw new Exception(['code' => 12, 'path' => $this->f_args['path']]);
        }

        $start = 0;
        $end = $file_size - 1; // Minus 1 (Byte ranges are zero-indexed)

        // Attempt to Open file for (r) reading (b=binary safe)
        if (($fp = @fopen($this->f_args['path'], 'rb')) == false) {
            throw new Exception(['code' => 1, 'path' => $this->f_args['path']]);
        }

        // If file was successfully opened, attempt to obtain_lock
        $lock_status = $this->obtain_lock($fp, true);

        // -----------------------
        // Handle "range" requests
        // -----------------------
        // Determine if the "range" Request Header was set
        $http_range = $this->sg->get_SERVER('HTTP_RANGE');


        if (isset($http_range)) {

            // Parse the range header
            if (preg_match('|=([0-9]+)-([0-9]+)$|', $http_range, $matches)) {
                $start = $matches["1"];
                $end = $matches["2"] - 1;
            } elseif (preg_match('|=([0-9]+)-?$|', $http_range, $matches)) {
                $start = $matches["1"];
            }

            // Make sure we are not out of range
            if (($start > $end) || ($start > $file_size) || ($end > $file_size) || ($end <= $start)) {
                http_response_code(416);
                exit();
            }

            // Position the file pointer at the requested range
            fseek($fp, $start);

            // Respond with 206 Partial Content
            http_response_code(206);

            // A "content-range" response header should only be sent if the "range" header was used in the request
            $response_headers['content-range'] = 'bytes ' . $start . '-' . $end . '/' . $file_size;
        } else {
            // If the range header is not present, respond with a 200 code and start sending some content
            http_response_code(200);
        }

        // Tell the client we support range-requests
        $response_headers['accept-ranges'] = 'bytes';
        // Set the content length to whatever remains
        $response_headers['content-length'] = ($file_size - $start);

        // ---------------------
        // Send the file headers
        // ---------------------
        // Send the "last-modified" response header
        // and compare with the "if-modified-since" request header (if present)
        $if_modified_since = $this->sg->get_SERVER('HTTP_IF_MODIFIED_SINCE');

        if (($timestamp = filemtime($this->f_args['path'])) !== false) {
            $response_headers['last-modified'] = gmdate("D, d M Y H:i:s", $timestamp) . ' GMT';
            if ((isset($if_modified_since)) && ($if_modified_since == $response_headers['last-modified'])) {
                http_response_code(304); // Not Modified

                // The below uncommented lines was used while testing,
                // and might still be useful if having problems caching a file.
                // Use it to manually compare "if-modified-since" and "last-modified" while debugging.

                // $datafile = $if_modified_since . PHP_EOL . $this->headers['last-modified'];
                // file_put_contents('/var/www/testing/tmp/' . $name . '.txt', $datafile);

                exit();
            }
        }

        // Get additional headers for the requested file-type
        if (($extension = $this->ft->has_extension($this->f_args['path'])) === false) {
            throw new Exception(['code' => 13, 'path' => $this->f_args['path']]);
        }

        $response_headers = $this->ft->get_file_headers($extension) + $response_headers;

        foreach ($response_headers as $header => $value) {
            header($header . ': ' . $value);
        }

        // ---------------------
        // Start the file output
        // ---------------------
        $buffer = $this->f_args['chunk_size'];
        while (!feof($fp) && ($pointer = ftell($fp)) <= $end) {

            // If next $buffer will pass $end,
            // calculate remaining size
            if ($pointer + $buffer > $end) {
                $buffer = $end - $pointer + 1;
            }

            // WARNING:
            // * In regards to this loop and calling fread inside a loop. *
            // The error supression of fread is intentional.
            // Without the supression we risk filling up all available disk space
            // with error logging on some servers - in mere seconds!!
            // While the specific obtain_lock() error should be fixed, the supression
            // was left in place just to be safe..
            echo @fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit();
    }

    use \doorkeeper\lib\class_traits\no_set;
}
