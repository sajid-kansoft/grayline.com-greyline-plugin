<?php
/**
 * cron_alert.php
 */

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type:application/json;charset=utf-8');

loadSenshiModal('dic');
$dic = new \GrayLineTourCMSSenshiModals\Dic();

// MAKE SURE JSON IS RETURNED
try {

    $checker = $dic::objectCronChecker();

    $time = isset($_GET['time']) ? $_GET['time'] : null;

    $response = $checker->test($time);

    $json['error'] = 'TOURCMS_OK';
    $json['response'] = $response;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);

    echo $json;
    exit;
}
catch (\Exception $e) {
    error_log($e->getMessage());
    $publicError = $e->getMessage();
    $json['error'] = 'TOURCMS_NOTOK';
    $json['response'] = $publicError;
    $json = json_encode($json, JSON_UNESCAPED_SLASHES);
    echo $json;
    exit;
}
