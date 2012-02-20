<?php

// User Class for user management using sessions
// you need to use session start before initalizing this class
/*
CREATE TABLE `users` (
`id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`username` VARCHAR( 20 ) NOT NULL ,
`password` VARCHAR( 40 ) NOT NULL ,
`email` VARCHAR( 20 ) NULL DEFAULT NULL ,
`fname` VARCHAR( 20 ) NULL DEFAULT NULL ,
`lname` VARCHAR( 20 ) NULL DEFAULT NULL ,
`hash` VARCHAR( 40 ) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE `usermeta` (
`id` BIGINT( 20 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_id` INT( 11 ) NOT NULL ,
`key` VARCHAR( 64 ) NOT NULL ,
`value` LONGTEXT NULL ,
INDEX ( `user_id` , `meta_key` )
) ENGINE = MYISAM ;
*/

Class User{
	// basic vars for this class
	public $id = '';
	public $user_name = '';
	public $name = '';
	public $email = '';
	private $hash = '';
	private $logged_in = false;
	private $db;
	
	public function __construct($username = null, $password = null){
		global $db;
		$this->db = $db;
		// if a username and password are provided then log the user in
		if((isset($username) && $username != null) && (isset($password) && $password != null)){
			$this->log_in($username, $password);
		}elseif(isset($_SESSION['hash']) && isset($_SESSION['user_id'])){
			if($this->check_session_hash()){
				$this->logged_in = true;
			}
		}
		
	}
	
	// check to see if a user is logged in
	public function is_logged_in(){
		return $this->logged_in;
	}
	
	// log a user in
	public function log_in($username, $password){
		// make sure there are no empty values
		if((isset($username) && !empty($username)) && (isset($password) && !empty($password))){
			
			// get user_id and hash for password hashing
			$res = $this->db->get_result("SELECT user_id, email, hash FROM users WHERE username='".$this->db->escape($username)."'", ARRAY_N);
			
			list($id, $email, $hash) = $res[0];
			// hash password
			$pass_hash = $this->hash_password($password, $username, $email, $hash);
			
			// if a user exists then their ID will be returned
			$user_info = $this->db->get_result("SELECT user_id, email, fname, lname FROM users WHERE username='".$this->db->escape($username)."' AND password='".$this->db->escape($pass_hash)."'");
			
			$user_info = $user_info[0];
			
			// if an ID is returned finish login process
			if($user_info->user_id != null){
				
				
				// set object variables
				$this->id = $user_info->user_id;
				$this->name = $user_info->fname.' '.$user_info->lname;
				$this->email = $user_info->email;
				$this->logged_in = true;

				// create a session hash to make sure user session cannot be stolen
				$sess_hash = $this->create_session_hash();
				$this->db->query("UPDATE users SET session_hash='$sess_hash' WHERE user_id=".$this->id);

				// set $_SESSION variables for referencing 
				$_SESSION['user_id'] = $this->id;
				$_SESSION['hash'] = $sess_hash;
				
				// return true on successful login
				return true;
			}
		}
	}
	
	// log out a user
	public function log_out(){
		
		// set session hash stored in the db to ''
		$this->db->query("UPDATE users SET session_hash='' WHERE user_id='".$this->db->escape($_SESSION['user_id'])."' AND session_hash='".$this->db->escape($_SESSION['hash'])."'");

		// set to null then unset $_SESSION vars
		$_SESSION['user_id'] = null;
		unset($_SESSION['user_id']);
		$_SESSION['hash'] = null;
		unset($_SESSION['hash']);
		
		// destroy session
		session_destroy();
		
		// set logged in to false
		$this->logged_in = false;
	}
	
	// create a new user
	public function create($username, $password, $email, $fname, $lname){
		// we'll assume that requirements have been
		// enforced before running create
		
		// create a user hash
		$hash = $this->user_hash($email);
		
		// hash password
		$password = $this->hash_password($password, $username, $email, $hash);
		
		// escape all input
		$username = $this->db->escape($username);
		$password = $this->db->escape($password);
		$email = $this->db->escape($email);
		$fname = $this->db->escape($fname);
		$lname = $this->db->escape($lname);
		$hash = $this->db->escape($hash);
		
		// insert user
		$this->db->query("INSERT INTO users(`username`, `password`, `email`, `fname`, `lname`, `hash`) VALUES('$username', '$password', '$email', '$fname', '$lname', '$hash')");
		
		return $this->db->insert_id;
	}
	
	// get extra user meta from the user meta table
	// will be moved into an extension class later
	public function get_user_meta($meta_key = null){
		if($meta_key == 'all'){
			return $wpdb->get_result("SELECT meta_value FROM user_meta WHERE user_id=$this->id");
		}else{
			return $wpdb->get_var("SELECT meta_value FROM user_meta WHERE user_id=$this->id AND meta_key='".$this->db->escape($meta_key)."'");
		}
	}
	
	// set/update a user meta value
	// will be moved into an extension class later
	public function set_user_meta($meta_key, $meta_value = null){
		if($this->get_user_meta($meta_key)){
			$query = "UPDATE user_meta SET meta_value='".$this->db->escape($meta_value)."' WHERE meta_key='".$this->db->escape($meta_key)."' AND user_id=".$this->id;
		}else{
			$query = "INSERT INTO user_meta(`user_id`, `meta_key`, `meta_value`) VALUES(".$this->id.", '".$this->db->escape($meta_key)."', '".$this->db->escape($meta_value)."')";
		}
		if($this->query($query)){
			return true;
		}else{
			return false;
		}
	}
	
	// delete a user meta 
	public function delete_user_meta($meta_key = 'all'){
		
		if($meta_key == 'all'){
			$query = "DELETE meta_value FROM user_meta WHERE user_id=$this->id";
		}else{
			$query = "DELETE meta_value FROM user_meta WHERE user_id=$this->id AND meta_key='".$this->db->escape($meta_key)."'";
		}
		
		if($this->query($query)){
			return true;
		}else{
			return false;
		}
	}
	
	// hash the users password
	private function hash_password($password, $username, $email, $hash){
		// create inital hash using password, email, and user hash
		$hash = sha1(md5($username.$email).$hash);
		return sha1(md5($hash).$password.md5($hash));
	}
	
	// create a session hash for security purposes
	private function create_session_hash(){
		
		// gather vars for hash
		$ip = $_SERVER['REMOTE_ADDR'];
		$time = time();
		$uid = $this->id;
		
		// return hash
		return sha1(md5($ip).md5($time).md5($uid));
	}
	
	// check for a valid session hash
	private function check_session_hash(){
		
		// make sure all session data is there if not log the user out
		if((isset($_SESSION['hash']) && empty($_SESSION['hash'])) || (isset($_SESSION['user_id']) && empty($_SESSION['user_id']))){
			$this->log_out();
			return false;
		}
		
		// if the user id and session hash are the same as the ones in the database it will return true
		return $this->db->get_var("SELECT count(*) FROM users WHERE user_id='".$this->db->escape($_SESSION['user_id'])."' AND session_hash='".$this->db->escape($_SESSION['hash'])."'");
		
	}
	
	// create a user has for security purposes
	private function user_hash($email){
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890`~!@#$%^&*()[]{}/=?+-_|';
		$str = str_shuffle(str_shuffle($chars));
		$ip = $_SERVER['REMOTE_ADDR'];
		$key = md5(rand());
		
		$hash = $str.$key.$ip.$key.$email.$key;
		
		for($i = 0; $i < 5; $i++){
			$hash = md5($hash);
		}
		return $hash;
	}
}
?>
