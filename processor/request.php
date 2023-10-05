<?php
/*
 Request creation and player_request overriding
*/

if ($gameRequest[0] == "funcret") { // Take out the functions part

	$returnFunction = explode("@", $gameRequest[3]); // Function returns here
	$functionCodeName=$returnFunction[1];
		
	//$request = str_replace("call function if needed,", "continue chat as $HERIKA_NAME,", $PROMPTS["inputtext"][0]); 
	if (isset($PROMPTS["afterfunc"]["cue"][$functionCodeName])) {
		$request =$PROMPTS["afterfunc"]["cue"][$functionCodeName];
	
	} else 
		$request =$PROMPTS["afterfunc"]["cue"]["default"];
	
	/*
	Functions of which return value is provided by server
	$returnFunction is in the form command@function codename@function parameter@result
	So here we will override the result (which probably will be nothing)
	*/
	
	if ($functionCodeName == "ReadQuestJournal") {
		$returnFunction[3] = $db->questJournal($returnFunction[2]); // Overwrite funrect content with info from database
		$gameRequest[3] .= $returnFunction[3];						// Add also to $gameRequest 
	
		// Store info.
		$db->insert(
			'eventlog',
			array(
				'ts' => $gameRequest[1],
				'gamets' => $gameRequest[2],
				'type' => 'chat',
				'data' => SQLite3::escapeString("The Narrator. Herika reads in diary:".$returnFunction[3]),
				'sess' => 'pending',
				'localts' => time()
			)
		);
		
		
	} else if ($functionCodeName == "SearchDiary") {
		
		$returnFunction[3] = $db->diaryLogIndex($returnFunction[2]);	// Overwrite funrect content with info from database
		$gameRequest[3] .= $returnFunction[3];							// Add also to $gameRequest 
		
		
	}  else if ($functionCodeName == "ReadDiaryPage") {
		
		$returnFunction[3] = $db->diaryLog($returnFunction[2]); // Overwrite funrect content with info from database
		$gameRequest[3] .= $returnFunction[3];					// Add also to $gameRequest 
		
	} else if ($functionCodeName == "SetCurrentTask") {
		// "Task" here is the last motto. "Let's take the hobbits to Isengard"->Current task should be "Travel to Isengard"
		$returnFunction[3] .= "ok"; // This is always ok
		$gameRequest[3].="done";
		// This table is a stack whithout pop.
		$db->insert(
			'currentmission',
			array(
				'ts' => $gameRequest[1],
				'gamets' => $gameRequest[2],
				'description' => SQLite3::escapeString($returnFunction[2]),
				'sess' => 'pending',
				'localts' => time()
			)
		);
	} else {
		if (isset($GLOBALS["FUNCSERV"][$functionCodeName])) {
			call_user_func_array($GLOBALS["FUNCSERV"][$functionCodeName],[]);
		}
		
	}
	
} else if ($gameRequest[0] == "chatnf_book") { // Takea out the functions part
	$request = $PROMPTS["book"]["cue"][0];
	$books=$db->fetchAll("select title from books order by gamets desc");
	// Override player request here. This request is generated by dll plugin, in english.
	$gameRequest[3]=$PROMPTS["book"]["player_request"][0]." ".$books[0]["title"];	
	
	
} else if ($gameRequest[0] == "diary") {
	$request = $PROMPTS["diary"]["cue"][0];
	$GLOBALS["FORCE_MAX_TOKENS"]=$GLOBALS["CONNECTOR"][DMgetCurrentModel()]["MAX_TOKENS_MEMORY"];


} else {
	if (isset($PROMPTS[$gameRequest[0]]["player_request"])) {
		$request = $PROMPTS[$gameRequest[0]]["cue"][0]; // Add support for arrays here	
		$gameRequest[3]=$PROMPTS[$gameRequest[0]]["player_request"][0];
	}
	else
		$request = $PROMPTS[$gameRequest[0]]["cue"][0]; // Add support for arrays here	
}






$commandSent = false;

// Add
if (($gameRequest[0] == "inputtext") || ($gameRequest[0] == "inputtext_s") || (strpos($gameRequest[0],"chatnf")!==false)) {
	$gameRequest[3] = $gameRequest[3]." $DIALOGUE_TARGET";
}

?>
