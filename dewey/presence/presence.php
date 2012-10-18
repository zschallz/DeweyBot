<?php

class Presence
{
	private $startTimestamp = 0;
	private $endTimestamp = 0;
	private $dataPoints = 0;
	
	public function __construct($theStartTimestamp, $theEndTimestamp)
	{
		$this->startTimestamp = $theStartTimestamp;
		$this->endTimestamp = $theEndTimestamp;
	}
	
	/* returns an array of BreakTime objects */
	public static function GetPresence($participantID, $startDate, $endDate)
	{
		$NUM_SECONDS_TOLERANCE = 30;
		
		$results = executeRoutineWithReader("PresenceDetectedByParticipantIDAndDateRange",
		    					"$participantID,FROM_UNIXTIME($startDate),FROM_UNIXTIME($endDate)");
		
	
		$dateTimes = array();
		
		for($i = 0; $i < mysql_num_rows($results); $i++)
		{
			$row    	 = mysql_fetch_row($results);
			$dateTimes[] = new DateTime($row[1]);
		}
		
		$potentialPresence = array();
		$breakNum = 0;
		$breakDataPoint = 0;
		
		for($i = 0; $i < sizeof($dateTimes)-1; $i++)
		{
			/* get difference between this data point and the next one */
			
			
			/* if the difference is less than the tolerance... */
			if($dateTimes[$i+1]->getTimestamp()-$dateTimes[$i]->getTimestamp()
					< $NUM_SECONDS_TOLERANCE)
			{
				//echo "found point at " . $dateTimes[$i]->format('Y-m-d H:i:s') . ".. currently on point " . $breakDataPoint . "   <br/>";
				/* and we're on the first breakDataPoint */
				if($breakDataPoint == 0) 
				{
					//echo $breakNum;
					$potentialPresence[$breakNum] = 
						new BreakTime($dateTimes[$i]->getTimestamp(),$dateTimes[$i]->getTimestamp());
					
				}
				else
				{	
					if( $i == sizeof($dateTimes)-2 )
					{
						$potentialPresence[$breakNum]->setDataPoints($breakDataPoint+1);
						$potentialPresence[$breakNum]->setEndTimestamp($dateTimes[$i+1]->getTimestamp());
					}
					else
						$potentialPresence[$breakNum]->setEndTimestamp($dateTimes[$i]->getTimestamp());
				}	
				$breakDataPoint++; // we've stepped past one break data point
			}
			else
			{
				/* The next data point isn't part of this break.
				 * if we've already discovered breakDataPoints and we're at this point 
				 * we've discovered a break. Move on.
				 */
				//echo "$breakDataPoint<br/>";
				
				if($breakDataPoint > 0)
				{
					$potentialPresence[$breakNum]->setEndTimestamp($dateTimes[$i]->getTimestamp());
					$potentialPresence[$breakNum]->setDataPoints($breakDataPoint);
					$breakNum++;
					$breakDataPoint = 0;
				}
				//else
				//{
				//	breakNum++;
				//	$potentialPresence[$breakNum] = 
				//		new BreakTime($dateTimes[$i]->getTimestamp(),$dateTimes[$i]->getTimestamp());
				//}
			
			}
			
		}
		
		if( $breakDataPoint > 0 )
			$breakNum++;
		
		return $potentialPresence;
	}
	
	

	/* getters and setters */
	public function getStartTimeStamp()
	{
		return $this->startTimestamp;
	}
	
	public function getEndTimeStamp()
	{
		return $this->endTimestamp;
	}
	
	public function getSeconds()
	{
		return ($this->endTimestamp-$this->startTimestamp);
	}
	
	public function setStartTimestamp($startTimestamp)
	{
		$this->startTimestamp = $startTimestamp;
	}
	
	public function setEndTimestamp($theEndTimestamp)
	{
		$this->endTimestamp = $theEndTimestamp;
	}
	
	public function setDataPoints($dataPoints)
	{
		$this->dataPoints = $dataPoints;	
	}
	public function getDataPoints()
	{
		return $this->dataPoints;
	}
	private	function getSecondDifference($startTimeStamp, $endTimeStamp)
	{
		return $endTimeStamp-$startTimeStamp;
	}

}
?>