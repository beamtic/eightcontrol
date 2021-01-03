<?php

/**
 *           Doorkeeper File Handler
 *
 *                Class to handle creating, editing, and deleting files and directories with support for locking.
 *
 *              write_file() should automatically handle locking, allowing use in some concurrency situations.
 *              This probably does not work for all applications, but should be enough in many cases.
 *              You may want to use a database for "heavy" load applications.
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

use doorkeeper\lib\php_helpers\{superglobals};
use Exception;

class file_handler
{

    private $error_messages = [
        1 => 'Failed to open file.',
        2 => 'Failed to create or write file.',
        3 => 'Failed to create directory.',
        4 => 'Unexpected fgets() fail after reading from file.',
        5 => 'The file or directory is not writeable.',
        6 => 'Unable to write to file, but the file is writable.',
        7 => 'Unable to obtain file lock (timeout reached).',
        8 => 'Unable to unlink file (possible race condition).',
        9 => 'File or directory already exists.',
        10 => 'Unable to remove directory.',
        11 => 'The file did not exist.',
        12 => 'Unexpected filesize() fail.',
        13 => 'The file path had no file extension.',
        14 => 'Failed to read line in file. This can happen if the process was interrupted.',
        15 => 'Failed to open file. The supplied path is not a file.',
        16 => 'Missing required dependencies, please check that everything was supplied.',
        17 => 'Unable to open remote resource.',
    ];

    private $http_user_agent = '';

    private $lock_max_time = 20; // File-lock maximum time in seconds.

    private $ft; // The $file_types object, used in http_stream_file()
    private $sg;

    public function __construct(superglobals $superglobals = null, file_types $file_types = null)
    {
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
        if (!is_writable($this->f_args['path'])) {
            $e_msg = $this->error_messages["5"] . ' @' . $this->f_args['path'] . ' ';
            throw new Exception($e_msg, 5);
        }
        // If not dealing with a directory
        if (!is_dir($file_or_dir)) {
            // If unlink failed, possibly due to a race condition, return an error
            if (!unlink($file_or_dir)) {
                $e_msg = $this->error_messages["8"] . ' @' . $file_or_dir . ' ';
                throw new Exception($e_msg, 8);
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
                    $e_msg = $this->error_messages["5"] . ' @' . $file_or_dir . '/' . $object . ' ';
                    throw new Exception($e_msg, 5);
                }
                // If unlink failed, possibly due to a race condition, return an error
                if (!unlink($file_or_dir . '/' . $object)) {
                    $e_msg = $this->error_messages["8"] . ' @' . $file_or_dir . '/' . $object . ' ';
                    throw new Exception($e_msg, 8);
                }
            }
        }
        if (!rmdir($file_or_dir)) {
            $e_msg = $this->error_messages["10"] . ' @' . $file_or_dir . '/' . $object . ' ';
            throw new Exception($e_msg, 10);
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
                    $e_msg = $this->error_messages["8"] . ' @' . $directory . '/' . $file . ' ';
                    throw new Exception($e_msg, 8);
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
    public function read_file_lines(string $path, int $start_line = 0, int $max_line_length = 4096, int $lines_to_read = null)
    {
        if (false === (file_exists($path))) {
            $e_msg = $this->error_messages["11"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 11);
        }
        // Only files should be opened for reading
        if (false === (is_file($path))) {
            $e_msg = $this->error_messages["15"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 15);
        }
        // Note on is_readable(): If a path is a directory, it will result in a false positive, so we need to use is_file() first.
        // Note on fopen(): In Theory, fopen can fail even if a file is readable;
        // this might happen if a system has too many open file handles.
        if (
            false === (is_readable($path)) || // Check if the file is readable
            false === ($fp = fopen($path, "r")) // Attempt to open the file
        ) {
            $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 1);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp, $path);

        // Reads entire file until End Of File has been reached
        if ($lines_to_read === null) {
            $lc = 1;
            $file_content = '';
            while (($buffer = fgets($fp, $max_line_length)) !== false) {
                if ($lc >= $start_line) {
                    $file_content .= $buffer;
                }
                ++$lc;
            }
        } else { // Only read the specified number of lines, current line is stored in $lc
            $file_content = '';
            $i = 0;
            $lc = 1;
            $lines_to_read = $lines_to_read + $start_line;
            while ($i < $lines_to_read) {
                if ($lc > $start_line) {
                    // fgets (read a single line)
                    if (($file_content .= fgets($fp, $max_line_length)) === false) {
                        $e_msg = $this->error_messages["14"] . ' @' . $path . ' ';
                        throw new Exception($e_msg, 14);
                    }
                } else {
                    if (fgets($fp, $max_line_length) === false) {
                        $e_msg = $this->error_messages["14"] . ' @' . $path . ' ';
                        throw new Exception($e_msg, 14);
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
        if ((!feof($fp)) && ($lines_to_read === false)) {
            fclose($fp);
            $e_msg = $this->error_messages["4"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 4);
        }
        fclose($fp); // Closing after successful operation
        return $file_content;
    }
    /**
     *  Method to count the lines in files, useful if you want to read a file x lines at a time
     *  within a loop. Also useful to count the lines in your source code.
     *
     *  Returns the line count as an int, or an array of int's if $search_string is used.
     * 
     *  
     *  @throws Exception on failure.
     */
    public function count_lines(string $path, int $max_line_length = 4096, string $search_string = null)
    {
        // It may be nessecery to count the lines in very large files, so we can read the file, say, 100 lines at a time.
        // Note. Any file-lock should be obtained outside this method, to prevent writing to a file while we are counting the lines in it

        // Attempt to open the file for reading
        if (($fp = @fopen($path, "r")) === false) {
            $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 1);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp, $path);

        // If we are supposed to return the line number where a given string occurs
        $lc = 0;
        if (null !== $search_string) {
            $line_occurrences = [];
            while (($line = fgets($fp, $max_line_length)) !== false) {
                if (strpos($line, $search_string) !== false) {
                    $line_occurrences[] = $lc;
                }
                ++$lc;
            }
        } else {
            while (fgets($fp, $max_line_length) !== false) {
                ++$lc;
            }
        }

        // If End Of File was not reached (unexpected at this point)...
        // Note. This chould happen if something interrupts the read process.
        if ((!feof($fp))) {
            fclose($fp);
            $e_msg = $this->error_messages["4"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 4);
        }
        fclose($fp); // Closing after a successful execution

        // Return the Line Number or array of line numbers
        return (isset($line_occurrences)) ? $line_occurrences : $lc;
    }
    /**
     *  Method to write to the filesystem.
     *
     *  A file lock should automatically be obtained, ideal in concurrency siturations.
     *
     *  @return true
     *  @throws Exception on failure.
     */
    public function write_file(string $path, string $content = '', string $mode = 'w', int $permissions = 0775)
    {
        // Attempt to open the file
        if (($fp = @fopen($path, $mode)) === false) {
            $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 1);
        }

        // Attempt to obtain_lock
        $this->obtain_lock($fp, $path);


        // If 0 bytes is written (!fwrite(...)) will cause an error, hence (false === fwrite(...))
        if (false === fwrite($fp, $content)) {
            $e_msg = $this->error_messages["2"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 2);
        } else {
            fclose($fp);
            // We should also update file permissions after creating the file
            chmod($path, $permissions);
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
    public function create_directory(string $path, string $base_path = null, int $permissions = 0775)
    {
        // If base_path is defined, subtract base_path from the path array
        // To get a list of directories we need to make
        // Note. This step is important in order to correctly set permissions recursively on the directories
        if (null !== $base_path) {
            // Create an array containing directories found in the path and base_path
            $path_arr = explode('/', trim($path, '/'));
            $base_path_arr = explode('/', trim($base_path, '/'));
            // Find out how many directories should be created
            $dirs_from_base_arr = array_diff_key($path_arr, $base_path_arr);
            $dirs_to_make_arr = array();

            // Add the base_path to the $dir_path string
            $dir_path = $base_path;
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
            $dirs_to_make_arr[] = $path;
        }

        // Check if there's anything to create
        if (count($dirs_to_make_arr) < 1) {
            $e_msg = $this->error_messages["9"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 9);
        }


        // Finally attempt to create the directories with the desired permissions
        foreach ($dirs_to_make_arr as $dir) {
            // Note. Error supression is on purpose,
            // since we only care if the action failed or not at this point.
            // If the action failed, it will most likely be due to permissions.
            // Subdirectories will be created recursively if present.
            if (!@mkdir($dir, $permissions, false)) { // Bug? Perform chmod after!
                $e_msg = $this->error_messages["3"] . ' @' . $path . ' ';
                throw new Exception($e_msg, 3);
            }
            // An apparent bug in PHPs mkdir() is causing directories to be made with
            // wrong permissions. Performing a chmod after creating the directory seems to solve this problem
            // - has this issue been reported to developers of PHP?
            chmod($dir, $permissions);
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
    public function obtain_lock($fp, string $path, $LOCK_SH = false)
    {
        // Add the right bitmask for use with flock
        if ($LOCK_SH === true) {
            $lock_type = LOCK_SH | LOCK_NB;
        } else {
            $lock_type = LOCK_EX | LOCK_NB;
        }
        if (!is_writable($path)) {
            $e_msg = $this->error_messages["5"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 5);
        }
        $i = 0;
        while (!flock($fp, $lock_type)) {
            ++$i;
            if (($i == $this->lock_max_time)) {
                $e_msg = $this->error_messages["7"] . ' @' . $path . ' ';
                throw new Exception($e_msg, 7);
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
     * Method to scan a directory and return the result as an array.
     */
    public function scan_dir(string $path_to_dir)
    {
        $items_arr = scandir($path_to_dir);
        // Make sure to remove "." and ".." since they are not needed.
        return is_array($items_arr) ? array_diff($items_arr, [".", ".."]) : false;
    }

    /**
     * Method to "stream" a file over HTTP in response to a client request.
     * "range" requests are supported, making it possible to stream audio and video files from PHP.
     * This function exits() on success, and outputs an error array on failure.
     * error 1 = failed to open file. error 11 = file did not exist
     * @throws Exception on failure.
     * 
     */
    public function http_stream_file(string $path, int $chunk_size = 8192)
    {
        if ((null === $this->sg) || (null === $this->ft)) {
            $e_msg = $this->error_messages["16"] . ' ';
            throw new Exception($e_msg, 16);
        }

        // Variables
        $response_headers = array();



        // -----------------------
        // Handle the file--------
        // -----------------------
        if (!file_exists($path)) {
            // The file did not exist, handle the error elsewhere
            $e_msg = $this->error_messages["11"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 11);
        }
        if (($file_size = filesize($path)) === false) {
            $e_msg = $this->error_messages["12"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 12);
        }

        $start = 0;
        $end = $file_size - 1; // Minus 1 (Byte ranges are zero-indexed)

        // Attempt to Open file for (r) reading (b=binary safe)
        if (($fp = @fopen($path, 'rb')) == false) {
            $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 1);
        }

        // If file was successfully opened, attempt to obtain_lock
        $lock_status = $this->obtain_lock($fp, $path, true);

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

        if (($timestamp = filemtime($path)) !== false) {
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
        if (($extension = $this->ft->has_extension($path)) === false) {
            $e_msg = $this->error_messages["13"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 13);
        }

        $response_headers = $this->ft->get_file_headers($extension) + $response_headers;

        foreach ($response_headers as $header => $value) {
            header($header . ': ' . $value);
        }

        // ---------------------
        // Start the file output
        // ---------------------
        $buffer = $chunk_size;
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

    /**
     * Buffer-based Download. Data is written to disk as it is received, avoiding running out of memory.
     * @return true on success
     * @throws Exception on failure.
     */
    public function download_to_file(string $url, string $output_file_path, string $method = 'GET', array $post_data = null, array $request_headers = [], int $max_line_length = 1024)
    {
        $request_headers['user-agent'] = $this->http_user_agent;
        foreach ($request_headers as $key => $value) {
            $request_headers_str = $key . ': ' . $value . "\r\n";
        }
        $request_headers_str = rtrim($request_headers_str, "\r\n");

        // Set request method and headers...
        $http_arr = array('method' => $method, 'header' => $request_headers_str);

        // If dealing with a POST, include $post_data if needed
        if ($method === 'POST') {
            $parms = '';
            if (null !== $post_data) {
                foreach ($post_data as $key => $value) {
                    $parms .= $key . '=' . $value . '&';
                }
                $parms = rtrim($parms, "&");
            }
            if (!empty($parms)) {
               $http_arr['content'] = $parms;
            }
        }

        // Create the stream context
        $context = stream_context_create(array('http' => $http_arr));

        // Attempt to open the remote resource
        $resource = fopen($url, "r", false, $context);
        if (!$resource) {
            $e_msg = $this->error_messages["17"] . ' @' . $url . ' ';
            throw new Exception($e_msg, 17);
        }
        while (!feof($resource)) {
            $line = fgets($resource, $max_line_length);
            $this->write_file($output_file_path, $line, 'a'); // a = append to end
        }
        fclose($resource);
        return true;
    }

    use \doorkeeper\lib\class_traits\no_set;
}
