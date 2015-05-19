<?php

/**
 * Description of MatchupPostCreator
 * 
 * Check if a matchup is newer than the last one and post a new matchup post if true
 *
 * @author jeppe
 */

// First of all, we make sure we are accessing the source file via SMF so that people can not directly access the file. 
if (!defined('SMF')) {
    die('Hack Attempt...');
}

require_once $_SERVER['DOCUMENT_ROOT'].'/fsp/GW2PHP-SDK/Gw2SDK.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/fsp/GW2PHP-SDK/Gw2Exception.php';

use \vesu\SDK\Gw2\Gw2SDK;
use \vesu\SDK\Gw2\Gw2Exception;

function checkMatchupPost(){
    $worldId = 2007;
    $gw2 = new Gw2SDK;
    $matches = $gw2->getMatches();
    
    //Get the relevant matchup for the given worldId
    $currentMatchup;
    foreach($matches as $matchup){
        if($matchup->red_world_id == $worldId || $matchup->green_world_id == $worldId || $matchup->blue_world_id == $worldId){
            echo "Checking ".$matchup->wvw_match_id . "<br />";
            print_r($matchup);
            $currentMatchup = $matchup;
            break;
        }
    }
    //I guess gw2's api is down?
    if(!isset($currentMatchup)){
        return;
    }
    
    //Retrive startTime for latest posted matchup
    global $smcFunc;
    $request = $smcFunc['db_query']('', '
        SELECT value
        FROM {db_prefix}settings
        WHERE variable = "post_last_matchup_id"
        LIMIT 1'
    );
    //Check if it has data for a previously posted matchup
    if ($smcFunc['db_num_rows']($request) > 0) {
        //Get selected row
        $row = $smcFunc['db_fetch_row']($request);
        //Check if fetched matchup is newer
        if($row[0] != $currentMatchup->start_time){
            createMatchupPost($currentMatchup);
        }
    } else {
        //no matchup has been posted before, so just post it now
        createMatchupPost($currentMatchup);
    }
}

function createMatchupPost($matchup){
    global $smcFunc, $sourcedir;
    //Easy conversion from world id to world name
    $worldIdToWorldName = [
    '1001' =>"Anvil Rock",
    '1002' =>"Borlis Pass",
    '1003' =>"Yak's Bend",
    '1004' =>"Henge of Denravi",
    '1005' =>"Maguuma",
    '1006' =>"Sorrow's Furnace",
    '1007' =>"Gate of Madness",
    '1008' =>"Jade Quarry",
    '1009' =>"Fort Aspenwood",
    '1010' =>"Ehmry Bay",
    '1011' =>"Stormbluff Isle",
    '1012' =>"Darkhaven",
    '1013' =>"Sanctum of Rall",
    '1014' =>"Crystal Desert",
    '1015' =>"Isle of Janthir",
    '1016' =>"Sea of Sorrows",
    '1017' =>"Tarnished Coast",
    '1018' =>"Northern Shiverpeaks",
    '1019' =>"Blackgate",
    '1020' =>"Ferguson's Crossing",
    '1021' =>"Dragonbrand",
    '1022' =>"Kaineng",
    '1023' =>"Devona's Rest",
    '1024' =>"Eredon Terrace",
    '2001' =>"Fissure of Woe",
    '2002' =>"Desolation",
    '2003' =>"Gandara",
    '2004' =>"Blacktide",
    '2005' =>"Ring of Fire",
    '2006' =>"Underworld",
    '2007' =>"Far Shiverpeaks",
    '2008' =>"Whiteside Ridge",
    '2009' =>"Ruins of Surmia",
    '2010' =>"Seafarer's Rest",
    '2011' =>"Vabbi",
    '2012' =>"Piken Square",
    '2013' =>"Aurora Glade",
    '2014' =>"Gunnar's Hold",
    '2101' =>"Jade Sea [FR]",
    '2102' =>"Fort Ranik [FR]",
    '2103' =>"Augury Rock [FR]",
    '2104' =>"Vizunah Square [FR]",
    '2105' =>"Arborstone [FR]",
    '2201' =>"Kodash [DE]",
    '2202' =>"Riverside [DE]",
    '2203' =>"Elona Reach [DE]",
    '2204' =>"Abaddon's Mouth [DE]",
    '2205' =>"Drakkar Lake [DE]",
    '2206' =>"Miller's Sound [DE]",
    '2207' =>"Dzagonur [DE]",
    '2301' =>"Baruch Bay [SP]"
   ];
    
    //Update latest posted matchup post start time
    $smcFunc['db_insert']('replace', 
        '{db_prefix}settings', 
        array('variable' => 'string', 'value' => 'string'), 
        array('post_last_matchup_id', $matchup->start_time), 
        array('variable')
    );
    
    require_once($sourcedir . '/Subs-Post.php');

    //used to redirect to gw2wvw.org
    $regionAndTier = explode("-",$matchup->wvw_match_id);
    
    //Template for the matchup post content
	$message = 'Servers
[list]
    [li][color=green][b]' . $worldIdToWorldName[$matchup->green_world_id] . '[/b][/color][/li]
    [li][color=blue][b]' . $worldIdToWorldName[$matchup->blue_world_id] . '[/b][/color][/li]
    [li][color=red][b]' . $worldIdToWorldName[$matchup->red_world_id] . '[/b][/color][/li]
[/list]

Matchup Live Maps
[list]
	[li][url=http://gw2wvw.org/?region='.$regionAndTier[0].'&tier='.$regionAndTier[1].']GW2WvW.org[/url][/li]
	[li][url=http://mos.millenium.org/]mos.millenium.org[/url][/li]
[/list]
Want something added to this post? feel free and tell us!
[i]This is an automated matchup post[/i]';
	preparsecode($message);

	$msgOptions = [
        'subject' => '[Matchup] ' . $worldIdToWorldName[$matchup->green_world_id] . ' - ' . $worldIdToWorldName[$matchup->blue_world_id] . ' - ' . $worldIdToWorldName[$matchup->red_world_id],
		'body' => $message,
        ];
	$topicOptions = array(
		'board' => 29, // this is ID of board where message gets posted
	);

    $posterOptions = array(
        'id' => 6031, //Far Shiverpeaks userid
        'update_post_count' => true,
        'ip' => "127.0.0.1",
    );
	createPost($msgOptions, $topicOptions, $posterOptions); 
}

//Call the method
checkMatchupPost();