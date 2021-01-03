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

// Include the File Handler, or use an autoloader
// Normally you would probably use an absolute path instead of a relative.
//   Read: https://beamtic.com/including-files-via-base-path
require '../../class_traits/no_set.php';
require '../file_handler.php';

// File handler to Write and Read files
$fh = new \doorkeeper\lib\file_handler\file_handler();

// Note. To append the content to the end of the file, you may use "a" as "mode".
$path = 'writer.txt'; // Note. Make sure the directory/file is writable
$content = "Hallo my king?";
$mode = 'a';

try {
    $fh->write_file($path, $content, $mode);
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}

// Success
echo 'File Written!';