<?php
/* under construction */
class SuggestedBreak
{
	private $id;
	private $participantID;
	private $suggestionType;
	private $datetimeCreated;
	private $alertIntensity;
	private $suggestionStatus;
	private $datetimeUpdated;
	
	public function __construct($theID, $theParticipantID, $theSuggestionType, $theDatetimeCreated,
								$theAlertIntensity, $theSuggestionStatus, $theDatetimeUpdated)
	{
		$this->id = $theID;
		$this->participantID = $theParticipantID;
		$this->suggestionType = $theSuggestionType;
		$this->datetimeCreated = $theDatetimeCreated;
		$this->alertIntensity = $theAlertIntensity;
		$this->suggestionStatus = $theSuggestionStatus;
		$this->datetimeUpdated = $theDatetimeUpdated;
	}
	
	/* returns an array of SuggestedBreak objects */
	public static function GetUnresolvedSuggestions($participantID, $startDate, $endDate)
	{
		$results = executeRoutineWithReader("SuggestionsUnresolvedByDateRange",
		    					"$participantID,FROM_UNIXTIME($startDate),FROM_UNIXTIME($endDate)");
		
		$unresolvedSuggestions = array();
		
		$i = 0;
		while( $row = mysql_fetch_array($results) )
		{
			$unresolvedSuggestions[$i] = 
				new SuggestedBreak( $row["id"], $row["fkParticipantID"], 
					$row["type"], strtotime($row["datetimeCreated"] . " GMT-0400"), $row["alertIntensity"],
					$row["status"], strtotime($row["datetimeUpdated"] . " GMT-0400") );
					
			$i++;
		}
		
		return $unresolvedSuggestions;
	}
	
	public static function NewSuggestion($participantID, $intensity)
	{
		$results = executeRoutineWithReader("NewSuggestion",
						"$participantID,$intensity");
	}
	
	public function update()
	{
		// update object in database
		$results = executeRoutineWithReader("UpdateSuggestion",
		    			"$this->id,$this->alertIntensity,'$this->suggestionStatus'");
		
		// get updated time from procedure
		$row = mysql_fetch_array($results);
		$this->datetimeUpdated = strtotime($row["datetimeUpdated"] . " GMT-0400");
		
	}
	
	/* Probably won't need */
	public function getLog()
	{
		
	}
	

	/* getters and setters */
	public function getDatetimeCreated()
	{
		return $this->datetimeCreated;
	}
	
	public function getDatetimeUpdated()
	{
		return $this->datetimeUpdated;
	}
	
	public function getAlertIntensity()
	{
		return $this->alertIntensity;
	}
	
	public function getSuggestionStatus()
	{
		return $this->suggestionStatus;
	}
	
	public function setDatetimeCreated($datetime)
	{
		$this->datetimeCreated = $datetime;
	}
	
	public function setDatetimeUpdated($datetime)
	{
		$this->datetimeUpdated = $datetime;
	}
	
	public function setAlertIntensity($intensity)
	{
		if( $intensity <= 3 && $intensity >= 0 )
			if($intensity == 1)
				$this->alertIntensity = 2;
			else
				$this->alertIntensity = $intensity;
	}
	
	public function setSuggestionStatus($status)
	{
		if( $status == "suggested" || $status == "delayed" || $status == "acknolwedged" ||
		    $status == "declined" || $status == "created" || $status == "taken" )
		{
			$this->suggestionStatus = $status;
		}
	}

}
?>