<?php
/*
 *          Doorkeeper File Handler
 *
 *        This example is intended to demonstrate simple use of the File Handler
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

$fp = @fopen($fileHandlerObj->f_args['path'], "r");

$file_arr = array();
$file_arr['path'] = BASE_PATH . 'writer.txt'; // Note. Make sure the directory/file is writable
$file_arr['content'] = "Hallo my king?";
// $file_arr['mode'] = 'a'; // Optional (default is w)
// Note. To append the content to the end of the file, you may use "a" as "mode".

$response = $fileHandlerObj->write_file($file_arr); // Try to write the file

if (isset($response['error'])) {
    // If there was an error, handle it here
    print_r($response);exit();
}