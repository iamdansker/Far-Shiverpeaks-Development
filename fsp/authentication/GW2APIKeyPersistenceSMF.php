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
 * Provide peristency to the retrieved information
 * This is implemented based on SMF's smcFunc database access methods
 * @author jeppe
 */
require_once 'GW2APIKeyIntegration.php';
class GW2APIKeyPersistence extends GW2APIKeyIntegration{
    
    /******************************
     * API Key Persistence
     ******************************/
    
    //If some information cannot be updated, set the time left befor it expires
    //be maximum informationTimeout
    const errorTimeout = 86400; //A day in seconds
    const successfulTimeout = 907200; //A week and a half in seconds

    private $apiKeyDBField = "cust_apikey";
    
    function getUserAPIKey($userId){
        global $smcFunc;
        //Query saved gw2 user data
        $request = $smcFunc['db_query']('', '
            SELECT value
            FROM {db_prefix}themes 	
            WHERE id_member = {int:user_id}
            AND id_theme = 1
            AND variable = {string:variable}
            LIMIT 1', 
            array(
                "user_id" => $userId,
                "variable" => $this->apiKeyDBField
            )
        );
        //New entry if number of rows <= 0
        if ($smcFunc['db_num_rows']($request) <= 0) {
            //User doesn't have an API Key saved
            return null;
        }
        //Get API Key from given user
        $row = $smcFunc['db_fetch_row']($request);
        //cleanup
        $smcFunc['db_free_result']($request);
        //return API key
        return $row[0];
    }
    
    function queryUsersAPIKeys(){
        global $smcFunc;
        //Query all saved gw2 user data
        $query = $smcFunc['db_query']('', '
            SELECT id_member, value
            FROM {db_prefix}themes 	
            WHERE id_theme = 1
            AND variable = {string:variable}
            LIMIT 1', 
            array(
                "variable" => $this->apiKeyDBField
            )
        );
        return $query;
    }
    
    function storeUserAPIKey($userId, $apiKey){
        //values to replace in the chosen fields
        $values = array(
            $userId, 
            $this->apiKeyDBField,
            $apiKey
        );
        $this->insertIntoThemesDb($values);
    }
    
    private function insertIntoThemesDb($values){
        global $smcFunc;
        return $smcFunc['db_query']('', '
            INSERT INTO {db_prefix}themes (id_member, id_theme, variable, value) 
            VALUES(
                {int:id_member}, 
                1, 
                {string:variable}, 
                {string:value}
            ) 
            ON DUPLICATE KEY UPDATE    
                value=VALUES(value)',
            $values
        );
    }
    
    
    /******************************
     * GW2 Account Info Persistence
     ******************************/
    
    
    /**
     * Get a list of all available GW2 account information stored in the database
     * for user $userid
     * @global type $smcFunc
     * @param type $userid
     * @return type
     */
    function getAccountInfo($userId){
        global $smcFunc;
        //Query saved gw2 user data
        $request = $smcFunc['db_query']('', '
			SELECT smf_user_id, uuid, username, world, expires
			FROM {db_prefix}gw2_account
			WHERE smf_user_id = {int:smf_user_id}
			LIMIT 1', array(
                'smf_user_id' => $userId
            )
        );
        //New entry if number of rows <= 0
        if ($smcFunc['db_num_rows']($request) <= 0) {
            //User doesn't exist
            return null;
        }
        //Get selected row
        $row = $smcFunc['db_fetch_assoc']($request);
        //cleanup
        $smcFunc['db_free_result']($request);
        return $row;
    }
    
    /**
     * 
     * @param type $apiKey
     * @param type $userId optional, if set will save it in DB to that user
     */
    function requestAccountInfo($apiKey, $userId){
        $json = parent::requestAccountInfo($apiKey);
        //If userId is set, save data to DB
        if(isset($userId)){
            //values to save
            $values = array(
                $userId, $json["id"], $json["name"], $json["world"], $this->calculateSuccessfulTimeoutTime()
            );
            $this->persistAccountInfo($values);
            $this->onAccountInfoUpdated($userId, $values);
        }
        return $json;
    }
    
    /**
     * 
     * @global type $smcFunc
     * @param type $values {userId, accountId, accountName, accountWorld, informationTimeout}
     */
    function persistAccountInfo($values){
        global $smcFunc;
        //fields to change
        $fields = array(
            'smf_user_id' => 'int', 'uuid' => 'string', 'username' => 'string', "world" => "int", "expires" => "int"
        );
        $smcFunc['db_insert']('replace', '{db_prefix}gw2_account', $fields, $values, array('smf_user_id'));
    }
    
    /**
     * Retrieve account information from all users with an API key and save 
     * update information to the database
     */
    function updateAccountsInfo($delay = 500){
        global $smcFunc;
        //Convert to nano seconds
        $delayNano = $delay * 1000;
        //Retrieve query for all API keys
        $query = $this->queryUsersAPIKeys();
        //Check if empty
        if ($smcFunc['db_num_rows']($query) <= 0) {
            //No users have saved any API key
            return;
        }
        //Loop through each users API Key
        while ($row = $smcFunc['db_fetch_row']($query)){
            $this->debug_echo("Updating account info for user: $row[0]");
            //Prevent the script from exiting after many checks as it might take a while
            set_time_limit(30);
            //Save start time in order to measure time spent
            $startTime = microtime(true);
            try{
                //This will also save it
                $this->requestAccountInfo($row[1], $row[0]);
            } catch (Exception $e) {
                $this->debug_echo("Could not update account info for user: $row[0]");
                $this->errorRequestTimeout($row[0]);
            }
            $timeSpent = (microtime(true) - $startTime) * 1000000;
            $this->debug_echo("Time spent in ms on user $row[0]: ".($timeSpent/1000));
            //Check if it should sleep or just continue
            if($delayNano > $timeSpent){
                $this->debug_echo("Sleeping for : ".($delay - ($timeSpent / 1000)));
                usleep($delayNano - $timeSpent);
            }
        }
        //cleanup
        $smcFunc['db_free_result']($query);
    }
    
    /**
     * Retrieve account information from all users from the database and
     * check if it has expired
     */
    function checkAllAccountData(){
        global $smcFunc;
        //Retrieve all account data
        $query = $smcFunc['db_query']('', '
			SELECT smf_user_id, uuid, username, world, expires
			FROM {db_prefix}gw2_account'
        );
        if ($smcFunc['db_num_rows']($query) <= 0) {
            //No users have any account data
            return;
        }
        //Loop through each users API Key
        while ($row = $smcFunc['db_fetch_row']($query)){
            $this->debug_echo("Checking account info for user: $row[0]");
            //Prevent the script from exiting after many checks as it might take a while
            set_time_limit(30);
            //Check if the account's data has expired, if so, calls onAccountInfoExpired($userId)
            $this->checkIfAccoundDataExpired($row[0], $row[4]);
            //Small sleep time of 10ms
            usleep(10000);
        }
        //cleanup
        $smcFunc['db_free_result']($query);
    }
    
    /**
     * Called when a user's account info has been updated
     * @param type $userId
     * @param type $values
     */
    public function onAccountInfoUpdated($userId, $values){
        $this->debug_echo("Account info updated for user: ".$userId);
    }
    
    /**
     * Called when a user's account info has expired
     * @param type $userId
     */
    public function onAccountInfoExpired($userId){
        $this->debug_echo("Account info expired for user: ".$userId);
    }
    
    /**
     * Calculate when a record should expire from right now using
     * the timeout from a successful request
     * @return type
     */
    function calculateSuccessfulTimeoutTime(){
        return time() + self::successfulTimeout;
    }
    
    /**
     * Calculate when a record should expire from right now using
     * the timeout from an unsuccessful request
     * @return type
     */
    function calculateErrorTimeoutTime(){
        return time() + self::errorTimeout;
    }
    
    /**
     * Set an account info to timeout within the errorTimeout time.
     * Do nothing if account info is timining out in less than the errorTimeout
     * Unless the account info has already expired, in which case call onAccountInfoExpired($userId)
     * @global type $smcFunc
     * @param type $userId
     */
    function errorRequestTimeout($userId){
        $accountInfo = $this->getAccountInfo($userId);
        $timeout = $this->calculateErrorTimeoutTime();
        //Check if account information is set to timeout after our new timeout
        //if so, set it to our new timeout
        if($accountInfo["expires"] > $timeout){
            $this->debug_echo("Account info set to expire for user: ".$userId);
            $this->updateAccountInfoExpires($userId, $timeout);
        //If it has already expired, the expires time will be negative, so don't expire
        //again if it isn't above 0
        } else {
            $this->checkIfAccoundDataExpired($userId,$accountInfo["expires"]);
        }
    }
    
    /**
     * Check if an accounts data has expired, if so, call onAccountInfoExpired($userId)
     * @param type $userId
     * @param type $expires
     */
    function checkIfAccoundDataExpired($userId, $expires){
        if($expires < time() && $expires >= 0){
            $this->onAccountInfoExpired($userId);
            $this->updateAccountInfoExpires($userId, -$expires);
        }
    }
    
    function updateAccountInfoExpires($userId, $expires){
        global $smcFunc;
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}gw2_account
            SET expires = {int:expires}
            WHERE smf_user_id = {int:userId}',
            array(
                "userId" => $userId,
                "expires" => $expires
            )
        );
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
