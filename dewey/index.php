<?php require("db/db.php");
	  require("breaks/break.php"); 
	  require("presence/presence.php");
?>
<html>

	<head>
		<title>Break Visualizer</title>
		<link type="text/css" href="http://jqueryui.com/themes/base/jquery.ui.all.css" rel="stylesheet" /> 
		<script type="text/javascript" src="http://jqueryui.com/jquery-1.4.2.js"></script> 
		<script type="text/javascript" src="http://jqueryui.com/ui/jquery.ui.widget.js"></script> 
		<script type="text/javascript" src="http://jqueryui.com/ui/jquery.ui.core.js"></script> 
		<script type="text/javascript" src="http://jqueryui.com/ui/jquery.ui.datepicker.js"></script> 
		<link type="text/css" href="http://jqueryui.com/demos/demos.css" rel="stylesheet" /> 
		
		
		<script type="text/javascript">
			$(function() {
				$("#startdatepicker").datepicker();
			});
			$(function() {
				$("#enddatepicker").datepicker();
			});
		</script>
	
	<head>

	<body>
		<form action="index.php" method="get">
		<p>Start Date: <input type="text" id="startdatepicker" name="startDate"/>
		End Date: <input type="text" id="enddatepicker" name="endDate"/></p>
		
		<p>Participant: <?php getParticipantList(); ?>
		Key: <input type="text" name="key"/> <br/>
		Display: <input type="radio" name="mode" value="breaks"/> Breaks
		<input type="radio" name="mode" value="presence"/> Presence</p>
		
		<input type="submit" value="Submit" />
		
		<?php
			if (sizeof($_GET) > 0) 
				if( doInputValidation() )
				{
					if($mode == "breaks")
					{
						getBreaks();
					}
					else if( $mode == "presence" )
					{
						getPresence();
					}
				}
		?>
	</body>

</html>
<?php
	global $participantID, $deweyKey, $startDate, $endDate, $mode;

	function getParticipantList()
	{
		echo "<select name=\"pid\">";
		$results = executeRoutineWithReader("ParticipantsGetAll","");
		for($i = 0; $i < mysql_num_rows($results); $i++)
		{
			$row = mysql_fetch_row($results);
			$id = $row[0];
			//$firstName = $row[1];
			//$lastName = $row[2];
			
			echo "	<option value=\"$id\">$id</option>";
		}
		echo "</select>";
	}
	
	function doInputValidation()
	{
		global $participantID, $deweyKey, $startDate, $endDate, $mode;

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
			
		if( isset($_GET["mode"]) )
		{
			$mode				= $_GET["mode"];
			if( $mode != "presence" && $mode != "breaks" )
				die("[Error]: invalid mode specified... are you playing with GET variables? :P");
		}
		else
			die("[Error]: mode not provided");
			
		if( isset($_GET["startDate"]) )
			$startDate			= $_GET["startDate"]; 
		else
			die("[Error]: start time not provided");
		
		$startDate = strtotime($startDate);
		
		if( isset($_GET["endDate"]) )
			$endDate			= $_GET["endDate"];
		else
			die("[Error]: end time not provided");
			
		$endDate = strtotime($endDate);
		
		if($startDate > $endDate)
			die("[Error]: start date must be before end date.");
		
		/* END INPUT VALIDATION */
		/* Authenticate */
		$results = executeRoutineWithReader("checkParticipantIDWithAlias",
											"'$deweyKey',$participantID");
											
		if( mysql_num_rows( $results ) < 1 )
			die("[Error]: Invalid key");
		
		return true;
	}
	
	/* analyzes break data from database and if it meets a certain threshold displays it in a table */
	function getBreaks()
	{
		global $participantID, $startDate, $endDate;
		
		$NUM_SECONDS_TOLERANCE = 30;
		
		$potentialBreaks = BreakTime::GetBreaks($participantID, $startDate, $endDate);	
		
		echo "Found: " . sizeof($potentialBreaks) . "<br/>";
		
		/* report all potential breaks in text */
		$BREAK_DURATION_SECONDS_TRESHOLD = 299;
		
		echo "<br/><table border=\"1\">";
		echo "<tr><td>Start</td><td>End</td><td>Duration</td><td>Data Points</td><td>Consistency</td></tr>";
		
		$totalBreakTime = 0;
		
		//			print_r($potentialBreaks);
		
		foreach( $potentialBreaks as $break )
		{
			$breakDuration = $break->getSeconds();
			//$breakDuration = getSecondDifference($break["start"]->getTimestamp(),$break["end"]->getTimestamp());
			if( $breakDuration > $BREAK_DURATION_SECONDS_TRESHOLD)
			{
				echo "<tr>";
					echo "<td>" . date('Y-m-d H:i:s', $break->getStartTimestamp()) . "</td>";
					echo "<td>" . date('Y-m-d H:i:s', $break->getEndTimestamp()) . "</td>";
					echo "<td>" . $breakDuration . " seconds (" . round($breakDuration/60,2) . " minutes) </td>";
					echo "<td>" . $break->getDataPoints() . "</td>";
					echo "<td>" . $break->getConsistency() . "%</td>";
				echo "</tr>";
				
				$totalBreakTime += $breakDuration;
			}
		}
		echo "</table><br/>Total break time: " . round($totalBreakTime/60,1) . " minutes";
	}
	
	/* analyzes break data from database and if it meets a certain threshold displays it in a table */
	function getPresence()
	{
		global $participantID, $startDate, $endDate;
		
		$NUM_SECONDS_TOLERANCE = 30;
		
		$potentialBreaks = Presence::GetPresence($participantID, $startDate, $endDate);	
		
		echo "Found: " . sizeof($potentialBreaks) . "<br/>";
		
		/* report all potential breaks in text */
		$BREAK_DURATION_SECONDS_TRESHOLD = 180;
		
		echo "<br/><table border=\"1\">";
		echo "<tr><td>Start</td><td>End</td><td>Duration</td><td>Data Points</td><td>Consistency</td></tr>";
		
		$totalBreakTime = 0;
		
		//			print_r($potentialBreaks);
		
		foreach( $potentialBreaks as $break )
		{
			$breakDuration = $break->getSeconds();
			//$breakDuration = getSecondDifference($break["start"]->getTimestamp(),$break["end"]->getTimestamp());
			if( $breakDuration > $BREAK_DURATION_SECONDS_TRESHOLD)
			{
				echo "<tr>";
					echo "<td>" . date('Y-m-d H:i:s', $break->getStartTimestamp()) . "</td>";
					echo "<td>" . date('Y-m-d H:i:s', $break->getEndTimestamp()) . "</td>";
					echo "<td>" . $breakDuration . " seconds ( " . round($breakDuration/60,2) . " minutes) </td>";
					echo "<td>" . $break->getDataPoints() . "</td>";
					echo "<td>" . $break->getConsistency() . "%</td>";
				echo "</tr>";
				
				$totalBreakTime += $breakDuration;
			}
		}
		echo "</table><br/>Total time present: " . round($totalBreakTime/60,1) . " minutes";
	}	
	
	function getSecondDifference($startTimeStamp, $endTimeStamp)
	{
		return $endTimeStamp-$startTimeStamp;
	}

?>
