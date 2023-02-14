<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Affiliate
{

    private $tmtIdConstant = "grayline_";

    private $affiliate_id;
    private $total;
    private $currency;
    private $description;


    public function __construct($id, $total, $currency, $description=null)
    {
        $affiliate_id = $this->tmtIdConstant . $id;
        $this->affiliate_id = $affiliate_id;
        $this->total = $total;
        $this->currency = $currency;
        $this->description = $description;
    }

    public function getData()
    {
        $data = array('affiliate_id' => $this->affiliate_id, 'total' => $this->total, 'currency' => $this->currency, 'description' => $this->description);
        //\FB::log("TMT Affiliate: " . implode(",", $data));
        return $data;
    }


}
