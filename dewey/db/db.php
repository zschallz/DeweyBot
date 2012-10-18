<?php

// 	db.php
//	
//	AUTHOR: Zachary Schall-Zimmerman (zschallz@indiana.edu)
//	
//	Project: Dewey (and its many other names)
//	
//	File purpose: The code within is a database class. With this class a DB object
//				can be created which contains the ability to interact with the
//				database.

function connect()
{
	$db_server 		= "127.0.0.1";
	$db_username 	= "root";
	$db_password 	= "tghwanoi";
	$db_name 		= "flowbot";
	
	$connection = mysql_connect($db_server, $db_username, $db_password)
		or die;
		
	mysql_select_db($db_name, $connection);
	
	if (!$connection) {
	  	return;
	} 
	else
		return $connection;
	
}

function executeRoutineNonReader($routineName, $commaDelimitedParams)
{
	$connection = connect();
	
	if(!$connection)
		die("Error: Could not connect to DB");
	
	
	$result = mysql_query("CALL $routineName($commaDelimitedParams)");
	
	disconnect($connection);
	
	if( !$result )
		return false;
	else
		return true;
}

function executeRoutineWithReader($routineName, $commaDelimitedParams)
{
	$connection = connect();
	
	if(!$connection)
		die("Error: Could not connect to DB");

	$result = mysql_query("CALL $routineName($commaDelimitedParams)");
	
	//echo "DEBUG: CALL $routineName($commaDelimitedParams)<br/>";
	
	if($result == false)
		echo "Error with query: " . mysql_error();
	
	disconnect($connection);
	
	return $result;
}

function rawQuery($sql)
{
	$connection = connect();
	
	if(!$connection)
		die("Error: Could not connect to DB");

	$result = mysql_query($sql);
	
	disconnect($connection);
	
	return $result;
}


function disconnect($connection)
{
	mysql_close($connection);
}
