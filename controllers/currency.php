<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class TourCMSCurrency extends MainController {

	public function set_up() {
		$this->currency_switch();
	}

	public function get_session_currency() {

		if(!empty($_SESSION['currency']) && !empty($_SESSION['exchange_rate'])) {
			$ret = [];
			$ret['currency'] = $_SESSION['currency'];
			$ret['exchange_rate'] = $_SESSION['exchange_rate'];
			echo json_encode($ret);
		}
	}

	public function currency_switch_price( $price, $round=0 ) {
		$currency = $this->getCookieCurrency();
		$exchange_rate = $this->exchangeRate( $currency );
		$switched_price = round( ($price * $exchange_rate), $round );
		return $switched_price;
	}

	private function getCookieCurrency() {
		$currency = DEF_CURRENCY;
		// In the case the currency was just set, we get this
		if ( isset( $_GET["currency"]) && !is_array($_GET["currency"])) $currency = $_GET["currency"];
		return $currency;
	}

	public static function exchangeRate( $currency ) {

        $json = loadJsonFile( "currency.json", "currency" );
		$exchange_rate = $json->rates->$currency;
		return $exchange_rate;
	}

	private function currency_switch() {
		// If there is no currency cookie set, or if there is, but there is a get currency var, then deal with this
		if(!empty($_GET['currency']) && !is_array($_GET["currency"])) {
			$currency = wp_strip_all_tags($_GET['currency']);  
			
			$currency = strtoupper($currency);
            
			$json = loadCurrencyFile("currency.json");

			include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php"); 
			
			if (!in_array($currency, $allowed_currencies)) {
		        $currency = DEF_CURRENCY;
		    }

			if(!empty($currency) && !empty($json->rates->$currency)) { 
				$def_currency = DEF_CURRENCY;
				
				$def_exchange_rate = $json->rates->$def_currency != 0 ?  $json->rates->$def_currency : 1;
				$selected_exchange_rate = $json->rates->$currency;
				$exchange_rate = (1 / $def_exchange_rate ) * $selected_exchange_rate;
				// The base currency is USD, so we need to account for this
				// Let's set the cookie for the currency selected
				
				if( array_key_exists('cookielawinfo-checkbox-analytics', $_COOKIE) && $_COOKIE['cookielawinfo-checkbox-analytics'] == "yes" ) {
					$this->setCookie( "currency", $currency );
					$this->setCookie( "exchange_rate", $exchange_rate );
				} else {
					$_SESSION['currency'] = $currency;
					$_SESSION['exchange_rate'] = $exchange_rate; 
					?>
					<?php
				}
			}
			
		} else if ( ! isset( $_COOKIE["currency"] ) ) { 
			$currency = DEF_CURRENCY;
			// Let's set the cookie for the currency selected
			// can't set cookies unless user has consented
			// if (isset($_COOKIE['cookies_allowed']) && $_COOKIE['cookies_allowed'] === '1') {
			if(array_key_exists('cookielawinfo-checkbox-analytics', $_COOKIE) && $_COOKIE['cookielawinfo-checkbox-analytics'] ) {
				$this->setCookie("currency", $currency);
				$this->setCookie("exchange_rate", 1);
			} else {
				$_SESSION['currency'] = $currency;
				$_SESSION['exchange_rate'] = 1; 
			}
			// FB::warn( "No currency cookie set, so set default, & exchange rate to 1" );
			// FB::info( "currency is $currency" );
		}
	}

	private function setCookie( $name, $val ) {
		// 24 hours for currency data
		$expire = time() + (24*3600);
		loadSenshiModal('palisis_cookie');
		$palisis_cookie = new \GrayLineTourCMSSenshiModals\PalisisCookie();
		$palisis_cookie->setCookie($name, $val, $expire );
	}
}