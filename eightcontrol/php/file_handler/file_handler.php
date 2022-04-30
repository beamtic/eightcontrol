<?php

/**
 *           eightcontrol File Handler
 *
 *                Class to handle creating, editing, and deleting files and directories with support for locking.
 *
 *              write_file() should automatically handle locking, allowing use in some concurrency situations.
 *              This probably does not work for all applications, but should be enough in many cases.
 *              You may want to use a database for "heavy" load applications.
 *
 *         @author Jacob (JacobSeated)
 */

namespace eightcontrol\file_handler;

use eightcontrol\php_helpers\{superglobals, php_helpers};
use Exception;

class file_handler
{

    private array $error_messages = [
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
        11 => 'The file or directory did not exist.',
        12 => 'Unexpected filesize() fail.',
        13 => 'The file path had no file extension.',
        14 => 'Failed to read line in file. This can happen if the process was interrupted.',
        15 => 'Failed to open file. The supplied path is not a file.',
        16 => 'Missing required dependencies, please check that everything was supplied.',
        17 => 'Unable to open remote resource.',
    ];

    private string $http_user_agent = '';
    private array $http_response_headers;

    public bool $prevent_access_to_php_files = true;

    private int $lock_max_time = 20; // File-lock maximum time in seconds.

    private $ft; // The $file_types object, used in http_stream_file()
    private $sg; // Superglobals object
    private $helpers; // PHP Helper functions

    public function __construct(php_helpers $php_helpers, superglobals $superglobals = null, file_types $file_types = null)
    {
        $this->ft = $file_types;
        $this->sg = $superglobals;
        $this->helpers = $php_helpers;
        $this->http_response_headers['server'] = 'File Handler';
    }

    /**
     * A standard method to delete both directories and files; if the permissions allow it a directory will be deleted, including subdirectories.
     * @param string $file_or_dir 
     * @return true 
     * @throws Exception 
     */
    public function simple_delete(string $file_or_dir): bool
    {
        if (false === file_exists($file_or_dir)) {
            $e_msg = $this->error_messages["11"] . ' @' . $file_or_dir . ' ';
            throw new Exception($e_msg, 11);
        }
        if (false === is_writable($file_or_dir)) {
            $e_msg = $this->error_messages["5"] . ' @' . $file_or_dir . ' ';
            throw new Exception($e_msg, 5);
        }
        // If not dealing with a directory
        if (false === is_dir($file_or_dir)) {
            // If unlink failed, possibly due to a race condition, return an error
            if (!unlink($file_or_dir)) {
                $e_msg = $this->error_messages["8"] . ' @' . $file_or_dir . ' ';
                throw new Exception($e_msg, 8);
            } else {
                return true;
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
     * Method to delete all files in a directory. E.g. /path/to/dir/*.txt
     * @param string $directory 
     * @param string $pattern 
     * @return true 
     * @throws Exception 
     */
    public function delete_files(string $directory, string $pattern): bool
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
     * Method to read a file or the specified parts of it. The default max_line_length is 4096.
     * Note. This function does not call clearstatcache(); do that from the outside if needed!
     * @param string $path 
     * @param int $start_line 
     * @param int $max_line_length 
     * @param int|null $lines_to_read 
     * @return string
     * @throws Exception 
     */
    public function read_file_lines(string $path, int $start_line = 0, int $max_line_length = 4096, int $lines_to_read = null): string
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
                    if (($line = fgets($fp, $max_line_length)) === false) {
                        $e_msg = $this->error_messages["14"] . ' @' . $path . ' ';
                        throw new Exception($e_msg, 14);
                    } else {
                        $file_content = $line;
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
        // Note. This could happen if something interrupts the read process.
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
     * Method to count the lines in files, useful if you want to read a file x lines at a time
     * within a loop. Also, useful to count the lines in your source code. Returns the line count as an int, or an array of int's if $search_string is used.
     * @param string $path 
     * @param int $max_line_length 
     * @param string|null $search_string 
     * @return array|int 
     * @throws Exception 
     */
    public function count_lines(string $path, int $max_line_length = 4096, string $search_string = null)
    {
        // It may be necessary to count the lines in very large files, so we can read the file, say, 100 lines at a time.
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
        // Note. This could happen if something interrupts the read process.
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
     * Method to write to the filesystem. A file lock should automatically be obtained, ideal in concurrency situations.
     * @param string $path 
     * @param string $content 
     * @param string $mode 
     * @param int $permissions 
     * @return true 
     * @throws Exception 
     */
    public function write_file(string $path, string $content = '', string $mode = 'w', int $permissions = 0775): bool
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
     * Method to recursively create directories - the way you expect :-)
     * @param string $path 
     * @param string|null $base_path 
     * @param int $permissions 
     * @return true 
     * @throws Exception 
     */
    public function create_directory(string $path, string $base_path = null, int $permissions = 0775): bool
    {
        if (null !== $base_path) {
            trigger_error('The $base_path parameter is deprecated and will be removed in a future update. It no longer has any effect.', E_USER_DEPRECATED);
        }
        if (file_exists($path)) {
            // Return true if the path already exists. This is a nice optimization.
            return true;
        }
        // Make sure we use forward slashes
        // Note. Also works on Windows, in fact, you could even mix backslash with forward, although that's a bit stupid
        $path = rtrim(str_replace('\\', '/', $path), '/');
        // If the supplied path is absolute, remember the fact and re-add it later when needed
        $starts_with = (preg_match('/^([a-zA-Z]{1}:[\/\\\]{1}|\/)/', $path, $matches)) ? $matches[1] : '';

        $deduced_dirs = explode('/', $path);

        if ('' !== $starts_with) {
            // The first element is of no interest if the path is absolute, so remove it
            $deduced_dirs = explode('/', $path);
            array_shift($deduced_dirs);
            // Re-add the beginning of path string, the "absolute" part. E.g. "c:/" or "/"
            $current_path = $starts_with;
        }

        // Build the $dirs_to_be_made array. E.g.:
        //   /first/
        //   /first/second/
        //   /first/second/third/
        $i = 0;
        $dirs_to_be_made = [];
        foreach ($deduced_dirs as $dir_name) {
            $current_path .= $dir_name . '/';
            if (!file_exists($current_path)) {
                $dirs_to_be_made["$i"] = $current_path;
            }
            ++$i;
        }

        // Check if there's anything to create
        if (count($dirs_to_be_made) < 1) {
            // If there is nothing to create, return true
            return true;
        }


        // Finally, attempt to create the directories with the desired permissions
        foreach ($dirs_to_be_made as $dir) {
            // Note. Error suppression is on purpose,
            // since we only care if the action failed or not at this point.
            // If the action failed, it will most likely be due to permissions.
            // Subdirectories will be created recursively if present.
            $dir = rtrim($dir, '/');
            if (!@mkdir($dir, $permissions, false)) { // Bug? Perform chmod after to try to apply correct permissions!
                $e_msg = $this->error_messages["3"] . ' @' . $path . ' ';
                throw new Exception($e_msg, 3);
            }
            // An apparent bug in PHPs mkdir() is causing directories to be made with
            // wrong permissions (Tested on Ubuntu LTS). But, performing a chmod after creating the directory seems to solve this problem
            // - has this issue been reported to developers of PHP?
            chmod($dir, $permissions);
        }
        // If all directories was created successfully return true
        return true;
    }

    /**
     * Method to obtain a file lock before reading (shared) or writing (single) from/to files.
     * 
     * @param mixed $fp 
     * @param string $path 
     * @param bool $LOCK_SH 
     * @return true 
     * @throws Exception 
     */
    public function obtain_lock($fp, string $path, $LOCK_SH = false): bool
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

    /**
     * Method to scan a directory and return the result as an array.
     * @param string $path_to_dir 
     * @param array $files_to_ignore 
     * @return array|false 
     * @throws Exception 
     */
    public function scan_dir(string $path_to_dir, array &$files_to_ignore = null)
    {
        if (false === ($items_arr = scandir($path_to_dir))) {
            // The file did not exist, handle the error elsewhere
            $e_msg = $this->error_messages["11"] . ' @' . $path_to_dir . ' ';
            throw new Exception($e_msg, 11);
        }

        $items_arr = array_diff($items_arr, [".", ".."]);

        if (null === $files_to_ignore) {
            // Make sure to remove "." and ".." since they are not needed.
            return $items_arr;
        }

        foreach ($files_to_ignore as $ignored_item) {
            if (($key = array_search($ignored_item, $items_arr)) !== false) {
                unset($items_arr[$key]);
            }
        }

        return array_values($items_arr);
    }

    /**
     * Recursivly scan a base directory, returning an array of all items contained within.
     * @param string $path_to_dir 
     * @return array 
     * @throws Exception 
     */
    public function scan_dir_recursive(string $path_to_dir, array &$files_to_ignore = null): array
    {
        $base_items = $this->scan_dir($path_to_dir, $files_to_ignore);

        $all_items = [];
        foreach ($base_items as $item) {
            $item_path = $path_to_dir . '/' . $item;
            if (is_dir($item_path)) {
                // Include the directory before contents note:
                //   Obviously you can not move files to a directory that does not exist
                //   So, this step is important to allow for easily calling mkdir in a loop
                //   on the resulting array, and subsequently copying contents.
                //   Otherwise users would have to re-parse the array to figure out the path structure and re-create
                //   it in a new location.
                $all_items[] = $item_path;

                // Now we scan the subdir's content
                $subdir_content = $this->scan_dir_recursive($item_path, $files_to_ignore);
                // This method is faster than array_append
                // Also note: "+" will not actually "merge" two arrays,
                // but elements will be overwritten, which we do not want,
                // so either this or array_merge
                foreach ($subdir_content as $sub_item) {
                    $all_items[] = $sub_item;
                }
                // Alternative way to do the same thing
                // $all_items = array_merge($all_items, $this->scan_dir_recursive($item_path, $files_to_ignore));
            } else {
              $all_items[] = $item_path;
            }
        }
        return $all_items;
    }

    /**
     * Return the dirs in a directory as an array
     * @param string $path_to_dir 
     * @return array 
     */
    public function get_dirs_in_dir(string $path_to_dir): array
    {
        $items = $this->scan_dir($path_to_dir);

        foreach ($items as $item) {
            if (is_dir($item)) {
                $all_directories[] = $item;
            }
        }

        return $all_directories;
    }

    /**
     * Method to "stream" a file over HTTP in response to a client request. HTTP "range" requests are supported, making it possible to stream audio and video files from PHP.
     * @param string $path 
     * @param int $chunk_size 
     * @return void
     * @throws Exception 
     */
    public function http_stream_file(string $path, int $chunk_size = 8192)
    {
        if ((null === $this->sg) || (null === $this->ft)) {
            $e_msg = $this->error_messages["16"] . ' ';
            throw new Exception($e_msg, 16);
        }

        // Check if the file exists before doing anything with it
        if (!file_exists($path)) {
            // The file did not exist, handle the error elsewhere
            $e_msg = $this->error_messages["11"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 11);
        }

        // Find out what the client accepts
        $accept = $this->sg->get_SERVER('HTTP_ACCEPT');
        $accept_encoding = $this->sg->get_SERVER('HTTP_ACCEPT_ENCODING');

        // -----------------------
        // Determine file type and client accept header
        // -----------------------
        // Get additional headers for the requested file-type
        if (($extension = $this->ft->has_extension($path)) === false) {
            $e_msg = $this->error_messages["13"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 13);
        }

        // Make sure the extension is in lower-case
        $extension = strtolower($extension);

        if ($this->prevent_access_to_php_files) {
            if ($extension === 'php') {
                $e_msg = $this->error_messages["11"] . ' @' . $path . ' ';
                throw new Exception($e_msg, 11);
            }
        }

        $this->http_response_headers = $this->ft->get_file_headers($extension) + $this->http_response_headers;

        // If the file is a text file, we should check if a compressed version exist
        // Note. This assumes that the file has been compressed as either gzip, deflate or brotli
        // .gz, .zz, .br
        if ((str_contains($this->http_response_headers['content-type'], 'text/'))
            || ('xml' === $extension)
            || ('svg' === $extension)
            || ('rss' === $extension)
            || ('atom' === $extension)
        ) {
            $path = $this->pick_compressed_text_file($path, $accept_encoding);
        }

        // If an image was requested, make sure it is supported by the client
        // if not, check if there is another type available, try to convert if not.
        // Note. Do not try to convert .png images. They sometimes end up larger when converted to avif.
        if (
            (str_contains($this->http_response_headers['content-type'], 'image/') &&
                // Do not touch the following file types:
                (
                    ('png' !== $extension) &&
                    ('svg' !== $extension) &&
                    ('gif' !== $extension)))
        ) {
            $this->check_image_accept($path, $extension, $accept);
        }

        // Attempt to Open file for (r) reading (b=binary safe)
        if (($fp = @fopen($path, 'rb')) == false) {
            $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 1);
        }

        // If file was successfully opened, attempt to obtain_lock
        $this->obtain_lock($fp, $path, true);

        // Fill out file_size variables after locking the file
        $file_size = $this->get_file_length($path);
        $start = 0;
        $end = $file_size - 1;

        // If Mime Type is not supported by the client
        // Send a 406 Not Acceptable response if the client does not support the content type
        //     if(str_contains($path, 'some-broken-file.txt')) {
        //       file_put_contents('/var/www/logs/log.txt', print_r(getallheaders(), true));
        //     }
        // If the accept header is not set, we assume the Browser wants the raw, unmodified, original 
        if (!empty($accept)) {
            if (
                // Mime Type of requested file
                (!str_contains($accept, $this->http_response_headers['content-type']))  &&
                // Any Mime Type (*/*)
                (!str_contains($accept, '*/*')) &&
                // If requested file is an image, and client claims to accept all image types
                (
                    (!str_contains($this->http_response_headers['content-type'], 'image/')) &&
                    (!str_contains($accept, 'image/*')))

            ) {
                header('content-type: ' . $this->http_response_headers['content-type']);
                http_response_code(406); // Not Acceptable
                exit();
            }
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
            if (($start > $file_size) || ($end > $file_size) || ($end <= $start)) {
                http_response_code(416);
                exit();
            }

            // Position the file pointer at the requested range
            fseek($fp, $start);

            // Respond with 206 Partial Content
            http_response_code(206);

            // A "content-range" response header should only be sent if the "range" header was used in the request
            $this->http_response_headers['content-range'] = 'bytes ' . $start . '-' . $end . '/' . $file_size;
        } else {
            // If the range header is not present, respond with a 200 code and start sending some content
            http_response_code(200);
        }

        // Tell the client we support range-requests
        $this->http_response_headers['accept-ranges'] = 'bytes';
        // Set the content length to whatever remains
        $this->http_response_headers['content-length'] = ($file_size - $start);

        // ---------------------
        // Send the file headers
        // ---------------------
        // Send the "last-modified" response header
        // and compare with the "if-modified-since" request header (if present)
        $if_modified_since = $this->sg->get_SERVER('HTTP_IF_MODIFIED_SINCE');

        if (($timestamp = filemtime($path)) !== false) {
            $this->http_response_headers['last-modified'] = gmdate("D, d M Y H:i:s", $timestamp) . ' GMT';
            if ((isset($if_modified_since)) && ($if_modified_since == $this->http_response_headers['last-modified'])) {
                http_response_code(304); // Not Modified

                // The below uncommented lines was used while testing,
                // and might still be useful if having problems caching a file.
                // Use it to manually compare "if-modified-since" and "last-modified" while debugging.

                // $datafile = $if_modified_since . PHP_EOL . $this->headers['last-modified'];
                // file_put_contents('/var/www/testing/tmp/' . $name . '.txt', $datafile);

                exit();
            }
        }

        foreach ($this->http_response_headers as $header => $value) {
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
            // * In regard to this loop and calling fread inside a loop. *
            // The error suppression of fread is intentional.
            // Without the suppression we risk filling up all available disk space
            // with error logging on some servers - in mere seconds!!
            // While the specific obtain_lock() error should be fixed, the suppression
            // was left in place just to be safe.
            echo @fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit();
    }

    /**
     * Method to pick the best available compression in the following order: brotli, deflate, gzip
     * @param string $path
     * @param $accept_encoding
     * @return string
     * @throws Exception
     */
    private function pick_compressed_text_file(string $path, $accept_encoding): string
    {
        // If the client does not include an "accept-encoding" header just send the uncompressed file
        if (empty($accept_encoding)) {
            return $path;
        } else {
            $encodings_arr = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', $accept_encoding);
        }

        // Only compress files that are larger than 4096 bytes
        if (filesize($path) < 4096) {
            return $path;
        }

        clearstatcache();
        if (!file_exists($path . '.json')) {
            $this->write_file($path . '.json', json_encode(
                [
                    'filemtime' => filemtime($path) // Used to compare cache with live version of a file
                ],
                JSON_UNESCAPED_SLASHES
            ));
        }

        // If the file has changed, recreate the compressed versions
        // Note. This is done by comparing the recorded filesize in a .json with that of the current file
        $c_file_stats = json_decode($this->read_file_lines($path . '.json'), true);
        // clearstatcache() is needed in order to update filemtime
        clearstatcache();
        if ((!isset($c_file_stats['filemtime'])) || ($c_file_stats['filemtime'] !== filemtime($path))) {
            $this->simple_delete($path . '.json');
            if (file_exists($path . '.br')) {
                $this->simple_delete($path . '.br');
            }
            if (file_exists($path . '.zz')) {
                $this->simple_delete($path . '.zz');
            }
            if (file_exists($path . '.gz')) {
                $this->simple_delete($path . '.gz');
            }
            $this->write_file($path . '.json', json_encode(['filemtime' => filemtime($path)], JSON_UNESCAPED_SLASHES));
        }

        if (in_array('br', $encodings_arr)) {
            if (!file_exists($path . '.br')) {
                if ($this->helpers->command_exists('brotli')) {
                    $command = 'brotli -q 11 ' . $path . ' -o ' . $path . '.br 2>&1';
                    shell_exec(escapeshellcmd($command));
                    $this->http_response_headers['content-encoding'] = 'br';
                    return $path . '.br';
                }
            } else {
                $this->http_response_headers['content-encoding'] = 'br';
                return $path . '.br';
            }
        }
        if (in_array('deflate', $encodings_arr)) {
            if (!file_exists($path . '.zz')) {
                $file_content = $this->read_file_lines($path);
                $compressed_or_uncompressed_body = gzdeflate($file_content, 9);
                $this->write_file($path . '.zz', $compressed_or_uncompressed_body);
            }
            $this->http_response_headers['content-encoding'] = 'deflate';
            return $path . '.zz';
        }
        if (in_array('gzip', $encodings_arr)) {
            if (!file_exists($path . '.gz')) {
                $file_content = $this->read_file_lines($path);
                $compressed_or_uncompressed_body = gzencode($file_content, 9);
                $this->write_file($path . '.gz', $compressed_or_uncompressed_body);
            }
            $this->http_response_headers['content-encoding'] = 'gzip';
            return $path . '.gz';
        }
        // If the client does not report that it supports any of above encodings, send the original unmodified
        // Some clients might specifically ask for "identity", which is the same as unmodified source, so noo need
        // to handle "identity" explicitly 
        return $path;
    }

    /**
     * Buffer-based Download. Data is written to disk as it is received, avoiding running out of memory.
     * @param string $url 
     * @param string $output_file_path 
     * @param string $method 
     * @param array|null $post_data 
     * @param array $request_headers 
     * @param int $max_line_length 
     * @return true 
     * @throws Exception 
     */
    public function download_to_file(string $url, string $output_file_path, string $method = 'GET', array $post_data = null, array $request_headers = [], int $max_line_length = 1024): bool
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

    /**
     * Method to get the length of a file
     * @param string $path 
     * @return int 
     * @throws Exception 
     */
    private function get_file_length(string $path): int
    {
        if (!file_exists($path)) {
            // The file did not exist, handle the error elsewhere
            $e_msg = $this->error_messages["11"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 11);
        }

        if (($file_size = filesize($path)) === false) {
            $e_msg = $this->error_messages["12"] . ' @' . $path . ' ';
            throw new Exception($e_msg, 12);
        }
        return $file_size;
    }

    /**
     * Method to determine Image support via Accept HTTP header, and to convert existing files into more suitable formats
     * @param string $path 
     * @param string $extension 
     * @param string $accept
     * @return bool 
     * @throws Exception 
     */
    private function check_image_accept(string $path, string $extension, string $accept): bool
    {
        // Sorry. I know this is getting complex.
        // We may decide to move some of these functions to a dedicated class at some point.

        // Note. Some clients do not follow redirects on images (I.e: LinkedIn preview crawler).
        // In fact. LinkedIn's crawler does not even appear to request
        // images with unsupported file extensions

        // Note. The gd conversion method is preferred, since it is more broadly supported.
        //       Once gd is able to convert to avif, use gd instead of the 'convert' command.

        $full_uri_clean = $this->sg->get_SERVER('full_uri_clean');

        // If the client specifically claims to support avif, we should redirect to the avif version.
        if (true === str_contains($accept, 'image/avif')) {
            // The avif format is relatively new, and currently only supported by very few clients.
            // avif, so far, provides the best compression, so of course we want to support it.
            if ('avif' === $extension) {
                return true;
            }

            $avif_server_loc = substr($path, 0, strrpos($path, '.')) . '.avif';
            $avif_public_loc = substr($full_uri_clean, 0, strrpos($full_uri_clean, '.')) . '.avif';

            // If the ideal file type already exists, redirect to the file
            if (file_exists($avif_server_loc)) {
                $this->redirect_to_suitable($avif_public_loc);
            }

            // If the file did not exist, check if we can convert the requested image
            // First check if the convert command is available
            if ($this->helpers->command_exists('convert')) {
                // We are going to use the 'convert' command for this
                // and, unfortunately, this probably only exists on Linux systems.

                // Attempt to convert the file
                shell_exec('convert ' . escapeshellcmd($path . ' -quality 50% ' . $avif_server_loc));

                // If the conversion was successful, redirect to the converted file
                if (file_exists($avif_server_loc)) {
                    $this->redirect_to_suitable($avif_public_loc);
                } else {
                    // Silently serve the original file as-is instead
                    return false;
                }
            }
        }

        // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        // It was not possible to serve an avif file, so we continue
        // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>

        // If a webp or avif file was requested, we ought to make sure the client supports it,
        // and if not we will instead try to redirect to the jpeg version of the file.
        if (('webp' === $extension) || ('avif' === $extension)) {
            if (
                // If either webp or avif are not supported
                ((false === str_contains($accept, 'image/webp')) || (false === str_contains($accept, 'image/avif'))) &&
                (
                    // and if jpeg is supported
                    (true === str_contains($accept, 'image/jpeg')) ||
                    // Client is asking for any available format
                    (true === str_contains($accept, '*/*')) ||
                    // Client is asking for any available image format
                    (true === str_contains($accept, 'image/*')))
            ) {

                $jpg_server_loc = substr($path, 0, strrpos($path, '.')) . '.jpg';
                $jpg_public_loc = substr($full_uri_clean, 0, strrpos($full_uri_clean, '.')) . '.jpg';

                // If the ideal file type already exists, redirect to the file
                if (file_exists($jpg_server_loc)) {
                    $this->redirect_to_suitable($jpg_public_loc);
                }

                // If the gd extension is not loaded, just serve the file as-is
                if (false === extension_loaded('gd')) {
                    return false;
                }

                // >>>>>>>>>>>>>>>>
                // Convert the file
                // >>>>>>>>>>>>>>>>

                //
                if ('webp' === $extension) {
                    // Attempt to Open file for (r) reading (b=binary safe)
                    if (($fp = @fopen($path, 'rb')) == false) {
                        $e_msg = $this->error_messages["1"] . ' @' . $path . ' ';
                        throw new Exception($e_msg, 1);
                    }
                    $this->obtain_lock($fp, $path, true);

                    if (
                        // Try to create image from webp
                        (false === ($img = imagecreatefromwebp($path))) ||
                        // Try To Convert the file to jpg with 80% quality
                        (!imagejpeg($img, $jpg_server_loc, 80))
                    ) {
                        // If either attempt failed, throw an Exception?
                        // Silently serve the requested file as-is

                        // throw new Exception("imagecreatefromwebp() or imagejpg() failed.");

                        return false;
                    } else {
                        imagedestroy($img);
                    }
                    // Release lock
                    flock($fp, LOCK_UN);
                } else if ('avif' === $extension) {
                    // We already checked if the .jpg exists, so if this point is reached
                    // we just assume the file does not exist, and try to convert the requested .avif
                    if ($this->helpers->command_exists('convert')) {
                        shell_exec('convert ' . escapeshellcmd($path . ' ' . $jpg_server_loc));
                    }
                }

                // If the conversion appears to have been successful, redirect the file
                if (file_exists($jpg_server_loc)) {
                    $this->redirect_to_suitable($jpg_public_loc);
                } else {
                    // Silently serve the original file as-is
                    return false;
                }
            }
        }
        // No ideal available, serving requested file as-is
        return false;
    }

    /**
     * Redirects to a more suitable file format and exits()
     * @param string $location 
     * @return void
     */
    private function redirect_to_suitable(string $location)
    {
        // Redirect the user to the preferred image format
        header("location: $location", false, 307);
        header('content-length: 0'); // No body length
        header('content-type:'); // Nullify the content-type if needed
        exit();
    }
}
