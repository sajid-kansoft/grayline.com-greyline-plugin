<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class Agent
{
    protected $isAgent = false;
    protected $agentName;
    protected $tcms_ag_bk;


    public function __construct()
    {
        $cookieTcmsAgBk = isset($_COOKIE["tcms_ag_bk"]) ? $_COOKIE["tcms_ag_bk"] : null;
        $sessionAgentName = isset($_SESSION["agent_name"]) ? $_SESSION["agent_name"] : null;
        if($cookieTcmsAgBk && $sessionAgentName)
        {
            $this->isAgent = true;
            $this->agentName = $sessionAgentName;
            $this->tcms_ag_bk = $cookieTcmsAgBk;
        }
    }

    public function getIsAgent() { return $this->isAgent; }
    public function getAgentName() { return $this->agentName; }

}