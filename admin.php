<?php session_start();

require_once( __DIR__ .'/ajax/config.php' );
require_once( __DIR__ .'/ajax/db.php' );

global $cookies;
require_once( __DIR__ .'/ajax/cookies.php' );
$cookies = new cookies();

if( isset( $_POST ) AND count( $_POST ) ){
    $cookies->admin_actions( $_POST );
    exit;
}

echo $cookies->render_adminpage();