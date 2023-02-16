<?php
namespace GrayLineTourCMSControllers;

defined('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE') or die("Access Denied.");

use Tours as Tours;
use TourCMS\Utils\TourCMS as TourCMS;

class TourSearchController extends MainController
{

    public $search_params;
    public $search_results;
    public $partials;
    //public $m;
    public $data_layer;
    public $country_name;

    protected $cacheBust;
    protected $travel_guid_url;
    protected $breadcrumbs;

    public function on_start()
    {  
        global $post;
        
        $this->breadcrumbs = array();
        $country_json = get_option('countries');
        $gl_country_list = (array)json_decode($country_json);

        if(isset($_GET['q'])&& $_GET['q']!='') {
            
            $exploded = explode(", ", $_GET['q']);

            if (count($exploded) > 1) {
                
                $country = strtoupper($exploded[count($exploded) - 1]);


                if (in_array($country, $gl_country_list)) {

                    unset($exploded[count($exploded) - 1]);

                    $location = implode(", ", $exploded);

                    $_GET['country_name'] = $country;
                    $_GET['location'] = $location;

                    $this->travel_guid_url = $location;

                    $this->breadcrumbs['country'] = $country;
                    $this->breadcrumbs['location'] = $location;

                    unset($_GET["q"]);

                    
                } else {
                    $this->travel_guid_url = $_GET['q'];
                    $this->breadcrumbs['search_keyword'] = $location;
                }

            } elseif (in_array(strtoupper($exploded[0]), $gl_country_list)) {
                $_GET['country_name'] = $exploded[0];
                $this->breadcrumbs['country'] = $exploded[0];
                $this->travel_guid_url = $exploded[0];
                unset($_GET["q"]);

                
            } else {
                $_GET['keywords'] = $_GET["q"];
                $this->breadcrumbs['search_keyword'] = $_GET["q"];
                $this->travel_guid_url = $_GET["q"];

                unset($_GET["q"]);
            } 
        }
        
        include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");
        $this->cacheBust = $cacheBust;

        $this->partials = $partials;
        //$this->m = $m;

        // Hack!
        if (isset($_GET["q"])) {
            $_GET["q"] = str_replace("--", "{spacehyphen}", $_GET["q"]);
            $_GET["q"] = str_replace("-", " ", $_GET["q"]);
            $_GET["q"] = str_replace("{spacehyphen}", " -", $_GET["q"]);
        } 
        // End Hack!


        // If we're reloading then load query results from cache
        if (!empty($_GET['reload'])) {

            $search_params = $_SESSION['search_params'];

            // If there's no location in cache, check the querystring
            if (!isset($search_params["location"]) && !isset($search_params["country_name"]) && !isset($search_params["search_term"])) {
                if (isset($_GET['from_tour'])) {
                    $url = home_url("?q=".$_GET["from_tour"]);
                    header("Location: $url");
                }
            } else {
                $search_params["reloaded"] = true;
            }

            // Otherwise process querystring
        } else {
            $search_params = array();

            /*$qs_append = "";
            if(!empty($tour_category) && $tour_category!="") {
                if($tour_category == "special-offers" || $tour_category == "special offers") {
                        $special_offer = 1;
                } else {
                    $cats = str_replace( " ", "+", trim($tour_category));
                    $cats_qs = "{$cats}&";
                    $qs_append .= $cats_qs;
                }
            }*/
            // Process querystring

            $channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0;


            if (isset($_GET['geocode']) ) {
                $coords = explode(",", $_GET['geocode']);
                $lat = $coords[0];
                $lng = $coords[1];

                $search_params['lat'] = $lat;
                $search_params['long'] = $lng;
                // Distance in km
                $search_params['geo_distance'] = 50;
            }


            isset($_GET['q']) ? $search_params["location"] = $_GET['q'] : null;
            isset($_GET['search_term']) ? $search_params["search_term"] = $_GET['search_term'] : null;

            isset($_GET['reloaded']) ? $search_params["reloaded"] = (int)$_GET["reloaded"] : null;

            isset($_GET['country_name']) ? $search_params["country_name"] = $_GET['country_name'] : null;
            (!empty($this->country_name) && $this->country_name != "") ? $search_params["country_name"] = $this->country_name : null;
            isset($_GET['location']) ? $search_params["location"] = $_GET['location'] : null;
            isset($_GET['filter']) ? $search_params["filter"] = $_GET['filter'] : null;


            isset($_GET['type']) ? $search_params["type"] = $_GET['type'] : null;

            isset($_GET['per_page']) ? $search_params["per_page"] = (int)$_GET['per_page'] : $search_params["per_page"] = 40; //It was 10
            isset($_GET['pageno']) ? $search_params["page"] = (int)$_GET['pageno'] : $search_params["page"] = 1;

            !empty($_GET['keywords']) ? $search_params["keywords"] = $_GET['keywords'] : null;

            // need to check
            (isset($_GET['addkey']) && $_GET['addkey']!="") ? $search_params["addkey"] = $_GET['addkey'] : null;
            unset($_GET['addkey']);

            isset($_GET['start_date_start']) ? $search_params["start_date_start"] = $_GET['start_date_start'] : null;

            isset($_GET['start_date_end']) ? $search_params["start_date_end"] = $_GET['start_date_end'] : null;

            // Store the search params in session
    
            $_SESSION['search_params'] = $search_params;

        }

        // Search results banner image
        /*$search_headers_dir = "/themes/grayline_global/images/searchheaders/";
        $this->partials["searchbanner"] = $search_headers_dir."default.jpg";

        if (isset($search_params["location"])) {
            $clean_location = strtolower(str_replace("/", "", $search_params["location"]));

            if (file_exists($_SERVER["DOCUMENT_ROOT"].$search_headers_dir.$clean_location.".jpg")) {
                $this->partials["searchbanner"] = $search_headers_dir.$clean_location.".jpg";
            }
        } elseif (isset($search_params["country_name"])) {
            $clean_country = str_replace(array("-", "/"), array(" ", ""), $search_params["country_name"]);
            if (file_exists($_SERVER["DOCUMENT_ROOT"].$search_headers_dir.$clean_country.".jpg")) {
                $this->partials["searchbanner"] = $search_headers_dir.$clean_country.".jpg";
            }
        }*/

        // Set up Tour results
        include_once(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'libraries/3rdparty/tourcms/views/tours.php');

        $tourcms =  new TourCMS(get_option('grayline_tourcms_wp_marketplace'), get_option('grayline_tourcms_wp_apikey'), 'simplexml', 35);

        // If we're adding a keyword, do a redirect so it's in the URL
        if (!empty($search_params["addkey"])) {
            $keywords = !empty($search_params["keywords"]) ? explode("|", $search_params["keywords"]) : array();

            if (count($keywords) < 3) {
                $keywords[] = $search_params["addkey"];

                for ($i = 0; $i < count($keywords); $i++) {
                    $keywords[$i] = strip_tags($keywords[$i]);

                }

                $search_params["keywords"] = implode("|", $keywords);
                unset($search_params["addkey"]);

                $page_base = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
                header("Location: $page_base?".http_build_query($search_params));
                exit();
            }
        }
        $this->search_params = $search_params;

        // Hack for Korea, republic of
        if(isset($search_params['country_name']) && $search_params['country_name'] == 'korea--republic-of') {
            $search_params['country_name'] = null;
            $search_params['country'] = 'KR';
        }
        if (isset($search_params['location']) && $search_params['location'] == 'Korea  Republic of') {
            $search_params['location'] = null;
            $search_params['country'] = 'KR';
        }
        
        $this->search_results = new Tours($tourcms, $search_params);

        // Handle no results from destination search
        // carry out a special keyword search
        if ($this->search_results->total_tour_count == 0) {


            if (empty($search_params["country_name"])) {

                unset($search_params["reloaded"]);

                if (count($search_params) <= 4 && !empty($search_params["location"])) {

                    $search_params["search_term"] = $search_params["location"];

                    unset($search_params["location"]);


                    $this->search_results = new Tours($tourcms, $search_params);


                    // Handle no results from special keyword search
                    // Redirect to destinations page
                    if ($this->search_results->total_tour_count == 0) { 
                        $destinations_url = home_url("/destinations/?not_found=true&q=".$search_params["search_term"]); 
                        header("Location: $destinations_url");
                    }
                } 
            }
        } else {
            //Need to check, don't be remove

            include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'libraries/3rdparty/tourcms/tag_manager_bridge.php');

            $offset = ((int)$this->search_results->page - 1) * (int)$this->search_results->per_page;

            $t = generate_tag_manager_impressions(
                $this->search_results->tours,
                $this->search_results->pretty_location.' '.$this->search_results->product_type_result_text(),
                $offset
            );
            $this->search_results->script = '<script>var dataLayer=dataLayer||[];</script>';
            $this->search_results->script .= $t;
        }

        // Fetch travel guid url
        if(!empty($this->travel_guid_url)) {
            $this->search_results->travel_guid_detail = $this->travel_guid_detail($this->travel_guid_url);
            
        }

        // Search page breadcrumbs

        //if(count($this->breadcrumbs)) {
            $this->search_results->breadcrumb = search_page_breadcrumbs($this->breadcrumbs);
            
        //}

        return $this->search_results;
    }

    public function travel_guid_detail($keyword) {

        $parent_page_attraction_id = get_field('parent_page_attraction', 'option');
        $parent_page_country_id = get_field('parent_page_country', 'option');
        $parent_page_city_id = get_field('parent_page_city', 'option');

        if(empty($keyword)) {
            return;
        }

        global $wpdb;

        /*$query = $wpdb->prepare("Select post_id, meta_value FROM wp_postmeta WHERE meta_key LIKE %s AND meta_value = %s order by CASE post_id 
            WHEN %s THEN 1
            WHEN %s THEN 2
            WHEN %s THEN 3",
            array(
                '%search_travel_tags', 
                $keyword,
                $parent_page_attraction_id,
                $parent_page_city_id,
                $parent_page_country_id
            )
        );*/
/*ORDER BY CASE when post_id=%s then 1 else CASE when post_id=%s then 2 else CASE when post_id=%s then 3 else 0 end end end*/

        $query = $wpdb->prepare("SELECT post_id, meta_value as name FROM wp_postmeta 
                WHERE meta_key LIKE %s AND meta_value = %s",
            array(
                '%search_travel_tags', 
                $keyword
            )
        );

        $result = $wpdb->get_row($query, ARRAY_A);
        return $result;
    }

    public function get_tour_tag($tour_tags) {
        
        $default_tags = array('Hop on hop off', 'City card', 'Bike Tour', 'Boat Tour', 'Walking Tour', 'Food Tour (Food)', 'Museums', 'Theme Parks', 'Day trip', 'Multiday trip', 'Entrance Tickets', 'Classes');
        
        if(count($tour_tags) > 0) {
            foreach($default_tags as $default_tag) {
                if(in_array(str_replace(" ", "-", strtolower($default_tag)), $tour_tags)) {
                    return $default_tag;
                }
            }
        }
    }
    
    /*public function view()
    {
        $search_params = $this->search_params;

        // Page title, keywords etc
        $pageTitle = "";
        $pageDescription = "";

        if ($this->search_results->total_tour_count > 0) {*/

            // Generate all the bits and pieces we need

            /*if (!empty($search_params["location"])) {
                $location_text = ucwords($search_params["location"]);
            } elseif (!empty($search_params["country_name"])) {
                $location_text = ucwords($search_params["country_name"]);
            }*/

            // Product type
            //$product_type = !empty($search_params["product_type"]) ? $search_params["product_type"] : 0;

            /*$product_type = !empty($this->search_results->product_type) ? $this->search_results->product_type : 0;*/
            // Could also be provided as the text-based version
            /*
            if(isset($search_params["type"]) && $product_type == 0) {
                print "Yup";
                $url_product_types = $this->search_results->url_product_types;
                if($temp = array_search($search_params["type"], $url_product_types)) {
                    $product_type = $temp;
                }
            }

            */
            /*$keywords = isset($search_params["keywords"]) ? explode("|", $search_params["keywords"]) : array();

            $keyword_text = (count($keywords) > 0) ? implode(", ", $keywords) : "";

            $filter = !empty($search_params["filter"]) ? $search_params["filter"] : "Best sellers";

            $special_offer_text = $filter == "On sale" ? "Special Deals & Discounts" : "Book";

            // Page title


            switch ($product_type) {
                case "2":

                    $pageTitle = "$special_offer_text $location_text Transfers - $location_text Transfers - Gray Line";

                    $pageDescription = "$location_text Transfers! $special_offer_text Buy direct from the world's most trusted sightseeing brand, Gray Line. $location_text transfers, $location_text day trips, and much more!";

                    break;
                case "4":

                    $pageTitle = "$special_offer_text $location_text Tours, Activities & Things to do in $location_text - $keyword_text Tours and Activities - Gray Line";

                    $pageDescription = "Things to do in $location_text! $special_offer_text Buy direct from the world's most trusted sightseeing brand, Gray Line. $location_text tours, $location_text sightseeing, activities in $location_text, $location_text day tours, $location_text attractions, $location_text bus tours, $location_text day trips, and much more!";

                    break;
                case "3":

                    $pageTitle = "$special_offer_text $location_text Multi-day Tours departing from $location_text - $keyword_text Overnight Tours - Gray Line";

                    $pageDescription = "Things to do in $location_text! $special_offer_text Buy direct from the world's most trusted sightseeing brand, Gray Line. $location_text tours, $location_text sightseeing, activities in $location_text, $location_text day tours, $location_text attractions, $location_text bus tours, $location_text day trips, and much more!";

                    break;
                default:

                    $pageTitle = "$special_offer_text $location_text Tours & Things to do in $location_text - $keyword_text View All Tours - Gray Line";
                    $pageDescription = "Things to do in $location_text! $special_offer_text Buy direct from the world's most trusted sightseeing brand, Gray Line. $location_text tours, $location_text sightseeing, activities in $location_text, $location_text day tours, $location_text attractions, $location_text transfers, $location_text bus tours, $location_text day trips, and much more!";
            }


        } else {*/
            // Page title
         /*   $pageTitle = "No products found";
        }
        $this->set('pageTitle', $pageTitle);
        $this->set('pageDescription', $pageDescription);*/

        // add scripts to page
        /*$v = View::getInstance();
        $v->addFooterItem(
            '<script defer src="/themes/grayline_global/js/build/tour-list/min/build.js?v='.$this->cacheBust.'"></script>'
        );

    }*/

    /*public function getHtml()
    {*/


        /*
            Render the Mustache template
        */


        // Load the autocomplete partial
        /* BUG: search_autocomplete.php file does not exist
        
        */

        /*$desktop = true;

        if (defined('MOBILE_THEME_IS_ACTIVE')) {
            if (MOBILE_THEME_IS_ACTIVE) {
                $desktop = false;
            }
        }*/

        /*Loader::library('3rdparty/Mobile_Detect');
        $md = new MobileDetect();
        $deviceType = 'desktop';
        if ($md->isTablet()) {
            $deviceType = 'tablet';
            $desktop = false;
        } else {
            if ($md->isMobile()) {
                $deviceType = 'mobile';
                $desktop = false;
            }
        }*/


        // Destination page link
        /*if (!empty($this->search_results->location) && !empty($this->search_results->country)) {


            $temp_pretty_location = str_replace(" _and_ ", "/", $this->search_results->location);
            $temp_pretty_location = str_replace("/", " / ", $temp_pretty_location);
            $temp_pretty_location = ucwords($temp_pretty_location);
            $temp_pretty_location = str_replace(" / ", "/", $temp_pretty_location);
            if (strtolower($temp_pretty_location) == "washington dc") {
                $temp_pretty_location = "Washington DC";
            }
            $this->search_results->temp_pretty_location = $temp_pretty_location;
            Loader::model('page_list');
            $pl = new PageList();*/

            /*
            Filters files by keywords, which searches
            - Name, Description, Indexed Content against MySQL full text functions
            - Any collection attributes marked as being included in the search index
            */
            /*$pl->filterByKeywords($this->search_results->location);
            $pl->filterByParentID('469');*/
//			error_reporting(E_ALL);
//			ini_set('display_errors', '1');
            /*$pages = $pl->getPage();*/
//			print "<pre>";
//			print_r($pages);
//			print "</pre>";
            /*foreach ($pages as $page) {

                //print_r($page);

                $page_destination = strtoupper($page->getCollectionAttributeValue('destination'));
                $page_country = strtoupper($page->getCollectionAttributeValue('country_code'));


                $search_destination = strtoupper($temp_pretty_location);
                $search_country = strtoupper($this->search_results->country);

                if ($page_destination == $search_destination && $page_country == $search_country) {
                    $this->search_results->destination_page_link = DIR_REL.$page->cPath;
                    //echo($this->search_results->destination_page_link);
                }
            }

        }


        if ($desktop) {*/
            // Load the single Tour partial
            /*$this->partials["tours-single"] = file_get_contents(
                'libraries/3rdparty/tourcms/templates/tours-single.mustache'
            );

            // Process the main Tours template
            return $this->m->render(
                file_get_contents('libraries/3rdparty/tourcms/templates/tours.mustache'),
                $this->search_results,
                $this->partials
            );
        } else {
            // Load the single Tour partial
            $this->partials["mobile-tours-single"] = file_get_contents(
                'libraries/3rdparty/tourcms/templates/mobile-tours-single.mustache'
            );

            // Process the main Tours template
            return $this->m->render(
                file_get_contents('libraries/3rdparty/tourcms/templates/mobile-tours.mustache'),
                $this->search_results,
                $this->partials
            );
        }


    }*/

} 
