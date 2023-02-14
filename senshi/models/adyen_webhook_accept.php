<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class AdyenWebhookAccept
{

    protected $user; // must match what's configured in Adyen account for webhooks
    protected $password; // must match what's configured in Adyen account for webhooks
    protected $logger;
    protected $webhookProcess;

    public function __construct(AdyenWebhookProcess $webhookProcess)
    {
        // this works to include library config file ok
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $this->user = $adyen_webhook_user;
        $this->password = $adyen_webhook_password;
        if ($remote_stage_webhook === true) {
            $this->webhookProcess = $webhookProcess;
            return true;
        }
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="My Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Access denied';
            die();
        } else {
            if ($_SERVER['PHP_AUTH_USER'] !== $this->user || $_SERVER['PHP_AUTH_PW'] !== $this->password) {
                header('WWW-Authenticate: Basic realm="My Realm"');
                header('HTTP/1.0 401 Unauthorized');
                echo 'You must be logged in to use this service';
                die();
            } else {
                $this->webhookProcess = $webhookProcess;
                return true;
            }
        }
    }

    public function accept($postVars, $account_id)
    {
        // intervene and don't progress if there's no PSP reference
        if (!$_POST['pspReference']) {
            print "[not accepted]";
            die();
        }
        $this->webhookProcess->dbStore($postVars, $account_id);
        /**
         * Returning [accepted], please make sure you always
         * return [accepted] to us, this is essential to let us
         * know that you received the notification. If we do NOT receive
         * [accepted] we try to send the notification again which
         * will put all other notification in a queue.
         */
        print "[accepted]";
    }
}
