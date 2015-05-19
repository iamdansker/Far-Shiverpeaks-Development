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
 * Description of TeamspeakConnector
 *
 * @author jeppe
 */

//load framework files
require_once($_SERVER['DOCUMENT_ROOT'].'/fsp/teamspeak/teamspeak_php_framework/libraries/TeamSpeak3/TeamSpeak3.php');
class TeamspeakConnector {
    protected $ts3_VirtualServer;
    
    public function __construct() {
        global $smcFunc;
        $query = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}settings
			WHERE variable IN ("TSServerQueryUser", "TSServerQueryUserPassword", "TSServerQueryAddress", "TSServerQueryPort", "TSServerPort")'
        );
        
        $tsArgs = [];
        while ($row = $smcFunc['db_fetch_row']($query)){
            $tsArgs[$row[0]] = $row[1];
        }
        //cleanup
        $smcFunc['db_free_result']($query);
        //Create connection
        $this->ts3_VirtualServer = TeamSpeak3::factory("serverquery://" . 
                $tsArgs['TSServerQueryUser'] . ":" . 
                $tsArgs['TSServerQueryUserPassword'] . "@" . 
                $tsArgs['TSServerQueryAddress'] . ":" . 
                $tsArgs['TSServerQueryPort'] . "/?server_port=" .
                $tsArgs['TSServerPort']);
    }
}