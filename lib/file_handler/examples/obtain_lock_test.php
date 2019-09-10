<?php
/*
 *          Doorkeeper File Handler
 *
 *        This example can be used to test and debug file-locking on a server.
 *        The file_handler class has build-in errors that can be used for debugging.
 *
 *        You will probably not need to use obtain_lock() manually. This is mainly to test if your server supports file-locking,
 *        and to test if the file is_writable(), which is required for locking to work.
 *
 *         @author Jacob Kristensen (JacobSeated)
 */

// ********************
// Composition Root****
// ********************

// Remove trailing slashes (if present), and add one manually.
// Note: This avoids a problem where some servers might add a trailing slash, and others not..
define('BASE_PATH', rtrim(realpath('../../../'), "/") . '/');

// Class autoloader
require BASE_PATH . 'shared/header.php';

// Required helper methods
$helpersObj = new \doorkeeper\lib\php_helpers\php_helpers();

// File handler to Write and Read files
$fileHandlerObj = new \doorkeeper\lib\file_handler\file_handler($helpersObj);

// Absolute path to file
$fileHandlerObj->f_args['path'] = BASE_PATH . 'writer.txt';

$fp = @fopen($fileHandlerObj->f_args['path'], "r");

$response = $fileHandlerObj->obtain_lock($fp); // Try to obtain file lock

if (isset($response['error'])) {
    // If there was an error, handle it here
    print_r($response);exit();
} else {
    // Perform action'(s) on file
}

sleep(25); // Sleep 25 secs.. This allows us to test if a file-lock is working as intended by running the script a second time.