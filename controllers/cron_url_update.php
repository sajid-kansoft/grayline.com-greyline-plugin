<?php

namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");


class CronUrlUpdate extends MainController {
	
	const QS_404_ERROR = "&404_tour_url=error";
	const TOUR_URL_KEYWORD = "tours";

	public function run() {
		
		$list =  $this->call_tourcms_api("list", self::QS_404_ERROR, null, null, null, null, "TOUR_URL_UPDATE"); 
		if(!empty($list)) {
			$json = json_encode( $list, JSON_UNESCAPED_SLASHES );
			$list = json_decode( $json );
			
			$total = $list->total_tour_count;
			$num = $total < 30 ? $total : 30;
			
			$i = 0;
			if ( empty($list->tour) )
				throw new \Exception( "No tours to update" );
			else {
				$tour_list = is_array( $list->tour ) ? $list->tour : array( $list->tour );
				foreach ( $tour_list as $tour ) {
					$i++;
					if ( $i < $num ) {
						$tour_id = $tour->tour_id;
						$tour_url = self::autoTourURL( $tour, false );
						$this->call_tourcms_api("update", null, $tour_id, null, null, null, $tour_url);
						
					}
				}
			}
			return $num . " out of " . $total . " 404 Tour URLs successfully updated";
		}
		else {
			return "Something went wrong.";
		}
	}

	public function autoTourURL($tour, $dir_rel_bool = true) {
		if ( defined('GL_BASIC_SITE') && GL_BASIC_SITE == true ) {
			return strtolower( $tour->tour_url );
		}
		else {
			$tour_id = $tour->tour_id;
			$keyword = self::TOUR_URL_KEYWORD;
			$name = $tour->tour_name_long;
			$name = strtr($name, self::$normalizeChars );
			$name = $this->sanitize_file($name);
			$name = str_replace("_", "-", $name);

			// If they have a "-" in the tour name, we need to deal with this
			$name = str_replace( "---", "-", $name );
			$name = str_replace( "--", "-", $name );
			
			$dir_rel = $dir_rel_bool == true ? GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH : "";
			
			$url = $dir_rel . "/$keyword/{$name}/";
			$url = str_replace(".", "", $url);
			return strtolower($url);
		}
	}

	public static $normalizeChars = array(
    'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
    'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
    'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
    'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
    'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
    'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
    'ă'=>'a', 'î'=>'i', 'â'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Î'=>'I', 'Â'=>'A', 'Ș'=>'S', 'Ț'=>'T',
		);

	public function sanitize_file($file) {
		$file = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $file);
		return trim($file);
	}

}
