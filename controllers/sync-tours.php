<?php
/*

Purpose: Sync Tourcms data with wordpress
1 Fetch the API data 
2 Fetch existing tours that are not today added
3 Categorized the data in following category 
 - To be added
 - To be updated 
 - To be removed
==
*/


namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

class SyncTours extends MainController {

	public function save_tours() {
		
		$date = new \DateTime("now");
		$current_date = $date->format('Y-m-d');
		
        global $post;
        global $wpdb;
        $update_flag = false;

        $tourcms_tour_ids = [];
		
		// Get all tourcms tour ids in bulk from tour cms api
		$api_results = $this->call_tourcms_api("list_tours", null, null, null);
		
		 if((!empty($api_results->error)) && $api_results->error == "OK" && !empty($api_results)) {

		 	$api_results = is_array($api_results)? $api_results: [$api_results];
			$this->log_sync_detail(json_encode($api_results));
			// Get all existing tours from database which are not updated today
		 	try {
				$query = "SELECT $wpdb->posts.ID as post_id, cu.meta_value as tour_id, $wpdb->posts.post_date as post_date, $wpdb->posts.post_modified as post_modified                
		            FROM $wpdb->posts
		            LEFT JOIN $wpdb->postmeta AS cu ON ($wpdb->posts.ID = cu.post_id AND cu.meta_key='grayline_tourcms_wp_tour_id')
		            WHERE $wpdb->posts.post_type = 'tour'
		            AND $wpdb->posts.post_status = 'publish'
		            ORDER BY $wpdb->posts.post_modified ASC";
		             /*AND $wpdb->posts.post_modified < '".//$current_date."'*/
				$saved_tours = $wpdb->get_results( $query, ARRAY_A );
				
				// format saved tour ids in Array
			    $existing_tour_ids = array();
				if(!empty($saved_tours)){
					foreach($saved_tours as $tour) {
						$existing_tour_ids[$tour['post_id']] = $tour['tour_id'];
					}
				}
				

				$new_tour_details = [];
				$update_tour_details = [];
				$api_tour_ids = [];

				foreach($api_results[0]->tour as $tour) {

					$api_tour_id = (int)$tour->tour_id;
					
					$api_tour_ids[] = $api_tour_id;

					if(in_array($api_tour_id, $existing_tour_ids)) {
						$post_id = array_search($api_tour_id, $existing_tour_ids);
						$update_tour_details[$post_id] = $tour;
					} else {
						$new_tour_details[$api_tour_id] = $tour;
					}
					
				}
				
				// Find out unwanted tours from existing tours
				$unwanted_tour_post_ids = [];
				foreach($existing_tour_ids as $key => $existing_tour_id) {
					
					if(!in_array($existing_tour_id, $api_tour_ids))
					{ 
						$unwanted_tour_post_ids[] = $key;
					}
				}

				// Remove unwanted tours
				if(count($unwanted_tour_post_ids) > 0) {
					$this->remove_unwanted_tours($unwanted_tour_post_ids);
				}
				
	            // Fetch SYNC_TOUR_COUNT from config file
				$sync_tour_count = 2;
				if(defined("SYNC_TOUR_COUNT")) {
					$sync_tour_count = SYNC_TOUR_COUNT;
				}

				$remaining_count = (count($new_tour_details) - $sync_tour_count);

				// save new tourcms tours into database
				if(count($new_tour_details) > 0) {
					$this->add_new_tour($new_tour_details, $sync_tour_count);
				}

				// update tourcms tours into database
				if(count($update_tour_details) > 0 && $remaining_count < 1) {
					$this->update_tour($update_tour_details, $sync_tour_count);
				}
			} catch (Exception $e) {
				$this->log_sync_detail($e->getMessage());
			}

		}
		
	}

	public function remove_unwanted_tours($unwanted_tour_post_ids) {
		// Remove tours
		foreach($unwanted_tour_post_ids as $post_id) { 
			wp_delete_post($post_id);
		}
	}

	public function add_new_tour($new_tour_details, $sync_tour_count) {
		
		$i = 1; 
		foreach($new_tour_details as $tour) {
			if($i <= $sync_tour_count) {

				// Create post object
				$tour_post = array();
				$tour_post['post_title']    = (string) $tour->tour_name;
				$tour_post['post_type']     = 'tour';
				$tour_post['post_status']   = 'publish';
				$tour_post['post_author']   = 1;
				$tour_post['post_category'] = array(0);

				$post_ID = wp_insert_post( $tour_post ); 

				// Save tour info
				$this->refresh_tour_info($post_ID, (int)$tour->tour_id, $tour, 1); 
			} else {
				break;
			}

			$i++;
		}
	}

	public function update_tour($update_tour_details, $sync_tour_count) {

		foreach($update_tour_details as $key => $tour) {
			
			$post_ID = $key;
			/*$tour_update = array(
								'ID' =>  $post_ID,
								'post_title'    => (string) $tour->tour_name,
								//'post_name'     => $this->set_slug((string) $tour->tour_name),
								'post_status'   => 'publish',
								'post_type'     => 'tour'
							);

			wp_update_post($tour_update);*/

			// Update tour info
			$this->refresh_tour_info($post_ID, (int)$tour->tour_id, $tour, 2); 
		}
	}

	public function refresh_tour_info($post_ID, $tour_id, $tour, $update_post_detail) {

		grayline_tourcms_wp_refresh_info($post_ID, $tour_id, $update_post_detail);

		if(is_object($tour)) {
			// update list_tours api data in wp_postmeta table
			update_post_meta( $post_ID, 'grayline_tourcms_wp_account_id', (string)$tour->account_id);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_has_d', (string)$tour->has_d);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_has_f', (string)$tour->has_f);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_has_h', (string)$tour->has_h);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_descriptions_last_updated', (string)$tour->descriptions_last_updated);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_supplier_id', (string)$tour->supplier_id);
			update_post_meta( $post_ID, 'grayline_tourcms_wp_supplier_tour_code', (string)$tour->supplier_tour_code);

			// update original channel id and original tour id
			update_post_meta( $post_ID, 'grayline_tourcms_wp_original_channel_id', get_channel_id_from_supplier_tour_code((string)$tour->supplier_tour_code));
			update_post_meta( $post_ID, 'grayline_tourcms_wp_original_tour_id', get_tour_id_from_supplier_tour_code((string)$tour->supplier_tour_code));
		} 
	}	

	public function set_slug($tour_name)
	{  
		$tour_name = strtr($tour_name, self::$normalize_chars );
		$tour_name = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $tour_name);
		$tour_name = str_replace("_", "-", $tour_name);
		// If they have a "-" in the tour name, we need to deal with this
		$tour_name = str_replace( "---", "-", $tour_name );
		$tour_name = str_replace( "--", "-", $tour_name );
		return strtolower($tour_name);
	}

	public function log_sync_detail($log_text) {

		$date = new \DateTime("now");
		$current_date = $date->format('Y-m-d');

		$filename = "sync_tour_".$current_date; 
		if (!file_exists(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'logs/sync_tours')) {

		    mkdir(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/sync_tours/", 0777, true);
		}
		$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."logs/sync_tours/";
		$filepath = $dir . $filename.".txt";

		$log_msg = "\n\n\n Date Time:".$current_date.
                "\n $log_text";

        $fh = fopen($filepath, 'a') or die("can't open file");
		fwrite($fh, $log_msg);
		fclose($fh);
		//file_put_contents( $filepath, $log_msg );
	}

	public static $normalize_chars = array(
    'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
    'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
    'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
    'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
    'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
    'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
    'ă'=>'a', 'î'=>'i', 'â'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Î'=>'I', 'Â'=>'A', 'Ș'=>'S', 'Ț'=>'T',
		);
}