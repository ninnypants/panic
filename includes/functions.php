<?php
// general functions
function add_setting($key, $value){
	global $db;

	if(is_object($value) || is_array($value)){
		$value = serialize($value);
	}
	$key = $db->escape($key);
	if($db->get_var("SELECT count(*) FROM settings WHERE setting_key='$key' LIMIT 1")){
		if($db->query("UPDATE settings SET setting_value='$value' WHERE setting_key='$key'"))
			return true;
	}else{
		if($db->query("INSERT INTO settings(setting_key, setting_value) VALUES ('$key', '$value')"))
			return true;
	}
	return false;
}


function update_setting($key, $value){
	add_setting($key, $value);
}

function delete_setting($key){
	global $db;

	$key = $db->escape($key);
	if($db->query("DELETE FROM settings WHERE setting_key='$key' LIMIT 1"))
		return true;
	return false;
}

function get_setting($key, $default=''){
	global $db;

	$key = $db->escape($key);
	$setting = $db->get_var("SELECT setting_value FROM settings WHERE setting_key='$key' LIMIT 1");
	if($db->num_rows == 1){
		$unserial = unserialize($setting);
		if($unserial !== false)
			return $unserial;
		else
			return $setting;
	}

	return $default;
}