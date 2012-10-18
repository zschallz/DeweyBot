<?php

class BreakTime
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
	public static function GetBreaks($participantID, $startDate, $endDate)
	{
		$NUM_SECONDS_TOLERANCE = 30;
		
		$results = executeRoutineWithReader("NoPresenceDetectedByParticipantIDAndDateRange",
		    					"$participantID,FROM_UNIXTIME($startDate),FROM_UNIXTIME($endDate)");
		
		//var_dump($results);
		$dateTimes = array();
		for($i = 0; $i < mysql_num_rows($results); $i++)
		{
			$row    	 = mysql_fetch_row($results);
			$dateTimes[$i] = new DateTime($row[1]);
		}
		
		$potentialBreaks = array();
		$breakNum = 0;
		$breakDataPoint = 0;
		
		for($i = 0; $i < sizeof($dateTimes)-1; $i++)
		{
			/* get difference between this data point and the next one */
			
			
			/* if the difference is less than the tolerance... */
			if($dateTimes[$i+1]->getTimestamp()-$dateTimes[$i]->getTimestamp()
					< $NUM_SECONDS_TOLERANCE)
			{
				/* and we're on the first breakDataPoint */
				if($breakDataPoint == 0) 
				{
					$potentialBreaks[$breakNum] = 
						new BreakTime($dateTimes[$i]->getTimestamp(),$dateTimes[$i]->getTimestamp());
					
				}
				else
				{	
					if( $i == sizeof($dateTimes)-2 )
					{
						$potentialBreaks[$breakNum]->setDataPoints($breakDataPoint+1);
						$potentialBreaks[$breakNum]->setEndTimestamp($dateTimes[$i+1]->getTimestamp());
					}
					else
						$potentialBreaks[$breakNum]->setEndTimestamp($dateTimes[$i]->getTimestamp());
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
					$potentialBreaks[$breakNum]->setEndTimestamp($dateTimes[$i]->getTimestamp());
					$potentialBreaks[$breakNum]->setDataPoints($breakDataPoint);
					$breakNum++;
					$breakDataPoint = 0;
				}
				//else
				//{
				//	breakNum++;
				//	$potentialBreaks[$breakNum] = 
				//		new BreakTime($dateTimes[$i]->getTimestamp(),$dateTimes[$i]->getTimestamp());
				//}
			
			}
			
		}
		
		if( $breakDataPoint > 0 )
			$breakNum++;
		
		return $potentialBreaks;
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
	public function getConsistency()
	{
		return round(($this->dataPoints/($this->getSeconds()/10))*100,1);
	}

}
?>