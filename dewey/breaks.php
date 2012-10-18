<?php
	require("db/db.php");
	

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