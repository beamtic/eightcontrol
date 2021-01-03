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
// Include the File Handler, or use an autoloader
// Normally you would probably use an absolute path instead of a relative.
//   Read: https://beamtic.com/including-files-via-base-path
require '../../class_traits/no_set.php';
require '../file_handler.php';

// File handler to Write and Read files
$fh = new \doorkeeper\lib\file_handler\file_handler();

// Absolute path to file
$path = 'writer.txt';

$fp = @fopen($path, "r");



try {
    $fh->obtain_lock($fp, $path); // Try to obtain file lock
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}

echo 'Lock obtained successfully!';

// Sleep 25 secs..
// Run 2 instances of the script at the same time, and one should fail to get a lock.
// Note. The maximum time to wait for a lock is defined in "lock_max_time" of the file handler.
// A script will not ba able to obtain a lock until an existing lock has been released.
sleep(25);