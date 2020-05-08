<?php

$endpoint = '/admin/tool/moodlenet/import.php';
$moodlesite = "http://localhost/master";
$course = 2;
$section = 0;
$resource = 'https://team.moodle.net/uploads/01E394TX0TXVCQ1DN0D48JKBRT/cat.png';
$resource2 = 'https://team.moodle.net/uploads/01E1GPC7V8EV829WBPTW64ZN4B/backup-moodle2-course-2-course_1-20190909-1514.mbz';
$resource3 = 'https://www.youtube.com/watch?v=nF3w-xnkRwE';

echo '
<html>
<head>
<style>
    body {
        font-size:11pt;
        color:#333333;
    }
    
    form {
        margin-bottom: 0px;
    }
    
    .wrapper {
        margin:auto;
        width:60%;
        border: solid 1px #bbbbbb;
        padding:10px;
    }
    
    .info {
        display: flex;
    }
    
    .stats {
        margin-left:10px;
    }
    
    .icon {
        width:50px;
        height:50px;
    }
    
    .valid {
        color:green;
    }
    
    .invalid {
        color:red;
    }
</style>
</head>
<body>
<br>
<div class="wrapper">
    <h3>A list of MoodleNet resources</h3>
</div>
<br>
';

// URL case - no course or section.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="https://icons.iconarchive.com/icons/paomedia/small-n-flat/256/social-youtube-icon.png" alt="A video" title="'.$resource3.'"/>
        <div class="stats">
        Use case: User starts in Moodle (course and section provided)<br>
        Resource type: URL<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint.'?course='.$course.'&section='.$section).'<br>
        POST data: <span class="valid">Valid</span>
        </div>
    </div>
    <br>
    <form name="testForm" id="testForm" action="'.$moodlesite.$endpoint.'?course='.$course.'&section='.$section.'" method="post">
        <input type="hidden" name="resourceurl" value="'.$resource3.'"/>
        <input type="hidden" name="resourcetype" value="url"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

// PNG case - no course or section.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="'.$resource.'" alt="A cat" title="'.$resource.'"/>
        <div class="stats">
        Use case: User starts in MoodleNet (no course or section)<br>
        File type: png<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint).'<br>
        POST data: <span class="valid">Valid</span>
        </div>
    </div>
    <br>
    <form name="testForm" id="testForm" action="'.$moodlesite.$endpoint.'" method="post">
        <input type="hidden" name="resourceurl" value="'.$resource.'"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

// PNG case - course and section provided.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="'.$resource.'" alt="A cat" title="'.$resource.'"/>
        <div class="stats">
        Use case: User starts in Moodle (course and section provided)<br>
        File type: png<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint.'?course='.$course.'&section='.$section).'<br>
        POST data: <span class="valid">Valid</span>
        </div>
    </div>
    <br>
    <form name="testForm" id="testForm" action="'.$moodlesite.$endpoint.'?course='.$course.'&section='.$section.'" method="post">
        <input type="hidden" name="resourceurl" value="'.$resource.'"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

// MBZ case - no course or section.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="https://moodlenet.prototype.moodledemo.net/theme/image.php/boost/core/1586999600/f/moodle-80" alt="Moodle backup file" title="'.$resource2.'"/>
        <div class="stats">
        Use case: User starts in Moodlenet (no course or section)<br>
        File type: mbz<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint).'<br>
        POST data: <span class="valid">Valid</span>
        </div>
    </div>
    <br>
    <form name="testForm" id="testForm" action="'.$moodlesite.$endpoint.'" method="post">
        <input type="hidden" name="resourceurl" value="'.$resource2.'"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

// MBZ case - course and section provided.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="https://moodlenet.prototype.moodledemo.net/theme/image.php/boost/core/1586999600/f/moodle-80" alt="Moodle backup file" title="'.$resource2.'"/>
        <div class="stats">
        Use case: User starts in Moodle (course and section provided)<br>
        File type: mbz<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint.'?course='.$course.'&section='.$section).'<br>
        POST data: <span class="valid">Valid</span>
        </div>
    </div>
    <br>
    <form name="testForm" id="testForm" action="'.$moodlesite.$endpoint.'?course='.$course.'&section='.$section.'" method="post">
        <input type="hidden" name="resourceurl" value="'.$resource2.'"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

// Invalid case - POST data missing resourceurl param.
echo '
<div class="wrapper">
    <div class="info">
        <img class="icon" src="https://moodlenet.prototype.moodledemo.net/theme/image.php/boost/core/1586999600/f/moodle-80" alt="Moodle backup file" title="'.$resource2.'"/>
        <div class="stats">
        Use case: invalid data<br>
        File type: mbz<br>
        Action: '.htmlspecialchars($moodlesite.$endpoint).'<br>
        POST data: <span class="invalid">Invalid</span>
        </div>
    </div>
    <br>
    <form action="'.$moodlesite.$endpoint.'" method="post">
        <input type="hidden" name="broken" value="'.$resource2.'"/>
        <input type="submit" value="Send to Moodle">
    </form>
</div>
<br>';

echo '
</div>
</body>
</html>';

