/**
 * Description of WvWwidget
 *
 * @author jeppe
 */

//World to show matchup details from
var world = 2007; //2007 == Far Shiverpeaks
//GW2 API library
var GW2Lib;
//Calculated ppt array
var ppt;
//Calculated score array
var score;
//Retrived match for the given world
var match;
//Retrived match details for the given world
var matchDetails;
//Images used to represent each server
var imageRed;
var imageBlue;
var imageGreen;
//Amount of images that has finished loading
var imagesLoaded = 0;
var TOTAL_IMAGES = 3; //const
//How often the widget should refresh its data
var REFRESH_TIME = 15000; //const

//Wait till document is loaded
document.addEventListener('DOMContentLoaded', function() {
    preInit();
}, false);

/*
 * Load all resources required
 * init() will be called when all resources
 * has been loaded
 */
function preInit(){
    imageRed = new Image();
    imageRed.onload = function() {
        imageLoaded();
    };
    imageRed.src = "/fsp/wvw/images/redGlobe.png";
    imageBlue = new Image();
    imageBlue.onload = function() {
        imageLoaded();
    };
    imageBlue.src = "/fsp/wvw/images/blueGlobe.png";
    imageGreen = new Image();
    imageGreen.onload = function() {
        imageLoaded();
    };
    imageGreen.src = "/fsp/wvw/images/greenGlobe.png";
}

/*
 * Images are loaded asyncronized, which means it might start drawing before
 * it has even loaded all the necessary images required.
 * In order to fix this, the imagesLoaded will be increased by one each time
 * an image is loaded until everything has been loaded
 */
function imageLoaded(){
    imagesLoaded++;
    if(imagesLoaded >= TOTAL_IMAGES){
        init();
    }
}

function init(){
    initGW2API();
    initPPTChart();
    initCookies();
    
    start();
}

/*
 * Initialize an instance of the GW2 API library
 */
function initGW2API(){
    GW2Lib = new GW2API();
}

/*
 * Initialize a ChartJS instance of a pie chart that will represent
 * each servers current PPT (like in game)
 */
function initPPTChart(){
    var contextCanvas = document.getElementById("wvw-ppt-canvas").getContext("2d");
    //Load images
    var redPattern = contextCanvas.createPattern(imageRed, 'no-repeat');
    var bluePattern = contextCanvas.createPattern(imageBlue, 'no-repeat');
    var greenPattern = contextCanvas.createPattern(imageGreen, 'no-repeat');
    //Initialize data
    var wvwData = [
        {
            value: 100,
            color: redPattern,
            //highlight: "#D79390",
            label: "Red"
        },
        {
            value: 100,
            color: bluePattern,
            //highlight: "#8CA6D0",
            label: "Blue"
        },
        {
            value: 100,
            color: greenPattern,
            //highlight: "rgba(103, 178, 22, 0.1)",
            label: "Green"
        }
    ];
    
    //Chart config
    var chartConfig = {
        responsive : true, 
        percentageInnerCutout : 0, 
        segmentStrokeColor : "#000",
        animationEasing : "",
        animationSteps : window.mobilecheck() ? 1 : 40,
        legendTemplate : "<ul class=\"<%=name.toLowerCase()%>-legend\"><% for (var i=0; i<segments.length; i++){%><li><span style=\"background-color:<%=segments[i].fillColor%>\"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>"
    };
    //Create instance of chart
    window.wvwPPTCanvas = new Chart(contextCanvas).Doughnut(wvwData, chartConfig);
}

//To load faster, the script will save a cookie with the last
//known match data. The script can then load this data if the page is reloaded
//to avoid calling the GW2 API during load
function initCookies(){
    score = getCookie("wvw-score").split(",");
    ppt = getCookie("wvw-ppt").split(",");
    match = getCookie("wvw-match");
    
    if(this.score.length === 3 && this.ppt.length === 3) {
        try{
            this.ppt[0] = parseInt(this.ppt[0]);
            this.ppt[1] = parseInt(this.ppt[1]);
            this.ppt[2] = parseInt(this.ppt[2]);
            this.match = JSON.parse(match);
            draw();
        } catch(e){
            console.log("Could not parse previous saved data");
            console.log(e);
        }
    }
}

/*
 * Everything has been loaded and is ready to begin
 */
function start(){
    //Determine if data was stored from a previous session
    if(this.score.length === 3 && this.ppt.length === 3) {
        draw();
        
        setTimeout(function(){ 
            updateMatchup(); //will call draw() at the end
            //loop
            updateLoop();
        }, 1000);
    } else {
        updateMatchup(); //will call draw() at the end
        //loop
        updateLoop();
    }
}

/*
 * Keeps the widget up to date
 */
function updateLoop(){
    setTimeout(function(){ 
        updateMatchup(); //will call draw() at the end
        //loop
        updateLoop();
    }, REFRESH_TIME);
}

/*
 * Score is fetched asyncronized, this method will be called when the data has
 * been fetched
 */
function updateMatchupCallback(){
    draw();
};

/*
 * Update matchup from the GW2 API
 */
function updateMatchup() {
    this.GW2Lib.getWvwMatches(gotWvWMatches); //Callback gotWvWMatches()
}

/*
 * Calback method called when a matchup has been retrived from the GW2 API
 * @param {JSON Array} matches - A list of all current GW2 WvW matches
 */
function gotWvWMatches(matches){
    if(matches instanceof Array){
        //Loop through each match
        for(var i = 0; i < matches.length; i++){
            var tempMatch = matches[i];
            //Check if this is the match we are looking for
            if(
                    tempMatch["red_world_id"] == world || 
                    tempMatch["blue_world_id"] == world ||
                    tempMatch["green_world_id"] == world 
            ){
                //Store match in global variable
                this.match = tempMatch;
                break;
            }
        }
    }
    //Determine if a match was found
    if(!(typeof this.match === "undefined")){
        //Retrive match details from the GW2 API
        this.GW2Lib.getMatchDetails(this.match["wvw_match_id"], gotMatchDetails); //Callback gotMatchDetails()
    }
}


/*
 * Calback method called when matchup details has been retrived from the GW2 API
 * @param {type} matchDetails - A list containing all relevant information for the
 * current matchup
 */
function gotMatchDetails(matchDetails){
    //Store retrived matchup details in global variable
    this.matchDetails = matchDetails;
    //Get score from matchup details
    score = matchDetails["scores"];
    //Store data in case the page is reloaded
    setCookie("wvw-score",score,  1 / 24 / 20);
    setCookie("wvw-match",JSON.stringify(match), 1 / 24 / 20);
    //Calculate PPT
    calculatePPT();
    //
    updateMatchupCallback();
}

/*
 * Calculate PPT based on objectives held by each server
 */
function calculatePPT(){
    //Borderlands & EternalBattlegrounds details
    var maps = matchDetails["maps"];
    var tempPPT = [0,0,0];
    //Determine PPT from each map
    for(var i = 0; i < maps.length; i++){
        var map = maps[i];
        var objectives = map["objectives"];
        //Determine PPT for each objective
        for(var k = 0; k < objectives.length; k++){
            var objective = objectives[k];
            //Check if objective is owned by any server
            if(!(typeof colorToId[objective["owner"]] === "undefined") && !(typeof objectivePPT[objective["id"]] === "undefined")){
                //Attribute objective ppt to its owner
                tempPPT[colorToId[objective["owner"]]] += objectivePPT[objective["id"]];
            } 
        }
    }
    //Store calculated ppt in global variable
    ppt = tempPPT;
    //Store data in case the page is reloaded
    setCookie("wvw-ppt",tempPPT,  1 / 24 / 20);
}

/*
 * Draw widget & update relevant fields
 */
function draw(){
    drawPPTCircle();
    drawPPTNumbers();
    drawScoreBar();
}


function drawPPTCircle(){
    window.wvwPPTCanvas.segments[0].label = world_names[match["red_world_id"]];
    window.wvwPPTCanvas.segments[1].label = world_names[match["blue_world_id"]];
    window.wvwPPTCanvas.segments[2].label = world_names[match["green_world_id"]];
    window.wvwPPTCanvas.segments[0].value = ppt[0];
    window.wvwPPTCanvas.segments[1].value = ppt[1];
    window.wvwPPTCanvas.segments[2].value = ppt[2];
    window.wvwPPTCanvas.update();
}

function drawPPTNumbers(){
    document.getElementById("wvw-ppt-number-red").innerHTML = ppt[0];
    document.getElementById("wvw-ppt-number-blue").innerHTML = ppt[1];
    document.getElementById("wvw-ppt-number-green").innerHTML = ppt[2];
    
    //Distingish easily between home server and other server by making home server bold
    
    /*document.getElementById("wvw-ppt-number-red").style.fontWeight = "normal";
    document.getElementById("wvw-ppt-number-blue").style.fontWeight = "normal";
    document.getElementById("wvw-ppt-number-green").style.fontWeight = "normal";
    document.getElementById("wvw-ppt-number-red").style.textDecoration  = "none";
    document.getElementById("wvw-ppt-number-blue").style.textDecoration  = "none";
    document.getElementById("wvw-ppt-number-green").style.textDecoration  = "none";
    if(match["red_world_id"] == 2007){
        document.getElementById("wvw-ppt-number-red").style.fontWeight = "bold";
//        document.getElementById("wvw-ppt-number-red").style.textDecoration  = "underline";
    } else if(match["blue_world_id"] == 2007){
        document.getElementById("wvw-ppt-number-blue").style.fontWeight = "bold";
//        document.getElementById("wvw-ppt-number-blue").style.textDecoration  = "underline";
    } else if(match["green_world_id"] == 2007){
        document.getElementById("wvw-ppt-number-green").style.fontWeight = "bold";
//        document.getElementById("wvw-ppt-number-green").style.textDecoration  = "underline";
    }*/
}

function drawScoreCircle() {
    window.wvwScoreCanvas.segments[0].label = world_names[match["red_world_id"]];
    window.wvwScoreCanvas.segments[1].label = world_names[match["blue_world_id"]];
    window.wvwScoreCanvas.segments[2].label = world_names[match["green_world_id"]];
    window.wvwScoreCanvas.segments[0].value = score[0];
    window.wvwScoreCanvas.segments[1].value = score[1];
    window.wvwScoreCanvas.segments[2].value = score[2];
    window.wvwScoreCanvas.update();
}
function drawScoreBar() {
    var maxScoreWidth = 120;
    //Used to determine the score that will fill the entire bar and how full the
    //others will be
    var maxScore = Math.max(score[0], score[1], score[2]);
    document.getElementById("wvw-score-red").style.width = maxScoreWidth * (score[0] / maxScore) + "px";
    document.getElementById("wvw-score-blue").style.width = maxScoreWidth * (score[1] / maxScore) + "px";
    document.getElementById("wvw-score-green").style.width = maxScoreWidth * (score[2] / maxScore) + "px";
    document.getElementById("wvw-score-bar-text-red").innerHTML = world_names[match["red_world_id"]];
    document.getElementById("wvw-score-bar-text-blue").innerHTML = world_names[match["blue_world_id"]];
    document.getElementById("wvw-score-bar-text-green").innerHTML = world_names[match["green_world_id"]];
    
    //Distingish easily between home server and other server by making home server bold
    
    /*
    max = Math.max(score[0],score[1],score[2])
    document.getElementById("wvw-score-label-red").innerHTML = score[0] - max;
    document.getElementById("wvw-score-label-blue").innerHTML = score[1] - max;
    document.getElementById("wvw-score-label-green").innerHTML = score[2] - max;
     */
    document.getElementById("wvw-score-label-red").innerHTML = score[0];
    document.getElementById("wvw-score-label-blue").innerHTML = score[1];
    document.getElementById("wvw-score-label-green").innerHTML = score[2];
    
    //Distingish easily between home server and other server by making home server bold
    
    /*document.getElementById("wvw-score-label-red").style.fontWeight = "normal";
    document.getElementById("wvw-score-label-blue").style.fontWeight = "normal";
    document.getElementById("wvw-score-label-green").style.fontWeight = "normal";
    if(match["red_world_id"] == 2007){
        document.getElementById("wvw-score-label-red").style.fontWeight = "bold";
//        document.getElementById("wvw-score-label-red").style.textDecoration  = "underline";
    } else if(match["blue_world_id"] == 2007){
        document.getElementById("wvw-score-label-blue").style.fontWeight = "bold";
//        document.getElementById("wvw-score-label-blue").style.textDecoration  = "underline";
    } else if(match["green_world_id"] == 2007){
        document.getElementById("wvw-score-label-green").style.fontWeight = "bold";
//        document.getElementById("wvw-score-label-green").style.textDecoration  = "underline";
    }*/
}

/*
 * Determine if client is using a mobile browser
 * @returns {Window.mobilecheck.check|Boolean}
 */
window.mobilecheck = function() {
  var check = false;
  (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))check = true})(navigator.userAgent||navigator.vendor||window.opera);
  return check;
}


/*
 * Store a cookie in the clients browser
 * @param {type} name of cookie
 * @param {type} value of cookie
 * @param {type} expires in days
 * @returns {undefined}
 */
function setCookie(name, value, expires) {
    var d = new Date();
    d.setTime(d.getTime() + (expires*24*60*60*1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = name + "=" + value + "; " + expires;
}

/*
 * Retrive a stored cookie
 * @param {type} name
 * @returns {String}
 */
function getCookie(name) {
    name += "=";
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' '){
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0){ 
            return c.substring(name.length,c.length);
        }
    }
    return "";
}


/*
 * ***************************
 * LOTS OF PRE SAVED VARIABLES 
 * *************************** 
 */


//Easy conversion of objective id to the amount of ppt it contributes to
var objectivePPT = {
    30: 10,
    57: 10,
    9: 35,
    20: 10,
    76: 0,
    25: 10,
    66: 0,
    32: 25,
    47: 10,
    37: 25,
    60: 5,
    27: 25,
    5: 5,
    50: 5,
    75: 0,
    16: 10,
    42: 10,
    40: 10,
    35: 10,
    70: 0,
    17: 10,
    64: 0,
    48: 5,
    69: 0,
    61: 5,
    52: 5,
    74: 0,
    22: 10,
    31: 25,
    62: 0,
    51: 5,
    55: 5,
    21: 10,
    29: 5,
    2: 25,
    18: 10,
    38: 10,
    15: 10,
    63: 0,
    26: 10,
    44: 25,
    33: 25,
    4: 5,
    10: 5,
    39: 5,
    6: 5,
    34: 5,
    11: 10,
    58: 5,
    45: 10,
    54: 5,
    12: 10,
    71: 0,
    49: 5,
    8: 5,
    1: 25,
    41: 25,
    53: 5,
    56: 5,
    68: 0,
    36: 10,
    53 : 5,
    23: 25,
    73: 0,
    14: 10,
    67: 0,
    24: 5,
    72: 0,
    3: 25,
    19: 10,
    28: 10,
    65: 0,
    46: 25,
    59: 5,
    7: 5,
    13: 10,
    43: 5
};

//Easy conversion from string color name to an id
var colorToId = {
    Red: 0,
    RedHome: 0,
    Blue: 1,
    BlueHome: 1,
    Green: 2,
    GreenHome: 2
};

//Pre-saved list of server names to avoid contacting the GW2 api each time to fetch
//the server names
var world_names = {
    1001: "Anvil Rock",
    1002: "Borlis Pass",
    1003: "Yak's Bend",
    1004: "Henge of Denravi",
    1005: "Maguuma",
    1006: "Sorrow's Furnace",
    1007: "Gate of Madness",
    1008: "Jade Quarry",
    1009: "Fort Aspenwood",
    1010: "Ehmry Bay",
    1011: "Stormbluff Isle",
    1012: "Darkhaven",
    1013: "Sanctum of Rall",
    1014: "Crystal Desert",
    1015: "Isle of Janthir",
    1016: "Sea of Sorrows",
    1017: "Tarnished Coast",
    1018: "Northern Shiverpeaks",
    1019: "Blackgate",
    1020: "Ferguson's Crossing",
    1021: "Dragonbrand",
    1022: "Kaineng",
    1023: "Devona's Rest",
    1024: "Eredon Terrace",
    2001: "Fissure of Woe",
    2002: "Desolation",
    2003: "Gandara",
    2004: "Blacktide",
    2005: "Ring of Fire",
    2006: "Underworld",
    2007: "Far Shiverpeaks",
    2008: "Whiteside Ridge",
    2009: "Ruins of Surmia",
    2010: "Seafarer's Rest",
    2011: "Vabbi",
    2012: "Piken Square",
    2013: "Aurora Glade",
    2014: "Gunnar's Hold",
    2101: "Jade Sea [FR]",
    2102: "Fort Ranik [FR]",
    2103: "Augury Rock [FR]",
    2104: "Vizunah Square [FR]",
    2105: "Arborstone [FR]",
    2201: "Kodash [DE]",
    2202: "Riverside [DE]",
    2203: "Elona Reach [DE]",
    2204: "Abaddon's Mouth [DE]",
    2205: "Drakkar Lake [DE]",
    2206: "Miller's Sound [DE]",
    2207: "Dzagonur [DE]",
    2301: "Baruch Bay [SP]"
};