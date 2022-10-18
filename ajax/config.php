<?php

unset( $COOKIES_CFG );
global $COOKIES_CFG;

$COOKIES_CFG = array();

/* BASIC CONFIG */

$COOKIES_CFG['HASH'] = 'ran0mh4$h';

$COOKIES_CFG['DB_HOST'] = 'localhost';
$COOKIES_CFG['DB_PORT'] = '3306';
$COOKIES_CFG['DB_USER'] = 'root';
$COOKIES_CFG['DB_PASS'] = '';
$COOKIES_CFG['DB_NAME'] = 'cookies_v2';
$COOKIES_CFG['DB_PREFIX'] = 'cks_';

$COOKIES_CFG['cookie_variable'] = 'visitor_id';

/* */

require_once( __DIR__ .'/db.php' );

global $cookies;
require_once( __DIR__ .'/cookies.php' );
$cookies = new cookies();

$is_ajax = ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest') ? true : false;
if( $is_ajax AND isset( $_POST['cookiepanel'] ) ) :

    $type = ( isset( $_POST['type'] ) ) ? $_POST['type'] : null;

    if( !$type ) exit;

    $result['success'] = false;
    $result['message'] = '';
    $result['confirmed'] = array();

    switch( $type ){
        case 'render-panel':
            $settings = ( isset( $_POST['settings'] ) ) ? $_POST['settings'] : array();
            $result['html'] = $cookies->render_panel( $settings );

            if( $result['html'] ) $result['success'] = true;
            break;
        case 'allow-all':
            $result = $cookies->allow_all();
            break;
        case 'allow-selected':
            $values = ( isset( $_POST['values'] ) ) ? $_POST['values'] : array();
            $result = $cookies->allow_selected( array( 'values' => $values ) );
            break;
        case 'deny-all':
            $result = $cookies->deny_all();
            break;
        case 'check-confirm':
            $category = ( isset( $_POST['category'] ) ) ? $_POST['category'] : null;
            if( $category ){
                $result['success'] = true;
                $result['confirm'] = $cookies->confirmed( $category );
            } else {
                $result['message'] = 'Missing category short name';
            }
            break;
    }

    $result['confirmed'] = $cookies->get_confirmed();
    echo json_encode( $result );
    exit;
endif;