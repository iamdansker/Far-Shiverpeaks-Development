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
 * Description of GW2APIKeyIntegration
 *
 * @author jeppe
 */
class GW2APIKeyIntegration {

    /**
     * Make a request for the GW2 JSON API
     * @param type $endPoint
     * @param type $apiKey
     * @return JSON
     */
    function makeRequest($endPoint, $apiKey) {
        //Prepare cURL request
        $curl = curl_init();
        //Set URL
        curl_setopt($curl, CURLOPT_URL, "https://api.guildwars2.com/" . $endPoint);
        //Return response as a String instead of outputting to a screen
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        //Prepare Request Header
        $headers = array();
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        //Add Request Header
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        //Perform request
        $response = curl_exec($curl);
        //HTTP Status
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //Close request connection
        curl_close($curl);
        //Check if request was successful
        if($http_status == 200){
            //Decode Json response
            $json = json_decode($response, true);

            //Check if request was successful
            if (isset($json["text"]) && $json["text"] == "endpoint requires authentication") {
                throw new GW2APIKeyException('endpoint requires authentication', $apiKey, $response, 1);

            //Known to be set if endpoint can't be found
            } elseif(isset($json["error"])){
                throw new GW2APIKeyException($json["error"], $apiKey, $response, 2);
            }
        } else {
            throw new GW2APIKeyException('HTTP Code: '.$http_status, $apiKey, $response, -1);
        }
        return $json;
    }

    /**
     * Request information from the account endpoint
     * @param type $apiKey
     * @return JSON
     * @throws GW2APIKeyException
     */
    function requestAccountInfo($apiKey) {
        $json = $this->makeRequest("v2/account", $apiKey);
        if (
                !isset($json["id"]) ||
                !isset($json["name"]) ||
                !isset($json["world"]) ||
                !isset($json["guilds"])
        ) {
            throw new GW2APIKeyException('Could not parse account information', $apiKey, $json, 3);
        }
        return $json;
    }
}

class GW2APIKeyException extends Exception {
    private $apiKey;
    private $response;
    public function __construct($message, $apiKey, $response, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->apiKey = $apiKey;
        $this->response = $response;
    }
    
    public function getResponse(){
        return $this->response;
    }
    
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message} APIKey: {$this->apiKey} Response: {$this->response}\n";
    }

}
