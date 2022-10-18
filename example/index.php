<?php

include_once('../ajax/config.php');

echo <<<EOF
<!DOCTYPE html>
<html>
<head>
<title>Cookie Banner - Example</title>

<link rel="stylesheet" href="../cookies.css" />
</head>
<body>
<a href="#" class="cookies-settings">Cookies Settings</a>

<script type="text/javascript" src="../jquery-3.3.1.min.js"></script>
<script type="text/javascript" src="../cookies.js"></script>
<script type="text/javascript">
var cookiesOptions = {
    parentDir: "../",
    settingsButton: "a.cookies-settings"
}
$("body").cookies( cookiesOptions ).showPanel();
</script>
</body>
</html>
EOF;