<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class LicenseesPayout
{
    protected $tourcmsWrapper;
    protected $agent;
    protected $licensee_prefix = 'gl_licensee_';
    protected $agent_prefix = 'agent_affiliate_';
    protected $info;
    protected $booking_id;

    public function __construct(TourcmsWrapper $tourcmsWrapper, Agent $agent)
    {
        $this->tourcmsWrapper = $tourcmsWrapper;
        $this->agent = $agent;
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function generateInfo(int $booking_id)
    {
        $this->booking_id = $booking_id;
        $payout_info  = array();
        $xml = $this->tourcmsWrapper->show_booking($booking_id, $this->tourcmsWrapper->getChannelId());
        $components = $xml->booking->components;
        if ($components) {
            foreach ($components->component as $component) {
                $c_id = (int)$component->component_id;
                $id = (string)$component->supplier_tour_code;
                $licensee_name = $this->licenseeName((string)$component->supplier_tour_code);
                $description = $this->genComponentInfo($component);
                $cost_total = (string)$component->cost_price_inc_tax_total;
                $cost_currency = (string)$component->cost_currency;
                $net_rate = $cost_currency.' '.$cost_total;
                $sale_price = (string)$component->sale_currency.' '.(string)$component->sale_price_inc_tax_total;
                $component_info = array(
                    'licensee_id' => $id,
                    'licensee_name' => $licensee_name,
                    'net_rate' => $net_rate,
                    'sale_price' => $sale_price,
                    'exchange_rate' => (string)$component->cost_exchange_rate,
                    'description' => $description,
                    'cId' => $c_id,
                );
                $payout_info[] = $component_info;
            }
        }
        $payout_info = $this->affiliateAgent($xml, $payout_info);
        $this->info = $payout_info;
    }

    private function bookingFee($xml, $licensees)
    {
        var_dump($xml);

    }

    private function affiliateAgent($xml, $payout_info)
    {
        // This is for a tripadvisor type affiliate agent
        $agentType = (string)$xml->booking->agent_type;
        $isAgent = $this->agent->getIsAgent();
        // Affiliates could be logged in, OR following a tracking link
        // We only send TMT an "AFFILIATE" node if it's NOT a logged in agent, i.e. only for a tracking link
        $loggedIn = $isAgent ? '(Agent Logged In)' : '';
        if ($agentType == "AFFILIATE")
        {
            // We have a proper tracking affiliate agent, need to pass an extra payout node in financial data
            $agentId = (string)$xml->booking->agent_id;
            $agentName = (string)$xml->booking->agent_name;
            $agentCode = (string)$xml->booking->agent_code;
            $id = $this->agent_prefix . $agentId;
            $commission = (string)$xml->booking->commission;
            // Adding on a description too
            $description = "Affiliate Agent Payment: $agentName (ID $agentId) (Agent Code $agentCode) (Agent Type $agentType) $loggedIn";
            $currency = (string)$xml->booking->commission_currency;
            $affiliate_payout = array('affiliate_id' => $id, 'total' => $commission, 'currency' => $currency, 'description' => $description);
            $payout_info[] = $affiliate_payout;
        }
        return $payout_info;
    }

    private function genComponentInfo($component)
    {
        $name = str_replace('"', '\'', (string)$component->component_name);
        $quant = (string)$component->sale_quantity;
        $rate = (string)$component->rate_description;
        $bookingId = $this->booking_id;
        $string = "Gray Line Booking $bookingId: \n";
        $string .= "$name ($quant x $rate) \n";

        return $string;
    }

    protected function licenseeChannelId(string $supplier_tour_code)
    {
        preg_match('/\|([0-9]+)/', $supplier_tour_code, $return);

        return $return[1];
    }

    protected function licenseeName($supplier_tour_code)
    {
        $channel_name = "Unknown ($supplier_tour_code)";
        $channel_id = $this->licenseeChannelId($supplier_tour_code);
        if (empty($channel_id)) {
            return $channel_name;
        }
        $db = Loader::db();
        $row = $db->GetRow('select channel_name from SenshiGlwwCommission where channel_id = ?', $channel_id);
        if (is_array($row)) {
            if (isset($row['channel_name'])) {
                $channel_name = $row['channel_name'];
            }
        }

        return $channel_name;
    }


}
