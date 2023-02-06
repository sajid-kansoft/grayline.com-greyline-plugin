<?php 

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

/**
*
* Generates currency rate exchange JSON
* 
*/

class CronGenCurrencyJson extends MainController {
	
	private $api_key = "e05047c2190a4a4fb5d51cc8a3762580";
	private $oex_url = "http://openexchangerates.org/api/latest.json?app_id=";
	private $dir = "currency";
	private $filename = "currency.json";

	public function run() { 
		try {
			$url =  $this->oex_url . $this->api_key; 
			$json = $this->curlJSON( $url );
			$result = $this->saveJSON( $this->filename, $this->dir, $json );
			return json_encode( __("Get exchange rates completed", "gray-line-licensee-wordpress-tourcms-plugin") );
		}
		catch ( Exception $e ) {
		    /*error_log($e->getMessage());*/
			return $e->getMessage() . "\n";
		}
		
	}
	
	private function curlJSON( $url ) {
		$ch = curl_init( $url );
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			 
		// Attempt to set content-length to 0 to workaround nginx error...
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));  
			
		$json = curl_exec($ch);
		$json_stdobj = json_decode( $json );
    
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
		
		$error = curl_error($ch);
	
		curl_close($ch);

		$jsonObj = json_decode( $json );


        if ( $json === FALSE ) {
            throw new Exception( "Cannot connect to Open Exchange source. Error: $error \n"  );
        }
        else if ( ! is_object($jsonObj) || ! $jsonObj->rates->USD ) {
            throw new Exception( "Cannot load rates Open Exchange source. Error: $error \n"  );
        }
		else if ( $httpCode != 200 ) {
			throw new Exception( "There was a problem connecting to Open Exchange. Code: $httpCode \n"  ); 
		}
		else if ( $json_stdobj->error ) {
			throw new Exception( $json_stdobj->status . " " . $json_stdobj->message . " " . $json_stdobj->description );
		}
		else { 
			return $json;
		}
	}
	
	private function saveJSON( $filename, $dir, $json ) {

		if (!file_exists(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'api_json/'.$dir)) {

		    mkdir(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."api_json/".$dir."/", 0777, true);
		}

		$filename = $this->sanitize($filename);
		$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."api_json/".$dir."/";
		$filepath = $dir . $filename;
		
		if ( ! is_dir( $dir ) || ! is_writeable( $dir ) )  
			throw new Exception( "We seem to be having file system issues. We are sorry for the inconvenience. \n" );
		else 
			file_put_contents( $filepath, $json );
	}

	/** 
	 * Cleans up a filename and returns the cleaned up version
	 * @param string $file
	 * @return string @file
	 */
	public function sanitize($file) {
		$file = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $file);
		return trim($file);
	}
	
}