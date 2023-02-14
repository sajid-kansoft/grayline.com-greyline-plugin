<?php
defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-type:text/plain;charset=utf-8');

loadSenshiModal('dic');

// MAKE SURE PLAIN TEXT IS RETURNED, PASSWORD PROTECTED
try {
    // this works to include library config file ok
    include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
    $tourcms_account_id = $account_id;
    $dic = new \GrayLineTourCMSSenshiModals\Dic();
    $adyenWebhookAccept = $dic::objectAdyenWebhookAccept();
    $adyenWebhookAccept->accept($_POST, $account_id);
} catch (\Exception $e) {
    error_log($e->getMessage());
    print('[not accepted]');
}