<?php

/**
 *           Doorkeeper File Handler Exception
 *
 *                Just some minor additions to the standard PHP Exception class
 *                Note. This class extends PHP's Exception class!
 * 
 *                To catch execptions thrown by the file_handler from the outside,
 *                include a "use" statement in your project. I.e:
 *                  use doorkeeper\lib\file_handler\exception as fhException
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

class Exception extends \Exception
{
    /**
     * Create a new Exception object that accepts an array as an argument
     * 
     * @param array $error_arr
     * @return void
     */
    public function __construct(array $error_arr)
    {
        $this->eArr = array_merge($this->msgArr["{$error_arr['code']}"], $error_arr);
        parent::__construct($this->eArr['msg'], $this->eArr['code']);
    }
    /**
     * Method to return specific error information as an array
     * For example, the array may contain a file path when attempting to open a file 
     * using the File Handler.
     * 
     * @return array 
     * 
     */
    public function getExceptionArray()
    {
        return $this->eArr;
    }
    
    // The Exception array
    private $eArr = array();

    // Array of error messages
    private $msgArr = [
        1 => ['msg' => 'Failed to open file.'],
        2 => ['msg' => 'Failed to create or write file.'],
        3 => ['msg' => 'Failed to create directory.'],
        4 => ['msg' => 'Unexpected fgets() fail after reading from file.'],
        5 => ['msg' => 'The file or directory is not writeable.'],
        6 => ['msg' => 'Unable to write to file, but the file is writable.'],
        7 => ['msg' => 'Unable to obtain file lock (timeout reached).'],
        8 => ['msg' => 'Unable to unlink file (possible race condition).'],
        9 => ['msg' => 'File or directory already exists.'],
        10 => ['msg' => 'Unable to remove directory.'],
        11 => ['msg' => 'The file did not exist.'],
        12 => ['msg' => 'Unexpected filesize() fail.'],
        13 => ['msg' => 'The file path had no file extension.'],
        14 => ['msg' => 'Failed to read line in file. This can happen if the process was interrupted.'],
        15 => ['msg' => 'Failed to open file. The supplied path is not a file.'],
    ];

}

// Exception types
// There seems to be no need for custom Exceptions,
// since the returned error object already contains
// all the information needed to know what went wrong.
// This comment is left as a reminder...

/*
class NotFound extends Exception {}
class AccessDenied extends Exception {}
class CouldNotGetFilesize extends Exception {}
class FailedToOpenFile extends Exception {}
*/