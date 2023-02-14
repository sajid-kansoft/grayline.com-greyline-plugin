<?php
/**
 * Tours view
 *
 * Wrapper for the "Search Tours/Hotels" API call
 * Provides basic information on Tours/Hotels matching search criteria
 * http://www.tourcms.com/support/api/mp/tour_search.php
 *
 */
	class Tours {
	
		public $tours = array();
		
		//public $num_pages;
		public $total_tour_count;
		
		public $country_name = "";
		//public $city_name = "";
		public $pretty_location = "";
		
		public $poss_per_pages = array(10, 50, 100, 200);
		public $per_page = 10;
		public $page = 1;
		
		//public $first_tour_num = 1;
		//public $last_tour_num = 1;
		
		public $poss_filters = array("Best sellers", "Deals", "Low to high", "High to low", "Rating");
		public $filter = "Best sellers";
		//public $filter_not_set = false;
		
		//public $type_not_set = false;

		/*public $poss_product_types = array(
				0 => "All",
				4 => "Tours & Activities",
				3 => "Multi-day Trips"
		);*/

		public $poss_types = array(
				0 => "all",
				4 => "tours-and-activities",
				3 => "multi-day-trips"
		);

		public $product_type_text = array(
				0 => "result",
				4 => "tour / activity",
				3 => "multi day trip"
		);

		public $product_type_text_plural = array(
				0 => "results",
				4 => "tours & activities",
				3 => "multi day trips"
		);
		
		public $product_type = 0;
		public $type = "";
		
		public $params;
		
		public $today;
		
		function __construct($tourcmsobj, $params = array()) {
            
			global $api_info; 
			// Set todays date
			// Could be a cookie stored by JavaScript containing local date

			// Need to check
			$this->today = date("Y-m-d");
			if(!empty($_COOKIE["local_date"])) {
				if(preg_match("/^[0-9]{4}-[0-9]{1,2}[0-9]{1,2}/", $_COOKIE["local_date"])) {
					$yesterday = date("Y-m-d", strtotime("-1 Day"));
					$tomorrow = date("Y-m-d", strtotime("+1 Day"));
					if( ($_COOKIE["local_date"] >= $yesterday ) && ($_COOKIE["local_date"] <= $tomorrow) )
						$this->today = $_COOKIE["local_date"];
				}
			}

		    include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php"); 

			// Include country list
			
			$country_json = get_option("countries");
			$iso_country_list = (array)json_decode($country_json);
			
			// Hide a certain Tour
			if(isset($params["not_tour"])) {
				
				// Request one more than we normally would
				if(isset($params["per_page"]))
					$original_per_page = $params["per_page"];
					$params["per_page"] = $params["per_page"] + count($params["not_tour"]);
			}
			
			// Per page
			if(isset($params["per_page"])) {
				if(!in_array($params["per_page"], $this->poss_per_pages) && !$params["per_page"]==4)
					$params["per_page"] = 10;
				
				$this->per_page = $params["per_page"];
			}
			
			// Page
			if(isset($params["page"])) {	
				$this->page = $params["page"];
			}
			
			// Location
			if(isset($params["location"])) {
			
			
				$params["location"] = str_replace(" _and_ ", "/", $params["location"]);	
				
					
				$this->location = $params["location"];	

				/*$temp_pretty_location = $this->location;
				$temp_pretty_location = str_replace("/", " / ", $temp_pretty_location);
				$temp_pretty_location = ucwords($temp_pretty_location);
				$temp_pretty_location = str_replace(" / ", "/", $temp_pretty_location);
				
				$this->pretty_location = $temp_pretty_location;	*/	
				/*$this->city_name = ucwords($params["location"]);

				if (strtolower($this->city_name) == "washington dc") {
					$this->city_name = "Washington DC";
				}*/
				/*if (strtolower($this->pretty_location) == "washington dc") {
					$this->pretty_location = "Washington DC";
				}*/
			}

			// Geo code
			$this->geocode = 0;
			if(isset($params["lat"])) {
				$this->geocode = 1;
			}

			// Country (by name)
			if(isset($params["country_name"])) {
				
				$params["country_name"] = strtoupper(str_replace("-", " ", $params["country_name"]));
				
				if(in_array($params["country_name"], $iso_country_list)) {
					$params["country"] = array_search($params["country_name"], $iso_country_list);
					$this->country_name = $params["country_name"];

				}
			}
			
			// Country (by code, or converted from name)
			if(isset($params["country"])) {
			
				$params["country"] = strtoupper(substr($params["country"], 0, 2));
				
				if(isset($iso_country_list[$params["country"]])) {
					$this->country = $params["country"];
					/*if($this->pretty_location!="")
						$this->pretty_location .= ", ";
					
					$this->pretty_location .= ucwords(strtolower($iso_country_list[$params["country"]]));*/
				} else {
					unset($params["country"]);
				}
			}
			
			
			// Filter
			if(!empty($params["filter"])) {	
				if(in_array($params["filter"], $this->poss_filters)) {
					$this->filter = $params["filter"];
					switch($params["filter"]) {
						case "Best sellers":
							unset($params["order"]);
							unset($params["has_offer"]);
							break;
						case "Deals":
							unset($params["order"]);
							$params["has_offer"] = 1;
							break;
						case "Low to high":
							$params["order"] = "price_up";
							unset($params["has_offer"]);
							break;
						case "High to low":
							$params["order"] = "price_down";
							unset($params["has_offer"]);
							break;
						case "Rating":
						    $params["order"] = "rating_down";
						    unset($params["has_offer"]);
						    break;
					}
				}
			} /*else {
				$this->filter_not_set = true;
			}*/
			
			// Date range
				if(isset($params["start_date_start"]) && isset($params["start_date_end"])) {
				
				
					if($params["start_date_start"] < $this->today) {
						$params["start_date_start"] = $this->today;
					}
					
					$this->params["start_date_start"] = $params["start_date_start"];
					$this->params["start_date_end"] = $params["start_date_end"];
				}
			
			
			// Keywords
			$keywords = array();
			
			if(isset($params["keywords"])) {	
				$keywords = explode("|", $params["keywords"]);
			}
			
			if(isset($params["addkey"])) {
				$keywords[] = $params["addkey"];
				unset($params["addkey"]);
			}
			
			// Special search term, used if no results from a location search
			if(isset($params["search_term"])) {
				$this->search_term = $params["search_term"];
				array_unshift($keywords, $params["search_term"]);
				//$this->pretty_location = $params["search_term"];
				unset($params["location"]);
			}
			
			if(count($keywords) > 0) {
				// API only supports three
				//array_splice($keywords, 3);
				
				for ($i = 0; $i < count($keywords); $i++) {
					if($i == 0)
						$params["k"] = $keywords[$i];
					else 
						$params["k" . ($i+1)] = $keywords[$i]; 
				}
				
				$params["keywords"] = implode("|", $keywords);
		
			}
			
			
			if(!empty($params["type"])) {
					
				if(!array_search($params["type"], $this->poss_types)) {
					$params["type"] = "";
					$params["product_type"] = "";
				} else  {
					$params["product_type"] = array_search($params["type"], $this->poss_types);
				}
				
				$this->product_type = $params["product_type"];
				$this->type = $params["type"];
			} /*else {
				$this->type_not_set = true;
			}*/
		
			
			$this->params = $params;
			$qs = "";
			
			if(!empty($params)) {
				$temp_params = $params;
				unset($temp_params["not_tour"]);
				unset($temp_params["country_name"]);
				unset($temp_params["type"]);
				
				$reloaded = false;
				if(isset($params["reloaded"])) {
					if($params["reloaded"]) {
						$reloaded = true;
					}
				}
				
				if($this->country_name!="" && !$reloaded) {
					unset($this->params["country_name"]);
					unset($this->params["country"]);
					unset($this->params["location"]);
					unset($this->params["product_type"]);
				}
				
				if(isset($this->params["search_term"])) {
					$keywords = explode("|", $this->params["keywords"]);
					unset($keywords[0]);
					$this->params["keywords"] = implode("|", $keywords);
				}
				$qs = http_build_query($temp_params);
			}
			$qs .= "&lang=en&404_tour_url=all";
		
			$tours = array();
			
			$loaded_cache = false;
			
			// Check if search results exist in cache
			$cache_key = md5($qs); 

			if(empty($api_info)) { 
				// Get saved tour search result from transient
				$api_info = get_data_from_transient($cache_key); 
				if(!empty($api_info)) {
					$result = $api_info;
					$result->tour = !is_array($result->tour)? [$result->tour]: $result->tour;
					$loaded_cache = true;
				}
			} 

			$cache_time = isset($cache_times["tours"]) ? $cache_times["tours"] : 5*60;

			if(!$loaded_cache) {  
				// Query the TourCMS API
				$result = $tourcmsobj->search_tours($qs . '&k_type=AND', $channel_id);
				
				if(empty($result->error)) {
					// Try again to query the TourCMS API
					$result = $tourcmsobj->search_tours($qs . '&k_type=AND', $channel_id);
				}
				
				if($result->error=="OK") {
					// Save the result to cache
					set_data_in_transient($cache_key, $result, $cache_time);
				} 

				 
			}

			$this->error = (string)(isset($result->error) ? $result->error : "NOTOK");
			
			$hidden_count = 0;
				
			if($this->error=="OK") { 

				foreach ($result->tour as $tour) {  
					// If we are hiding certain tours
					if(isset($params["not_tour"])) {
						
						if(!in_array((int)$tour->tour_id, $params["not_tour"])) {
							$this->tours[] = $tour;
						} else {
							$hidden_count++;
							$result->total_tour_count --;
						}
						
						
					} else {

						// Check if this Tour has special offers

						if (defined('SPECIAL_OFFERS') && SPECIAL_OFFERS === true) {

							if (!empty($tour->soonest_special_offer) || !empty($tour->recent_special_offer)) {
								$tour->hasoffer = true;
							}


							if (isset($tour->soonest_special_offer) && isset($tour->recent_special_offer)) {

								// Get shorter variable names!
								$soon_offer = $tour->soonest_special_offer;
								$recent_offer = $tour->recent_special_offer;


								// Set displayed offer details based on whether any of the
								// returned offers current price matches the from price
								$soon_price = $soon_offer->price_1;
								$recent_price = $recent_offer->price_1;
								$from_price = $tour->from_price;


								if ((string)$from_price == (string)$soon_price) {

									$tour->was_price = $soon_offer->original_price_1;
									$tour->was_price_display = $soon_offer->original_price_1_display;
									if( count( (array)$soon_offer->special_offer_note)  != 0) {
										$tour->offer_note = (string)$soon_offer->special_offer_note;
									} else {
										$tour->offer_note = "";
									}

									$tour->on_offer = true;


								} elseif ((string)$from_price == (string)$recent_price) {

									$tour->was_price = $recent_offer->original_price_1;
									$tour->was_price_display = $recent_offer->original_price_1_display;

									if( count( (array)$recent_offer->special_offer_note)  != 0) {
									$tour->offer_note = (string)$recent_offer->special_offer_note;
									} else {
										$tour->offer_note = "";
									}

									$tour->on_offer = true;


								} else {

									unset($tour->on_offer);

								}

								if ( count( (array)$tour->offer_note) != 0 && $tour->offer_note == "") {
									$tour->offer_note = "Sausages";
									unset($tour->offer_note);
								}
							}
						}
						
						$this->tours[] = $tour; 
					}
				}

				// Delete any Tours if we have too many		
				if(isset($params["not_tour"]) && count($this->tours) > $original_per_page) {
		
					for($i=1; $i<=(count($this->tours) - $original_per_page); $i++) {
						$unset_key = count($this->tours) - (int)$i;
		
						unset($this->tours[(int)$unset_key]);
					}
				}
				
			}

			
			// Process number of Tours and pages
			if(isset($result->total_tour_count)) {
				//$this->num_pages = ceil($result->total_tour_count / $this->per_page);
				$this->total_tour_count = (int)$result->total_tour_count;
				
				//$this->first_tour_num = (($this->page - 1) * $this->per_page) + 1;
				//$this->last_tour_num = $this->page * $this->per_page;
				
				//$this->last_tour_num = $this->last_tour_num > $this->total_tour_count ? $this->total_tour_count : $this->last_tour_num;
			}

			$this->show_keyword_internal_search = true;
			
			$this->page_title = "Search results".$page_titles_base;
			
			// Fix Washington DC
			/*$search_locs = array("Washington Dc");
			$replace_locs = array("Washington DC");*/
			//$this->pretty_location = str_replace($search_locs, $replace_locs, $this->pretty_location);

			// Generate pretty URLs
			// SSLify image links
			$index = 0; 
			if (isset($this->tours)) { 
				$i=1;
				foreach ($this->tours as $tour) { 
						$tour->url = get_product_url(
						(string)$tour->tour_name_long,
						$tour->channel_id,
						(int)$tour->tour_id,
						(string)$tour->location
					); 

					$tour->original_channel_id = get_original_channel_id((int)$tour->tour_id); 
					$tour->original_tour_id = get_original_tour_id((int)$tour->tour_id);
					/*$tour->feefo_image_url = $feefo->getLogoUrl(
						'grayline-product-stars_en.png',
						$tour->original_channel_id.'_'.$tour->original_tour_id
					);*/

					// SSL images
					$tour->thumbnail_image = tour_image_secure($tour->thumbnail_image);


					// set the index of the tour to lazy load images
					$index++;
					if ($index > 3) {
						$tour->lazy_load = 1;
					}
					// Easy cancellation
					// handle object issue
					$custom1 = (array)$tour->custom1;
				    if (count($custom1)>0 && (strtolower((string)$tour->custom1) === 'false' || (string)$tour->custom1 === '0')) {
						// if there is an easy cancellation key for this tour but it contains anything other than "false" then treat it as if it does have easy cancellation
					}
					else {
						$tour->easy_cancellation = true;
					}

					// Product badges
					$tour->product_badge_code = false;

					// handle object issue
					$custom2 = (array)$tour->custom2;
					$badge = "";
					if (count($custom2)>0) {
						$badge = (string)strtoupper($tour->custom2);
					}

					if (isset($badge)) {
						$code = product_code($badge);
						$tour->product_badge_code = $code;
						$tour->product_badge_text = product_badge($code);
					}

					$tour->tour_name_long_escaped = htmlentities($tour->tour_name_long);

				} 
			}

		}


		/*public function isok() {
			return (string)$this->error==="OK";
		}
		
		public function notok() {
			return !$this->isok();
		}*/
		
		// Current url
		
		/*public function current_url() {
			$params = $this->params;
			unset($params["order"]);
			return "?" . http_build_query($params);
		}
		
		public function current_url_page_one() {
			$params = $this->params;
			$params["page"] = 1;
			unset($params["order"]);
			return "?" . http_build_query($params);
		}*/
		
		// Per page links
		
		/*public function showperpages() {
			return ($this->total_tour_count > $this->poss_per_pages[0]);
		}*/
		
		/*public function perpages() {
			$return = array();
			
			for ($i = 0; $i < count($this->poss_per_pages); $i++) {
				
				$per_page = $this->poss_per_pages[$i];*/
//				$show_this_option = false;
//				
//				if($i == 0) {
//					$show_this_option = true;
//					//print "Yup";
//				} elseif ($this->total_tour_count > $this->poss_per_pages[$i - 1]) {
//					$show_this_option = true;
//					//print "Yup2";
//				}

				/*$show_this_option = true;
				
				if($show_this_option) {
					//print "Yup3";
					$params = $this->params;
					unset($params["order"]);
					$params["per_page"] = $per_page;
					$params["page"] = 1;
					
					$return[] = array(
								"text" => $per_page,
								"selected" => ($per_page==$this->per_page),
								"url" => "?" . http_build_query($params)
							);
				}
			}
			
			return $return;
		}*/
		
		// Ordering links
		
		/*public function filters() {
			
			$return = array();
			
			foreach ($this->poss_filters as $filter) {
			
				$params = $this->params;
				unset($params["order"]);
				$params["filter"] = $filter;
				$params["page"] = 1;
				
				$return[] = array(
							"text" => $filter,
							"selected" => ( ($filter==$this->filter) && !$this->filter_not_set),
							"url" => "?" . http_build_query($params)
						);
						
			}
			return $return;
		}
		*/
		/*public function has_keywords() {
			$params = $this->params;
			
			return !empty($params["keywords"]);
		}*/
		
		/*public function max_keywords() {
			$params = $this->params;
			$keyword_list = explode("|", $params["keywords"]);
			
			if(isset($this->search_term)) {
				$keyword_list[] = $this->search_term;
			}
			
			return count($keyword_list) >= 3 ? true : false;
		}*/
		
		/*public function keywords() {
			
			$return = array();
			
			$params = $this->params;
			unset($params["order"]);
			
			if(!empty($params["keywords"])) {
				$keyword_list = explode("|", $params["keywords"]);
				
				foreach ($keyword_list as $keyword) {
					$temp_keywords = $keyword_list;
					$temp_params = $params;
					
					if(($key = array_search($keyword, $temp_keywords, true)) !== FALSE) {
					        unset($temp_keywords[$key]);
					    }
					
					$temp_params["keywords"] = implode("|", $temp_keywords);
					
					$return[] = array(
							"text" => $keyword,
							"url" => "?" . http_build_query($temp_params)
					);
				}
			}
			
			return $return;
		}*/
		
		// Product types
		
				
		/*public function producttypes() {
		
			$return = array();
					
			foreach ($this->poss_types as $key => $type) {
		
				$params = $this->params;
				unset($params["order"]);
				$params["page"] = 1;
				$params["type"] = $type;
				$text = $this->poss_product_types[$key];
					
				$return[] = array(
					"text" => $text,
					"selected" => ( ( (int)$key==(int)$this->product_type ) && !$this->type_not_set),
					"url" => "?" . http_build_query($params),
					"value" => $type
				);
			}
			return $return;
		}*/
		
		// Pages
		
		/*public function pages() {
		
			$return = array();
			
			if($this->page > 1) {
			
				$params = $this->params;
				unset($params["order"]);
				$params["page"] --;
				
				$return[] = array(
					"text" => "Previous",
					"class" => "previous",
					"selected" => "",
					"url" => "?" . http_build_query($params),
					"rel" => "prev"
				);
			
			}
			
			// If we have less than 9 pages just show them all
			if($this->num_pages <= 7) {
				for($i=1; $i <= $this->num_pages; $i++) {
	
					$params = $this->params;
					//unset($params["order"]);
					$params["page"] = $i;
				
					$return[] = array(
						"text" => $i,
						"class" => "",
						"selected" => ($this->page==$i),
						"url" => "?" . http_build_query($params),
						"rel" => ""
					);
				}
			// Otherwise do something a bit more clever (hopefully)
			} else {
				
				$params = $this->params;
				$either_side = 1;
				
				// Always link first page
				$params["page"] = 1;
				
				$return[] = array(
					"text" => $params["page"],
					"class" => "",
					"selected" => ($this->page==$params["page"]),
					"url" => "?" . http_build_query($params),
					"rel" => "first"
				);
				
				// If we're past page 4
				
				if($this->page > $either_side + 2) {
					$return[] = array(
						"text" => "...",
						"class" => "",
						"selected" => false,
						"url" => "",
						"rel" => ""
					);
				}	
				
				// Pages prior to our current page
				
				$min_prev = ($this->page - $either_side > 1) ? $this->page - $either_side : 2;
				if($this->page == $this->num_pages)
					$min_prev = $min_prev - 3;
				elseif($this->page == $this->num_pages - 1)
					$min_prev --;
				
				for($i = $min_prev; $i < $this->page; $i++) {
					$params["page"] = $i;
					$return[] = array(
						"text" => $params["page"],
						"class" => "",
						"selected" => ($this->page==$params["page"]),
						"url" => "?" . http_build_query($params),
						"rel" => ""
					);
				}
				
				// Current page
				if($this->page != 1 && $this->page != $this->num_pages) {
					$params["page"] = $this->page;
					$return[] = array(
						"text" => $params["page"],
						"class" => "",
						"selected" => ($this->page==$params["page"]),
						"url" => "?" . http_build_query($params),
						"rel" => ""
					);
				}
				
				// Pages after our current page
				$max_next = ($this->page + $either_side < $this->num_pages) ? $this->page + $either_side : $this->num_pages - 1;
				if($this->page < 4) {
					$max_next = $this->page == 1 ? $max_next + (5 - $this->page) : $max_next + (4 - $this->page);
				}
				
				
				for($i = $this->page + 1; $i <= $max_next; $i++) {
					$params["page"] = $i;
					$return[] = array(
						"text" => $params["page"],
						"class" => "",
						"selected" => ($this->page==$params["page"]),
						"url" => "?" . http_build_query($params),
						"rel" => ""
					);
				}
				
				// If we're prior to 2 pages before the end
				if($this->page < ($this->num_pages - ($either_side + 1))) {
					$return[] = array(
						"text" => "…",
						"class" => "",
						"selected" => false,
						"url" => "",
						"rel" => ""
					);
				}
				
				// Always link last page
				$params["page"] = $this->num_pages;
				
				$return[] = array(
					"text" => $params["page"],
					"class" => "",
					"selected" => ($this->page==$params["page"]),
					"url" => "?" . http_build_query($params),
					"rel" => "last"
				);
			}
			
			if($this->page < $this->num_pages) {
			
				$params = $this->params;
				//unset($params["order"]);
				$params["page"] ++;
				
			
				$return[] = array(
					"text" => "Next",
					"class" => "next",
					"selected" => "",
					"url" => "?" . http_build_query($params),
					"rel" => "next"
				);
			
			}
			
			/*foreach ($this->poss_per_pages as $per_page) {
				$return[] = array(
							"perpage" => $per_page,
							"selected" => ($per_page==$this->per_page)
						);
			}*/
			
			/*return $return;
		}
		
		function multiplepages() {
			return $this->num_pages > 1;
		}*/
		
		/*
			Get a search string with no dates
		*/
		/*function search_no_dates() {
			$params = $this->params;
			unset($params["page"]);
			unset($params["start_date_start"]);
			unset($params["start_date_end"]);
			
			return "?" . http_build_query($params);
		}
		
		function american_start_date() {
			$params = $this->params;
			if(!empty($params['start_date_start'])) {
				return date("m/d/Y", strtotime($params['start_date_start']));
			} else {
				return '';
			}
		}
		
		function american_end_date() {
			$params = $this->params;
			if(!empty($params['start_date_end'])) {
				return date("m/d/Y", strtotime($params['start_date_end']));
			} else {
				return '';
			}
		}
		
		function long_start_date() {
			$params = $this->params;
			if(!empty($params['start_date_start'])) {
				return date("M jS Y", strtotime($params['start_date_start']));
			} else {
				return '';
			}
		}
		
		function long_end_date() {
			$params = $this->params;
			if(!empty($params['start_date_end'])) {
				return date("M jS Y", strtotime($params['start_date_end']));
			} else {
				return '';
			}
		}*/
		
		/*
			Check if we have searched for dates or not
		*/
		/*function has_dates() {
			if(!(empty($this->params["start_date_start"]) || empty($this->params["start_date_end"]))) {
				if($this->params["start_date_end"] >= $this->today) {
					return true;
				}
			}
			return false;
		}*/
		
		/*
			Check if we have searched for dates or not
		*/
		/*function has_date_range() {
			if(!( empty($this->params["start_date_start"]) || empty($this->params["start_date_end"]))) {
				if(	$this->params["start_date_start"] != $this->params["start_date_end"] )
					return true;
			}
			return false;
		}
		
		
		function get_start_date() {
			if(isset($this->params["start_date_start"])) {
				if(strtotime($this->params["start_date_start"]) > time()) {
				
					$ar_date = explode("-", $this->params["start_date_start"]);
				
					if(count($ar_date)==3) {
						return $ar_date[2] . "/" . $ar_date[1] . "/" .$ar_date[0];
					}
					
				}
			} 
			return date("d/m/Y", strtotime($this->today));
				
		}
		
		function get_date_rage() {
			if(isset($this->params["start_date_start"]) && isset($this->params["start_date_end"])) {
				$start_date = strtotime($this->params["start_date_start"]);
				$end_date = strtotime($this->params["start_date_end"]);
				
				$datediff = $end_date - $start_date;
				return floor($datediff/(60*60*24));
			} else {
				return "7";
			}
		}*/
		
		/*
			Product type text
			Showing X to Y of Z <??>	
		*/
		
		function product_type_result_text() {
			$product_type = empty($this->product_type) ? 0 : $this->product_type;
			
			if($this->total_tour_count > 1) {
				return $this->product_type_text_plural[$product_type];
			} else {
				return $this->product_type_text[$product_type];
			}
		}
		
		/*
			Get pretty date range text
		*/
		
		function get_date_text() {

			$return = "";
			
			if(isset($this->params["start_date_start"]) && isset($this->params["start_date_end"])) {
				$start_date = strtotime($this->params["start_date_start"]);
				$end_date = strtotime($this->params["start_date_end"]);
		
				if($end_date - $start_date > 0) {
					$return .= "Between <span class='no-break'>" . date("l jS", $start_date);
					
					if(date("M", $start_date) != date("M", $end_date) || date("Y", $start_date) != date("Y", $end_date)) {
						$return .= " " . date(" F", $start_date);
					}
					
					if(date("Y", $start_date) != date("Y", $end_date)) {
						$return .= " " . date(" Y", $start_date);
					}
					
					$return .= "</span> and";
						
				}
				
				$return .= " <span class='no-break'>" . date("l jS F Y", $end_date) . "</span>";
				
			}
			
			return $return;
			
		}
		

	}
?>
