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
    '??'=>'S', '??'=>'s', '??'=>'Dj','??'=>'Z', '??'=>'z', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A', '??'=>'A',
    '??'=>'A', '??'=>'A', '??'=>'C', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'E', '??'=>'I', '??'=>'I', '??'=>'I',
    '??'=>'I', '??'=>'N', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'O', '??'=>'U', '??'=>'U',
    '??'=>'U', '??'=>'U', '??'=>'Y', '??'=>'B', '??'=>'Ss','??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a', '??'=>'a',
    '??'=>'a', '??'=>'a', '??'=>'c', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'e', '??'=>'i', '??'=>'i', '??'=>'i',
    '??'=>'i', '??'=>'o', '??'=>'n', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'o', '??'=>'u',
    '??'=>'u', '??'=>'u', '??'=>'y', '??'=>'y', '??'=>'b', '??'=>'y', '??'=>'f',
    '??'=>'a', '??'=>'i', '??'=>'a', '??'=>'s', '??'=>'t', '??'=>'A', '??'=>'I', '??'=>'A', '??'=>'S', '??'=>'T',
		);

	public function sanitize_file($file) {
		$file = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $file);
		return trim($file);
	}

}
