<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class CookieConsent extends MainController {

	public function setUp($request) {
		$cookies_allowed = (isset($request["allowCookies"]) && $request["allowCookies"] == 1) ? "yes":"no";

		$expire = time()+1*24*3600;
		if(isset($request["allowCookies"]) && $request["allowCookies"] == 1) {
			$expire = time()+365*24*3600;
		}
		
		$palisis_cookie = new PalisisCookie();

		$name = "allowCookies";
		$val =  $cookies_allowed;
		$palisis_cookie->setCookie($name, $val, $expire);

		return true;
	}	
}