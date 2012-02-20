<?php
// load the config
require '../config.php';

// include constants
define('INC', ABSPATH.'/includes');
define('MODULES', ABSPATH.'/modules');



// load the database
require INC.'/mysqlidb.class.php';

$db = new DB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

// load user handling
require INC.'/user.class.php';

session_start();
$user = new User();

// load functions
require INC.'/functions.php';