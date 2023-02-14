<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

loadSenshiModal('checker');

/**
 * Class CronChecker
 */
class CronChecker extends Checker
{

    /**
     *
     */
    const FEX_JOB_ID = 7;
    /**
     *
     */
    const SUCCESS_TEXT_FEX = 'The Job was run successfully.';
    /**
     * @var string
     */
    protected $msg_fail = 'FEX Job NOT successfully run within the last %Y% minutes (%X% hours)';
    /**
     * @var string
     */
    protected $msg_success = 'Successful FEX job ran within the last %Y% minutes (%X% hours)';
    /**
     * @var int
     */
    protected $default_time_minutes = 2880; // 24 hours


    /**
     * CronChecker constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string
     * @throws SenshiException
     */
    protected function dateSuccessfullyRun() : string
    {
        $row = $this->dbGetRow();
        if (!array_key_exists('jDateLastRun', $row) ) {
            throw new \SenshiException(self::MSG_EMPTY_FIELDS);

        }
        if (!array_key_exists('jLastStatusText', $row)) {
            throw new \SenshiException(self::MSG_EMPTY_FIELDS);
        }
        if (!array_key_exists('jDescription', $row)) {
            throw new \SenshiException(self::MSG_EMPTY_FIELDS);
        }
        if ($row['jLastStatusText'] !== self::SUCCESS_TEXT_FEX) {
            $msg = "Job failure: " . $row['jLastStatusText'];
            throw new \SenshiException($msg, 'CRITICAL');
        }
        $this->msg_success .= ' - ' . $row['jDescription'];
        $this->msg_fail .= ' - ' . $row['jDescription'];
        $date = $row['jDateLastRun'];
        return $date;

    }


    /**
     * @return array
     * @throws SenshiException
     */
    protected function dbGetRow() : array
    {
        $db = Loader::db();
        $query = "SELECT * FROM Jobs WHERE jID=? LIMIT 1";
        $row = $db->getRow($query, array(self::FEX_JOB_ID));
        if (is_array($row)) {
            return $row;
        } else {
            throw new \SenshiException(self::MSG_NO_DB_ARRAY);
        }
    }


}