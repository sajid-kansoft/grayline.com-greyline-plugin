<?php

class Commission
{

    private $total = 0;
    private $licensees = array();
    private $commissionArray = array();
    private $glwwCommission = 15;

    public function __construct()
    {
        $this->genCommArray();
    }

    public function getTotal() { return $this->total; }
    public function getLicensees() { return $this->licensees; }

    public function genAll()
    {
        $rows = $this->dbGetAll();
        $licensees = array();

        // load licensee model
        loadSenshiModal('licensee');

        if (is_array($rows) || is_object($rows))
        {
            foreach ($rows as $row)
            {
                //////\FB::info("Licensee Row is");
                //////\FB::log($row);
                $licensee = new GrayLineTourCMSSenshiModals\Licensee($row);
                $licensees[] = $licensee;
            }

        }
        $this->total = count($licensees);
        ////\FB::warn("Total licensees is {$this->total}");
        $this->licensees = $licensees;
    }

    private function genCommArray()
    {
        $array = array();
        $rows = $this->dbGetAll();
        if (is_array($rows) || is_object($rows))
        {  
            foreach ($rows as $row)
            {
                $channelId = (int)$row['channel_id'];
                $commission = floatval($row['commission']);
                $array[$channelId] = $commission;
            }
        }
        $this->commissionArray = $array;
    }

    public function getLicensee($channelId)
    {
        // load licensee model
        loadSenshiModal('licensee');
        $channelId = (int)$channelId;
        $row = $this->dbGetOne($channelId); 
        $row = isset($row[0])? $row[0]: $row;
        $licensee = new GrayLineTourCMSSenshiModals\Licensee($row);
        return $licensee;
    }

    public function updateCommission(GrayLineTourCMSSenshiModals\Licensee $licensee)
    {   
        $this->dbUpdateCommission($licensee);
    }

    private function dbGetAll()
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM wp_senshi_glww_commission ORDER BY channel_name ASC");

        $rows = $wpdb->get_results($query, ARRAY_A);
        //////\FB::log("Query,: $query");
        //////\FB::info("SeshId is {$this->seshId}");
        //////\FB::log("Rows returned: " . count($rows));
        return $rows;
    }

    private function dbGetOne($id)
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM wp_senshi_glww_commission WHERE channel_id=%s LIMIT 1",
                    array($id)
                );
        $rows = $wpdb->get_results($query, ARRAY_A);
        ////\FB::log("Query,: $query");
        ////\FB::info("Id is {$id}");
        ////\FB::log("Rows returned: " . count($rows));
        ////\FB::log($rows);
        return $rows;
    }

    private function dbUpdateCommission(GrayLineTourCMSSenshiModals\Licensee $licensee)
    {  
        global $wpdb;
        
        $query = $wpdb->prepare("UPDATE wp_senshi_glww_commission SET commission=%s WHERE channel_id=%s",
                    array($licensee->getCommission(), $licensee->getChannelId())
                );
        
        $rows = $wpdb->query($query);
        //////\FB::log("Query: $query");
        //////\FB::log("Rows returned: " . count($rows));
        return $rows;
    }
}
