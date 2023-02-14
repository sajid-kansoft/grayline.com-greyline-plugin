<?php
/**
 *
 * Responsible for deleting any items out the Dojo Tourcms Payment table that are older than 4 weeks
 */

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CronFlushCartPayments {

    public function getJobName() {
        return "Flush Cart Payments";
    }

    public function getJobDescription() {
        return "Deletes all cart items and TEMP bookings out the DB that are older than 8 weeks";
    }

    public function run() {
        global $wpdb;

        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

        $past = date( "Y-m-d H:i:s", strtotime( "-8 weeks") );

        $sql =  $wpdb->prepare("DELETE FROM wp_tcmsc_bookings WHERE modified < %s AND status = %s",
        array($past, 'TEMP')
        );

        $wpdb->query($sql);

        $sql =  $wpdb->prepare("DELETE FROM wp_tcmsc_cartitems WHERE modified < ? OR modified IS NULL",
        array($past)
        );

        $result = $wpdb->query($sql);

        if ( $result > 0)
            return "Cart items and TEMP bookings older than 2 months successfully deleted from the DB";
        else
            return "Problems deleting payment records from the DB";
    }


}