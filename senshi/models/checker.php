<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

abstract class Checker
{

    /**
     *
     */
    const MSG_NO_DB_ARRAY = 'DB not returning an array';

    /**
     *
     */
    const MSG_EMPTY_FIELDS = 'DB not returning necessary fields';

    /**
     * @var string
     */
    protected $msg_fail = 'Fail within the last %Y% minutes (%X% hours)';

    /**
     * @var string
     */
    protected $msg_success = 'Success within the last %Y% minutes (%X% hours)';

    /**
     * @var int
     */
    protected $default_time_minutes = 360; // 6 hours


    /**
     * Checker constructor.
     */
    public function __construct()
    {

    }

    /**
     * @param null $time
     * @return string
     * @throws PublicException
     */
    public function test($time = null): string
    {
        try {
            $date = $this->dateSuccessfullyRun();
            // time is not an integer throw exception
            if (! is_null($time) && ! ctype_digit($time)) {
                throw new \PublicException('Time must be an integer');
            }
            $minutes = $time ? intval($time) : $this->default_time_minutes;
            $time_string = "-$minutes minutes";
            if (strtotime($date) < strtotime($time_string)) {
                throw new \SenshiException($this->dynamicMsg($this->msg_fail, $minutes), 'CRITICAL');
            } else {
                $response = $this->dynamicMsg($this->msg_success, $minutes);
            }

            return $response;
        } catch (\Exception $e) {
            throw new \PublicException($e->getMessage());
        }
    }

    /**
     * @param $msg
     * @param $minutes
     * @return string
     */
    protected function dynamicMsg($msg, $minutes) : string
    {
        $hours = $this->minutesToHours($minutes);
        $msg = str_replace(['%X%','%Y%'], [$hours, $minutes], $msg);
        return $msg;
    }

    /**
     * @param $minutes
     * @return float
     */
    protected function minutesToHours($minutes) : float
    {
        if ($minutes && $minutes > 0) {
            return round(floatval($minutes / 60), 2);
        }
        return 0;
    }

    /**
     * @return string
     */
    abstract protected function dateSuccessfullyRun() : string;


    /**
     * @return array
     */
    abstract protected function dbGetRow() : array;


}