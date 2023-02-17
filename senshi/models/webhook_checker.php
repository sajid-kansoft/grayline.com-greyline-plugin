<?php
/**
 * webhook_checker.php
 * User: Iona iona@palisis.com
 * Date: 2022-09-12
 * Time: 10:25
 */

 namespace GrayLineTourCMSSenshiModals;

 defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

/**
 * Class WebhookChecker
 */
class WebhookChecker extends Checker
{

    /**
     * @var string
     */
    protected $msg_fail = 'Last successful Adyen Webhook transaction older than %Y% minutes (%X% hours)';

    /**
     * @var string
     */
    protected $msg_success = 'Successful Adyen Webhook transaction within the last %Y% minutes (%X% hours)';

    /**
     * @var int
     */
    protected $default_time_minutes = 360; // 6 hours

    /**
     * @var CartDb
     */
    protected $cartDb;


    /**
     * WebhookChecker constructor.
     * @param CartDb $cartDb
     */
    public function __construct(\CartDb $cartDb)
    {
        parent::__construct();
        $this->cartDb = $cartDb;
    }


    /**
     * @return string
     * @throws SenshiException
     */
    protected function dateSuccessfullyRun() : string
    {
        $row = $this->dbGetRow();
        if (!array_key_exists('eventDate', $row) ) {
            throw new \SenshiException(self::MSG_EMPTY_FIELDS);

        }
        $date = $row['eventDate'];
        return $date;

    }


    /**
     * @return array
     * @throws SenshiException
     */
    protected function dbGetRow() : array
    {
        // $conn = $this->cartDb->getConn();
        global $wpdb;
        
        // Query the DB to get our items
        $sql =  $wpdb->prepare("SELECT * FROM wp_adyen_webhooks WHERE success = %s ORDER BY itemId DESC LIMIT 1",
        array('true')
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($rows) && isset($rows[0])) {
            $row = $rows[0];
            return $row;
        } else {
            throw new \SenshiException(self::MSG_NO_DB_ARRAY);
        }
    }

}