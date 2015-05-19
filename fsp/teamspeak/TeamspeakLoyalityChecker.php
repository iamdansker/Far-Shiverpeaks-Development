<?php

/*
 * The MIT License
 *
 * Copyright 2015 jeppe.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of TeamspeakLoyalityChecker
 *
 * @author jeppe
 */
require_once($_SERVER['DOCUMENT_ROOT'].'/fsp/authentication/GW2LoyalityChecker.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/fsp/teamspeak/teamspeak_php_framework/libraries/TeamSpeak3/TeamSpeak3.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/fsp/teamspeak/TeamspeakConnector.php');
class TeamspeakLoyalityChecker extends TeamspeakConnector{
    const FSP_TS_SERVER_GROUP = 56; 
    
    public function getAuthenticatedTSDbids(){
        global $smcFunc;
        //Query authenticated teamspeak users
        $request = $smcFunc['db_query']('', '
			SELECT teamspeak_dbid
			FROM {db_prefix}teamspeak_link ts
            INNER JOIN {db_prefix}gw2_account gw2
                ON ts.smf_user_id = gw2.smf_user_id
			WHERE gw2.expires > 0
                AND gw2.world = {int:world}', 
            array(
                'world' => GW2LoyalityChecker::fsp_home_world
            )
        );
        
        $dbidList = [];
        while ($row = $smcFunc['db_fetch_row']($request)){
            $dbidList[] = $row[0];
        }
        //cleanup
        $smcFunc['db_free_result']($request);
        
        return $dbidList;
    }
    
    public function checkFSPMemberGroup(){
        $authenticatedTSDbids = $this->getAuthenticatedTSDbids();
        $clients = $this->ts3_VirtualServer->serverGroupClientList(self::FSP_TS_SERVER_GROUP);
        foreach($clients as $client){
            $tsDbid = $client["cldbid"];
            //Remove unauthorized users
            $index = array_search($tsDbid, $authenticatedTSDbids);
            //Check if not found
            if($index === false){
                $this->removeFSPMemberGroup($tsDbid);
            } else {
                $this->debug_echo("Already Authenticated ".$tsDbid);
                //Remove from list in order to see afterwards, which users need to 
                //be added to the FSP servergroup
                unset($authenticatedTSDbids[$index]);
            }
            //$this->debug_echo($client);
        }
        foreach($authenticatedTSDbids as $authenticatedTSDbid){
            $this->addFSPMemberGroup($authenticatedTSDbid);
        }
    }
    
    public function addFSPMemberGroup($tsDbid){
        $this->debug_echo("Adding tsDbid ".$tsDbid);
        $this->ts3_VirtualServer->clientGetByDbid($tsDbid)->addServerGroup(self::FSP_TS_SERVER_GROUP);
    }
    public function removeFSPMemberGroup($tsDbid){
        $this->debug_echo("Removing tsDbid ".$tsDbid);
        $this->debug_echo("Not implemented");
        //$this->ts3_VirtualServer->clientGetByDbid($tsDbid)->remServerGroup(self::FSP_TS_SERVER_GROUP);
    }
    
    
    public $_debug = false;
    private $_debug_last_function = "";
    public function debug_echo($message){
        if($this->_debug){
            $callers=debug_backtrace();
            $size = count($callers);
            $space = 40;
            $color = "blue";
            if($callers[1]["function"] != $this->_debug_last_function){
                $this->_debug_last_function = $callers[1]["function"];
                //$this->debug_echo($callers[1]);
                echo "<h2 style='margin: 10px 0px 0px 0px; padding-left:" . ($size * $space) . "px'><font style='color: blue; border-left: $color; border-left-style: solid; padding-left: 10px;'>" . $this->_debug_last_function . "()</font> - " . $callers[1]["class"] . " - [ <font color='green'><i>" . $callers[2]["function"] . "()</i></font> ]</h2>";
            }
            if(is_array($message)){
                echo "<div style='margin-left:" . ($size * $space) . "px'><pre style='margin: 0px; border-left: $color; border-left-style: solid; padding-left: 10px;'>".$callers[0]["line"].": ";
                print_r($message);
                echo "</pre></div>";
            } else {
                echo "<div style='margin: 0px 0px 0px 0px; padding-left:" . ($size * $space) . "px'><p style='margin: 0px; border-left: $color; border-left-style: solid; padding: 5px; padding-left: 10px'>" . $callers[0]["line"] . ": " . $message . "</p></div>";
            }
        }
    }
}
