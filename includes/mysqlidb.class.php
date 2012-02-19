<?php
/*
Title: MySQLI DB
Creator: Tyrel Kelsey
URI: http://ninnypants.com/
Description: MySQL database class similar to ezSQL but uses MySQLI

*/

define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');

class DB{
	
	// private properties
	private $db = NULL;
	private $con = NULL;
	private $host = NULL;
	private $username = NULL;
	private $password = NULL;
	private $queries = array();
	private $get_vars = array();
	
	private $show_errors = true;
	private $get_results = array();
	private $last_result = array();
	private $mysql_error_str = array(
		1 => 'Require $username and $password to connect to a database server',
		2 => 'Error establishing mySQL database connection. Correct user/password? Correct hostname? Database server running?',
		3 => 'Require $dbname to select a database',
		4 => 'mySQL database connection is not active',
		5 => 'Unexpected error while trying to select database'
	);
	
	
	
	
	// public properties
	public $error = NULL;
	public $last_error = NULL;
	public $last_query = NULL;
	public $num_queries = 0;
	public $insert_id = NULL;
	public $insert_ids = array();
	public $captured_errors = array();
	public $num_rows = 0;
	
	/**/
	public function __construct($uname, $pass, $db = NULL, $hst = 'localhost'){
		
		// set local connection variables
		$this->host = $hst;
		$this->username = $uname;
		$this->password = $pass;
		$this->db = $db;
		
		if(!$this->username){
			$this->error = $this->mysql_error_str[1].' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);
		}
		
		if(!$this->password && $this->password != ''){
			$this->error = $this->mysql_error_str[1].' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);

		}
		
		
		// open connection
		$this->con = new mysqli($this->host, $this->username, $this->password, $this->db);
		
		// if there's an error and error reporting is on show the error
		if($this->con->connect_error){
			$this->error = $this->con->error.' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);
		}
		
	}
	
	/**/
	public function select($dbname){
		
		$this->db = $dbname;
		
		if(!$this->db){
			$this->error = $this->mysql_error_str[3].' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);
		}
		
		$this->con->select_db($this->db);
		
		if($this->con->error){
			$this->error = $this->con->error.' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);
		}
	}
	
	/**/
	public function escape($string){
		return $this->con->escape_string(stripslashes($string));
	}
	
	/**/
	public function query($query){
		$returnval = 0;
		
		// if $query isn't set reuse last query
		$this->last_query = trim($query);
		
		// number queries
		$this->num_queries++;
		
		// run query
		$result = $this->con->query($this->last_query);
		
		// if there is an error and error reporting is on show error
		if($this->con->error){
			$this->error = $this->con->error.' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error);
		}
		
		if(preg_match("/^(insert|update|delete|replace)\s+/i", $this->last_query)){
			
			$this->affected_rows = $this->con->affected_rows;
			
			if(preg_match("/^(insert|replace|update)\s+/i", $this->last_query)){
				// set $insert_id
				$this->insert_id = $this->con->insert_id;
			}
			
			$returnval = $this->affected_rows;
			
		}else{
			// store results
			$numrows = 0;
            $this->last_result = array();
			while($row = $result->fetch_object()){
				$this->last_result[$numrows] = $row;
				$numrows++;
			}
			
			$this->num_rows = $result->num_rows;
			$returnval = $this->num_rows;
			$result->free();
		}
		
		return $returnval;
	}
	
	
	/**/
	public function get_result($query = NULL, $type = OBJECT){
		
		// if $query isn't set reuse last query
		if($query){
			$this->query($query);
		}
		
		// return the correct type
		if($type == OBJECT){
			return $this->last_result;
		}elseif($type == ARRAY_A || $type == ARRAY_N){
			$i = 0;
			$new_arr = array();
			foreach($this->last_result as $result){
				// create associative arry
				$new_arr[$i] = get_object_vars($result);
				// if ARRAY_N convert to numeric arry
				if($type == ARRAY_N){
					$new_arr[$i] = array_values($new_arr[$i]);
				}
				$i++;
			}
			// return the array
			return $new_arr;
		}
	}
	
	/**/
	public function get_var($query = NULL, $x=0, $y=0){
		if($query){
			$this->query($query);
		}
		$vars = @array_values(get_object_vars($this->last_result[$y]));
		return $vars[$x] ? $vars[$x] : NULL;
	}
	
	/*
	public function multi_query(){
		
		// if there is an error and error reporting is on show error
		if($this->con->error){
			$this->error = $this->con->error.' in File:'.$_SERVER['SCRIPT_FILENAME'].' on Line:'.__LINE__;
			$this->register_error($this->error, 'm');
			$this->show_errors ? trigger_error($this->error, E_USER_WARNING) : NULL;
		}
		
	}
	*/
	/**/
	private function register_error($error){
		$this->last_error = $error;
		$this->captured_errors[] = array(
			'error_string' => $this->last_error,
			'query' => $this->last_query
		);
		$this->show_errors ? trigger_error($this->error, E_USER_WARNING) : NULL;
	}
	
	/**/
	public function show_errors(){
		$this->show_errors = true;
	}
	
	/**/
	public function hide_errors(){
		$this->show_errors = false;
	}
	
}
?>
