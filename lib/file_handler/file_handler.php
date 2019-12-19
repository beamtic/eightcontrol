<?php
/**
 *           Doorkeeper File Handler
 *
 *                Class to handle creation, editing, and deletion of files and directories with support for locking
 *
 *                The handle_error() function is used to return an error, which can then be handled and translated from the calling location.
 *
 *              write_file() should automatically handle locking, allowing use in some concurrency siturations.
 *              This probably does not work for all applications, but should be enough in many cases.
 *              You may want to use a database for "heavy" load applications.
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

class file_handler
{

    private $additional_error_data = array();
    // private $helpers; // The $helpers object

    private $f_args;
    private $lock_max_time = 20; // File-lock maximum time in seconds.

    private $ft; // The $file_types object, used in http_stream_file()

    public function __construct(object $helpers, object $superglobals, object $file_types)
    {
        $this->helpers = $helpers; // Helper methods to solve common programming problems
        $this->ft = $file_types;
        $this->sg = $superglobals;
    }
    /**
     *  A standard method to delete both directories and files,
     *  if the permissions allow it, a directory will be deleted, including subdirectories.
     *  @param string $file_or_dir Path to file or directory
     *
     */
    public function simple_delete(string $file_or_dir)
    {
        $this->additional_error_data = array('source' => __METHOD__); // Source = class and method name where the error occured

        if (is_writable($file_or_dir)) { // Check for write permissions
            if (is_dir($file_or_dir)) { // Do this if we are dealing with a directory
                $objects = scandir($file_or_dir); // Handle subdirectories (if any)
                $objects = array_diff($objects, array('..', '.')); // Removes "." and ".." from the array, since they are not needed.
                foreach ($objects as $object) {
                    if (is_dir($file_or_dir . '/' . $object)) {
                        $this->simple_delete($file_or_dir . '/' . $object); // If dealing with a subdirectory, perform another simple_delete()
                    } else {
                        if (is_writable($file_or_dir . '/' . $object)) { // Check for write permissions
                            if (!unlink($file_or_dir . '/' . $object)) {
                                // If unlink failed, possibly due to a race condition, return an error
                                return $this->handle_error(array('action' => 'unlink', 'path' => $file_or_dir . '/' . $object));
                            }
                        } else {
                            return $this->handle_error(array('action' => 'is_writable', 'path' => $file_or_dir . '/' . $object));
                        }
                    }
                }
                if (!rmdir($file_or_dir)) {
                    return $this->handle_error(array('action' => 'rmdir', 'path' => $file_or_dir . '/' . $object));
                } // Delete directory
                return true; // Return true on success
            } else {
                if (!unlink($file_or_dir)) {
                    // If unlink failed, possibly due to a race condition, return an error
                    return $this->handle_error(array('action' => 'unlink', 'path' => $file_or_dir));
                }
            }
        } else { // If the file or directory was not writable, we show an error
            return $this->handle_error(array('action' => 'is_writable', 'path' => $file_or_dir . '/' . $object));
        }

    }
    /**
     *  Method to delete all files in a directory
     *  @param string $directory Path to directory
     *  @param string $pattern I.e.: *.
     *
     */
    public function delete_files(string $directory, string $pattern)
    {
        $directory = rtrim($directory, '/'); // Remove slashes at end if needed
        $files = glob($directory . '/' . $pattern); // Re-add slash manually followed by $pattern
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    // If unlink failed, possibly due to a race condition, return an error
                    return $this->handle_error(array('action' => 'unlink', 'path' => $directory . '/' . $file));
                }
            }
        }
        return true;
    }
    /**
     *  Method to read a file or the specified parts of it
     *
     *  @param array $arguments_arr path=REQUIRED (string), start_line=0 (int), max_line_length=4096 (int), lines_to_read=false (int)
     *
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

        // If an error occurs, add some shared debugging info
        $this->additional_error_data = array(
            'source' => __METHOD__,
            'start_line' => $this->f_args['start_line'],
            'max_line_length' => $this->f_args['max_line_length'],
            'lines_to_read' => $this->f_args['lines_to_read'],
        );
        if ($fp = fopen($this->f_args['path'], "r")) {

            // Attempt to obtain_lock
            $lock_status = $this->obtain_lock($fp, true);
            // Return the error array if the timeout is reached before obtaining the lock
            if ($lock_status !== true) {
                return $lock_status;
            }
            if ($this->f_args['lines_to_read'] === false) { // Reads entire file until End Of File has been reached
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
                        $file_content .= fgets($fp, $this->f_args['max_line_length']);
                    } else {fgets($fp, $this->f_args['max_line_length']);} // Just move the position indicator without saving the read data
                    ++$i; ++$lc;
                }
            }
            if ((!feof($fp)) && ($this->f_args['lines_to_read'] === false)) { // If End Of File was not reached (unexpected) at this point...
                if (!$this->additional_error_data['ftell'] = ftell($fp)) { // Get location of file pointer as debug info
                    fclose($fp); // Closing after ftell
                    $this->additional_error_data['ftell'] = 'false'; // If the ftell also failed, include this info
                    return $this->handle_error(array('action' => 'feof', 'path' => $this->f_args['path']));
                }
                fclose($fp); // Closing after ftell
                return $this->handle_error(array('action' => 'feof', 'path' => $this->f_args['path']));
            }
            fclose($fp); // Closing after successful operation
            return $file_content;
        } else { // If the fopen failed, we throw an error
            return $this->handle_error(array('action' => 'fopen', 'path' => $this->f_args['path']));
        }

    }
    /**
     *  Method to count the lines in files, useful if you want to read a file x lines at a time
     *  within a loop. Also useful to count the lines in your source code.
     *
     *  @param array $arguments_arr path=REQUIRED (string), start_line=0 (int), max_line_length=4096 (int), lines_to_read=false (int)
     *  @return mixed
     */
    public function count_lines(array $arguments_arr)
    {
        // It may be nessecery to count the lines in very large files, so we can read the file, say, 100 lines at a time.
        // Note. Any file-lock should be obtained outside this method, to prevent writing to a file while we are counting the lines in it
        $default_argument_values_arr = array(
            'path' => ['type' => 'string', 'required' => true],
            'start_line' => ['default' => '0', 'type' => 'string', 'required' => false],
            'max_line_length' => ['default' => '0', 'type' => 'string', 'required' => false],
            'lines_to_read' => ['default' => false, 'type' => 'int', 'required' => false],
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        // If an error occurs, add some shared debugging info
        $this->additional_error_data = array(
            'source' => __METHOD__,
            'max_line_length' => $this->f_args['max_line_length'],
        );
        if ($fp = @fopen($this->f_args['path'], "r")) {

            // Attempt to obtain_lock
            $lock_status = $this->obtain_lock($fp, true);
            // Return the error array if the timeout is reached before obtaining the lock
            if ($lock_status !== true) {
                return $lock_status;
            }
            $lc = 0;
            while (($buffer = fgets($fp, $this->f_args['max_line_length'])) !== false) {
                ++$lc;
            }
            if ((!feof($fp))) { // If End Of File was not reached (unexpected) at this point...
                if (!$this->additional_error_data['ftell'] = ftell($fp)) { // Get location of file pointer as debug info
                    fclose($fp); // Closing after ftell
                    $this->additional_error_data['ftell'] = 'false'; // If the ftell also failed, include this info
                    return $this->handle_error(array('action' => 'feof', 'path' => $this->f_args['path']));
                }
                fclose($fp); // Closing after ftell
                return $this->handle_error(array('action' => 'feof', 'path' => $this->f_args['path']));
            }
            fclose($fp); // Closing after a successful execution
            return $lc; // Return the Line Number
        } else {return $this->handle_error(array('action' => 'fopen', 'path' => $this->f_args['path']));}
    }
    /**
     *  Method to write to the filesystem.
     *
     *  A file lock should automatically be obtained, ideal in concurrency siturations.
     *
     *  @param array $arguments_arr path=REQUIRED (string), content='' (string), mode=w (string)
     *  @return mixed
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

        if ($fp = @fopen($this->f_args['path'], $this->f_args['mode'])) {

            // Attempt to obtain_lock
            $lock_status = $this->obtain_lock($fp);
            // Return the error array if the timeout is reached before obtaining the lock
            if ($lock_status !== true) {
                return $lock_status;
            }
            if (!fwrite($fp, $this->f_args['content'])) {
                return $this->handle_error(array('action' => 'fwrite', 'path' => $this->f_args['path']));
            } else {
              fclose($fp);
              // We should also update file permissions after creating the file
              chmod($this->f_args['path'], $this->f_args['permissions']);
              return true;
            } // fclose also releases the file lock
        } else {
            return $this->handle_error(array('action' => 'fopen', 'path' => $this->f_args['path']));
        }
    }
    /**
     *  Method to create a new directory.
     *
     *   @param array $arguments_arr path=REQUIRED (string), permissions=0775 (int)
     *   @return mixed
     */
    public function create_directory(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'path' => ['required' => true, 'type' => 'string'],
            'permissions' => ['required' => 'false', 'type' => 'int', 'default' => 0775], // This should be an int value!
        );
        $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);
        if (file_exists($this->f_args['path'])) {
            $this->additional_error_data = array(
                'source' => __METHOD__, // The class and method name where the error occured
            );
            return $this->handle_error(array('action' => 'file_exists', 'path' => $this->f_args['path']));
        }
        if (!mkdir($this->f_args['path'], 0777, true)) { // Bug? Perform chmod after!
            return $this->handle_error(array('action' => 'mkdir', 'path' => $this->f_args['path']));
        } else {
            // An apparent bug in PHPs mkdir() is causing directories to be made with
            // wrong permissions. Performing a chmod after creating the directory seems to solve this problem - has this been reported to developers of PHP?
            chmod($this->f_args['path'], $this->f_args['permissions']);
            return true;
        }
    }
    /**
     *  Method to obtain a file lock before reading (shared) or writing (single) from/to files.
     *
     *   @param $fp file pointer.
     *   @param boolean $LOCK_SH The type of lock to be obtained.
     *   @return mixed
     */
    public function obtain_lock($fp, $LOCK_SH = false)
    { 
        // Add the right bitmask for use with flock
        if ($LOCK_SH === true) {
            $lock_type = LOCK_SH | LOCK_NB;
        } else {
            $lock_type = LOCK_EX | LOCK_NB;
        }
        if (is_writable($this->f_args['path'])) {
            $i = 0;
            while (!flock($fp, $lock_type)) {
                ++$i;
                if (($i == $this->lock_max_time)) {
                    return $this->handle_error(array('action' => 'flock', 'path' => $this->f_args['path']));
                }
                $rand = rand(100, 1000);
                usleep($rand * 1000); // nanoseconds->milliseconds
            }
            return true; // Return true if file-lock was successfully obtained
        } else {
            return $this->handle_error(array('action' => 'is_writable', 'path' => $this->f_args['path']));
        }
    }
    private function read_file_settings(array $arg_arr)
    {
        $read_setting = array();
        $read_setting['start_line'] = 0; // The line to start reading from, use this if you want to resume reading a file at a certain point (or use ftell)
        $read_setting['max_line_length'] = 4096; // Max length of line In bytes (This should be large enough to hold the longest line)
        $read_setting['lines_to_read'] = false; // False indicates the entire file should be read

        foreach ($arg_arr as $key => $value) {
            $read_setting["$key"] = $value; // Overwrite defaults if nessecery
        }
        return $read_setting;
    }
    private function handle_error(array $arguments_arr)
    {
        $default_argument_values_arr = array(
            'action' => ['required' => true, 'type' => 'string'],
            'path' => ['required' => false, 'type' => 'string', 'default' => ''],
        );
        $this->f_args = $this->helpers->handle_arguments($arguments_arr, $default_argument_values_arr);

        $aed_html_table = '';

        if (count($this->additional_error_data) >= 1) { // Has to count, since we may have strings as keys.
            foreach ($this->additional_error_data as $key => $value) {
                $aed_html_table .= '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
            }$aed_html_table = '<section><h1>Additional error data:</h1><table>' . $aed_html_table . '</table></section>';
        } else { $aed_html_table = '';}

        switch ($this->f_args['action']) {
            case "fopen":
                $error_arr = array(
                    'error' => '1',
                    'msg' => 'Failed to open file.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "fwrite":
                $error_arr = array(
                    'error' => '2',
                    'msg' => 'Failed to create or write file.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "mkdir":
                $error_arr = array(
                    'error' => '3',
                    'msg' => 'Failed to create directory.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "feof":
                $error_arr = array(
                    'error' => '4',
                    'msg' => 'Unexpected fgets() fail after reading from file.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "is_writable":
                $error_arr = array(
                    'error' => '5',
                    'msg' => 'The file or directory is not writeable.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "file_put_contents":
                $error_arr = array(
                    'error' => '6',
                    'msg' => 'Unable to write to file, but the file is writable.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "flock":
                $error_arr = array(
                    'error' => '7',
                    'msg' => 'Unable to obtain file lock (timeout reached).',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "unlink":
                $error_arr = array(
                    'error' => '8',
                    'msg' => 'Unable to unlink file, possible race condition.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "file_exists":
                $error_arr = array(
                    'error' => '9',
                    'msg' => 'File or directory already exists.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "rmdir":
                $error_arr = array(
                    'error' => '10',
                    'msg' => 'Unable to remove directory.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "!file_exists":
                $error_arr = array(
                    'error' => '11',
                    'msg' => 'The file did not exist.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "filesize":
                $error_arr = array(
                    'error' => '12',
                    'msg' => 'Unexpected filesize() fail.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
            case "has_extension":
                $error_arr = array(
                    'error' => '13',
                    'msg' => 'The file path had no file extension.',
                    'path' => $this->f_args['path'],
                    'aed' => $aed_html_table,
                );
                break;
        }

        return $error_arr; // Return the error, and handle it elsewhere
    }

    /**
     * Method to "stream" a file over HTTP in response to a client request.
     * "range" requests are supported, making it possible to stream audio and video files from PHP.
     * This function exits() on success, and outputs an error array on failure.
     * error 1 = failed to open file. error 11 = file did not exist
     * @param array $arguments_arr
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
            return $this->handle_error(array('action' => '!file_exists', 'path' => $this->f_args['path']));
        }
        if (($file_size = filesize($this->f_args['path'])) === false) {
            return $this->handle_error(array('action' => 'filesize', 'path' => $this->f_args['path']));
        }

        $start = 0;
        $end = $file_size - 1; // Minus 1 (Byte ranges are zero-indexed)

        // Open file for (r) reading (b=binary safe)
        if ($fp = @fopen($this->f_args['path'], 'rb')) {
            // Attempt to obtain_lock
            $lock_status = $this->obtain_lock($fp, true);
            // Return the error array if the timeout is reached before obtaining the lock
            if ($lock_status !== true) {
               return $lock_status;
            }
        } else {
            return $this->handle_error(array('action' => 'fopen', 'path' => $this->f_args['path']));
        }

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
            return $this->handle_error(array('action' => 'has_extension', 'path' => $this->f_args['path']));
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
            // Without the supression, we risk filling up all available disk space
            // with error logging on some servers, in mere seconds!!
            // While the specific obtain_lock() error should be fixed, the supression
            // was left in place just to be safe..
            echo @fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit();
    }

}