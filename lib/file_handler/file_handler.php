<?php
/*
 *           Doorkeeper File Handler
 *
 *		        Class to handle creation, editing, and deletion of files and directories with support for locking
 *		
 *		        The handle_error() function is used to return an error, which can then be handled and translated from the calling location.
 *
 *              write_file() should automatically handle locking, allowing use in some concurrency siturations.
 *              This probably does not work for all applications, but should be enough in many cases.
 *              You may want to use a database for "heavy" load applications.
 *	  
 *         @author Jacob Kristensen (JacobSeated)
 */

namespace doorkeeper\lib\file_handler;

class file_handler {

 private $additional_error_data = array();

 public $f_args = array(); // Contains arguments to be used in file methods
 public $lock_max_time = 20; // File-lock maximum time in seconds.


 public function __construct(object $helpers) {
    $this->helpers = $helpers; // Helper methods to solve common programming problems
 }

 public function simple_delete(string $file_or_dir) {
	$this->additional_error_data = array('source' => __METHOD__); // Source = class and method name where the error occured

	if (is_writable($file_or_dir)) { // Check for write permissions
	  if (is_dir($file_or_dir)) { // Do this if we are dealing with a directory
		$objects = scandir($file_or_dir); // Handle subdirectories (if any)
		$objects = array_diff($objects, array('..', '.')); // Removes "." and ".." from the array, since they are not needed.
		foreach ($objects as $object) {
			if (is_dir($file_or_dir.'/'.$object)) {
			  $this->simple_delete($file_or_dir.'/'.$object); // If dealing with a subdirectory, perform another simple_delete()
			} else {
			  if (is_writable($file_or_dir.'/'.$object)) { // Check for write permissions
				if(!unlink($file_or_dir.'/'.$object)) {
				  // If unlink failed, possibly due to a race condition, return an error
				  return $this->handle_error(array('action'=>'unlink', 'path' => $file_or_dir.'/'.$object));
				}
			  } else {
				return $this->handle_error(array('action'=>'is_writable', 'path' => $file_or_dir.'/'.$object));
			  }
			}
		}
		if(!rmdir($file_or_dir)) {
		  return $this->handle_error(array('action'=>'rmdir', 'path' => $file_or_dir.'/'.$object));
		} // Delete directory
		return true; // Return true on success
	  } else {
		if(!unlink($file_or_dir)) {
		  // If unlink failed, possibly due to a race condition, return an error
		  return $this->handle_error(array('action'=>'unlink', 'path' => $file_or_dir));
		}
	  }
	} else { // If the file or directory was not writable, we show an error
	  return $this->handle_error(array('action'=>'is_writable', 'path' => $file_or_dir.'/'.$object));
	}
	 
 }

 public function read_file_lines(array $arguments_arr) {
    $default_argument_values_arr = array(
		'path' => 'REQUIRED',
		'start_line' => 0,
		'max_line_length' => '4096',
		'lines_to_read' => false
	  );
	$this->f_args = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);

   // If an error occurs, add some shared debugging info
   $this->additional_error_data = array(
	   'source' => __METHOD__,
	   'start_line' => $this->f_args['start_line'],
	   'max_line_length' => $this->f_args['max_line_length'],
	   'lines_to_read' => $this->f_args['lines_to_read']
   );
   if ($fp = fopen($this->f_args['path'], "r")) {
    $this->obtain_lock($fp); // Attempt to obtain file lock, error if the timeout is reached before obtaining the lock
   	if ($this->f_args['lines_to_read'] === false) { // Reads entire file until End Of File has been reached
   	  $lc = 1;
   	  $file_content = '';
   	  while (($buffer = fgets($fp, $this->f_args['max_line_length'])) !== false) {
   	  	if($lc >= $this->f_args['start_line']) {
 	      $file_content .= $buffer;
   	  	}
   	  	++$lc;
 	  }
   	} else { // Only read the specified number of lines, current line is stored in $lc
 	  $file_content = '';$i = 0;$lc = 1;
 	  $lines_to_read = $this->f_args['lines_to_read']+$this->f_args['start_line'];
 	  while ($i < $lines_to_read) {
 	  	if($lc > $this->f_args['start_line']) {
 	  	  $file_content .= fgets($fp, $this->f_args['max_line_length']);
 	  	} else {fgets($fp, $this->f_args['max_line_length']);} // Just move the position indicator without saving the read data
 	     ++$i;++$lc;
 	  }
   	}
   	if ((!feof($fp)) && ($this->f_args['lines_to_read'] === false)) { // If End Of File was not reached (unexpected) at this point...
 	  if(!$this->additional_error_data['ftell'] = ftell($fp)) { // Get location of file pointer as debug info
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
 public function count_lines(array $arguments_arr) {
   // It may be nessecery to count the lines in very large files, so we can read the file, say, 100 lines at a time.
   // Note. Any file-lock should be obtained outside this method, to prevent writing to a file while we are counting the lines in it
     $default_argument_values_arr = array(
	  'path' => 'REQUIRED',
	  'start_line' => 0,
	  'max_line_length' => '4096',
	  'lines_to_read' => false
    );
	$this->f_args = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);
	
 	// If an error occurs, add some shared debugging info
 	$this->additional_error_data = array(
		'source' => __METHOD__,
		'max_line_length' => $this->f_args['max_line_length']
	 );
 	if ($fp = @fopen($this->f_args['path'], "r")) {
	  $this->obtain_lock($fp); // Attempt to obtain file lock, error if the timeout is reached before obtaining the lock
 	  $lc = 0;
 	  while (($buffer = fgets($fp, $this->f_args['max_line_length'])) !== false) {
 		++$lc;
 	  }
 	  if ((!feof($fp))) { // If End Of File was not reached (unexpected) at this point...
 	  	if(!$this->additional_error_data['ftell'] = ftell($fp)) { // Get location of file pointer as debug info
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
 public function write_file(array $arguments_arr) {
   $default_argument_values_arr = array(
		'path' => 'REQUIRED',
		'content' => '',
		'mode' => 'w' // w = open for writing, truncates the file, and attempts to create the file if it does not exist.
   );
   $this->f_args = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);

   $this->additional_error_data = array(
	'source' => __METHOD__, // The class and method name where the error occured
   );

   if($fp = @fopen($this->f_args['path'], $this->f_args['mode'])) {
	 $this->obtain_lock($fp); // Attempt to obtain file lock, error if the timeout is reached before obtaining the lock
     if(!fwrite($fp, $this->f_args['content'])) {
       return $this->handle_error(array('action' => 'fwrite', 'path' => $this->f_args['path']));
     } else {fclose($fp);return true;} // fclose also releases the file lock
   } else {
   	 return $this->handle_error(array('action' => 'fopen', 'path' => $this->f_args['path']));
   }
 }
 public function create_directory(array $arguments_arr) {
	$default_argument_values_arr = array(
		'path' => 'REQUIRED',
		'permissions' => '0775'
    );
	$this->f_args = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);
	if (file_exists($this->f_args['path'])) {
	  return $this->handle_error(array('action' => 'file_exists', 'path' => $this->f_args['path']));
	}
 	if (!mkdir($this->f_args['path'], $permissions)) {
	  return $this->handle_error(array('action' => 'mkdir', 'path' => $this->f_args['path']));
	} else {return true;}
 }
 public function obtain_lock($fp) {
	if(is_writable($this->f_args['path'])) {
	  $i = 0;
	  while (!flock($fp, LOCK_EX | LOCK_NB) ) {
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
 private function read_file_settings(array $arg_arr) {
   $read_setting = array();
   $read_setting['start_line'] = 0; // The line to start reading from, use this if you want to resume reading a file at a certain point (or use ftell)
   $read_setting['max_line_length'] = 4096; // Max length of line In bytes (This should be large enough to hold the longest line)
   $read_setting['lines_to_read'] = false; // False indicates the entire file should be read
 	
   foreach ($arg_arr as $key => $value) {
     $read_setting["$key"] = $value; // Overwrite defaults if nessecery
   }
   return $read_setting;
 }
 private function handle_error(array $arguments_arr) {
	$default_argument_values_arr = array(
		'action' => 'REQUIRED',
		'path' => ''
    );
    $arg = $this->helpers->default_arguments($arguments_arr, $default_argument_values_arr);
	
	$aed_html_table = '';
	
 	if (count($this->additional_error_data) >= 1) { // Has to count, since we may have strings as keys.
 	  foreach ($this->additional_error_data as $key => $value) {
 	    $aed_html_table .= '<tr><td>'.$key.'</td><td>'.$value.'</td></tr>';
 	  } $aed_html_table = '<section><h1>Additional error data:</h1><table>' . $aed_html_table . '</table></section>';
 	} else {$aed_html_table='';}
	 
	switch ($arg['action']) {
		case "fopen":
		  $error_arr = array(
			'error' => '1',  // 1 = Failed to open file
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "fwrite":
		  $error_arr = array(
			'error' => '2', // 2 = Failed to create or write file
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "mkdir":
		  $error_arr = array(
			'error' => '3', // 3 = Failed to create directory
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "feof":
		  $error_arr = array(
			'error' => '4', // 4 = Unexpected fgets() fail after reading from file
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "is_writable":
		  $error_arr = array(
			'error' => '5', // 5 = The file or directory is not writeable
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "file_put_contents":
		  $error_arr = array(
			'error' => '6', // 6 = Unable to write to file, but the file is writable
			'path' => $arg['path'],
			'aed' => $aed_html_table
		  );
		break;
		case "flock":
		$error_arr = array(
		  'error' => '7', // 7 = Unable to obtain file lock (timeout reached)
		  'path' => $arg['path'],
		  'aed' => $aed_html_table
		);
		break;
		case "unlink":
		$error_arr = array(
		  'error' => '8', // 8 = Unable to unlink file, possible race condition
		  'path' => $arg['path'],
		  'aed' => $aed_html_table
		);
		break;
		case "file_exists":
		$error_arr = array(
		  'error' => '9', // 9 = File or directory already exists
		  'path' => $arg['path'],
		  'aed' => $aed_html_table
		);
		break;
		case "rmdir":
		$error_arr = array(
		  'error' => '10', // 10 = Unable to remove directory
		  'path' => $arg['path'],
		  'aed' => $aed_html_table
		);
		break;
	}
	 
 	return $error_arr; // Return the error, and handle it elsewhere
 }
}