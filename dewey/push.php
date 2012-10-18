<?php
	require("db/db.php");
	/* 	push.php
		
		AUTHOR: Zachary Schall-Zimmerman (zschallz@indiana.edu)
		
		Project: Dewey (and its many other names)
		
		File purpose: The code within is used by the Dewey prototype (circa Summer 2010)
				to report any information obtained from its sensors. Every time presence
				is detected by both the temperature and motion sensor, Dewey "Pushes" this
				information to this script.
				
				This script uses stored routines (code within the MySQL database) to store
				the presence information.
				
				Future: It is also possible that this script will tell Dewey what to do next, when
				presence has been reported, but probably not.
	*/
	
	/* INPUT VALIDATION */
	/* Authentication Values */
	if( isset($_GET["pid"]) )
		$participantID 		= $_GET["pid"];
	else
		die("[Error]: pid not provided");
		
	if( isset($_GET["key"]) )
		$deweyKey			= $_GET["key"]; // alias in db
	else
		die("[Error]: key not provided");
		
	/* What should we do? Get command */
	if( isset($_GET["com"]) )
		$command 			= $_GET["com"];
	else
		die("[Error]: command not provided");
		
	/* For diagnostic log data */
	
	/* For person detection */
	if( $command == "sensor" )
	{
		if( isset($_GET["temp"]) )
			$temperatureValue 	= $_GET["temp"];
		else
			die("[Error]: temperature not provided");
		if( isset($_GET["motion"]) )
			$motionValue		= $_GET["motion"];
		else
			die("[Error]: motion not provided");
	}
	else if( $command == "diag" )
	{
		if( isset($_GET["eventType"]) )
		{
			$eventType = $_GET["eventType"];
			if( $eventType != "heartbeat" 
				&& $eventType != "turnedOn"
				&& $eventType != "invalidResponse" )
			{
				die("[Error]: eventType not recognized");
			}
		}
		else
			die("[Error]: eventType not supplied.");
	}
	
	/* END INPUT VALIDATION */
	
	/* Authenticate */
	$results = executeRoutineWithReader("checkParticipantIDWithAlias",
										"'$deweyKey',$participantID");
										
	if( mysql_num_rows( $results ) < 1 )
	{
		mysql_error();
		die("[Error]: Invalid pid/key combination");
	}
	else
	{
		/* authenticated... now check command and store data */
		if( $command == "sensor" )
		{
			$results = executeRoutineNonReader("PersonDetected", 
											   "$participantID,$temperatureValue,'$motionValue'");		
			if( $results )
				echo "[ok]"; // success!
			else
			{
				mysql_error();
				die("[Error]: Failed to insert sensor data");
			}
		}
		else if( $command == "diag" )
		{
			$results = executeRoutineNonReader("insertDiagnosticEvent", 
									   			"'$eventType',$participantID");
									   			
			if( $results )
				echo "[ok]"; // success!
			else
			{
				mysql_error();
				die("Error: Failed to insert sensor data");
			}
		}
		
	}

?>