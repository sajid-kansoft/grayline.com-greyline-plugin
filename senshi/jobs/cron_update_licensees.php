<?php
defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use TourCMS\Utils\TourCMS as TourCMS;

class CronUpdateLicensees
{

    // Need to use special marketplace credentials for this job
    private $marketplaceId = 39972;
    private $apiKey = '35ece6e29b0b';


    /*public function getJobName()
    {
        return _e("GLWW Commission - Update Licensee List", "gray-line-licensee-wordpress-tourcms-plugin");
    }

    public function getJobDescription()
    {
        return _e("Updates the TourCMS licensee list cache (used for setting variable GLWW Commission on all licensees)" , "gray-line-licensee-wordpress-tourcms-plugin");
    }*/

    public function run($manually_update = null)
    {   
        
        try
        { 
            $total = $this->listLicensees();

            if($manually_update == null) {
                return sprintf( __( 'Licensees cache updated %1$s licensees', 'wp_grayline_tourcms_theme' ), $total ); 
            } else {
                $success = __( 'Licensees successfully updated', 'wp_grayline_tourcms_theme' ); 
                wp_redirect( home_url('/wp-admin/admin.php?page=glww-commission&msg='.$success) ); 
            }
        }
        catch (\TourcmsException $e)
        {
            //\FB::error("Tourcms Exception $e");
            return $e->getMessage() . "\n";
        }
        catch (\Exception $e)
        {
            //\FB::error("Exception $e");
            return $e->getMessage() . "\n";
        }
    }

    private function listLicensees()
    {
        $tourcms = new TourCMS($this->marketplaceId, $this->apiKey, "simplexml"); 
        $result = $tourcms->list_channels();

        if ($result->error == "OK") { 
            // Loop through each channel
            //$db = Loader::db();
            $total = count($result->channel); 
            //\FB::warn("Total number of licensees is $total");
            $channelIds = array();
            $channelInfo = array();
            foreach($result as $channel)
            {
                $channelId = (int)$channel->channel_id;
                if ($channelId > 0)
                {
                    $channelIds[] = $channelId;
                    $accountId = (int)$channel->account_id;
                    $channelName = (string)$channel->channel_name;
                    $costCurrency = (string)$channel->sale_currency;
                    $adyen_account_id = (string)$channel->payment_gateway_marketplace->field3;
                    $this->updateDB($channelId, $accountId, $channelName, $costCurrency, $adyen_account_id);
                    $channelInfo[$channelId] = "Channel ID ($channelId), Account ID ($accountId), Channel Name($channelName), Cost Currency($costCurrency)";
                    ////\FB::log("Channel ID ($channelId), Account ID ($accountId), Channel Name($channelName), Cost Currency($costCurrency");
                }
            }
            // some safety measures when deleting old accounts
            if (!empty($channelIds) && count($channelIds) > 90) {
                $this->cleanDB($channelIds, $channelInfo);
            }
            return $total;
        }
        else {
            //$msg = json_encode($result);
            $logMsg = "Can't load channel list. Result: \n";
            $logMsg .= json_encode($result, JSON_UNESCAPED_SLASHES);
            //\FB::error($logMsg);
            throw new \TourcmsException($logMsg, 'CRITICAL');
        }
    }


    private function updateDB($channelId, $accountId, $channelName, $costCurrency, $adyen_account_id)
    {
        global $wpdb;

        $query =  $wpdb->prepare(
                
            "INSERT INTO wp_senshi_glww_commission SET channel_id = %s, account_id = %s, channel_name = %s, cost_currency = %s, adyen_account_id = %s ON DUPLICATE KEY UPDATE account_id = %s, channel_name = %s, cost_currency = %s, adyen_account_id = %s",
            
            array($channelId, $accountId, $channelName, $costCurrency, $adyen_account_id, $accountId, $channelName, $costCurrency, $adyen_account_id)
        );

        $rows = $wpdb->query($query);

        return $rows;
    }

    // we also need to delete old records
    private function cleanDB($channelIds, $channelInfo)
    {
            $rows = $this->getAll();
            //\FB::log("CLEAN DB");
            //\FB::log($rows);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = $row['channel_id'];

                    try {
                        if (! in_array($id, $channelIds)) {
                            $this->deleteRecord($id);
                            $logMsg = "Licensee Channel Deletion \n\n";
                            $logMsg .= "Licensee channel $id has been deleted / unlinked from GLWW account\n\n";
                            $logMsg .= $channelInfo[$id];
                            throw new InternalException($logMsg, 'CRITICAL');
                        }
                    }
                        // we just want the logging on this don't break loop
                    catch (\InternalException $e) {
                        // just log
                    }
                }
            }
    }

    private function deleteRecord($channelId)
    {
        global $wpdb;

        $query = $wpdb->prepare("DELETE FROM wp_senshi_glww_commission WHERE channel_id = %s",
            array($channelId)
        );

        $rows = $wpdb->query($query);

        //\FB::warn("CLEAN DB DELETE channel id $channelId");
        return $rows;
    }

    private function getAll()
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT channel_id FROM wp_senshi_glww_commission");

        $rows = $wpdb->get_results($query, ARRAY_A);

        return $rows;
    }





}
