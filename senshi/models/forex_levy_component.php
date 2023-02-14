<?php

namespace GrayLineTourCMSSenshiModals;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

// If selected currency is USD, no levy required
// If selected currency is USD, and cost currency is NOT USD, no levy required
class ForexLevyComponent
{

    protected $forexLevy;
    protected $component;
    protected $costCurrency;
    protected $applyLevy = false;

    protected $salePrice;
    protected $saleQuantRule;
    protected $quantity;
    protected $rateId;


    public function __construct(ForexLevy $forexLevy, $component, $saleQuantRule)
    {
        die("OLD FEX CODE ForexLevyComponent");
        $this->forexLevy = $forexLevy;
        $this->component = $component;
        $this->costCurrency = (string)$component->cost_currency;
        $this->saleQuantRule = (string)$saleQuantRule;
        //\FB::info("Component costCurrency is {$this->costCurrency}");
        //\FB::warn("Sale quant rule: {$this->saleQuantRule}");
    }

    // We might want to apply a forex levy on a specific price
    // E.g. it's already had currency conversion on it, or is a breakdown price etc (legacy code)
    public function applyForexLevy($price=null)
    {
        //\FB::info("applyForexLevy($price)");
        if ($this->forexLevy->getIsForexLevy())
        {
            // Ok, we may need to apply forex levy, let's now check the cost currency is in fact USD
            if ($this->forexLevy->getIsCostCurrencyLevy($this->costCurrency))
            {
                //\FB::warn("Cost currency not USD, selected Currency is USD, we need to apply a levy to the price");
                $this->applyLevy = true;
                $price = $this->forexLevy->levyPrice($price);
            }
        }
        return $price;
    }


    // the person might switch currencies on checkout so PricePPLevy should always be levvied
    public function getPricePPLevy($price)
    {
        $price = $this->forexLevy->levyPrice($price);
        return $price;
    }

    public function getSalePrice()
    {
        $this->salePrice = (string)$this->component->total_price;
        return $this->salePrice;
    }

    public function getSaleQuantRule()
    {
        $this->saleQuantRule = (string)$this->saleQuantRule;
        return $this->saleQuantRule;
    }


    public function ratePricePP($i, $quantity)
	{
		//\FB::log("PRICE BREAKDOWN on component");

        // Problem, some tours aren't returning price breakdown rows for all rates

        if (is_null($this->component->price_breakdown->price_row[$i])) {
            $rateNumber = $i+1; // index of array
            $log = "TCMS COMPONENT ERROR BREAKDOWN, rate $rateNumber | ";
            throw new \Exception($log);
        }
        else {

            $attributes = $this->component->price_breakdown->price_row[$i]->attributes();

            $row_price = (float)$attributes["price"];

            // So, we use the cost price & multiply by
            $rule = $this->saleQuantRule;
            if ($rule == "PERSON")
            {
                $quantity = (float)$quantity;
            }
            else if ($rule == "GROUP")
            {
                $quantity = (float)1;
            }

            $pricePP = $row_price / $quantity;

            $pricePP =  sprintf('%.2F', $pricePP);


            return $pricePP;
        }
    }



    /* NOTE - have to integrate TMT forex with this
    public function breakdownLevy($i)
    {
        $attributes = $this->component->price_breakdown->price_row[$i]->attributes();

        $row_label = $attributes["label"];
        $row_price = $attributes["price"];
        $row_currency = $attributes["sale-currency"];

        $converted_amount = $this->applyForexLevy($row_price);
        $currency_format = "{amount}";

        $newRow = $row_label . " " . str_replace("{amount}", $converted_amount, $currency_format);
        echo "NEW ROW: $newRow";
        return $newRow;

    }
    */


}
