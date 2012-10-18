<?php

	  require("db/db.php");
	  require("breaks/break.php");
	  require("presence/presence.php");
	  require("breaks/SuggestedBreak.php"); 
/* 	pull.php
	
	AUTHOR: Zachary Schall-Zimmerman (zschallz@indiana.edu)
	
	Project: Dewey (and its many other names)
	
	File purpose: The code within is used by the Dewey prototype (circa Summer 2010)
			to know when to suggest a break to a user. It executes some stored routines
			(code within the mysql database) that tell it whether or not to suggest a break
			Output is then given to the Arduino and then parsed and acted upon.
			
			This script is probably going to be "pulled" by each Arduino every 10-20 seconds.
*/

	if (sizeof($_GET) > 0) 
		if( doInputValidation() )
		{
			suggestBreakIfNeeded();
		}



	function doInputValidation()
	{
		global $participantID, $deweyKey;

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

		/* END INPUT VALIDATION */
		/* Authenticate */
		$results = executeRoutineWithReader("checkParticipantIDWithAlias",
											"'$deweyKey',$participantID");
											
		if( mysql_num_rows( $results ) < 1 )
			die("[Error]: Invalid key");
		
		return true;
	}
	
	function suggestBreakIfNeeded()
	{	
		
		global $startDate, $endDate, $participantID;
		
		$endDate = time();
		$startDate = $endDate-86400; // Yesterday. 86400 seconds in a day
		
		/* Get robot mode... do nothing if pretest */
		if( getRobotMode() != "pretest" )
		{	
			// Is there currently an unresolved break suggestion? (not declined and not taken)
			if( isSuggestionUnresolved() )
			{
				// yes: was there a 5 min break after the creation of the suggestion?
				if( resolveIfBreakHappened() )
				{
					echo "[DEBUG]: break resolved because of break that coincided with unresolved suggestion.";
					return;
				}
				// yes: Is it time to alert again?
				if( isTimeToAlertAgain() )
				{
					// yes: increase intensity and suggest break again.
					alertSnoozedSuggestion();
				}
				else
				{
					//no:
					echo "[DEBUG]: not time to alert yet, but unresolved suggestion exists.";
				}
			}
			else
			{
				$potentialBreaks = BreakTime::GetBreaks($participantID, $startDate, $endDate);
				createNewSuggestionIfNeeded($potentialBreaks);
			}
		}
	}
	function resolveIfBreakHappened()
	{
		global $participantID, $startDate, $endDate, $unresolvedSuggestion;
		
		$BREAK_THRESHOLD_SECONDS = 300; // 5 minutes
		
		$breaksSinceCreation = BreakTime::GetBreaks($participantID, $unresolvedSuggestion->getDatetimeCreated(), $endDate);
	
		if( sizeof($breaksSinceCreation) > 0 )
		{
			foreach($breaksSinceCreation as $break)
			{
				if( $break->getSeconds() > $BREAK_THRESHOLD_SECONDS )
				{
					$unresolvedSuggestion->setSuggestionStatus("taken");
					$unresolvedSuggestion->update();
					return true;
				}
			}
		}
		
		return false;
	}
	
	/* maybe won't have */
	function isBreakGoalMet($breaksArray)
	{
		$BREAK_GOAL_SECONDS = 3600; //1 hour = 3600 seconds
		$totalBreakTime = 0;
		
		foreach( $breaksArray as $break )
			$totalBreakTime =+ $break->getSeconds();
			
		if( $totalBreakTime >= $BREAK_GOAL_SECONDS )
			return true;
		else
			return false;
	}
	
	function isSuggestionUnresolved()
	{
		global $participantID, $startDate, $endDate, $unresolvedSuggestion;
		// get unresolved suggested breaks
		$suggestedBreaks = SuggestedBreak::GetUnresolvedSuggestions($participantID, $startDate, $endDate);
		
		if( sizeof($suggestedBreaks) > 1 )
		{
			errorMail("More than one unresolved break for participant id: " . $participantID);
			$unresolvedSuggestion = $suggestedBreaks[sizeof($suggestedBreaks)-1];
			return true;
		}
		else if ( sizeof($suggestedBreaks) == 1 )
		{
			$unresolvedSuggestion = $suggestedBreaks[0];
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function createNewSuggestionIfNeeded($breaks)
	{
		global $participantID, $startDate, $endDate;
		$SECONDS_SINCE_LAST_BREAK = 3600; // 60 minutes
		$SECONDS_SINCE_LAST_BREAK_HIGH = 7200; // 2 hours

		/* were there no breaks? */
		if( sizeof($breaks) == 0 )
		{
			$potentialPresence = Presence::GetPresence($participantID, $startDate, $endDate);
			
			if( sizeof($potentialPresence) > 0 )
			{
				$beginningOfDay = $potentialPresence[0]->getStartTimeStamp(); // first detection of presence
				$timeSinceBeginningOfDay = time() - $beginningOfDay;
				if( $timeSinceBeginningOfDay > $SECONDS_SINCE_LAST_BREAK )
				{
					newSuggestion(1); // low intensity
				}
			}
		}
		else
		{
			$lastBreakEndTime = $breaks[sizeof($breaks)-1]->getEndTimeStamp();
			$timeSinceLastBreakEnd = time() - $lastBreakEndTime;
			/* if time since the end of the last break is greater than the threshold */
			if ( $timeSinceLastBreakEnd > $SECONDS_SINCE_LAST_BREAK_HIGH )
			{
				newSuggestion(3); // high intensity
			}
			else if ( $timeSinceLastBreakEnd > $SECONDS_SINCE_LAST_BREAK )
			{
				newSuggestion(0); // low intensity
			}
			else
			{
				echo "[DEBUG]: It's not time for a new break yet.";
			}
		}
	}
	
	function isTimeToAlertAgain()
	{
		global $unresolvedSuggestion;
		$SECONDS_BETWEEN_SNOOZED_ALERTS = 600; // 10 minutes
		/* check if unresolved suggestion has timed out */
		if( $unresolvedSuggestion != null )
		{
			$timeSinceLastSuggestion = time() - $unresolvedSuggestion->getDatetimeUpdated();
			//echo time() . " - " .$unresolvedSuggestion->getDatetimeUpdated(). " seconds<br/>";
			if( $timeSinceLastSuggestion > $SECONDS_BETWEEN_SNOOZED_ALERTS )
				return true;
			else
				return false;
		}
		else
		{
			die("[ERROR]: There was no unresolved suggestion, yet isTimeToAlert was called!");
		}
	}
	
	function alertSnoozedSuggestion()
	{
		global $unresolvedSuggestion;
		
		$intensity = $unresolvedSuggestion->getAlertIntensity();
		if($intensity == 0) // skip 1
			$unresolvedSuggestion->setAlertIntensity($unresolvedSuggestion->getAlertIntensity()+2);
		else
			$unresolvedSuggestion->setAlertIntensity($unresolvedSuggestion->getAlertIntensity()+1);
		$unresolvedSuggestion->setSuggestionStatus("suggested");
		
		$unresolvedSuggestion->update();
		
		echo "[Suggestion][" . $unresolvedSuggestion->getAlertIntensity() . "]";
	}
	
	function getRobotMode()
	{
		global $participantID;
		
		$results = rawQuery("SELECT robotMode 
							 FROM participants 
							 WHERE id = " . $participantID . ";");
		
		/* input validation already done so no worries about validity of participantID,
		   just grab the mode from the first (only) row 
		 */
		
		$row = mysql_fetch_array($results);
		
		return $row["robotMode"];
		
	}
	function newSuggestion($intensity)
	{
		global $participantID;
		SuggestedBreak::NewSuggestion($participantID,$intensity);
		echo "[Suggestion][$intensity]";
	}
	
	function errorMail($message)
	{
		$SEND_TO = "zschallz@indiana.edu";
		mail($SEND_TO,
			 "Problem with Dewey!",
			 $message);
	}	
?>
