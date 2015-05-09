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
 * Description of GW2LoyalityChecker
 *
 * @author jeppe
 */
require_once 'GW2APIKeyPersistenceSMF.php';
class GW2LoyalityChecker extends GW2APIKeyPersistence{
    const fsp_home_world = 2007;
    const fsp_member_group = 16;
    
    function onAccountInfoExpired($userId) {
        parent::onAccountInfoExpired($userId);
        $this->removeFromFSPUserGroup($userId);
    }
    
    function onAccountInfoUpdated($userId, $values) {
        parent::onAccountInfoUpdated($userId, $values);
        $this->checkWorldLoyality($userId, $values[3]);
    }
    
    /**
     * Check if a user's world is FSP, if true, add them to the FSP membergroup,
     * if not, remove them from it
     * @param type $userId
     * @param type $worldId
     * @return boolean
     */
    public function checkWorldLoyality($userId, $worldId){
        $this->debug_echo("Check loyality of user:" . $userId . " world: ". $worldId);
        //Check world association
        if($worldId == self::fsp_home_world){
            $this->addToFSPUserGroup($userId);
            return true;
        } else {
            $this->removeFromFSPUserGroup($userId);
            return false;
        }
    }
    
    /**
     * Remove all users from the FSP Member group who does not have any saved
     * accound data
     * @global type $smcFunc
     * @global type $modSettings
     */
    public function checkFSPUserGroup(){
        global $smcFunc, $modSettings;
        $startTime = microtime(true);
        //Query saved gw2 user data
        $modSettings['disableQueryCheck'] = true;
        $request = $smcFunc['db_query']('', '
            SELECT members.id_member
            FROM {db_prefix}members members
            WHERE 
                (id_group = {int:id_group}
                OR FIND_IN_SET({int:id_group}, additional_groups) > 0) 
                AND NOT EXISTS (
                    SELECT *
                    FROM {db_prefix}gw2_account account
                    WHERE 
                        members.id_member = account.smf_user_id
                        AND account.expires > {int:time}
                )',
            array(
                'id_group' => self::fsp_member_group, 
                'time' => time()
            )
        );
        $modSettings['disableQueryCheck'] = false;
        
        $this->debug_echo("Removing ".$smcFunc['db_num_rows']($request)." Members access");
        
        //Get selected row
        while ($row = $smcFunc['db_fetch_row']($request)){
            //Prevent the script from exiting after many checks as it might take a while
            set_time_limit(30);
            $startTimeUser = microtime(true);
            $this->removeFromFSPUserGroup($row[0]);
            //nano seconds
            $timeSpent = (microtime(true) - $startTimeUser) * 1000000;
            $this->debug_echo("Time spent in ms on user $row[0]: ".($timeSpent/1000));
        }
        //cleanup
        $smcFunc['db_free_result']($request);
        $this->debug_echo("Time spent in ms total: ".((microtime(true) - $startTime) * 1000));
    }
    
    protected function addToFSPUserGroup($userId){
        global $smcFunc;
        
        $group = self::fsp_member_group;
        $members = array($userId);
        //Taken from Subs-Membergroups.php line 553
        $response = $smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				id_group = CASE WHEN id_group = {int:regular_group} THEN {int:id_group} ELSE id_group END,
				additional_groups = CASE WHEN id_group = {int:id_group} THEN additional_groups
					WHEN additional_groups = {string:blank_string} THEN {string:id_group_string}
					ELSE CONCAT(additional_groups, {string:id_group_string_extend}) END
			WHERE id_member IN ({array_int:member_list})
				AND id_group != {int:id_group}
				AND FIND_IN_SET({int:id_group}, additional_groups) = 0',
			array(
				'member_list' => $members,
				'regular_group' => 0,
				'id_group' => $group,
				'blank_string' => '',
				'id_group_string' => (string) $group,
				'id_group_string_extend' => ',' . $group,
			)
		);
        $this->debug_echo("Response: ".$response);
        $this->debug_echo("Added user " . $userId . " to FSP member group");
    }
    
    protected function removeFromFSPUserGroup($userId){
        global $smcFunc;
        
        $groups = array(self::fsp_member_group);
        $members = array($userId);
        //Taken from Subs-Membergroups.php line 372
        
        // First, reset those who have this as their primary group - this is the easy one.
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}members
            SET id_group = {int:regular_member}
            WHERE id_group IN ({array_int:group_list})
                AND id_member IN ({array_int:member_list})',
            array(
                'group_list' => $groups,
                'member_list' => $members,
                'regular_member' => 0,
            )
        );

        // Those who have it as part of their additional group must be updated the long way... sadly.
        $request = $smcFunc['db_query']('', '
            SELECT id_member, additional_groups
            FROM {db_prefix}members
            WHERE (FIND_IN_SET({raw:additional_groups_implode}, additional_groups) != 0)
                AND id_member IN ({array_int:member_list})
            LIMIT ' . count($members),
            array(
                'member_list' => $members,
                'additional_groups_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
            )
        );
        $updates = array();
        while ($row = $smcFunc['db_fetch_assoc']($request))
        {
            $updates[$row['additional_groups']][] = $row['id_member'];
        }
        $smcFunc['db_free_result']($request);

        foreach ($updates as $additional_groups => $memberArray){
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}members
                SET additional_groups = {string:additional_groups}
                WHERE id_member IN ({array_int:member_list})',
                array(
                    'member_list' => $memberArray,
                    'additional_groups' => implode(',', array_diff(explode(',', $additional_groups), $groups)),
                )
            );
        }
        $this->debug_echo("Removed user " . $userId . " from FSP member group");
    }
}
