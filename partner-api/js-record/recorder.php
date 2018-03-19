<?php
$user_id = $_GET['userid'];
if (!$user_id) {
    header('Location: ' . $_SERVER["PHP_SELF"] . '?userid=' . rand(1111, 9999));
    die();
}

// Obtain Feel Subs token
$FEEL_SUBS_SERVER_PATH = 'https://api.pibds.com';
$FEEL_SUBS_APP_KEY = '<FeelSubs key>'; // Replace with your own FeelSubs key

$FEEL_APPS_SERVER_PATH = 'https://api.feel-app.com';
$PARTNER_KEY = '<FeelApps key>'; // Replace with your own Feel Apps partner key
$VIDEO_ID = 'recorded-video'; // Set video id here

// Obtain Feel Apps partner token
//
$string = file_get_contents("{$FEEL_APPS_SERVER_PATH}/api/v1/partner/{$PARTNER_KEY}/token?user=" . urlencode($user_id));
$json = json_decode($string, true);
$feel_apps_token = $json['partner_token'];

// Obtain QR code
//
$string = file_get_contents("{$FEEL_APPS_SERVER_PATH}/api/v1/user/{$user_id}/auth?partner_token={$feel_apps_token}");
$json = json_decode($string, true);
$qrcode = $json['auth_token'];

// Getting user status (online/offline, etc)
$userstatus = file_get_contents("{$FEEL_APPS_SERVER_PATH}/api/v1/user/{$user_id}/status?partner_token={$feel_apps_token}");


// Request a video-specific read/write token
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "api-key: " . $FEEL_SUBS_APP_KEY . "\r\n"
    ]
];
$context = stream_context_create($opts);
$json = file_get_contents($FEEL_SUBS_SERVER_PATH . '/api/v2/app/token?write=1&video=' . $VIDEO_ID, false, $context);

$result = json_decode($json);
$feel_subs_token = $result->apptoken;


?>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css"
          integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css"
          integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">

    <style type="text/css">
        .progress .progress-bar {
            -webkit-transition: none;
            -moz-transition: none;
            -ms-transition: none;
            -o-transition: none;
            transition: none;
        }
    </style>
</head>
<body>
<!-- Fixed navbar -->
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">

            <a class="navbar-brand">
                <img src="feel-logo.png" style="display: inline-block; height: 30px; margin-top: -5px"/>
                Feel apps recorder example
            </a>
        </div>
    </div>
</nav>

<div class="container" role="main" style="padding-top: 60px;">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">User</h3>
                </div>
                <div class="panel-body">
                    <h4>User ID</h4>
                    <pre><?php echo $user_id; ?></pre>
                    <h4>User status</h4>
                    <pre><?php echo $userstatus; ?></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">QR code to scan</h3>
                </div>
                <div class="panel-body">
                    <center>
                        <p><img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo $qrcode; ?>"></p>
                        <pre><?php echo $qrcode; ?></pre>
                    </center>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Recording</h3>
                </div>
                <div class="panel-body">
                    <ul class="list-group">
                        <li class="list-group-item text-center">
                            <button class="btn btn-danger btn-lg" id="start"><span class="glyphicon glyphicon-record"></span> Record</button>
                            <button type="button" class="btn btn-danger btn-lg" id="stop"><span class="glyphicon glyphicon-stop"></span>
                                Stop <span id="counter">0:00</span></button>
                            <button type="button" class="btn btn-success btn-lg" id='save'><span class="glyphicon glyphicon-save"></span> Save</button>
                        </li>
                        <li class="list-group-item">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" id="device-value" style="width: 0%;"></div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script type="text/javascript" src="https://netdna.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
<script src="<?php echo $FEEL_APPS_SERVER_PATH . "/static/recordjs/1.0/record.js"; ?>"></script>
<script>
    // Initialize the JS SDK. Set feel subs application token and feel apps partner key
    var FEEL_SUBS_TOKEN = '<?php echo $feel_subs_token; ?>'
    var FEEL_APPS_TOKEN = '<?php echo $feel_apps_token; ?>'

    feelrecord.init(FEEL_APPS_TOKEN)
    $('#stop').hide()

    let interval = null
    $('#start').click(function () {
        $('#stop').show()
        $('#start').hide()
        var timeSeconds = 0
        feelrecord.record(timeSeconds) // Overwrite everything after timeSeconds
        const startRecordingTime = (new Date()).getTime()/1000
        interval = setInterval(() => {
            const time = Math.floor((new Date()).getTime()/1000 - startRecordingTime)
            const secs = (time%60 < 10 ? '0' : '') + time%60
            const mins = Math.floor(time/60)
            $('#counter').text(mins + ':' + secs)
        }, 200)
    })

    $('#stop').click(function () {
        $('#stop').hide()
        $('#start').show()
        feelrecord.stop()
        clearInterval(interval)
    })

    $('#save').click(function () {
        feelrecord.save(FEEL_SUBS_TOKEN)
    })

    feelrecord.onData(function (percent) {
        console.log('Incoming value', percent)
        $('#device-value').width(percent + '%')
    })

</script>
</body>
</html>