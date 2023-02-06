<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Licensee 
{

    private $channelId;
    private $accountId;
    private $channelName;
    private $commission;
    private $costCurrency;
    private $adyenAccountId;


    public function __construct($row=null, $channelId=null)
    {
        ////\FB::error("Type of row is " . gettype($row));

        if($channelId && !$row)
        {
            $row = $this->dbGetOne($channelId); 
        }

        if (is_array($row))
        {  
            $this->channelId = $row['channel_id'];
            $this->accountId = $row['account_id'];
            $this->channelName = $row['channel_name'];
            $this->commission = $row['commission'];
            $this->costCurrency = $row['cost_currency'];
            $this->adyenAccountId = $row['adyen_account_id'];
            ////\FB::log("Channel id is " . $this->channelId);
        }

    }

    public function getChannelId() { return $this->channelId; }
    public function getAccountId() { return $this->accountId; }
    public function getChannelName() { return $this->channelName; }
    public function getCommission() { return $this->commission; }
    public function getCostCurrency() { return $this->costCurrency; }
    public function getAdyenAccountId() { return $this->adyenAccountId; }

    public function setCommission($val)
    {
        $this->commission = (float)$val;
        //\FB::log("Licensee commission updated, val is " . $this->commission);
    }

    private function dbGetOne($id)
    {   
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM wp_senshi_glww_commission WHERE channel_id= %s",
            array($id)
        );

        $rows = $wpdb->get_row($query, ARRAY_A); 
        //\FB::log("Query,: $query");
        //\FB::info("Id is {$id}");
        //\FB::log("Rows returned: " . count($rows));
        //\FB::log($rows);
        return $rows;
    }

}
