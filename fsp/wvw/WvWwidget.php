<?php

/**
 * Description of WvWwidget
 *
 * @author jeppe
 */

echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>';
echo '<link rel="stylesheet" type="text/css" href="fsp/wvw/WvWwidget.css">';
echo "<script src='/fsp/wvw/lib/Chart.min.js'></script>";
echo "<script src='/fsp/wvw/lib/gw2-api-wrapper.js'></script>";
echo "<script src='/fsp/wvw/WvWwidget.js'></script>";

echo '
<div id="wvw-widget" style=" position: relative;">
    <div id="wvw-canvas-holder">
        <canvas id="wvw-ppt-canvas" width="300" height="100"></canvas>
    </div>
    <div id="wvw-ppt-container">
        <div class="wvw-label">Tick</div>
        <div id="wvw-ppt-number-green" class="wvw-ppt-number green"></div>
        <div id="wvw-ppt-number-blue" class="wvw-ppt-number blue"></div>
        <div id="wvw-ppt-number-red" class="wvw-ppt-number red"></div>
    </div>
    <div id="wvw-score">
        <div class="wvw-label">Score</div>
        <div class="wvw-score-bar-outer">
            <div id="wvw-score-bar-text-green" class="wvw-score-bar-server-name"></div>
            <div class="wvw-score-bar-base">
                <div id="wvw-score-green" class="wvw-score-bar green-bg"></div>
            </div>
            <div id="wvw-score-label-green" class="wvw-label green"></div>
        </div>
        <div class="wvw-score-bar-outer">
            <div id="wvw-score-bar-text-blue" class="wvw-score-bar-server-name"></div>
            <div class="wvw-score-bar-base">
                <div id="wvw-score-blue" class="wvw-score-bar blue-bg"></div>
            </div>
            <div id="wvw-score-label-blue" class="wvw-label blue"></div>
        </div>
        <div class="wvw-score-bar-outer">
            <div id="wvw-score-bar-text-red" class="wvw-score-bar-server-name"></div>
            <div class="wvw-score-bar-base">
                <div id="wvw-score-red" class="wvw-score-bar red-bg"></div>
            </div>
            <div id="wvw-score-label-red" class="wvw-label red"></div>
        </div>
    </div>
</div>
';

