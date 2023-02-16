<?php
/*
Plugin Name: Gray Line Tour CMS Plugin
Plugin URI: https://www.tourcms.com/
Description: Integrate WordPress with TourCMS to aid creating specialist Tour, Activity and Accommodation Operator websites.
Version: 1.0
Author: Tour CMS
Author URI: https://www.tourcms.com/
License: later
Text Domain: gray-line-licensee-wordpress-tourcms-plugin
Domain Path: /languages/
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

define('GRAYLINE_WORDPRESS_TOURCMS_PLUGIN_EXECUTE', TRUE);

const CART_COOKIE_EXPIRE = 604800;

require 'vendor/autoload.php';
// use TourCMS\Utils\TourCMS as TourCMS;

//require_once 'libraries/exceptions.php';
require_once 'senshi/libraries/exceptions.php';

require_once 'controllers/controller.php';
use GrayLineTourCMSControllers as GrayLineTourCMSControllers;

$tourcms_plugin_namespace = 'gray-line-licensee-wordpress-tourcms-plugin/v1';

function create_grayline_plugin_database_table() { 
	include('installer/index.php');
}
register_activation_hook( __FILE__, 'create_grayline_plugin_database_table' );

function remove_grayline_plugin_database_table() {
     include('uninstaller/index.php');
}    
register_uninstall_hook( __FILE__, 'remove_grayline_plugin_database_table' );

require_once 'config.php';

// Create custom post types and taxonomies
add_action( 'init', 'grayline_tourcms_init' );

// Check/refresh cache
add_action('template_redirect', 'grayline_tourcms_wp_refresh_cache');

// Currency switch
add_action('template_redirect', 'grayline_tourcms_wp_currency_switch');

add_action('template_redirect', 'add_to_cart');
add_action('template_redirect', 'process_checkout_key');

function process_checkout_key() {
	global $wp;

	if($wp->request == 'checkout_key/process') {
		$checkoutKeyController = new GrayLineTourCMSControllers\CheckoutKeyController();
		$checkoutKeyController->process();
	} 
}

// Register settings
add_action('admin_init', 'grayline_tourcms_wp_register');

// Add a config menu to the Admin area
add_action('admin_menu', 'grayline_tourcms_wp_adminmenu');

// Enqueue additional admin scripts
add_action('admin_enqueue_scripts', 'wp_grayline_tourcms_theme_admin_scripts');

function wp_grayline_tourcms_theme_admin_scripts($hook) {

	// wp_enqueue_style('jquery-ui-css', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');

	// wp_enqueue_script('jquery-ui-datepicker', '', array('jquery-ui-core') , 0.1, TRUE);

	wp_enqueue_script('wp_grayline_plugin_sync_tourjs', plugin_dir_url( __FILE__ ) . '/assets/js/sync-tour.js', array(), '1.0.0', true);

	wp_enqueue_script('wp_grayline_plugin_graylinejs', plugin_dir_url( __FILE__ ) . '/assets/js/grayline.js', array(), '1.0.0', true);

	if( $hook != 'widgets.php' ) 
		return;

	wp_enqueue_media();
	wp_enqueue_script('special_offer_widget_script', plugin_dir_url( __FILE__ ) . '/assets/js/special-offer-widget.js', array(), '1.0.0', true);
	wp_enqueue_script('home_page_banner_widget_script', plugin_dir_url( __FILE__ ) . '/assets/js/home-page-banner-widget.js', array(), '1.0.0', true);
}

function start_session() {
	if(!session_id()) {
		session_start();
	}
}

// Admin menu page details
function grayline_tourcms_wp_adminmenu() {
	add_submenu_page('options-general.php', __('Gray Line Licensee TourCMS Plugin', 'gray-line-licensee-wordpress-tourcms-plugin'), __('Gray Line Licensee TourCMS Plugin', 'gray-line-licensee-wordpress-tourcms-plugin'), 'administrator', 'grayline_tourcms_wp', 'grayline_tourcms_wp_optionspage');
}

// Save post
add_action( 'save_post', 'grayline_tourcms_wp_save_tour', 1, 2);

add_action('grayline_tourcms_wp_related_tours', 'grayline_tourcms_wp_related_tours', 10, 1);

// Add a "Settings" link to the menu
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'grayline_tourcms_wp_plugin_settings_link' );

// Add settings link on plugin page
function grayline_tourcms_wp_plugin_settings_link($links) { 
	$settings_link = '<a href="options-general.php?page=grayline_tourcms_wp">Settings</a>'; 
	array_push($links, $settings_link); 
	return $links; 
}

function grayline_tourcms_init() {
	if ( !is_admin() )
		wp_enqueue_script('jquery');
	
	register_post_type( 'tour',
		array(
			'label' => __('Tours', 'gray-line-licensee-wordpress-tourcms-plugin'),
			'singular_label' => __('Tour', 'gray-line-licensee-wordpress-tourcms-plugin'),
			'labels' => array("add_new_item" => __("New Tour", 'gray-line-licensee-wordpress-tourcms-plugin'), "edit_item" => __("Edit Tour", 'gray-line-licensee-wordpress-tourcms-plugin'), "view_item" => __("View Tour", 'gray-line-licensee-wordpress-tourcms-plugin'), "search_items" => __("Search Tours", 'gray-line-licensee-wordpress-tourcms-plugin'), "not_found" => __("No Tours found", 'gray-line-licensee-wordpress-tourcms-plugin'), "not_found_in_trash" => __("No Tours found in Trash", 'gray-line-licensee-wordpress-tourcms-plugin')),
			'rewrite' => array("slug" => "tours"),
			'supports' => array('page-attributes', 'title', 'editor', 'author', 'excerpt', 'thumbnail'),
			'menu_position' => 20,
			'show_in_nav_menus' => true,
			'public' => true,
			'has_archive' => true
		)
	);

	/*register_post_type( 'city',
		array(
			'label' => __('Cities', 'gray-line-licensee-wordpress-tourcms-plugin'),
			'singular_label' => __('City', 'gray-line-licensee-wordpress-tourcms-plugin'),
			'labels' => array("add_new_item" => __("New City", 'gray-line-licensee-wordpress-tourcms-plugin'), "edit_item" => __("Edit City", 'gray-line-licensee-wordpress-tourcms-plugin'), "view_item" => __("View City", 'gray-line-licensee-wordpress-tourcms-plugin'), "search_items" => __("Search Cities", 'gray-line-licensee-wordpress-tourcms-plugin'), "not_found" => __("No Cities found", 'gray-line-licensee-wordpress-tourcms-plugin'), "not_found_in_trash" => __("No Cities found in Trash", 'gray-line-licensee-wordpress-tourcms-plugin')),
			'rewrite' => array("slug" => "cities"),
			'supports' => array('page-attributes', 'title', 'editor', 'author', 'excerpt', 'thumbnail', 'custom-fields'),
			'menu_position' => 20,
			'show_in_nav_menus' => true,
			'public' => true,
			'has_archive' => true,
			'hierarchical' => true

		)
	);*/
	start_session();
}

// Whitelist options
function grayline_tourcms_wp_register() {
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_marketplace', 'intval');
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_channel', 'intval'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_apikey');		
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_update_frequency'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_contact_no'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_email'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_licensee'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_sublogo'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_fblink'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_twitterlink'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_pinterestlink'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_instagramlink'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_youtubelink'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_search_keyword'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_allow_tour_activities');
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_allow_multi_day_trip'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_allow_transfer'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_allow_accommodation'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_header_script'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_footer_script'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_fb_pixel_enabled'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_fb_pixel_id'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_bing_ads_enabled'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_google_ecom'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_tracking_script'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_use_cdn'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_custom_order');
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_account_id'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_enquiry_key'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_enq_enabled');  
	
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_mail_send_method'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_host'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_auth'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_port'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_username'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_password'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_secure'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_from'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_smtp_from_name'); 
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_spreedly_3ds2_disabled');
	register_setting('grayline_tourcms_wp_settings', 'grayline_tourcms_wp_feefo_show');
	// Add custom meta box
	if ( function_exists( 'add_meta_box' ) ) {
		add_meta_box( 'grayline_tourcms_wp', 'TourCMS', 'grayline_tourcms_edit' , 'tour', 'advanced', 'high' );
	}
}

// Generate HTML for the menu page
function grayline_tourcms_wp_optionspage() {
	$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br /></div>
		<h2><?php _e('Grayline Licensee TourCMS Plugin Settings', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h2>
		<form method="post" action="options.php" enctype="multipart/form-data">
			<?php settings_fields('grayline_tourcms_wp_settings'); ?>

			<h3><?php _e('TourCMS API Status', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p><?php _ex('Number of hits of API left', 'Remaining Hits:', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
			<?php $result = $grayLineTourCMSMainControllers->call_tourcms_api("api_rate_limit_status");
			echo $result->remaining_hits ?></p>
			<p><?php echo _e('Hourly Limit:', 'gray-line-licensee-wordpress-tourcms-plugin') ?> <?php echo $result->hourly_limit ?></p>
			<p><?php echo _e('API Status:', 'gray-line-licensee-wordpress-tourcms-plugin')?> <?php echo $result->error ?></p>

			<h3><?php _e('API Settings', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<p><?php _e('You can find your settings by logging into TourCMS then heading to <strong>Configuration &amp; Setup</strong> &gt; <strong>API</strong> &gt; <strong>XML API</strong>.', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_apikey"><?php _e('Marketplace ID', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="text" name="grayline_tourcms_wp_marketplace" value="<?php echo get_option('grayline_tourcms_wp_marketplace'); ?>" autocomplete="false" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_channel"> <?php _e('Channel ID', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="text" name="grayline_tourcms_wp_channel" size="6" value="<?php echo get_option('grayline_tourcms_wp_channel'); ?>" autocomplete="false" /> <!--span class="description">Set this to 0 if you are a Marketplace Partner</span-->
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_apikey"><?php _e('API Key', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="password" name="grayline_tourcms_wp_apikey" value="<?php echo get_option('grayline_tourcms_wp_apikey'); ?>"  autocomplete="false" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_account_id"><?php _e('TourCMS Account ID', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="text" name="grayline_tourcms_wp_account_id" value="<?php echo get_option('grayline_tourcms_wp_account_id'); ?>"  autocomplete="false" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_enquiry_key"><?php _e('TourCMS Enquiry Key', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="text" name="grayline_tourcms_wp_enquiry_key" value="<?php echo get_option('grayline_tourcms_wp_enquiry_key'); ?>"  autocomplete="false" />
					</td>
				</tr>
				<?php if(is_admin()){ ?>
				<tr valign="top">
					<th scope="row">
						<?php _e('Enable Tour Enquiries', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_enq_enabled')=="") ? $grayline_tourcms_wp_enq_enabled = 0 : $grayline_tourcms_wp_enq_enabled = intval(get_option('grayline_tourcms_wp_enq_enabled')); ?>

						<input type="checkbox" name="grayline_tourcms_wp_enq_enabled" value="1" <?php 
							$grayline_tourcms_wp_enq_enabled==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<?php } ?>
			</table>

			<h3>
				<?php _e('Custom Fields', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
			</h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php _e('Contact No', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_contact_no" value="<?php echo get_option('grayline_tourcms_wp_contact_no'); ?>" placeholder='' /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Email Address', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_email" value="<?php echo get_option('grayline_tourcms_wp_email'); ?>" placeholder='' /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Licensee', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_licensee" value="<?php echo get_option('grayline_tourcms_wp_licensee'); ?>" placeholder=''  /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Sub Logo Icon', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="file" size="25" name="grayline_tourcms_wp_sublogo" value="<?php echo get_option('grayline_tourcms_wp_sublogo'); ?>" placeholder=''  /> 
						<?php 
						if(get_option('grayline_tourcms_wp_sublogo') != "" && !empty(get_option('grayline_tourcms_wp_sublogo'))) { 
							$sublogo = get_option('grayline_tourcms_wp_sublogo');
							$sublogo = json_decode($sublogo);
						?>
							<img src="<?php echo $sublogo->url; ?>" style="width: 10%;" id="img_sublogo">
							<input type="hidden" id="upload_sublogo_url" value="<?php echo get_site_url(null, "/wp-json/gray-line-licensee-wordpress-tourcms-plugin/v1/sublogo/"); ?>">
							<a href="#" id="remove_sublogo">
								<?php _e('Remove', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>
						<?php } ?>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row">
						<?php _e('Search Keyword', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<textarea name="grayline_tourcms_wp_search_keyword" rows="4" cols="80" placeholder='e.g. "XX Tours, YY Tours"'><?php echo get_option('grayline_tourcms_wp_search_keyword'); ?></textarea>
					</td>
				</tr>

			</table>
			<h3><?php _e('Social Media Section Links', 'gray-line-licensee-wordpress-tourcms-plugin') ?> </h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php _e('Facebook Link', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_fblink" value="<?php echo get_option('grayline_tourcms_wp_fblink'); ?>" placeholder=''  /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Twitter Link', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_twitterlink" value="<?php echo get_option('grayline_tourcms_wp_twitterlink'); ?>" placeholder=''  /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Pinterest Link', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_pinterestlink" value="<?php echo get_option('grayline_tourcms_wp_pinterestlink'); ?>" placeholder=''  /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Instagram Link', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_instagramlink" value="<?php echo get_option('grayline_tourcms_wp_instagramlink'); ?>" placeholder=''  /> 
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('You Tube Link', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<input type="text" size="25" name="grayline_tourcms_wp_youtubelink" value="<?php echo get_option('grayline_tourcms_wp_youtubelink'); ?>" placeholder=''  /> 
					</td>
				</tr>
			</table>
			<h3><?php _e('Custom Scripts', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php _e('Header Script', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<textarea name="grayline_tourcms_wp_header_script" rows="6" cols="80"><?php echo get_option('grayline_tourcms_wp_header_script'); ?></textarea>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Footer Script', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<textarea name="grayline_tourcms_wp_footer_script" rows="6" cols="80"><?php echo get_option('grayline_tourcms_wp_footer_script'); ?></textarea>
					</td>
				</tr>
			</table>

			<h3><?php _e('Category Search Settings', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<?php _e('Tours & Activities', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_allow_tour_activities')=="") ? $grayline_tourcms_wp_allow_tour_activities = 0 : $grayline_tourcms_wp_allow_tour_activities = intval(get_option('grayline_tourcms_wp_allow_tour_activities')); ?>

						<input type="checkbox" name="grayline_tourcms_wp_allow_tour_activities" value="1" <?php 
							$grayline_tourcms_wp_allow_tour_activities==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Multi-Day Trips', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_allow_multi_day_trip')=="") ? $grayline_tourcms_wp_allow_multi_day_trip = 0 : $grayline_tourcms_wp_allow_multi_day_trip = intval(get_option('grayline_tourcms_wp_allow_multi_day_trip')); ?>
						
						<input type="checkbox" name="grayline_tourcms_wp_allow_multi_day_trip" value="1" <?php 
							$grayline_tourcms_wp_allow_multi_day_trip==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Transfer', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_allow_transfer')=="") ? $grayline_tourcms_wp_allow_transfer = 0 : $grayline_tourcms_wp_allow_transfer = intval(get_option('grayline_tourcms_wp_allow_transfer')); ?>
						
						<input type="checkbox" name="grayline_tourcms_wp_allow_transfer" value="1" <?php 
							$grayline_tourcms_wp_allow_transfer==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php _e('Accommodation', 'gray-line-licensee-wordpress-tourcms-plugin') ?>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_allow_accommodation')=="") ? $grayline_tourcms_wp_allow_accommodation = 0 : $grayline_tourcms_wp_allow_accommodation = intval(get_option('grayline_tourcms_wp_allow_accommodation')); ?>
						
						<input type="checkbox" name="grayline_tourcms_wp_allow_accommodation" value="1" <?php 
							$grayline_tourcms_wp_allow_accommodation==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>

			<h3><?php _e('Payment Gateway', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p><?php _e('Please review your payment gateway, and enable 3D Secure Payments', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></p>
			<?php 
			$result = apply_filters('grayline_tourcms_wp_channel_details', '');
			$gateway_error = false;
			$gateway_type = "";
			$gateway_name = "";
			if(!empty($result) && !empty($result->channel->payment_gateway->gateway_type)) {
				$gateway_type 	= (string)$result->channel->payment_gateway->gateway_type;
			}

			if(!empty($result) && !empty($result->channel->payment_gateway->name)) {
				$gateway_name	= (string)$result->channel->payment_gateway->name;;
			}
			if ($gateway_type !== 'SPRE' && $gateway_type !== 'TMTP') {
	            $gateway_error = "Error! Payment gateway defined ($gateway_type) must be either SPRE or TMTP (Spreedly or Trust My Travel Modal). Please login to TourCMS to resolve or <a style='color:white; text-decoration: underline' href='mailto:support@palisis.com'>contact Palisis support</a>. Your site may fail to process payments until this is fixed";
	        	}
			if ($gateway_error) { ?>
			<div class="message error"><?php echo $gateway_error ?></div>
			<?php } ?>

			<p><?php _e("3D Secure Payments - 2019 EU directive on secure customer authentication when making payments. Shifts fraud liability to bank rather than merchant.", "gray-line-licensee-wordpress-tourcms-plugin") ?>
			<b> <?php _e("IMPORTANT Note: if you enable 3DS payments, it must also be switched on on your payment gateway, e.g. Trust My Travel, Adyen etc", "gray-line-licensee-wordpress-tourcms-plugin"); ?></b>
			<a href="https://palisis.zendesk.com/hc/en-us/articles/360034882291" target="_blank"><?php _e('More info', 'gray-line-licensee-wordpress-tourcms-plugin') ?></a></p>
			<table class="entry-form">
			<tr>
			<td class="subheader">
			<label><?php _e('Payment gateway', 'gray-line-licensee-wordpress-tourcms-plugin')?></label>
			</td>
			<td valign=top>
			<?php echo $gateway_type ?><br>
			</td>
			</tr>

			<tr>
			<td class="subheader">
			<label><?php _e('Name', 'gray-line-licensee-wordpress-tourcms-plugin')?></label>
			</td>
			<td valign=top>
			<?php  echo $gateway_name ?>
			</td>
			</tr>



			<tr>
			<td class="subheader">
			<label><?php _e('Enable 3D Secure', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></label>

			</td>
			<td valign=top>
			<?php (get_option('grayline_tourcms_wp_spreedly_3ds2_disabled')=="") ? $grayline_tourcms_wp_spreedly_3ds2_disabled = 0 : $grayline_tourcms_wp_spreedly_3ds2_disabled = intval(get_option('grayline_tourcms_wp_spreedly_3ds2_disabled')); ?>

						<input type="checkbox" id="grayline_tourcms_wp_spreedly_3ds2_disabled" name="grayline_tourcms_wp_spreedly_3ds2_disabled" value="1" <?php 
							$grayline_tourcms_wp_spreedly_3ds2_disabled==1 ? print ' checked="checked"' : null;
						?> />
			</td>
			</tr>
			</table>

			<h3><?php _e('Custom Settings', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<p><strong><?php _e('Custom Tour List Order', 'gray-line-licensee-wordpress-tourcms-plugin') ?></strong></p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_custom_order"><?php _e('On', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_custom_order')=="") ? $grayline_tourcms_wp_custom_order = "" : $grayline_tourcms_wp_custom_order = get_option('grayline_tourcms_wp_custom_order'); ?>

						<input type="radio" name="grayline_tourcms_wp_custom_order" value="1" <?php 
							$grayline_tourcms_wp_custom_order=='1' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>

				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_custom_order"><?php _e('Off', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_custom_order')=="") ? $grayline_tourcms_wp_custom_order = "" : $grayline_tourcms_wp_custom_order = get_option('grayline_tourcms_wp_custom_order'); ?>

						<input type="radio" name="grayline_tourcms_wp_custom_order" value="0" <?php 
							$grayline_tourcms_wp_custom_order=='0' || $grayline_tourcms_wp_custom_order=='' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>

			<h3><?php _e('Ecommerce', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<p><?php _e('Google Ecommerce Tracking - choose either: Google data layer OR standard ecommerce tracking OR neither.', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			<p><?php _e('Data Abstraction Layer works in combination with Google Tag Manager, so you must have this set up -' , 'gray-line-licensee-wordpress-tourcms-plugin') ?><a href="https://developers.google.com/tag-platform/tag-manager/web" target="_blank"><?php _e('Google Guide', 'gray-line-licensee-wordpress-tourcms-plugin') ?>) ?></a></p>
			<p><?php _e('Standard Google Ecommerce - ', 'gray-line-licensee-wordpress-tourcms-plugin') ?><a href="https://developers.google.com/tag-platform/tag-manager/web" target="_blank"><?php _e('Google Guide', 'gray-line-licensee-wordpress-tourcms-plugin') ?></a></p>
			<p><?php _e('Please see this article on how to add tracking code to the rest of your site - ') ?><a href="https://www.zendesk.com/IN/help-center-closed/?utm_content=dojodesignstudio.zendesk.com&utm_source=helpcenter-closed&utm_medium=poweredbyzendesk" target="_blank"><?php _e('Senshi Guide', 'gray-line-licensee-wordpress-tourcms-plugin') ?></a></p>
			<p><strong><?php _e('Ecommerce Tracking', 'gray-line-licensee-wordpress-tourcms-plugin') ?></strong></p>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_google_ecom"><?php _e('Data Abstraction Layer', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_google_ecom')=="") ? $grayline_tourcms_wp_google_ecom = "" : $grayline_tourcms_wp_google_ecom = get_option('grayline_tourcms_wp_google_ecom'); ?>

						<input type="radio" name="grayline_tourcms_wp_google_ecom" value="data_layer" <?php 
							$grayline_tourcms_wp_google_ecom=='data_layer' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_google_ecom"><?php _e('Standard Ecommerce Tacking', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="radio" name="grayline_tourcms_wp_google_ecom" value="standard" <?php 
							$grayline_tourcms_wp_google_ecom=='standard' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_google_ecom"><?php _e('None', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="radio" name="grayline_tourcms_wp_google_ecom" value="" <?php 
							$grayline_tourcms_wp_google_ecom=='' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_tracking_script"><?php _e('Tracking Script', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<textarea name="grayline_tourcms_wp_tracking_script" rows="6" cols="80"><?php echo get_option('grayline_tourcms_wp_tracking_script'); ?></textarea>
					</td>
				</tr>
			</table>
			
			<h3><?php _e('Facebook Pixel', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<p><?php _e('Enable / Disable the Facebook Pixel Code (fires on all pages).', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			<p><?php _e('Get your FB Pixel ID - ', 'gray-line-licensee-wordpress-tourcms-plugin') ?><a href="https://support.palisis.com/hc/en-us/articles/4416926813457" target="_blank"><?php _e('Facebook Pixel Manual Integration Guide', 'gray-line-licensee-wordpress-tourcms-plugin') ?></a></p>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_fb_pixel_enabled"><?php _e('Facebook Pixel Tracking', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td> 
						<?php (get_option('grayline_tourcms_wp_fb_pixel_enabled')=="") ? $grayline_tourcms_wp_fb_pixel_enabled = 0 : $grayline_tourcms_wp_fb_pixel_enabled = intval(get_option('grayline_tourcms_wp_fb_pixel_enabled')); ?>

						<input type="checkbox" name="grayline_tourcms_wp_fb_pixel_enabled" value="1" <?php 
							$grayline_tourcms_wp_fb_pixel_enabled==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_fb_pixel_id"><?php _e('Facebook Pixel ID e.g. 635909084322496', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="text" name="grayline_tourcms_wp_fb_pixel_id" value="<?php echo get_option('grayline_tourcms_wp_fb_pixel_id'); ?>"  autocomplete="false" />
					</td>
				</tr>
			</table>

			<h3><?php _e('Bing Ads', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<p><?php _e('Enable / Disable Bing Ads UET Revenue Variable (fires on cart_complete page only)', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			<p><?php _e('Please add in your UET code to the page header, through Google Tag Manager or c5 interface.', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			<p><?php _e('Wiki article', 'gray-line-licensee-wordpress-tourcms-plugin') ?> <a href="https://support.palisis.com/hc/en-us/articles/4916130977553" target="_blank">https://support.palisis.com/hc/en-us/articles/4916130977553</a></p>
			<p><?php _e('Enabling this will fire a dynamic revenue price on the checkout complete page.', 'gray-line-licensee-wordpress-tourcms-plugin') ?></p>
			
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_bing_ads_enabled"><?php _e('Bing Ads UET Revenue', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_bing_ads_enabled')=="") ? $grayline_tourcms_wp_bing_ads_enabled = 0 : $grayline_tourcms_wp_bing_ads_enabled = intval(get_option('grayline_tourcms_wp_bing_ads_enabled')); ?>

						<input type="checkbox" id="grayline_tourcms_wp_bing_ads_enabled" name="grayline_tourcms_wp_bing_ads_enabled" value="1" <?php 
							$grayline_tourcms_wp_bing_ads_enabled==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_bing_ads_enabled"><?php _e('Use CDN for assets option', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_use_cdn')=="") ? $grayline_tourcms_wp_bing_ads_enabled = "Yes" : $grayline_tourcms_wp_bing_ads_enabled = get_option('grayline_tourcms_wp_use_cdn'); ?>
						<select name="grayline_tourcms_wp_use_cdn" id="grayline_tourcms_wp_bing_ads_enabled">
							<option value="Yes" <?php if($grayline_tourcms_wp_bing_ads_enabled == "Yes") echo "selected";?>>Yes</option>
							<option value="No" <?php if($grayline_tourcms_wp_bing_ads_enabled == "No") echo "selected";?>>No</option>
						</select>
					</td>
				</tr>
			</table>
			
			<h3><?php _e('Feefo Switch', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<table class="form-table">
				<tr valign="top">
					<td> 
						<?php (get_option('grayline_tourcms_wp_feefo_show')=="") ? $grayline_tourcms_wp_feefo_show = 0 : $grayline_tourcms_wp_feefo_show = intval(get_option('grayline_tourcms_wp_feefo_show')); ?>

						<input type="checkbox" name="grayline_tourcms_wp_feefo_show" value="1" <?php 
							$grayline_tourcms_wp_feefo_show==1 ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>
			
			<h3><?php _e('Send Mail Method', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_mail_send_method"><?php _e('Default Wordpress Mail Function', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<?php (get_option('grayline_tourcms_wp_mail_send_method')=="") ? $grayline_tourcms_wp_mail_send_method = "" : $grayline_tourcms_wp_mail_send_method = get_option('grayline_tourcms_wp_mail_send_method'); ?>

						<input type="radio" name="grayline_tourcms_wp_mail_send_method" value="PHP_MAIL" <?php 
							$grayline_tourcms_wp_mail_send_method=='PHP_MAIL' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="grayline_tourcms_wp_mail_send_method"><?php _e('External SMTP Server', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
					</th>
					<td>
						<input type="radio" name="grayline_tourcms_wp_mail_send_method" value="SMTP" <?php 
							$grayline_tourcms_wp_mail_send_method=='SMTP' ? print ' checked="checked"' : null;
						?> />
					</td>
				</tr>
			</table>
			<div id="smtp_setting_section" <?php if($grayline_tourcms_wp_mail_send_method != "SMTP") { ?>style="display:none;" <?php } ?>> 
				<h3><?php _e('SMTP Settings', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_host"><?php _e('Host', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_host" value="<?php echo get_option('grayline_tourcms_wp_smtp_host'); ?>"  autocomplete="false" />
						</td>
					<tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_auth"><?php _e('SMTP Auth', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<?php $grayline_tourcms_wp_smtp_auth = get_option('grayline_tourcms_wp_smtp_auth'); ?>
							<select name="grayline_tourcms_wp_smtp_auth" id="grayline_tourcms_wp_bing_ads_enabled">
								<option value="" <?php if($grayline_tourcms_wp_smtp_auth == "") echo "selected";?>>None</option>
								<option value="True" <?php if($grayline_tourcms_wp_smtp_auth == "True") echo "selected";?>>True</option>
								<option value="False" <?php if($grayline_tourcms_wp_smtp_auth == "False") echo "selected";?>>False</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_port"><?php _e('Port', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_port" value="<?php echo get_option('grayline_tourcms_wp_smtp_port'); ?>"  autocomplete="false" />
						</td>
					<tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_username"><?php _e('Username', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_username" value="<?php echo get_option('grayline_tourcms_wp_smtp_username'); ?>"  autocomplete="false" />
						</td>
					<tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_password"><?php _e('Password', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_password" value="<?php echo get_option('grayline_tourcms_wp_smtp_password'); ?>"  autocomplete="false" />
						</td>
					<tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_secure"><?php _e('SMTP Secure', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<?php $grayline_tourcms_wp_smtp_secure = get_option('grayline_tourcms_wp_smtp_secure'); ?>
							<select name="grayline_tourcms_wp_smtp_secure" id="grayline_tourcms_wp_bing_ads_enabled">
								<option value="" <?php if($grayline_tourcms_wp_smtp_secure == "") echo "selected";?>>None</option>
								<option value="ssl" <?php if($grayline_tourcms_wp_smtp_secure == "ssl") echo "selected";?>>SSL</option>
								<option value="tls" <?php if($grayline_tourcms_wp_smtp_secure == "tls") echo "selected";?>>TLS</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_from"><?php _e('From', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_from" value="<?php echo get_option('grayline_tourcms_wp_smtp_from'); ?>"  autocomplete="false" />
						</td>
					<tr>
					<tr valign="top">
						<th scope="row">
							<label for="grayline_tourcms_wp_smtp_from_name"><?php _e('FromName', 'gray-line-licensee-wordpress-tourcms-plugin') ?></label>
						</th>
						<td>
							<input type="text" name="grayline_tourcms_wp_smtp_from_name" value="<?php echo get_option('grayline_tourcms_wp_smtp_from_name'); ?>"  autocomplete="false" />
						</td>
					<tr>
				</table>
			</div>

			<p class="submit">
				<input class="button-primary" type="submit" value="Save Changes" name="Submit" />
			</p>
		</form>

		<h2><?php _e('Sync Tours', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h2>
		<!-- <form action="<?php echo esc_attr('admin-post.php'); ?>" method="post"> -->
			<p class="submit">
				<input type="hidden" id="sync_tour_url" value="<?php echo get_site_url(null, "/wp-json/gray-line-licensee-wordpress-tourcms-plugin/v1/sync_tour/"); ?>">
				<input class="button-primary" id="synch_tour" type="button" value="Sync Tours" name="Submit" />
			</p>
		<!-- </form> -->

		<h2><?php _e('Clear All Cached Data', 'gray-line-licensee-wordpress-tourcms-plugin') ?></h2>
		<p class="submit">
			<input type="hidden" id="clear_cache_url" value="<?php echo get_site_url(null, "/wp-json/gray-line-licensee-wordpress-tourcms-plugin/v1/clear_cache/"); ?>">
			<input class="button-primary" id="clear_cache" type="button" name="clear_cache" value="Clear Cache" />
		</p>
	</div>
	<?php
}

add_action('grayline_tourcms_wp_channel_details', 'channel_details');

// channel details
function channel_details() { 
	global $api_info; 

	$channel_id = get_option('grayline_tourcms_wp_channel');
	// Check if search results exist in cache
	if(empty($api_info)) { 
		// Get saved channel from transient
		$api_info = get_data_from_transient($channel_id); 
	} 

	if( !empty($api_info) ) { 
		return $api_info;
	} 

	$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;
	$results = $grayLineTourCMSMainControllers->call_tourcms_api("show_channel");

	set_data_in_transient($channel_id, $results);

	$api_info = get_data_from_transient($channel_id); 
	return $api_info;
}

// When the user is editing a Tour/Hotel we will display a box to let them select a TourCMS product to
// link to, if this Tour/Hotel has been edited previously we'll also show cached TourCMS data
function grayline_tourcms_edit() {
	global $post;
	$marketplace_account_id = get_option('grayline_tourcms_wp_marketplace');
	$channel_id = get_option('grayline_tourcms_wp_channel');
	$api_private_key = get_option('grayline_tourcms_wp_apikey');
	
	wp_nonce_field( 'grayline_tourcms_wp', 'grayline_tourcms_wp_wpnonce', false, true );
	
	if($marketplace_account_id===false || $channel_id===false || $api_private_key===false) 
		$configured = false;
	else
		$configured = true;
	
	// Output if allowed
	if ( $configured ) { 

		$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;

		$results = $grayLineTourCMSMainControllers->call_tourcms_api("list_tours");
		$curval = get_post_meta( $post->ID, 'grayline_tourcms_wp_tourid', true );
		?>	

		<div class="form-field form-required">
			<?php
				if($results->error!="OK") {
					print "<p>Unable to link this Tour/Hotel with a product in TourCMS at this time, the following error message was returned:</p>";
					print "<p>".$results->error."</p>";
					print '<p>You can find <a href="http://www.tourcms.com/support/api/mp/error_messages.php" target="_blank">explanations of these error messages</a>, view the <a href="http://www.tourcms.com/support/webdesign/wordpress/installation.php" target="_blank">plugin installation instructions</a> or <a href="http://www.tourcms.com/company/contact.php" target="_blank">contact us</a> if you need some help.</p>';
				} else {
				// Plain text field
				echo '<p>&nbsp;</p><label for="grayline_tourcms_wp_tourid">Tour</label>';
				?>
				<select name="grayline_tourcms_wp_tourid">
					<!--option value="0">Do not associate with a TourCMS Tour/Hotel</option-->
					<?php
					
						foreach($results->tour as $tour) {
							print '<option value="'.$tour->tour_id.'"';
							if($tour->tour_id==$curval)
								print ' selected="selected"';
							print '>'.$tour->tour_name.'</option>';
						}
					?>
				</select>
				<?php if($curval>0) : ?>
				<p><?php 
				(get_option('grayline_tourcms_wp_update_frequency')=="") ? $grayline_tourcms_wp_update_frequency = 14400 : $grayline_tourcms_wp_update_frequency = intval(get_option('grayline_tourcms_wp_update_frequency'));
				
				if($grayline_tourcms_wp_update_frequency>1) {
					$hours = $grayline_tourcms_wp_update_frequency / 3600;
					if($hours > 1)
						$hours = $hours." ".__('hours', 'gray-line-licensee-wordpress-tourcms-plugin'); 
					else
						$hours = __("hour", "gray-line-licensee-wordpress-tourcms-plugin");
					echo "The following data is refreshed from TourCMS each time you save this Tour/Hotel plus automatically every $hours.";
				} else {
					_e("The following data is refreshed from TourCMS each time you save this Tour/Hotel.", "gray-line-licensee-wordpress-tourcms-plugin"); 
				}
				?><br /></p>
				<table class="widefat">
					<thead>
						<tr>
							<th style="width: 190px;"><?php _e('Field', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></th>
							<th><?php _e('Value', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td class="row-title" title="[last_updated]"><?php _e('Last updated', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc" style="overflow: hidden">
								<?php 
									$last_updated = get_post_meta( $post->ID, 'grayline_tourcms_wp_last_updated', true ); 
									
									$time_since_update = time() - $last_updated;
									
									echo grayline_tourcms_wp_convtime($time_since_update)." ago";
								?>
							</td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[tour_name]"><?php _e('Tour name', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_tour_name', true ); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[tour_id]"><?php _e('Tour ID', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_tour_id', true ); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[tour_code]"><?php _e('Tour code', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_tour_code', true ); ?></td>
						</tr>
						<tr>
							<td class="row-title"><?php _e('Rates', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php 
							
								$grayline_tourcms_wp_rates_all = get_post_meta( $post->ID, 'grayline_tourcms_wp_rates', true );
								
								
								if($grayline_tourcms_wp_rates_all != '') {
								
									$rates = json_decode($grayline_tourcms_wp_rates_all, JSON_OBJECT_AS_ARRAY);
									
									if(!isset($rates[0])) {
										$rates = [$rates];
									}
									
									foreach($rates as $key => $rate) {
										
										$label_2 = is_array($rate['label_2']) ? "" : "- ".$rate['label_2'];
										echo "{$rate['rate_id']} $label_2 - from {$rate['from_price_display']}<br />";
										/*echo "{$rate['rate_id']} - {$rate['label_2']} - from {$rate['from_price_display']}<br />";*/
											
									}
							
								}
							?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title"><?php _e('Dates', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php 
							
								$grayline_tourcms_wp_dates_all = get_post_meta( $post->ID, 'grayline_tourcms_wp_dates', true );
								
								
								if($grayline_tourcms_wp_dates_all != '') {
								
									echo count(json_decode($grayline_tourcms_wp_dates_all)) . " dates";
							
								} else {
									_e("No dates", "gray-line-licensee-wordpress-tourcms-plugin");
								}
							?></td>
						</tr>
						<tr>
							<td class="row-title"><?php _e('Priority', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_priority', true ); ?> (<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_priority_num', true ); ?>)</td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[has_sale]"><?php _e('On sale', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php 
								if((int)get_post_meta( $post->ID, 'grayline_tourcms_wp_has_sale', true )==1) {
									echo "Yes (1) - ";
									$months = array("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec");
									$monthstr = "";
									foreach($months as $month) {
										if((int)get_post_meta( $post->ID, 'grayline_tourcms_wp_has_sale_'.$month, true )==1) {
											$monthstr .= ucwords($month).", ";
										}
									}
									print substr($monthstr, 0, strlen($monthstr)-2).".";
								} else
									echo "No (0)";
									?></td>
						</tr>
						<tr>
							<td class="row-title" title="[book_url]"><?php _e('Book url', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc" style="overflow: hidden;"><?php 
									$book_url = get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ); 
									if(strlen($book_url)>43) {
										$book_url = substr($book_url, 0, 40)."...";
										echo '<a href="'.get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ).'" target="_blank" title="'.get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ).'">'.$book_url.'</a>';
									} else 
										echo '<a href="'.get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ).'" target="_blank" title="'.get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ).'">'.get_post_meta( $post->ID, 'grayline_tourcms_wp_book_url', true ).'</a>';
								?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[from_price_display]"><?php _e('From price (display)', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_from_price_display', true ); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[from_price]"><?php _e('From price', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_from_price', true ); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[sale_currency]"><?php _e('Sale currency', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_sale_currency', true ); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[location]"><?php _e('Primary location', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_location', true ); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[geocode_start]"><?php _e('Geocode start (Long, Lat)', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_geocode_start', true ); ?> <a href="https://maps.google.com/?q=<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_geocode_start', true ); ?>" target="_blank" title="View on Google Maps">&raquo;</a></td>
						</tr>
						<tr>
							<td class="row-title" title="[geocode_end]"><?php _e('Geocode end (Long, Lat)', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_geocode_end', true ); ?> <a href="https://maps.google.com/?q=<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_geocode_end', true ); ?>" target="_blank" title="<?php _e('View on Google Maps', 'gray-line-licensee-wordpress-tourcms-plugin'); ?> ">&raquo;</a></td>
						</tr>
						<tr class="alternate">
							<td class="row-title"><?php _e('All gecodes', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php 
							
								$grayline_tourcms_wp_geocode_all = get_post_meta( $post->ID, 'grayline_tourcms_wp_geocode_all', true );
								
								
								if($grayline_tourcms_wp_geocode_all != '') {
								
									$points = json_decode($grayline_tourcms_wp_geocode_all);
									
									foreach($points as $key => $point) {
										print "[" . $key . "] " 
											. $point->label
											. " <a href='https://maps.google.com/?q="
											. $point->geocode
											. "' target='_blank' title='View on Google Maps'>&raquo;</a>"
											. "<br />";
											
									}
							
								}
							?></td>
						</tr>
						<tr>
							<td class="row-title" title="[duration_desc]"><?php _e('Duration description', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_duration_desc', true ); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[available]"><?php _e('Available', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_available', true ); ?></td>
						</tr>
						<tr>
							<td class="row-title"><?php _e('Images', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php 
								for($i=0; $i<=10; $i++) {
									$img_src = get_post_meta( $post->ID, 'grayline_tourcms_wp_image_url_'.$i, true );
									if($img_src != "") {
									?>
									
									<img src="<?php echo $img_src;  ?>" title="<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_image_desc_'.$i, true ); ?>" style="height: 100px;" />
									<?php
									}
								}
							?></td>
						</tr>
						
						<tr class="alternate">
							<td class="row-title" title="[vid_embed]"><?php _e('Video', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc">
								<?php
									$vid_url = get_post_meta( $post->ID, 'grayline_tourcms_wp_video_url_0', true ); 
									if(!empty($vid_url)) {
										?>
										<a href="<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_video_url_0', true ); ?>" target="_blank"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_video_url_0', true ); ?></a>
										<?php
									}
								?>
							</td>
						</tr>
						
						<tr>
							<td class="row-title" title="[document_link]"><?php _e('Document', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php
								$vid_url = get_post_meta( $post->ID, 'grayline_tourcms_wp_document_url_0', true ); 
								if(!empty($vid_url)) {
									?>
									<a href="<?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_document_url_0', true ); ?>" target="_blank"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_document_desc_0', true ); ?></a>
									<?php
								}
							?></td>
						</tr>
						
						<tr class="alternate">
							<td class="row-title" title="[summary]"><?php _e('Summary', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_summary', true ); ?></td>
						</tr>
						
						<tr>
							<td class="row-title" title="[essential]"><?php _e('Essential', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_essential', true ); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[rest]"><?php _e('Restrictions', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_rest', true ); ?></td>
						</tr>
						
						<tr>
							<td class="row-title" title="[pick]"><?php _e('Pick up / drop off', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_pick', true ))); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[inc]"><?php _e('Includes', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_inc', true ))); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[ex]"><?php _e('Excludes', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_ex', true ))); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[extras]"><?php _e('Extras / upgrades', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_extras', true ))); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[itinerary]"><?php _e('Itinerary', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_itinerary', true ))); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[exp]"><?php _e('Experience', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_exp', true ))); ?></td>
						</tr>
						<tr>
							<td class="row-title" title="[redeem]"><?php _e('Redemption instructions', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_redeem', true ))); ?></td>
						</tr>
						<tr class="alternate">
							<td class="row-title" title="[shortdesc]"><?php _e('Short description', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_shortdesc', true ); ?></td>
						</tr>
						
						<tr>
							<td class="row-title" title="[longdesc]"><?php _e('Long description', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo nl2br(strip_tags(get_post_meta( $post->ID, 'grayline_tourcms_wp_longdesc', true ))); ?></td>
						</tr>

						<?php 
							//later in the request...
							global $wpdb;

							$results = $wpdb->get_results(
								"
								SELECT meta_key
								FROM {$wpdb->prefix}postmeta
								WHERE meta_key
								LIKE 'grayline_tourcms_wp_hands_%'
								AND post_id = " . (int)$post->ID . "
								"
							);
							$alternate = true;
							foreach($results as $result) {
								?>
									<tr class="<?php echo $alternate ? "alternate" : ""; ?>">
										<td class="row-title"><?php echo htmlspecialchars(grayline_tourcms_get_default_strings($result->meta_key)); ?></td>
										<td class="desc"><?php echo get_post_meta( $post->ID, $result->meta_key, true ); ?></td>
									</tr>
								<?php 
								$alternate = !$alternate;
							}
						?>
						
						<tr class="alternate">
							<td class="row-title"><?php _e('Suitable for solo', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_solo', true ); ?></td>
						</tr>
						
						<tr>
							<td class="row-title"><?php _e('Suitable for couples', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_couples', true ); ?></td>
						</tr>
						
						<tr class="alternate">
							<td class="row-title"><?php _e('Suitable for children', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_children', true ); ?></td>
						</tr>
						
						<tr>
							<td class="row-title"><?php _e('Suitable for groups', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_groups', true ); ?></td>
						</tr>
						
						<tr class="alternate">
							<td class="row-title"><?php _e('Suitable for business', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_business', true ); ?></td>
						</tr>

						<tr class="">
							<td class="row-title"><?php _e('Suitable for wheelchairs', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_suitable_for_wheelchairs', true ); ?></td>
						</tr>
						
						<tr class="alternate">
							<td class="row-title"><?php _e('Languages spoken', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></td>
							<td class="desc"><?php echo get_post_meta( $post->ID, 'grayline_tourcms_wp_languages_spoken', true ); ?></td>
						</tr>
						
						
						<tr>
							<td class="row-title">
							<?php _e('Alternative tours', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>
							</td>
							<td>
								<?php
								$at_xml = get_post_meta( $post->ID, 'grayline_tourcms_wp_alternative_tours', true);
								if($at_xml != '') {
								$alternative_tours = simplexml_load_string($at_xml);
								
								foreach($alternative_tours->tour as $alternative_tour) {
									?>
										<a href="<?php echo $alternative_tour->tour_url; ?>" target="_blank"><?php echo $alternative_tour->tour_name_long; ?></a> (<?php echo $alternative_tour->tour_id; ?>)<br />
									<?php
								} } ?>
							</td>
						</tr>
					</tbody>
				</table>
				<?php else : ?>
				<p>Additional fields will be displayed here once you have saved this Tour/Hotel.</p>
				<?php endif ?>
				<?php
				}
				?>
			</div>
		<?php
	} else { ?>
			<div class="form-field form-required">
			<p><?php _e('You must configure the', 'gray-line-licensee-wordpress-tourcms-plugin'); ?> <a href="options-general.php?page=grayline_tourcms_wp"> <?php _e('TourCMS Plugin Settings', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></a> <?php _e('before you can link this Tour/Hotel to a product.', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></p>
			</div>
		<?php
	} ?>
	<?php
}

add_action( 'plugins_loaded', 'grayline_tourcms_plugin_load_text_domain' );
function grayline_tourcms_plugin_load_text_domain() {
    load_plugin_textdomain( 'gray-line-licensee-wordpress-tourcms-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

register_setting("grayline_tourcms_wp_register", "grayline_tourcms_wp_sublogo", "handle_file_upload");

function handle_file_upload($option)
{  
	global $sublogo_info;
	if(!empty($_FILES["grayline_tourcms_wp_sublogo"]["tmp_name"])) { 
		$urls = wp_handle_upload($_FILES["grayline_tourcms_wp_sublogo"], array('test_form' => FALSE));
		if(empty($urls["url"])) {
			return json_encode($sublogo_info);   
		} else {
			$sublogo_info['file'] = $urls["file"];
			$sublogo_info['url'] = $urls["url"];
			return json_encode($sublogo_info); 
		} 
	} else {
		return get_option('grayline_tourcms_wp_sublogo');
	}

	return $option;
}


// Check cache freshness, update if expired
function grayline_tourcms_wp_refresh_cache() {
	if(is_single() && get_query_var('post_type') == 'tour') {
		// Post details
		global $post;
		$last_updated = get_post_meta( $post->ID, 'grayline_tourcms_wp_last_updated', true );
		$tour_id = get_post_meta( $post->ID, 'grayline_tourcms_wp_tourid', true );
		// Cache update frequency
		(get_option('grayline_tourcms_wp_update_frequency')=="") ? $grayline_tourcms_wp_update_frequency = 14400 : $grayline_tourcms_wp_update_frequency = intval(get_option('grayline_tourcms_wp_update_frequency'));
		// Calculate next update time
		$next_update = (int)$last_updated + (int)$grayline_tourcms_wp_update_frequency;
		
		// Only update if cache is expired
		if(($grayline_tourcms_wp_update_frequency!=-1) && ($next_update <= time())) 
			grayline_tourcms_wp_refresh_info($post->ID, $tour_id);
	}
}

// Save custom meta when page is saved
function grayline_tourcms_wp_save_tour( $post_id, $post ) {
	// Check nonce and permissions

	if (empty($_POST['grayline_tourcms_wp_wpnonce']) || !wp_verify_nonce( $_POST[ 'grayline_tourcms_wp_wpnonce' ], 'grayline_tourcms_wp'))
		return;
	if (!current_user_can( 'edit_post', $post_id ))
		return;
	if ($post->post_type != 'page' && $post->post_type != 'tour')
		return;
	
	// Save Tour ID
	$tour_id = intval($_POST['grayline_tourcms_wp_tourid']);		
	grayline_tourcms_wp_refresh_info($post_id, $tour_id);
}

// Updates TourCMS information on a particular Tour/Hotel, called either when
// editing in WordPress or when being viewed with a stale cache
function grayline_tourcms_wp_refresh_info($post_id, $tour_id, $update_post_detail = false) {

	update_post_meta( $post_id, 'grayline_tourcms_wp_tourid', $tour_id);
	
	
	$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;

	// Query API
	$results = $grayLineTourCMSMainControllers->call_tourcms_api("tour", null, $tour_id);
	
	$date_results = $grayLineTourCMSMainControllers->call_tourcms_api("deals", null, $tour_id);
	
	$qs = "tour_id={$tour_id}";
	$tour_other_info = $grayLineTourCMSMainControllers->call_tourcms_api("related", $qs);
	
     if(!empty($tour_other_info->tour) || (string)$tour_other_info->error == "OK") {
     	update_post_meta( $post_id, 'grayline_tourcms_wp_custom1', is_object($tour_other_info->custom1) ? '' : (string)$tour_other_info->custom1);
		update_post_meta( $post_id, 'grayline_tourcms_wp_custom2', is_object($tour_other_info->custom2) ? '' : (string)$tour_other_info->custom2);
     }

	// If there's any sort of error, return
	if(empty($results) || $results->error != "OK")
		return;

	// Update main fields
	$tour = $results->tour;

	$supplier_tour_code = (string)$tour->supplier_tour_code;
     // original tour id
	$original_tour_id = get_tour_id_from_supplier_tour_code($supplier_tour_code);
	update_post_meta( $post_id, 'grayline_tourcms_wp_original_tour_id', $original_tour_id);

	// original channel id
	$original_channel_id = get_channel_id_from_supplier_tour_code($supplier_tour_code);
     update_post_meta( $post_id, 'grayline_tourcms_wp_original_channel_id', $original_channel_id);
	


	// need to check this code
	// tour feefo rating 
	
	if($tour->ratings) {

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_rating', (string)$tour->ratings->average);

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_count', (string)$tour->ratings->count);

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_max', (int)$tour->ratings->max);

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_platform', (string)$tour->ratings->platform);
	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_rating', '');

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_count', '');

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_max', 0);

		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_feefo_platform', '');
	}
	

	if($update_post_detail == true) { 
		$syncTours = new GrayLineTourCMSControllers\SyncTours;
		// update title and slug
		$post_data = array(
			'ID' =>  $post_id,
			'post_title'    => (string)$tour->tour_name_long,
			'post_name'     => $syncTours->set_slug((string) $tour->tour_name_long),
		);

		wp_update_post($post_data);
	}

	// If the tour is a transfer, we need to add in the direction attribute as a child element
	// (JSON does not show attibutes the way that XML does)
	$direction = -1;
	if ( $tour->product_type == 2 ) {
		$direction = $tour->product_type->attributes()->direction;
	}
	update_post_meta( $post_id, 'grayline_tourcms_wp_transfer_direction', (string)$direction);
	update_post_meta( $post_id, 'grayline_transfer_channel_id', (string)$tour->channel_id);
	update_post_meta( $post_id, 'grayline_transfer_tour_url', (string)$tour->tour_url);
	update_post_meta( $post_id, 'grayline_tourcms_wp_min_booking_size', (string)$tour->min_booking_size);
	update_post_meta( $post_id, 'grayline_tourcms_wp_max_booking_size', (string)$tour->max_booking_size);

	$minDuration = $tour->new_booking->date_selection->duration_minimum ? (int)$tour->new_booking->date_selection->duration_minimum : 0;
	update_post_meta( $post_id, 'grayline_tourcms_wp_min_duration', $minDuration);

	$maxDuration = $tour->new_booking->date_selection->duration_maximum ? (int)$tour->new_booking->date_selection->duration_maximum : 0;
	update_post_meta( $post_id, 'grayline_tourcms_wp_max_duration', $maxDuration);

	// Mandatory fields
	update_post_meta( $post_id, 'grayline_tourcms_wp_last_updated', time());
	update_post_meta( $post_id, 'grayline_tourcms_wp_book_url', (string)$tour->book_url);
	update_post_meta( $post_id, 'grayline_tourcms_wp_from_price', (string)$tour->from_price);
	update_post_meta( $post_id, 'grayline_tourcms_wp_from_price_display', (string)$tour->from_price_display);
	update_post_meta( $post_id, 'grayline_tourcms_wp_sale_currency', (string)$tour->sale_currency);
	update_post_meta( $post_id, 'grayline_tourcms_wp_geocode_start', (string)$tour->geocode_start);
	update_post_meta( $post_id, 'grayline_tourcms_wp_geocode_end', (string)$tour->geocode_end);
	
	// Save country code and country name
	update_post_meta( $post_id, 'grayline_tourcms_wp_country_code', (string)$tour->country);
	$country_code = (string)$tour->country;
	$country_json = get_option("countries");
	$iso_country_list = json_decode($country_json, true);
	
	if(isset($iso_country_list[$country_code])) {
		update_post_meta( $post_id, 'grayline_tourcms_wp_country_name', $iso_country_list[$country_code]);
	}

	// Save tags
	if(!empty($tour->tour_tags)) {
		$tag_arr = array();
		foreach($tour->tour_tags->tag as $tag) {
			if (!str_contains($tag->token, 'suitable')) { 
				$tag_arr[] = (string)$tag->token;
			}
		}

		$tags = "";
		if(count($tag_arr) > 0) {
			$tags = implode(",",$tag_arr);
		}
		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_tags', $tags);
		
	}
	
	// All geocode points
	$points[] = array(
		'geocode' => (string)$tour->geocode_start,
		'can_start_end_here' => 1,
		'label' =>  __( 'Start', 'grayline_tourcms_wp' )
	);
	
	if(!empty($tour->geocode_midpoints)) {
		foreach($tour->geocode_midpoints->midpoint as $point) {
			$points[] = array(
				'geocode' => (string)$point->geocode,
				'can_start_end_here' => (string)$point->can_start_end_here,
				'label' => (string)$point->label
			);
		}
	}
		
	$points[] = array(
		'geocode' => (string)$tour->geocode_end,
		'can_start_end_here' => 1,
		'label' =>  __( 'End', 'grayline_tourcms_wp' )
	);
	
	update_post_meta( $post_id, 'grayline_tourcms_wp_geocode_all', json_encode($points));	
	// End all geocode points
	
	$rate_details = json_encode($tour->new_booking->people_selection->rate);
	update_post_meta( $post_id, 'grayline_tourcms_wp_rates', (string)$rate_details );

	$rate_details = json_encode($tour->new_booking->people_selection);

	update_post_meta( $post_id, 'grayline_tourcms_wp_all_rates', (string)$rate_details );

	// End Rates

	// Dates and deals 
	$dates = [];

	if(!empty($date_results->dates_and_prices) && !empty($date_results->dates_and_prices->date)){
		foreach($date_results->dates_and_prices->date as $date) {
			$dates[] = [
				'd' => (string)$date->start_date,
				's' => (int)$date->special_offer_type
			];
		}
	}		

	$date_details = json_encode($dates);

	update_post_meta( $post_id, 'grayline_tourcms_wp_dates', (string)$date_details );
	// End Dates and deals
	
	update_post_meta( $post_id, 'grayline_tourcms_wp_duration_desc', (string)$tour->duration_desc);
	update_post_meta( $post_id, 'grayline_tourcms_wp_available', (string)$tour->available);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale', (string)$tour->has_sale);
	update_post_meta( $post_id, 'grayline_tourcms_wp_tour_id', (int)$tour->tour_id);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_tour_name', (string)$tour->tour_name_long);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_location', (string)$tour->location);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_summary', (string)$tour->summary);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_shortdesc', (string)$tour->shortdesc);	
	update_post_meta( $post_id, 'grayline_tourcms_wp_priority', (string)$tour->priority);	
	switch ((string)$tour->priority) {
		case "HIGH":
			update_post_meta( $post_id, 'grayline_tourcms_wp_priority_num', "A");
			break;
		case "LOW":
			update_post_meta( $post_id, 'grayline_tourcms_wp_priority_num', "C");
			break;
		default:
			// MEDIUM
			update_post_meta( $post_id, 'grayline_tourcms_wp_priority_num', "B");
			break;
	}
	
	
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_jan', (string)$tour->has_sale_jan);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_feb', (string)$tour->has_sale_feb);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_mar', (string)$tour->has_sale_mar);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_apr', (string)$tour->has_sale_apr);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_may', (string)$tour->has_sale_may);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_jun', (string)$tour->has_sale_jun);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_jul', (string)$tour->has_sale_jul);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_aug', (string)$tour->has_sale_aug);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_sep', (string)$tour->has_sale_sep);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_oct', (string)$tour->has_sale_oct);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_nov', (string)$tour->has_sale_nov);
	update_post_meta( $post_id, 'grayline_tourcms_wp_has_sale_dec', (string)$tour->has_sale_dec);
	
	// Number only fields
	update_post_meta( $post_id, 'grayline_tourcms_wp_grade', (string)$tour->grade);
	update_post_meta( $post_id, 'grayline_tourcms_wp_accomrating', (string)$tour->accomrating);
	update_post_meta( $post_id, 'grayline_tourcms_wp_product_type', (string)$tour->product_type);
	update_post_meta( $post_id, 'grayline_tourcms_wp_tourleader_type', (string)$tour->tourleader_type);
	
	// Suitable for
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_solo', (string)$tour->suitable_for_solo);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_couples', (string)$tour->suitable_for_couples);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_children', (string)$tour->suitable_for_children);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_groups', (string)$tour->suitable_for_groups);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_students', (string)$tour->suitable_for_students);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_business', (string)$tour->suitable_for_business);
	update_post_meta( $post_id, 'grayline_tourcms_wp_suitable_for_wheelchairs', (string)$tour->suitable_for_wheelchairs);

	if(isset($tour->soonest_special_offer)) {
		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_price_1', (string)$tour->soonest_special_offer->price_1);

		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_original_price_1', (string)$tour->soonest_special_offer->original_price_1);

		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_note', (string)$tour->soonest_special_offer->special_offer_note);

	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_price_1', 0);

		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_original_price_1', 0);

		update_post_meta( $post_id, 'grayline_tourcms_wp_soonest_special_offer_note', "");
	}
			


	if(isset($tour->recent_special_offer)) {
		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_price_1', (string)$tour->recent_special_offer->price_1);

		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_original_price_1', (string)$tour->recent_special_offer->original_price_1);

		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_note', (string)$tour->recent_special_offer->special_offer_note);
	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_price_1', 0);

		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_original_price_1', 0);

		update_post_meta( $post_id, 'grayline_tourcms_wp_recent_special_offer_note', "");
	}
		
	if(isset($tour->pickup_points->pickup)) {
		update_post_meta( $post_id, 'grayline_tourcms_wp_pickup_points_exist', 'true');
	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_pickup_points_exist', 'false');
	}
	
	// Health and safety
	foreach($tour->health_and_safety->item as $hands) {
		update_post_meta( $post_id, 'grayline_tourcms_wp_hands_' . (string)$hands->name, (string)$hands->value );
	}

	// Languages spoken
	update_post_meta( $post_id, 'grayline_tourcms_wp_languages_spoken', (string)$tour->languages_spoken);
	
	// Optional fields
	if(isset($tour->tour_code))
		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_code', (string)$tour->tour_code);	
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_tour_code', '');	 
	
	if(isset($tour->inc_ex))
		update_post_meta( $post_id, 'grayline_tourcms_wp_inc_ex', (string)$tour->inc_ex);	
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_inc_ex', '');

	if(isset($tour->inc))
		update_post_meta( $post_id, 'grayline_tourcms_wp_inc', (string)$tour->inc);	
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_inc', '');
	
	if(isset($tour->ex))
		update_post_meta( $post_id, 'grayline_tourcms_wp_ex', (string)$tour->ex);	
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_ex', '');
	
			
	if(isset($tour->essential))
		update_post_meta( $post_id, 'grayline_tourcms_wp_essential', (string)$tour->essential);	
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_essential', '');	
		
	if(isset($tour->rest))
		update_post_meta( $post_id, 'grayline_tourcms_wp_rest', (string)$tour->rest);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_rest', '');
	
	if(isset($tour->redeem))
		update_post_meta( $post_id, 'grayline_tourcms_wp_redeem', (string)$tour->redeem);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_redeem', '');
	
	if(isset($tour->longdesc))
		update_post_meta( $post_id, 'grayline_tourcms_wp_longdesc', (string)$tour->longdesc);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_longdesc', '');
	
	if(isset($tour->itinerary))
		update_post_meta( $post_id, 'grayline_tourcms_wp_itinerary', (string)$tour->itinerary);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_itinerary', '');
		
	
	if(isset($tour->exp))
		update_post_meta( $post_id, 'grayline_tourcms_wp_exp', (string)$tour->exp);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_exp', '');
		
	
	if(isset($tour->pick))
		update_post_meta( $post_id, 'grayline_tourcms_wp_pick', (string)$tour->pick);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_pick', '');
		
	if(isset($tour->extras))
		update_post_meta( $post_id, 'grayline_tourcms_wp_extras', (string)$tour->extras);
	else
		update_post_meta( $post_id, 'grayline_tourcms_wp_extras', '');
		
	if (isset($tour->custom_fields->field[0])) {
		foreach ($tour->custom_fields->field as $custom_field) {
		
			$field_name = (string)$custom_field->name;
			$field_value = (string)$custom_field->value;
		
			update_post_meta( $post_id, 'grayline_tourcms_wp_custom_' .$field_name , $field_value);
		}
	} elseif (isset($results->custom_fields->field[0])) {
		foreach ($results->custom_fields->field as $custom_field) {
		
			$field_name = (string)$custom_field->name;
			$field_value = (string)$custom_field->value;
		
			update_post_meta( $post_id, 'grayline_tourcms_wp_custom_' .$field_name , $field_value);
		}
	} 
	
	// Video
	if(!empty($tour->videos->video[0]->video_id)) {
		$vid = $tour->videos->video[0];
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_id_0' , (string)$vid->video_id);
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_service_0' , (string)$vid->video_service);
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_url_0' , (string)$vid->video_url);
	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_id_0' , '');
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_service_0' , '');
		update_post_meta( $post_id, 'grayline_tourcms_wp_video_url_0' , '');
	}
	
	// Document
	if(!empty($tour->documents->document[0]->document_url)) {
		$doc = $tour->documents->document[0];
		update_post_meta( $post_id, 'grayline_tourcms_wp_document_desc_0' , (string)$doc->document_description);
		update_post_meta( $post_id, 'grayline_tourcms_wp_document_url_0' , (string)$doc->document_url);
	} else {
		update_post_meta( $post_id, 'grayline_tourcms_wp_document_desc_0' , '');
		update_post_meta( $post_id, 'grayline_tourcms_wp_document_url_0' , '');
	}
	// print_r($tour);exit;
	// Alternative tours
	if(!empty($tour->alternative_tours)) {
		$alternative_tours = $tour->alternative_tours;
		$alternative_tours_xml = $alternative_tours->asXml();
		update_post_meta ($post_id, 'grayline_tourcms_wp_alternative_tours', $alternative_tours_xml);
	} else {
		update_post_meta ($post_id, 'grayline_tourcms_wp_alternative_tours', '');
	}
	
	
	// Update images
	for($i=0;$i<=10;$i++) {
		if(isset($tour->images->image[$i]->url)) {
			update_post_meta( $post_id, 'grayline_tourcms_wp_image_url_'.$i, (string)$tour->images->image[$i]->url);
			update_post_meta( $post_id, 'grayline_tourcms_wp_image_desc_'.$i, (string)$tour->images->image[$i]->image_desc);
			
			if(isset($tour->images->image[$i]->url_thumbnail)) {
				update_post_meta( $post_id, 'grayline_tourcms_wp_image_url_thumbnail_'.$i, (string)$tour->images->image[$i]->url_thumbnail);
			} else {
				delete_post_meta( $post_id, 'grayline_tourcms_wp_image_url_thumbnail_'.$i);
			}

			if(isset($tour->images->image[$i]->url_large)) {
				update_post_meta( $post_id, 'grayline_tourcms_wp_image_url_large_'.$i, (string)$tour->images->image[$i]->url_large);
			} else {
				delete_post_meta( $post_id, 'grayline_tourcms_wp_image_url_large_'.$i);
			}

		} else {
			delete_post_meta( $post_id, 'grayline_tourcms_wp_image_url_'.$i);
			delete_post_meta( $post_id, 'grayline_tourcms_wp_image_desc_'.$i);
			delete_post_meta( $post_id, 'grayline_tourcms_wp_image_url_thumbnail_'.$i);
		}
	}

	//Total image count
	if(isset($tour->images->image))
			update_post_meta( $post_id, 'grayline_tourcms_wp_image_count', $tour->images->image->count());
		
}

function grayline_tourcms_wp_convtime($seconds) {
	$ret = "";

	$hours = intval(intval($seconds) / 3600);
	if($hours > 0)
	{
		$ret .= "$hours hours ";
	}

	$minutes = (intval($seconds) / 60)%60;
	
	if (function_exists('bcmod')) {
		$minutes = bcmod((intval($seconds) / 60),60);
	} else {
		$minutes = (intval($seconds) / 60)%60;
	}
	
	if($hours > 0 || $minutes > 0)
	{
		$ret .= "$minutes minutes ";
	}

	if($ret =="")
		$ret .= "Seconds";

	return $ret;
}


function grayline_tourcms_get_default_strings($key) {
	$strings = [
		'grayline_tourcms_wp_hands_face_masks_requied_for_travelers' => __('Face masks required for travelers','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_face_masks_requied_for_guides' => __('Face masks required for guides','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_face_masks_provided_for_travelers' => __('Face masks provided for travelers','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_hand_sanitizer_available' => __('Hand sanitizer available to travelers and staff','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_social_distancing_enforced' => __('Social distancing enforced throughout experience','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_regularly_sanitized' => __('Regularly sanitized high-traffic areas','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_equipment_sanitized' => __('Gear/equipment sanitized between use','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_transportation_vehicles_sanitized' => __('Transportation vehicles regularly sanitized','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_guides_required_to_wash_hands' => __('Guides required to wash hands','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_temperature_checks_for_staff' => __('Regular temperature checks for staff','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_temperature_checks_for_travelers' => __('Temperature checks for travelers upon arrival','gray-line-licensee-wordpress-tourcms-plugin'),
		'grayline_tourcms_wp_hands_contactless_payments_for_extras' => __('Contactless payments for gratuities and add-ons','gray-line-licensee-wordpress-tourcms-plugin')
	];

	return array_key_exists($key, $strings) ? $strings[$key] : $key;
}

// Get just the unique start dates
function grayline_tourcms_wp_dates() {
	global $post;

	$dates_json = get_post_meta( $post->ID, 'grayline_tourcms_wp_dates', true );
	
	if(!empty($dates_json))
		return array_column(json_decode($dates_json), 'd');

	return [];
}

// Get information on rates 
function grayline_tourcms_wp_rates() {
	global $post;

	$rates_json = get_post_meta( $post->ID, 'grayline_tourcms_wp_rates', true );
	
	if(!empty($rates_json))
		return json_decode($rates_json);

	return [];
}

    
// REST API for bookings
function tourcms_check_availability($request)
{
	// Process inputs
	$query_params = $request->get_query_params();

	// Validation 
	$availability = [];
	$errors = [];
	if(empty($query_params['id'])) 
		$errors[] = __('Tour ID not provided', 'gray-line-licensee-wordpress-tourcms-plugin');
	
	if(empty($query_params['date'])) 
		$errors[] = __('Date not provided', 'gray-line-licensee-wordpress-tourcms-plugin');
	
	if(empty($query_params['r1']) && empty($query_params['r2']) && empty($query_params['r3']) && empty($query_params['r4']) && empty($query_params['r5']) && empty($query_params['r6']) && empty($query_params['r7']) && empty($query_params['r8']) && empty($query_params['r9']) && empty($query_params['r10'])) 
		$errors[] = __('Please provide at least one rate', 'gray-line-licensee-wordpress-tourcms-plugin');
	
	// Check availability
	if(count($errors) == 0) {
		
		$tour_id = $query_params['id']; 
		unset($query_params['id']);
		$params = http_build_query($query_params);
		
		$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;

		// Call API 
		$results = $grayLineTourCMSMainControllers->call_tourcms_api("availability", $params, $tour_id);
		
		if(empty($results)) {
			return null;
		}

		if(!empty($results->error) && $results->error != "OK")
			$errors[] = $results->error;

		// Format output 
		foreach($results->available_components->component as $component) {
			$availability[] = [
				"total_price" => (string)$component->total_price,
				"total_price_display" => (string)$component->total_price_display,
				"component_key" => (string)$component->component_key,
				"note" => (string)$component->note,
				"date_code" => (string)$component->date_code
			];
		}

	}

	$response = [
		"availability" => $availability,
		"errors" => implode(", ", $errors)
	];

	return $response;
}
	

add_action( 'rest_api_init', function () {

	global $tourcms_plugin_namespace;

	$my_custom_routes = array(
        '/availability',
        '/form_add_cart_advanced',
        '/check_avail_advanced',
        '/apply_promo_code',
        '/checkout',
        '/currency_switch',
        '/sync_tour',
        '/clear_cache',
        '/form_submit_controller',
        '/sublogo',
        '/log_payment_flow',
        '/wp/v2/pages',
        '/wp/v2/posts',
        '/wp/v2/posts',
        '/primary_menu',
        '/get_feefo_section',
        '/cart_count',
        '/refresh_availability',
        '/adyen_available_payment_methods',
        '/adyen_apple_init',
        '/adyen_apple_session',
        '/adyen_process_payment',
        '/adyen_process_3ds_two',
        '/adyen_process_state_data',
        '/adyen_card_valid',
        '/get_currency_rates',
        '/get_currency',
        '/widget_ticket',
        '/remove_cart'
     );
  
  	$is_admin = current_user_can('manage_options');  // all user they have mange option will get 

	if (!$is_admin) {
		/*require_once('tools/filter_rest_api_endpoints.php');
	    	new Filter_Rest_Api_Endpoints( $my_custom_routes );*/
	}

	// Check avail
	register_rest_route( $tourcms_plugin_namespace, '/availability', array(
		'methods' => 'GET',
		'callback' => 'tourcms_check_availability',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $tourcms_plugin_namespace, '/form_add_cart_advanced', array(
		'methods' => 'POST',
		'callback' => 'tourcms_form_add_cart_advanced',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $tourcms_plugin_namespace, '/check_avail_advanced', array(
		'methods' => 'POST',
		'callback' => 'tourcms_check_avail_advanced',
		'permission_callback' => '__return_true',
	) );

	// Start booking
	register_rest_route( $tourcms_plugin_namespace, '/book', array(
		'methods' => 'GET',
		'callback' => 'tourcms_get_check_key',
		'permission_callback' => '__return_true',
	) );

	// Start booking
	register_rest_route( $tourcms_plugin_namespace, '/start_booking', array(
		'methods' => 'GET',
		'callback' => 'tourcms_start_booking',
		'permission_callback' => '__return_true',
	) );

	// Promo code
	register_rest_route( $tourcms_plugin_namespace, '/apply_promo_code', array(
		'methods' => 'POST',
		'callback' => 'tourcms_promo_code',
		'permission_callback' => '__return_true',
	) );

	// checkout
	register_rest_route( $tourcms_plugin_namespace, '/checkout', array(
		'methods' => 'POST',
		'callback' => 'tourcms_checkout',
		'permission_callback' => '__return_true',
	) );

	// currency switch
	register_rest_route( $tourcms_plugin_namespace, '/currency_switch', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_currency_switch_through_session',
		'permission_callback' => '__return_true',
	) );

	// sync tours
	register_rest_route( $tourcms_plugin_namespace, '/sync_tour', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_sync_tour',
		'permission_callback' => '__return_true',
	) );

	// clear cache
	register_rest_route( $tourcms_plugin_namespace, '/clear_cache', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_clear_cache',
		'permission_callback' => '__return_true',
	) );
	
	register_rest_route( $tourcms_plugin_namespace, '/form_submit_controller', array(
		'methods' => 'POST',
		'callback' => 'grayline_form_submit_controller',
		'permission_callback' => '__return_true',
	) );
	
	// remove sublogo 
	register_rest_route( $tourcms_plugin_namespace, '/sublogo', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_remove_sublogo',
		'permission_callback' => '__return_true',
	) );

	// log payment flow
	register_rest_route( $tourcms_plugin_namespace, '/log_payment_flow', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_log_payment_flow',
		'permission_callback' => '__return_true',
	) );

	// primary menu
	register_rest_route( $tourcms_plugin_namespace, '/primary_menu', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_primary_menu',
		'permission_callback' => '__return_true',
	) );

	// primary menu
	register_rest_route( $tourcms_plugin_namespace, '/get_feefo_section', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_get_feefo_section',
		'permission_callback' => '__return_true',
	) );

	// cart count
	register_rest_route( $tourcms_plugin_namespace, '/cart_count', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_cart_count',
		'permission_callback' => '__return_true',
	) );	

	register_rest_route( $tourcms_plugin_namespace, '/refresh_availability', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_refresh_availability',
		'permission_callback' => '__return_true',
	) );	

	register_rest_route( $tourcms_plugin_namespace, '/adyen_available_payment_methods', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_adyen_available_payment_methods',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_apple_init', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_adyen_apple_init',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_apple_session', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_adyen_apple_session',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_process_payment', array(
		'methods' => 'POST',
		'callback' => 'grayline_tourcms_adyen_process_payment',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_process_3ds_two', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_adyen_process_3ds_two',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_process_state_data', array(
		'methods' => 'POST',
		'callback' => 'grayline_tourcms_adyen_process_state_data',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/adyen_card_valid', array(
		'methods' => 'POST',
		'callback' => 'grayline_tourcms_adyen_card_valid',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/get_currency_rates', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_get_currency_rates',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/get_currency', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_get_currency',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/widget_ticket', array(
		'methods' => 'GET',
		'callback' => 'grayline_tourcms_widget_ticket',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( $tourcms_plugin_namespace, '/remove_cart', array(
		'methods' => 'POST',
		'callback' => 'grayline_tourcms_remove_cart',
		'permission_callback' => '__return_true',
	) );

} );

add_action('grayline_tourcms_wp_currency', 'grayline_tourcms_wp_docurrency');

function grayline_tourcms_adyen_available_payment_methods($request) {
	
	require_once 'senshi/tools/adyen_available_payment_methods.php';
}

function grayline_tourcms_adyen_apple_init($request) {
	
	require_once 'senshi/tools/adyen_apple_init.php';
}

function grayline_tourcms_adyen_apple_session($request) {
	
	require_once 'senshi/tools/adyen_apple_session.php';
}

function grayline_tourcms_adyen_process_payment($request) {
	require_once 'senshi/tools/adyen_process_payment.php';
}

function grayline_tourcms_adyen_process_3ds_two($request) {
	
	require_once 'senshi/tools/adyen_process_3ds_two.php';
}

function grayline_tourcms_adyen_process_state_data($request) {
	
	require_once 'senshi/tools/adyen_process_state_data.php';
}

function grayline_tourcms_adyen_card_valid($request) {
	
	require_once 'senshi/tools/adyen_card_valid.php';
}

function grayline_tourcms_refresh_availability() {
	require_once('nc/refresh_availability.php');
}

// From currency hook function
function grayline_tourcms_wp_currency() {
		do_action('grayline_tourcms_wp_currency');
}
    
 // Print out currency drop down 
function grayline_tourcms_wp_docurrency() {
	global $truncateCurrencies;
	global $defaultCurrencies;

	//Currency list.
	$currency_list = [];
	$currency_list['USD'] = ['class'=>'dollars', 'sign'=>'$']; 
	$currency_list['EUR'] = ['class'=>'euros', 'sign'=>'€']; 
	$currency_list['GBP'] = ['class'=>'pounds', 'sign'=>'£']; 
	$currency_list['AED'] = ['class'=>'aed', 'sign'=>'&#1583;.&#1573;']; 
	$currency_list['AUD'] = ['class'=>'aud', 'sign'=>'$'];
	$currency_list['BRL'] = ['class'=>'brl', 'sign'=>'R$'];
	$currency_list['CAD'] = ['class'=>'cad', 'sign'=>'$'];
	$currency_list['CHF'] = ['class'=>'chf', 'sign'=>'Fr.'];
	$currency_list['CLP'] = ['class'=>'clp', 'sign'=>'$'];
	$currency_list['CNY'] = ['class'=>'cny', 'sign'=>'¥'];
	$currency_list['HKD'] = ['class'=>'hkd', 'sign'=>'$'];
	$currency_list['INR'] = ['class'=>'inr', 'sign'=>'₹'];
	$currency_list['ISK'] = ['class'=>'isk', 'sign'=>'kr'];
	$currency_list['JPY'] = ['class'=>'jpy', 'sign'=>'¥'];
	$currency_list['MXN'] = ['class'=>'mxn', 'sign'=>'$'];
	$currency_list['NOK'] = ['class'=>'nok', 'sign'=>'kr'];
	$currency_list['NZD'] = ['class'=>'nzd', 'sign'=>'$'];
	$currency_list['THB'] = ['class'=>'thb', 'sign'=>'฿'];
	$currency_list['ZAR'] = ['class'=>'zar', 'sign'=>'R'];

	$preset_currency = "";
	if (!empty($_COOKIE["currency"])) {
		$currency = $_COOKIE["currency"];
	} else if(!empty($_SESSION["currency"])) {
		$currency = $_SESSION["currency"];
	}

	if(!empty($_GET['currency']) && !is_array($_GET["currency"])) {
		$selected_currency = wp_strip_all_tags($_GET['currency']); 
		//$selected_currency = preg_replace('/[^A-Za-z0-9\-]/', '', $selected_currency);
		$selected_currency = strtoupper($selected_currency);
	}

	$currency_requested = (!empty($_GET['currency']) && !is_array($_GET["currency"])) ? $selected_currency: (!empty($currency)? $currency :DEF_CURRENCY);

	// currency selection
	$action = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	//$_SERVER["PHP_SELF"]

	$truncCurr = defined('TRUNCATE_CURRENCY') && TRUNCATE_CURRENCY ? true : false;
	$currencies = $truncCurr ? $truncateCurrencies : $defaultCurrencies;

	$currency_switch_form = '<div id="currencies">
		<form style="display:none;" data-submit="currency" method="get" id="currency_switch_form" action="'.$action .'">
		<select name="currency" id="currency_switch_select">';

	$currency_switch_form .= '<option value=""></option>';
	foreach ( $currencies as $key => $currency ) {
		$selected = "";
		if($key == $currency_requested) $selected = "selected";

		$currency_switch_form .= '<option value="'. $key .'" '.$selected.'>'. $currency.' '.$key.'</option>';
	}

	$currency_switch_form .= '</select>
		</form>
		</div>';	

	$currency_dd = $currency_switch_form.'<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">€  EUR</a>';
	$currency_dd .= '<ul class="dropdown-menu" id="drop" aria-labelledby="navbarDropdown">';

	foreach($currency_list as $key => $currency) {

		if(!$truncCurr || $key === "USD" || $key === "EUR" || $key === "GBP")
			$currency_dd .= '<li data-currency="'.$key.'"><a href="#" class="dropdown-item '.$currency['class'].'"><span>'.$currency['sign'].'</span> '.$key.'</a></li>';
	}

	$currency_dd .='</ul>';

	echo $currency_dd;
}

function grayline_tourcms_wp_currency_output($currency) {
	$currencies = [
		"USD" => ["symbol" => "&#36;", "name" => "USD", "output" => "&#36;"],
		"EUR" => ["symbol" => "&#128;", "name" => "EUR", "output" => "&#128;"],
		"GBP" => ["symbol" => "&#163;", "name" => "GBP", "output" => "&#163;"],
		"AUD" => ["symbol" => "&#36;", "name" => "AUD", "output" => "A$"],
		"BRL" => ["symbol" => "R&#36;", "name" => "BRL", "output" => "R&#36;"],
		"CAD" => ["symbol" => "&#36;", "name" => "CAD", "output" => "Can&#36;"],
		"CHF" => ["symbol" => "Fr.", "name" => "CHF", "output" => "CHF"],
		"CLP" => ["symbol" => "&#36;", "name" => "CLP", "output" => "CLP&#36;"],
		"CNY" => ["symbol" => "&#165;", "name" => "CNY", "output" => "&#165; "],
		"HKD" => ["symbol" => "&#36;", "name" => "HKD", "output" => "HK&#36;"],
		"INR" => ["symbol" => "&#8377;", "name" => "INR", "output" => "&#8377; "],
		"ISK" => ["symbol" => "kr", "name" => "ISK", "output" => "ISK"],
		"JPY" => ["symbol" => "&#165;", "name" => "JPY", "output" => "&#165;"],
		"MXN" => ["symbol" => "&#36;", "name" => "MXN", "output" => "Mex&#36;"],
		"NOK" => ["symbol" => "kr", "name" => "NOK", "output" => "NOK"],
		"NZD" => ["symbol" => "&#36;", "name" => "NZD", "output" => "NZ&#36;"],
		"THB" => ["symbol" => "&#3647;", "name" => "THB", "output" => "&#3647;"],
		"ZAR" => ["symbol" => "R", "name" => "ZAR", "output" => "R "],
		"AED" => ["symbol" => "&#1583;.&#1573;", "name" => "AED", "output" => "&#1583;.&#1573; "]
	];
	$symbol = $currencies[$currency]["output"];
	return $symbol;
}

function grayline_tourcms_wp_dosearchtour_old() { 
	$params = $_REQUEST;
	return tour_search($params);
}
add_filter('grayline_tourcms_wp_search_tour_old', 'grayline_tourcms_wp_dosearchtour_old');

function tour_search_old($args) {  
	global $api_info; 
	//global $wp;
	
	//$current_request = $wp->request;
	
	$special_offer = 0;
	global $post;
	$tour_location = get_post_meta( $post->ID, 'tour_location', true);
	$tour_category = get_post_meta( $post->ID, 'tour_category', true);
	
	$current_slug = "";
	if(!empty($tour_location) && $tour_location!="") {
		$current_slug = $tour_location;
	} 
	
	/*if(!empty($current_request))
	{
		$slug_arr = explode('/',$current_request); 

		if(count($slug_arr) > 1) {
			$current_slug = $slug_arr[count($slug_arr)-1];
		} else if(count($slug_arr) > 0) {
			if($slug_arr[0] == "special-offers") {
				$special_offer = 1;
			}
		}
	}*/
	
	$params = [];
	$qs = "";
	$qs_append = "";
	if(!empty($tour_category) && $tour_category!="") {
		if($tour_category == "special-offers" || $tour_category == "special offers") {
				$special_offer = 1;
		} else {
			$cats = str_replace( " ", "+", trim($tour_category));
			$cats_qs = "{$cats}&";
			$qs_append .= $cats_qs;
		}
	}

	$input_params = [];

	// Location wise tour search
	if(!empty($current_slug)) 
	{
		$params["location"] = str_replace("-", "+", $current_slug);
	}
	
	$keyword = !empty($args['keyword'])?$args['keyword']:""; 
	$keyword_filter = (!empty($args['keyword_filter'])? htmlspecialchars($args['keyword_filter'], ENT_QUOTES, 'UTF-8') : "");

	$k  = (!empty($args['k'])? $args['k'] : "");
	$k2 = (!empty($args['k2'])? $args['k2'] : "");
	$k3 = (!empty($args['k3'])? $args['k3'] : "");

	$keywords_all = array( $keyword, $keyword_filter, $k, $k2, $k3 );
	$keywords = [];

	// Let's strip out any blank keywords
	foreach ( $keywords_all as $key => $val ) { 
		if (!empty($val)) {
			if(!in_array( $val, $keywords)){
				$keywords[] = $val;
			}
		}
	}

	if ( !empty($keywords[0])) {
		$params["k"] = $keywords[0];
	}
	
	// Let's set the keyword type to be AND, rather than OR
	if ( !empty($keywords[1]) ) {
		$params["k2"] = $keywords[1];
		$params["k_type"] = "AND";
	}
	if ( !empty($keywords[2]) ) {
		$params["k3"] = $keywords[2];
	}

	if ( !empty($keyword) ) {
		array_shift( $keywords );

		if ( count( $keywords ) == 3 )
			array_pop( $keywords );
	} 
	// We need to unshift the keywords before returning them
	array_unshift( $keywords, "" );
	$keywords = array_slice( $keywords, 0, 4 );
	
	if(!empty($keywords[1])) {
		$input_params["k"]  = $keywords[1];
	}

	if(!empty($keywords[2])) {
		$input_params["k2"] = $keywords[2];
	}

	if(!empty($keywords[3])) {
		$input_params["k3"] = $keywords[3];
	}

	$product_type = (!empty($args['product_type'])? $args['product_type']:"");

	if ( !empty($product_type) )  {
		// If product type is 4 (day tours), we must also include events, 6
		$product_type = $product_type == 4 ? "4,6" : $product_type;
		$params["product_type"] = $product_type;
	}

	$order = !empty($args['orderby']) ? $args['orderby'] : "";
	
	// If order is set to special offer recent, let's only show special offers
	if ( (!empty($order) && $order == "offer_recent") || $special_offer == 1) {
		$params["has_offer"] = 1;
	} 
		
	if (!empty($order)) {
		$params["order"] = $order;
	} else{
		$custom_order = get_option('grayline_tourcms_wp_custom_order');
		if(!empty($custom_order) && $custom_order == 1) {
			$params["order"] = 'display_points_up';
		}
	}

	if(!empty($args['geocode'])) {
		$coords = explode(",", $args['geocode']);
		$lat = $coords[0];
		$lng = $coords[1];

		$params['lat'] = $lat;
		$params['long'] = $lng;
		$params['geo_distance'] = 10;
	}

	// Searching the tours
	$search = (!empty($args['search_tours'])? $args['search_tours'] :"");
	$start_date_start = (!empty($args['start_date_start'])? $args['start_date_start'] :"");
	$start_date_end = (!empty($args['start_date_end'])? $args['start_date_end'] :"");

	// If only start date submitted, use this alone
	if ( $start_date_start && ! $start_date_end ) {
		$params["start_date"] = $start_date_start;
		$start_date_start = NULL;
	}

	// If only to date submitted, substitute in today's date as start date
	else if ( $start_date_end && ! $start_date_start ) {
		$start_date_start = date("Y-m-d");
	}
	if ( $start_date_start ) {
		$params["start_date_start"] = $start_date_start;
	}
	if ( $start_date_end ) {
		$params["start_date_end"] = $start_date_end;
	}

	$paged = !empty($args['pageno'])?$args['pageno']:"";
	$params["page"] = $paged ? $paged : 1;

	$per_page = !empty($args['per_page'])?$args['per_page']:"";
	$params["per_page"] = $per_page ? $per_page : 50;

	//$params["404_tour_url"] = "all";

	$qs = http_build_query($params, '', '&'); 
	$qs = $qs_append.$qs;
	$cache_key = str_replace( "&", "-", $qs );
	$cache_key = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $cache_key);
	
	// Check if search results exist in cache
	if(empty($api_info)) { 
		// Get saved tour search result from transient
		$api_info = get_data_from_transient($cache_key); 
	} 

	if( !empty($api_info) ) { 
		$api_info->input_args = (count($input_params)>0?(object)$input_params:''); 
		return $api_info;
	} 
	
	$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;
	// Call TourCMS Api's
	$results = $grayLineTourCMSMainControllers->call_tourcms_api("list", $qs. '&k_type=AND');
	
	if(empty($results)) {
		return null;
	}

	// If the result is null, AND there was only one search term used, then lets split up the keywords, and research
	$num_res_count =  $results->total_tour_count;
	if ( $num_res_count == 0 && $keyword ) {
		$input_params["hide_keyword_search"] = 1;
		// Convert the single searh keyword into an array of words
		$keywords = explode( " ", $keyword ); 
		if ( !empty($keywords[0]) )
			$params["k"] = $keywords[0];

			// Let's set the keyword type to be AND, rather than OR
			if (!empty($keywords[1])) {
				$params["k2"] = $keywords[1];
				$params["k_type"] = "AND";
			}
			if (!empty($keywords[2] ))
				$params["k3"] = $keywords[2];

			$qs = http_build_query($params, '', '&');
			$qs = $qs_append.$qs;
			$cache_key = str_replace( "&", "-", $qs );
			$cache_key = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $cache_key);

			$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;

			// Call TourCMS Api's again
			$results = $grayLineTourCMSMainControllers->call_tourcms_api("list", $qs. '&k_type=AND');

			// If this doesn't work, let's expand once more and change the type of keyword search from AND to OR
			$num_res_count =  $results->total_tour_count;
			if ( $num_res_count == 0 ) {
				$params["k_type"] = "OR";
				
				$qs = http_build_query($params, '', '&');
				$qs = $qs_append.$qs;
				$cache_key = str_replace( "&", "-", $qs );
				$cache_key = preg_replace(array("/[\s]/","/[^0-9A-Z_a-z-.]/"),array("_",""), $cache_key);

				
				// Call TourCMS Api's again
				$results = $grayLineTourCMSMainControllers->call_tourcms_api("list", $qs. '&k_type=AND'); 
			}
		
	}

	if(empty($results)) {
		return null;
	}

	if((!empty($results->error) && $results->error != "OK") || empty($results)) {
		// if record not found then delete cache
		delete_transient($cache_key);

		return null;
	} else {  
		$pages = ceil($results->total_tour_count / $params["per_page"]);
		$results->total_pages =  $pages;
		$results->page =  $params["page"];
		$results->page_range =  page_range($params["page"], $params["per_page"], $results->total_tour_count);
		

		// Load data into runtime cache for 12 hours
		set_data_in_transient($cache_key, $results);
		
		$result = get_data_from_transient($cache_key); 
		$result->input_args = (!empty($input_params) && count($input_params)>0?(object)$input_params:''); 

		return $result;
	}
}

function set_data_in_transient($cache_key, $results, $cache_time = "") {
	if(empty($cache_time)) {
		 $cache_time = 5*60*60;
	}

	$cache_key = 'grayline_tourcms_wp_cache_key_'.$cache_key;
	set_transient($cache_key, json_encode($results), $cache_time );
}

function get_data_from_transient($cache_key) {
	$cache_key = 'grayline_tourcms_wp_cache_key_'.$cache_key; 
	$api_data = get_transient($cache_key); 
	return json_decode($api_data); 
}

function delete_data_in_transient($cache_key) {

	$cache_key = 'grayline_tourcms_wp_cache_key_'.$cache_key; 

	delete_transient($cache_key); 
}

function tour_list($args = array()) {
	global $api_info; 
	$params = array();
	$qs = "";
	$cats_qs = null;
	$tourids_qs = null;
	
	$per_page = (!empty($args['count']))? $args['count'] : 3;
	
	if(!empty($args)) {
		
		if (!empty($args['special_offer'])) { 
			$params['has_offer'] = 1;
		}
		if ($args['category']) {
			$cats = str_replace(" ", "+", trim($args['category']));
			$cats_qs = "{$cats}&";
		} else {
			if ($args['location']) {
				$params["location"] = $args['location'];
			} else { 
				if ($args['ids']) {  
					$tIDs = str_replace(" ", "", trim($args['ids']));
					$tourids_qs = "tour_id=$tIDs&";
					
					$pieces = explode(",", $tIDs);
					$per_page = count($pieces);
				}
			}
		}
		//$params['order'] = 'display_points_up';
		$params["page"] = 1;
		$params["per_page"] = $per_page; 
		$params["404_tour_url"] = "all";
		$custom_order = get_option('grayline_tourcms_wp_custom_order');
		if(!empty($custom_order) && $custom_order == 1) {
			$params["order"] = 'display_points_up';
		}
		$qs .= $cats_qs;
		$qs .= $tourids_qs;
		$qs .= http_build_query($params, '', '&');
			
		$cache_key = str_replace("&", "-", $qs);
		$cache_key = str_replace("category", "", $cache_key);
		$cache_key = "tour-list-".$cache_key;
			
		if(empty($api_info)) {
			$api_info = get_data_from_transient($cache_key); 
		}

		if( !empty($api_info) ) {  
			return $api_info;
		}

		$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;

		// Call TourCMS Api's
		$results = $grayLineTourCMSMainControllers->call_tourcms_api("list", $qs);

		if(!empty($results) && (string)$results->error === "OK") { 
			set_data_in_transient($cache_key, $results);
		} else { 
			$cache_key = 'grayline_tourcms_wp_cache_key_'.$cache_key; 
			delete_transient($cache_key); 
			return null;
		}
		
		return get_data_from_transient($cache_key);
	}
}
	
function page_range( $page, $per_page, $total_tour_count ) {
	$v1 = $page * $per_page - $per_page + 1;
	$v2 = $v1 + $per_page - 1;
	// If we are on the last page, we must be sure to calculate the end range prooperly
	if ( $v2 > $total_tour_count )
		$v2 = $total_tour_count;
	$page_range = "$v1 - $v2";

	return $page_range;
}
	
function get_embed_url($video_id, $video_service = "youtube", $options = array()) {

	$services = array("vimeo", "youtube");

	if(!empty($video_id)) {
		// Generate the correct embed code according to service
		if(in_array(strtolower($video_service), $services)) {
			$secure = false;
			if(!empty($_SERVER['HTTPS'])) {
				$secure = true;
			}

			$autoplay  = (!empty($options['autoplay']) ? $options['autoplay'] : "");
			$func_name = $video_service . "_embed_url";
			return $func_name($video_id, $secure, $autoplay);
		}
	}
}

// Generate YouTube embed code
function youtube_embed_url($video_id, $secure, $autoplay) {
	return 'http' . ($secure ? "s" : "") . '://www.youtube.com/embed/' . $video_id . '?rel=0'  . ($autoplay ? "&autoplay=1" : "");
}

// Generate Vimeo embed code
function vimeo_embed_url($video_id, $secure, $autoplay) {
	return 'http' . ($secure ? "s" : "") . '://player.vimeo.com/video/' . $video_id . ($autoplay ? "?autoplay=1" : "");
}

/*function asterisk2Ul( $string ) 
{   
	$string = preg_replace("/^\*+(.*)?/im","<ul><li>$1</li></ul>", $string); 
	$string = preg_replace("/(\<\/ul\>\n(.*)\<ul\>*)+/","", $string); 
	return $string;
}*/

function makeClickableLinks($s, $target=null)
{
	$target = $target ? $target : "_self";
	$pattern = '@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@';
	return preg_replace($pattern, '<a href="$1" target="' . $target . '">$1</a>', $s);
}


function convertDate($format, $new_format, $date_change) {
	$date = DateTime::createFromFormat($format, $date_change);
	return $date->format($new_format);
}

// Make TourCMS images SSL
function tourImageSecure($url) {
	
	if (empty((array)$url)) {
		return '';
	}

	$urlparts = explode('.', $url);
	// check for presence of 'r' segment
	if (preg_match('/r\d+/', $urlparts[1])) {
		$urlparts[1] = 'ssl';
		// put url back together
		$url = implode('.', $urlparts);
	}
	
	return $url ;
}

// Related tours
function grayline_tourcms_wp_related_tours($post_id) { 
	
	if(!empty($post_id)) {
		$tour_id = get_post_meta( $post_id, 'grayline_tourcms_wp_tour_id', true );
		$alts = get_post_meta( $post_id, 'grayline_tourcms_wp_alternative_tours', true );

		$alts = simplexml_load_string($alts);

		$product_type = get_post_meta( $post_id, 'grayline_tourcms_wp_product_type', true );

		$location = get_post_meta( $post_id, 'grayline_tourcms_wp_location', true );

		$alt_cnt = 0;
		$tour_limit = 4;
		$tour_count=0;
		$alternative_tour_ids = [];
		if(!empty($alts)) { 
			foreach ( $alts->tour as $alt_tour ) { 
				if($tour_count >= 3)
				{
					break;
				}

				// We must make sure that the tour id doesn't match the current tour id
				if (  $alt_tour->tour_id != $tour_id )
				{
					$alternative_tour_ids[] = (string)$alt_tour->tour_id; 
					$tour_count=$tour_count+1;
				}
			} 


			//$tour_count = count($alternative_tour_ids);
			$alt_cnt = ($tour_count >= 3 ? 3 : ($tour_limit - $tour_count));
		}

		if($alt_cnt === 3) { 
			$args = [
				'post_type'=>'tour',  
				'post_status'=>'publish', 
				'meta_query' => array(
				'relation' => 'AND', 
					array(
						'key' => 'grayline_tourcms_wp_tour_id',
						'value' => $alternative_tour_ids,
						'type' => 'numeric',
						'compare' => 'In'
					)
				),
			'orderby' => 'post_modified',
			'order'   => 'DESC',
			'post__not_in' => array($post_id),
			'nopaging' => true
			];  
		} else if($alt_cnt < 3 && $alt_cnt > 0) { 

			$args = [
				'post_type'=>'tour',  
				'post_status'=>'publish', 
				'meta_query' => array(
					'relation' => 'OR', 
					array(
						'key' => 'grayline_tourcms_wp_tour_id',
						'value' => $alternative_tour_ids,
						'type' => 'numeric',
						'compare' => 'In'
					),
					array(
						'key' => 'grayline_tourcms_wp_product_type',
						'value' => $product_type,
						'type' => 'numeric',
						'compare' => '='
					),
				),
				'posts_per_page'=> '4',
				'post__not_in' => array($post_id),
				'nopaging' => true
			]; 

		} else {  

			$args = [
				'post_type'=>'tour',  
				'post_status'=>'publish', 
				'meta_query'=> array(
					array( 
						'key'     => 'grayline_tourcms_wp_product_type',
						'value'   => $product_type
					),
					array( 
						'key'     => 'grayline_tourcms_wp_location',
						'value'   => $location
					)
				),
				'posts_per_page'=> '4',
				'post__not_in' => array($post_id),
				'nopaging' => true
			]; 

		}

		$post_list2 = new WP_Query($args); 
		if(empty($post_list2->posts)) {
			$args = [
                   'post_type'=>'tour',  
                   'post_status'=>'publish', 
                   'posts_per_page'=> '4',
			    'post__not_in' => array($post_id),
			    'nopaging' => true
              ]; 

               $rel_post_list = new WP_Query($args); 
               $tour_list = array_slice( $rel_post_list->posts, 0, 4 );
			return $tour_list;
		}

		if(!empty($post_list2->posts)) {
			$tour_list = array_slice( $post_list2->posts, 0, 4 );
			return $tour_list;
		} else {
			return null;
		}
	}
}

require_once 'controllers/sync-tours.php';	
	
function sync_tours() { 
	$syncTours = new GrayLineTourCMSControllers\SyncTours();
	$syncTours->save_tours();
}

add_action( 'sync_tour_action', 'sync_tours' );

function grayline_tourcms_sync_tour() { 
	return wp_schedule_single_event( time() , 'sync_tour_action' );
}

function grayline_tourcms_sync_tour_daily() { 
	if ( ! wp_next_scheduled( 'sync_tour_daily_action' ) ) { 
		wp_schedule_event( time(), 'daily', 'sync_tour_daily_action' );
	}
}
//add_action('init', 'grayline_tourcms_sync_tour_daily', 1);
add_action( 'sync_tour_daily_action', 'sync_tours' );

// require('libraries/firephp-core-master/lib/FirePHPCore/fb.php');

// Include Availability Widget
// include_once('libraries/3rdparty/tourcms/countries.php');
// print_r($iso_country_list);die;
require_once 'libraries/3rdparty/tourcms/functions.php';
require_once 'widgets/tourAvail/tourAvail.php';
// Include Map Widget
//require_once 'widgets/tourMapBox/tourMapBox.php';
require_once 'widgets/tourMap/tourMap.php';
// Include Feefo Widget
// require_once 'widgets/FeefoReviews/FeefoReviews.php';
// Include Home Page Special Offer Widget
require_once 'widgets/homePageSpecialOffer/homePageSpecialOffer.php';
// Include Home Page Special Offer Widget
require_once 'widgets/homePageBannerSection/homePageBannerSection.php';
// Include Home Page Special Offer Widget
require_once 'widgets/featuredPost/featuredPost.php';
// Include Location Highlights Widget
require_once 'widgets/locationHighlights/locationHighlights.php';
// Include Tour List Block Widget
require_once 'widgets/tourlistblock/tourlistblock.php';
// Include Tour List Banner Widget
require_once 'widgets/tourListPageBannerSection/tourListPageBannerSection.php';
// Include Tour List Banner Widget
require_once 'widgets/saleBanner/saleBanner.php';

require_once 'controllers/tour_single.php';	
require_once 'controllers/checkout.php';	
require_once 'controllers/cart.php';	
// require_once 'controllers/palisis-cookie.php';	
require_once 'controllers/cart_complete.php';	
require_once 'controllers/currency.php';
//require_once 'controllers/cron_gen_currency_json.php';	
require_once 'controllers/cron_url_update.php';	
require_once 'controllers/enquiry.php';	
require_once 'controllers/checkout_key.php';	
require_once 'controllers/checkout_check.php';	
require_once 'controllers/checkout_submit.php';	

require_once 'models/logger.php';	
require_once 'controllers/agents.php';
require_once 'libraries/TmtAuthstring/Create.php';
require_once 'libraries/TmtAuthstring/Validate.php';
require_once 'tools/log_payment_flow.php';
require_once 'tools/primary_menu.php';
require_once 'jobs/cron_cache_locations.php';

add_action( 'template_redirect', 'grayline_load_external_controllers' );
function grayline_load_external_controllers() {

    if(is_page_template('templates/agent-register.php')) {

        require_once 'external_form_controllers/agent_register.php';

    } else if(is_page_template('templates/agent-login.php')) {

        require_once 'external_form_controllers/agent_login.php';

    } else if(is_page_template('templates/affiliate-register.php')) {

        require_once 'external_form_controllers/affiliate_register.php';

    } else if(is_page_template('templates/affiliate-login.php')) {

        require_once 'external_form_controllers/affiliate_login.php';

    }

}

/*add_filter( 'template_include', 'grayline_load_external_controllers');
function grayline_load_external_controllers( $t ) {

	$current_template = basename($t);
	if(isset($current_template) && $current_template == "affiliate_login.php") { 
		require_once 'external_form_controllers/affiliate_login.php';
	}
	return $current_template;
}*/

/*add_action( 'init', 'grayline_load_external_controlers' );
function grayline_load_external_controlers() {
	$available_templates = wp_get_theme()->get_page_templates();
	
	if(isset($available_templates['agent_register.php'])) { 
		require_once 'external_form_controllers/agent_register.php';
	}

	if(isset($available_templates['agent_login.php'])) {
		require_once 'external_form_controllers/agent_login.php';
	}

	if(isset($available_templates['affiliate_register.php'])) {
		require_once 'external_form_controllers/affiliate_register.php';
	}

	if(isset($available_templates['affiliate_login.php'])) {
		require_once 'external_form_controllers/affiliate_login.php';
	}
}*/

function tourcms_form_add_cart_advanced($request) {
	// Process inputs
	$body_params = $request->get_body_params();
	
	if(empty($body_params['add_to_cart_json'])) 
		$errors[] = "Add to cart json not found";
	
	$tourSingle = new GrayLineTourCMSControllers\TourSingle();
	return $tourSingle->form_add_cart_advanced($body_params['add_to_cart_json']);
}

function tourcms_check_avail_advanced($request) {
	// Process inputs
	
	require_once 'senshi/tools/get_availability.php';
	//$body_params = $request->get_body_params();
	
	/*$tourSingle = new GrayLineTourCMSControllers\TourSingle();
	return $tourSingle->check_avail_advanced($request);*/
}

function tourcms_promo_code($request) {
	// Process inputs
	$body_params = $request->get_body_params();
	
	$checkout = new GrayLineTourCMSControllers\Checkout();
	return $checkout->on_start($body_params);
}

function tourcms_checkout($request) {
	// Process inputs
	$body_params = $request->get_body_params();
	
	$checkout = new GrayLineTourCMSControllers\Checkout();
	return $checkout->on_start($body_params);
}
    
function set_cart_cookie( $seshID, $num ) {
	
	$palisis_cookie = new GrayLineTourCMSControllers\PalisisCookie();

	$expire = time() + CART_COOKIE_EXPIRE;
	$info["seshID"] = $seshID;
	$info["num"] = $num;
	$name = "cart_cookie";
	$val =  json_encode($info);
	$palisis_cookie->setCookie($name, $val, $expire);
}

// Currency switch
function grayline_tourcms_wp_currency_switch($request) {
	// Process inputs
	if(!empty($_GET['currency']) && !is_array($_GET["currency"])) {
		
		include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

		$currency = strtoupper($_GET['currency']);

		if (!in_array($currency, $allowed_currencies)) {
	        $currency = "USD";
	    	}
		
		palisis_set_cookie("selcurrency",  $currency);
		
		wp_redirect($_SERVER['HTTP_REFERER']);
	}
}

// Currency switch
function grayline_tourcms_currency_switch_through_session($request) {
	
	// Process inputs
	// $query_params = $request->get_query_params();
	$tourcms_currency = new GrayLineTourCMSControllers\TourCMSCurrency();

	return $tourcms_currency->get_session_currency();
	
}


function grayline_form_submit_controller() {
	$agents = new GrayLineTourCMSControllers\AgentsController();
	return $agents->form_submit_controller();
}

function loadCurrencyFile($filename) {
	//$dir = 'libraries'; - used as temp fix for non-live currency exchange

	$dir = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'api_json/currency/';
	$dirBak = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH.'libraries';

	$filepath = $dir . $filename;

	try {
		if (file_exists($filepath)) {
			$json = file_get_contents($filepath);
			if ( $json === false )
				throw new Exception( "File Problem: File get contents problem $filepath" );
			else {
				return json_decode( $json );
			}
		}
		else {
			$dirBak = "$dirBak/";
			$filepathBak = $dirBak . $filename;
			if (file_exists($filepathBak)) {
				$json = file_get_contents($filepathBak);
				if ( $json === false )
					throw new Exception( "File Problem: File get contents problem $filepathBak" );
				else {
					return json_decode( $json );
				}
			}
			error_log( "File doesn't exist $filepath" );
			return 0;
		}
	}
	catch( TourcmsException $e ) {
		echo "We seem to be having issues connecting to our tour source. We are sorry for the inconvenience. \n";
	}
	catch ( FileException $e ) {
		echo "We seem to be having file system issues. We are sorry for the inconvenience. \n";
	}
	catch ( Exception $e ) {
		echo $e->getMessage() . "\n";
	}
}

function run_cron() {
	if ( ! wp_next_scheduled( 'currency_cron_hook' ) ) { 
		wp_schedule_event( time(), 'daily', 'currency_cron_hook' );
	}
	if ( ! wp_next_scheduled( 'cache_channels_hook' ) ) { 
		wp_schedule_event( time(), 'daily', 'cache_channels_hook' );
	}
	cache_channels_exec();
}
add_action('init', 'run_cron', 1);
add_action( 'currency_cron_hook', 'currency_cron_exec' );
add_action( 'cache_channels_hook', 'cache_channels_exec' );

function currency_cron_exec()
{     
	require_once 'jobs/cache_currencies.php';	
	$cronGenCurrencyJson = new GrayLineTourCMSJobs\CacheCurrencies();

	// need to be logged error if any error occurred
	$cronGenCurrencyJson->run();
}

function cache_channels_exec()
{     
	require_once 'senshi/jobs/cache_channels.php';
	$cronGenCurrencyJson = new GrayLineTourCMSJobs\CacheChannels();

	// need to be logged error if any error occurred
	$cronGenCurrencyJson->run();
}

function get_site_translation_strings() 
{
	require_once 'tools/site_translation_strings_js.php';
}
	
add_action('grayline_tourcms_wp_faceboox_pixel', 'faceboox_pixel_script');

function faceboox_pixel_script() {

	if(get_option('grayline_tourcms_wp_fb_pixel_enabled') == 1 && array_key_exists('cookielawinfo-checkbox-analytics', $_COOKIE) && $_COOKIE['cookielawinfo-checkbox-analytics'] == 'yes') {

		echo " <!-- Facebook Pixel Code -->
			<script>
					!function(f,b,e,v,n,t,s)
					{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
						n.callMethod.apply(n,arguments):n.queue.push(arguments)};
						if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
						n.queue=[];t=b.createElement(e);t.async=!0;
						t.src=v;s=b.getElementsByTagName(e)[0];
						s.parentNode.insertBefore(t,s)}(window, document,'script',
						'https://connect.facebook.net/en_US/fbevents.js');
					fbq('init', '".get_option('grayline_tourcms_wp_fb_pixel_id')."');
					fbq('track', 'PageView');
					</script>";
	}
}

function grayline_tourcms_wp_faceboox_pixel() {
	do_action('grayline_tourcms_wp_faceboox_pixel');
}

// clear cache
function grayline_tourcms_clear_cache() {
	global $wpdb;

	$query = $wpdb->prepare("DELETE FROM wp_options WHERE option_name LIKE %s",
		array('%grayline_tourcms_wp_cache_key_%')
	);

	$result = $wpdb->query($query);
	return ($result > 0) ? true : false;
}

//remove sublogo
function grayline_tourcms_remove_sublogo() { 
	$url = get_option('grayline_tourcms_wp_sublogo');
	$url = json_decode($url);
	
	if(!empty($url->file) && file_exists($url->file)) {
		unlink($url->file);
	}
	return delete_option('grayline_tourcms_wp_sublogo');
}

//log payment flow
function grayline_tourcms_log_payment_flow($request) {
     
	if (empty($_GET['grayline_tourcms_wp_log_payment_wpnonce']) || !wp_verify_nonce( $_GET[ 'grayline_tourcms_wp_log_payment_wpnonce' ], 'grayline_tourcms_wp_log_payment')) { 
		return;
	} 
   
	// Process inputs
	$body_params = $request->get_query_params();
	return new LogPaymentFlow($body_params);	
}

//Primary Menu
function grayline_tourcms_primary_menu($request) {

	if (empty($_GET['grayline_tourcms_wp_menu_wpnonce']) || !wp_verify_nonce( $_GET[ 'grayline_tourcms_wp_menu_wpnonce' ], 'grayline_tourcms_wp_menu')) { 
		return;
	} 

	$primary_menu = new PrimaryMenu();	
	return $primary_menu->setUp();
}

function feefo()
{
	require_once 'models/feefo.php';

	$feefo = new Feefo();
	return $feefo;
}

function hook_header_scripts($script = "") { 
	if(is_singular('tour')) {
		global $post;
		require_once 'models/feefo.php';
		require_once 'models/feefo_reviews.php';
		
		$plugin_path = GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH;
		$tour_id = (int)get_post_meta($post->ID, 'grayline_tourcms_wp_original_tour_id', true );  
		$cache_time = 43200;	// 12 hours
		$cache_location = "api_json/";

		$feefo = new Feefo();
		$feefo_data = new FeefoReviews($feefo, (string) GLWW_CHANNEL . '_' . (string) $tour_id, 15, array(
					"cache_time" => $cache_time,
					"cache_location" => $plugin_path.$cache_location
			));

		$shortdesc = get_post_meta($post->ID, 'grayline_tourcms_wp_shortdesc', true);
		$img1url = get_post_meta($post->ID, 'grayline_tourcms_wp_image_url_0', true);
		$img1url = tourImageSecure($img1url);
          $organicFeefo = $feefo_data->organicProductStars($img1url, $shortdesc);
        	echo $organicFeefo;
	}
	if(is_page( array( 'checkout') ) ) {
		if (ADYEN_ENV === 'test') {
			echo "<script>let SEN_ADYEN_ENVIRONMENT = 'test'</script>";
		} else {
			echo "<script>let SEN_ADYEN_ENVIRONMENT = 'live'</script>";
		}
	}

	if(is_page_template('archive-tour.php')) {
		echo $script;
	}
}
add_action('wp_head','hook_header_scripts','10',1);

/*********************************************************************/
/* Add blog featured checkbox
/********************************************************************/
add_action( 'add_meta_boxes', 'add_featured_checkbox_function' );
function add_featured_checkbox_function() {
   add_meta_box('featured_checkbox_id','Blog Featured', 'featured_checkbox_callback_function', 'post', 'normal', 'high');
}
function featured_checkbox_callback_function( $post ) {
   global $post;
   $isFeatured = get_post_meta( $post->ID, 'is_featured_blog', true );
?>
   
   <input type="checkbox" name="is_featured_blog" value="yes" <?php echo (($isFeatured=='yes') ? 'checked="checked"': '');?>/> YES
<?php
}

add_action('save_post', 'save_featured_post'); 
function save_featured_post($post_id) { 
   $is_featured = !isset($_POST['is_featured_blog']) ? "" : $_POST['is_featured_blog'];
   	update_post_meta( $post_id, 'is_featured_blog', $is_featured);
}

add_action('grayline_tourcms_wp_clear_cookies', 'clear_cookies_preferences');

// clear cookies preferences
function clear_cookies_preferences() { 

	if (isset($_SERVER['HTTP_COOKIE'])) {
		$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
		foreach($cookies as $cookie) {
			$parts = explode('=', $cookie);
			$name = trim($parts[0]);
			setcookie($name, '', time()-1000);
			setcookie($name, '', time()-1000, '/');
		}
	}
	wp_redirect(home_url("/"));
}

add_action('grayline_tourcms_wp_highlighted_extended_markup', 'highlighted_extended_markup', 10, 2);

function highlighted_extended_markup($fulltext, $highlight) {
	// $fulltext = strip_tags($fulltext);
	$text = @preg_replace("#\n|\r#", ' ', $fulltext);
     
	$matches = [];
	$highlight = str_replace(array('"',"'","&quot;"),'',$highlight); // strip the quotes as they mess the regex

	$regex = '([[:alnum:]|\'|\.|_|\s]{0,45})'. $highlight .'([[:alnum:]|\.|_|\s]{0,45})';
	preg_match_all("#$regex#ui", $text, $matches);
	
	if(!empty($matches[0])) { 
		$body_length = 0;
		$body_string = array();
		foreach($matches[0] as $line) {
			$body_length += strlen($line);

			$r = apply_filters('grayline_tourcms_wp_highlighted_markup', $line, $highlight, false);
			if ($r) {
				$body_string[] = $r;
			}
			if($body_length > 150)
				break;
		}
		if(!empty($body_string))
			return @implode("&hellip;<wbr>", $body_string);
	} 
}

add_action('grayline_tourcms_wp_highlighted_markup', 'highlighted_markup', 10, 3);

function highlighted_markup($fulltext, $highlight, $shortText) {
	if (!$highlight) {
		return $fulltext;
	}
	$hColor = '#EFE795';
	$htext = $fulltext;
	$hHighlight  = str_replace(array('"',"'","&quot;"),'',$highlight); // strip the quotes as they mess the regex
	$htext = @preg_replace( "#$hHighlight#ui", '<span style="background-color:'. $hColor .';">$0</span>', $htext );

	$numChars = 255;
	if($shortText == true && strlen($htext) > $numChars) {
		$tail = '…';
		return substr($htext, 0, $numChars) . $tail;
	}
	return $htext;
}

add_action('grayline_tourcms_wp_blog_list', 'blog_list');

// blog list
function blog_list() { 
	global $wp_query;

	$paged = ( get_query_var( 'pageno' ) ) ? get_query_var( 'pageno' ) : 1;
	$args = [
		'post_type'=>'post',  
		'post_status'=>'publish', 
		'posts_per_page'=>2, 
		'paged' => $paged,
		'order' => 'DESC',
		'orderby' => 'post-date', 
		'meta_query' => array(
		'relation' => 'AND',
		array(
	        'key'     => 'is_featured_blog',
	        'value'   => 'yes',
	        'compare' => '!='
		))];

	if(is_category() || is_tag()) {
		$term = get_queried_object();
		
		if(is_category())
		{
			$args['category_name'] = $term->slug;
		}

		if(is_tag())
		{
			$args['tag'] = $term->slug;	
		}
	}
	
	$post_list = new WP_Query($args);

	return $post_list;
}

function udpating_tour_urls() {
	if ( ! wp_next_scheduled( 'udpating_tour_urls_hook' ) ) { 
		wp_schedule_event( time(), 'daily', 'udpating_tour_urls_hook' );
	}
}

add_action('init', 'udpating_tour_urls', 1);
add_action( 'udpating_tour_urls_hook', 'udpating_tour_urls_exec' );


function udpating_tour_urls_exec()
{     
	$cronUrlUpdate = new GrayLineTourCMSControllers\CronUrlUpdate();
	$result = $cronUrlUpdate->run();
}

function custom_badge_code($custom2)
{  
    // Product badges
    $code = null;
    $custom2_arr = (array)$custom2;
    $badge = empty($custom2_arr)?"":(string)strtoupper($custom2);

    if ($badge) {
        $code = productCode($badge);
    }

    return $code;
}
add_action('grayline_tourcms_wp_custom_badge_code', 'custom_badge_code', 10, 1);

function custom_badge_text($custom2)
{
    // Product badges
    $custom2_arr = (array)$custom2;
    $badge = empty($custom2_arr)?"":(string)strtoupper($custom2);
    
    $text = null;
    if ($badge) {
        $code = productCode($badge);
        $text = productBadge($code);
    }
    return $text;
}
add_action('grayline_tourcms_wp_custom_badge_text', 'custom_badge_text', 10, 1);

function productCode($string)
{
    return trim((string)strtolower($string));
}

function productBadge($string)
{
    $string = strtoupper($string);
    $array = array(
        "SO" => __('Likely ToSell Out', 'wp_grayline_tourcms_theme'),
        "NT" => __('New Tour', 'wp_grayline_tourcms_theme'),
        "BS" => __('Bestseller', 'wp_grayline_tourcms_theme'),
        "ET" => __('Essential Tour', 'wp_grayline_tourcms_theme'),
        "MS" => __('Must See', 'wp_grayline_tourcms_theme'),
        "PT" => __('Private Tour', 'wp_grayline_tourcms_theme')
    );

    if (array_key_exists($string, $array)) {
        return $array[$string];
    }

    return null;
}

function lookup_latlong($location) {
	if(empty($location)) {
		return;
	}
	$timeout = 5;
	$map_key = "";
	if(defined('DESTINATIONX_GOOGLE_MAP_API_KEY')) {
		$map_key = DESTINATIONX_GOOGLE_MAP_API_KEY;
	}
	$base_url = "http://maps.google.com/maps/api/geocode/json?key=".$map_key."&sensor=false";
	$request_url = $base_url . "&address=".urlencode($location);

	if (function_exists('curl_init')) {
		$curl_handle = curl_init();
	
		if (defined('HTTP_PROXY_HOST') && HTTP_PROXY_HOST!= null ) {
			@curl_setopt($curl_handle, CURLOPT_PROXY, HTTP_PROXY_HOST);
			@curl_setopt($curl_handle, CURLOPT_PROXYPORT, HTTP_PROXY_PORT);

			// Check if there is a username/password to access the proxy
			if (defined('HTTP_PROXY_USER') && HTTP_PROXY_USER!= null) {
				@curl_setopt($curl_handle, CURLOPT_PROXYUSERPWD, HTTP_PROXY_USER . ':' . HTTP_PROXY_PWD);
			}
		}

		curl_setopt($curl_handle, CURLOPT_URL, $request_url);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
		$contents = curl_exec($curl_handle);
		$http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
		curl_close($curl_handle);
		if ($http_code == 404) {	
			return false;
		}
		
		return $contents;
	}

}
add_action('grayline_tourcms_wp_lookup_latlong', 'lookup_latlong', 10, 1);


function grayline_tourcms_get_feefo_section() {
	if (get_option('grayline_tourcms_wp_feefo_show') == 1) { 
		if(array_key_exists('cookielawinfo-checkbox-analytics', $_COOKIE) && $_COOKIE['cookielawinfo-checkbox-analytics'] == 'yes') { 
			$title = __("Feefo Reviews 3rd party independent for Gray Line", 'wp_grayline_tourcms_theme');
			$area_lable = __("Feefo Reviews 3rd party independent for Gray Line", 'wp_grayline_tourcms_theme');
			$feefo_image = __("Feefo image", 'wp_grayline_tourcms_theme');
			return ' <div class="feefo" >
				<div class="container">
				<div class="row">
					<div class="col-12">
					<a href="https://www.feefo.com/en/reviews/'. FEEFO_MERCHANT_ID.'" target="_blank" title="'.$title.'" aria-label="'.$area_lable.'">
					<img class="feefoTmp" src="https://api.feefo.com/api/logo?merchantidentifier='. FEEFO_MERCHANT_ID .'&amp;template=Service-Stars-Grey-175x44.png&amp;since=all" alt="'.$feefo_image.'">
					</a>
					<div id="feefologohere"></div>
					</div>
				</div>
			</div></div>';
		}
 	}
}

// cart count
function grayline_tourcms_cart_count($request) {
	return require_once 'nc/get_cart_count.php';
}


// agent login and logout
add_action( 'template_redirect', 'grayline_tourcms_agent_login' );

function grayline_tourcms_agent_login() {

	if(isset($_POST["agent_login"]) || isset($_POST["agent_logout"])) {

		$agents = new GrayLineTourCMSExternalFormControllers\AgentLoginExternalFormBlockController();

		if(isset($_POST["agent_login"])) {

			$agents->action_submit_form();

		} else if(isset($_POST["agent_logout"])) {

			$agents->action_log_out();
		}
	}
}

// cron job of cache location
function cache_location_event() {
	
	if ( ! wp_next_scheduled( 'cache_location_hook' ) ) { 
		wp_schedule_event( time(), 'daily', 'cache_location_hook' );
	}
}

add_action('init', 'cache_location_event', 1);
add_action( 'cache_location_hook', 'cache_location_exec' );

function cache_location_exec()
{     
	$cronCacheLocations = new CronCacheLocations();
	$cronCacheLocations->run();
}

// affiliate login and logout
add_action( 'template_redirect', 'grayline_tourcms_affiliate_login_logout' );

function grayline_tourcms_affiliate_login_logout() {

	

	if(isset($_POST["affiliate_login"]) || isset($_POST["affiliate_logout"])) {

		$affiliate = new GrayLineTourCMSExternalFormControllers\AffiliateLoginExternalFormBlockController(); 

		if(isset($_POST["affiliate_login"])) {

			$affiliate->action_submit_form();

		} else if(isset($_POST["affiliate_logout"])) {

			$affiliate->action_log_out();
		}
	}
}

// update licensees details start	
function cron_update_licensees($manually_update = null) {

	require_once 'senshi/jobs/cron_update_licensees.php';	

	$cronUpdateLicensees = new CronUpdateLicensees(); 
	$cronUpdateLicensees->run($manually_update);
}

add_action( 'cron_update_licensees_action', 'cron_update_licensees', 10, 1 );

/*function grayline_tourcms_cron_update_licensees() { 
	return wp_schedule_single_event( time() , 'cron_update_licensees_action' );
}*/

function grayline_tourcms_cron_update_licensees_daily() { 
	if ( ! wp_next_scheduled( 'cron_update_licensees_daily_action' ) ) { 
		wp_schedule_event( time(), 'daily', 'cron_update_licensees_daily_action' );
	}
}

add_action('init', 'grayline_tourcms_cron_update_licensees_daily', 1);
add_action( 'cron_update_licensees_daily_action', 'cron_update_licensees' );
// update licensees details end 


add_action('admin_menu', 'addAdminPageContent');
function addAdminPageContent() {
    add_menu_page(' Glww Commission', ' Glww Commission', 'manage_options', 'glww-commission', 'commission_licensee_list', 'dashicons-list-view
');

    $mypage = add_submenu_page( 
        '', 
        'Edit Commission', 
        'Edit Commission', 
        'manage_options', 
        'edit-commission', 
        'commission_edit_page'
    );
}

function commission_licensee_list() {  

// load commission model
loadSenshiModal('commission');

	$commission = new Commission();
	$commission->genAll();
	$licensees = $commission->getLicensees();
	$total =  $commission->getTotal();
?>
<style>
    table.form-table td {
        padding: 10px;
    }
    table.form-table tr:nth-child(even) {
        background-color: lightgrey;
    }


    table.form-table tr:nth-child(odd) {
        background-color: white;
    }
    table.form-table th {
	  text-align: left;
	  padding-left: 1%;
	}
	form.form-stacked {
	    text-align: right !important;
	}
</style>
<script>
	jQuery(document).ready(function(){
		jQuery("#setting-error-commission_licensee").click(function() {
			jQuery(this).remove();
			var url = window.location.href.split("?");

			if(url.length > 0) 
				window.location.href = url[0]+'?page=glww-commission';
		});
	});
	
</script>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>

	<h2><?php _e('Commission Licensee List','gray-line-licensee-wordpress-tourcms-plugin'); ?></h2>


	<?php if(isset($_GET['msg'])) { ?>
		<div id="setting-error-commission_licensee" class="notice notice-success settings-error is-dismissible"> 
			<p><strong><?php echo $_GET['msg']; ?></strong></p><button type="button" class="notice-dismiss msg-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
	<?php } ?>

	<form class="form-stacked" method="post" action="<?php echo admin_url('/admin-post.php'); ?>">
		<input type="hidden" name="action" value="glww_commission_update">
		<input class="button-primary" type="submit" value="<?php _e('Update List', 'gray-line-licensee-wordpress-tourcms-plugin')?>" name="Submit" />
	</form>
	<h3><?php echo sprintf(__('Viewing %1$s licensees','gray-line-licensee-wordpress-tourcms-plugin'), $total); ?></h3>
	<br>

    <table border="1" class="form-table" cellspacing="0" cellpadding="10" id="orderList" width="100%">
        <thead>
        <tr>
		<th><h4><?php echo _e('Channel ID','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
		<th><h4><?php echo _e('Account ID','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
		<th><h4><?php echo _e('Cost Currency','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
		<th><h4><?php echo _e('Name','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
		<th><h4><?php echo _e('Commission','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
		<th><h4><?php echo _e('Adyen ACC','gray-line-licensee-wordpress-tourcms-plugin') ?></h4></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($licensees as $licensee) {  ?>
                <tr>
                    <td><?php echo $licensee->getChannelId(); ?></td>
                    <td><?php echo $licensee->getAccountId(); ?></td>
                    <td><?php echo $licensee->getCostCurrency(); ?></td>
                    <td><a href="<?php echo admin_url('/admin.php?page=edit-commission&id='.$licensee->getChannelId()); ?>"><?php echo $licensee->getChannelName(); ?></a></td>
                    <td><?php echo $licensee->getCommission() . "%"; ?></td>
                  <td><?php echo $licensee->getAdyenAccountId(); ?></td>
                </tr>
                <?php } ?>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>

<?php }

add_action('admin_post_glww_commission_update', 'glww_commission_update');
function glww_commission_update() { 
	do_action('cron_update_licensees_action', 1);
}

function loadSenshiModal($senshiModal) { 
	require_once 'senshi/models/'.$senshiModal.'.php';
}

function commission_edit_page() {  

	$channelId = (isset($_GET['id'])? $_GET['id']: 0);

	// load commission model
	loadSenshiModal('commission');

	$commissionObj = new Commission(); 
	$licensee = $commissionObj->getLicensee($channelId); 
	$commission = $licensee->getCommission();

	// we need to split up the commission to pass to view
	$com = (string)$commission; 
	$pieces = explode('.', $com);
	$cm = (int)$pieces[0];
	$cmF = (int)(isset($pieces[1])? $pieces[1] : 0);

	$task = (isset($_GET['task'])? $_GET['task']: "");
	if ($task == 'edit')
	{
	 	form_save_settings($licensee, $commissionObj);
	}

	$cmListArray = array(5 => 5, 7 => 7, 10 => 10, 15 => 15);
	$cmFListArray = array(0 => 0, 25 => 25, 5 => 5, 75 => 75);
?>
<div class="wrap">
	<h2><?php _e('Edit Commission','gray-line-licensee-wordpress-tourcms-plugin'); ?></h2>

	<form class="form-stacked" method="post" action="<?php echo admin_url('/admin-post.php?id='.$channelId.'&task=edit'); ?>">
		<fieldset>

			<legend>
			<?php 
				sprintf(__('Licensee: %1$s','gray-line-licensee-wordpress-tourcms-plugin'), $licensee->getChannelName());
			?>
			</legend>

			<h3><?php sprintf(__('Channel Id: %1$s','gray-line-licensee-wordpress-tourcms-plugin'), $licensee->getChannelId()); ?></h3>
			<h3><?php sprintf(__('Account Id: %1$s','gray-line-licensee-wordpress-tourcms-plugin'), $licensee->getAccountId()); ?></h3>
			<h3><?php sprintf(__('Licensee Cost Currency: %1$s ','gray-line-licensee-wordpress-tourcms-plugin'), $licensee->getCostCurrency()); ?></h3>
			<h3><?php sprintf(__('Current Commission: %1$s%%','gray-line-licensee-wordpress-tourcms-plugin'), $licensee->getCommission()); ?></h3>

		</fieldset>

		<br>

		<fieldset>

			<b><?php _e('New Commission', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></b>

			<div class="form-group">
				<label class="control-label"><?php _e('Commission', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></label>

				<select name="cm" id="cm" style="width: 50px;">
					<?php foreach($cmListArray as $cmInfo) { ?>
						<option value="<?php echo $cmInfo;?>" <?php if($cmInfo == $cm) echo "selected";?>><?php echo $cmInfo;?></option>
					<?php } ?>
				</select>
				<?php echo "." ?>
				<select name="cmF" id="cmF" style="width: 50px;">
					<?php foreach($cmFListArray as $cmFInfo) { ?>
						<option value="<?php echo $cmFInfo;?>" <?php if($cmFInfo == $cmF) echo "selected";?>><?php echo $cmFInfo;?></option>
					<?php } ?>
				</select>
			</div>
		</fieldset>

		<br><br>

		<input type="hidden" name="action" value="glww_commission_edit">
		<input type="hidden" name="channelId" value="<?php echo $channelId; ?>">
		<a href="<?php echo admin_url('/admin.php?page=glww-commission'); ?>" class="btn btn-default pull-left"><?php _e('Cancel', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></a>
		<input class="button-primary" type="submit" value="<?php _e('Save', 'gray-line-licensee-wordpress-tourcms-plugin')?>" name="Submit" />
	</form>
</div>
<?php
}


add_action('admin_post_glww_commission_edit', 'commission_edit_page');

function form_save_settings($licensee, $commissionObj) { 
	$cm        = $_POST['cm'];
	$cmF       = $_POST['cmF'];
	
	$com = "$cm.$cmF";
	$commission = floatval($com);
	//\FB::warn("Commission is $commission");

	$licensee->setCommission($commission);
     $commissionObj->updateCommission($licensee);

	$success = __("Commission successfully updated", "gray-line-licensee-wordpress-tourcms-plugin");
	wp_redirect( home_url('/wp-admin/admin.php?page=glww-commission&msg='.$success) ); 
}

add_action( 'phpmailer_init', 'my_phpmailer_example' );
function my_phpmailer_example( $phpmailer ) { 

    if(get_option('grayline_tourcms_wp_mail_send_method') === "SMTP") { 
        $phpmailer->isSMTP();     
        $phpmailer->Host = get_option('grayline_tourcms_wp_smtp_host');
        $phpmailer->SMTPAuth = get_option('grayline_tourcms_wp_smtp_auth');
        $phpmailer->Port = get_option('grayline_tourcms_wp_smtp_port');
        $phpmailer->Username = get_option('grayline_tourcms_wp_smtp_username');
        $phpmailer->Password = get_option('grayline_tourcms_wp_smtp_password');
        $phpmailer->SMTPSecure = get_option('grayline_tourcms_wp_smtp_secure');
        $phpmailer->From = get_option('grayline_tourcms_wp_smtp_from');
        $phpmailer->FromName = get_option('grayline_tourcms_wp_smtp_from_name'); 
    } 
}

/*add_action( 'template_redirect', 'grayline_tourcms_search_tours' );

function grayline_tourcms_search_tours() { 
	if(isset($_GET['searchInput'])) {
		include_once 'nc/search_redir.php';
	}
}*/

/*add_action( 'grayline_tourcms_wp_simple_tidy_url', 'simple_tidy_url',10,1);
function simple_tidy_url($name)
{ 
    return str_replace("%20", "-", rawurlencode(strtolower(str_replace("/", "-_and_-", $name))));
}*/

/*add_action("init", "send_email_test");
function send_email_test() {
	$to = 'manoj.t@kansoftware.com';
	$subject = 'Apple Computer';
	$message = 'manoj, I think this computer thing might really take off.';
	$sent = wp_mail( $to, $subject, $message );
	if($sent){

	}else{
		echo "error";exit;
	}
}*/

function loadJob($job) {
	require_once 'jobs/'.$job.'.php';
}

/*function palisis_set_cookie($name, $val, $expire=0, $path='/')
{
    setcookie($name, $val, $expire, $path, get_site_url(), TRUE );
}*/

// function get_original_channel_id($tour_id)
// {
// 	global $wpdb;
    
//     	if (empty($tour_id)) {
// 		return CHANNEL_ID;
//     	}

// 	$query =  $wpdb->prepare(

// 		"SELECT original_channel_id from TourcmsTourListCache where tour_id = %s",

// 		array(
// 		 $tour_id
// 		)
// 	);

//     $row = $wpdb->get_row($query, ARRAY_A);

//     if (empty($row) || empty($row['original_channel_id'])) {
//         return CHANNEL_ID;
//     }

//     return $row['original_channel_id'];
// }

// function get_original_tour_id($tour_id)
// {
// 	global $wpdb;

// 	$query =  $wpdb->prepare(

// 		"SELECT original_channel_id from wp_tourcms_tour_list_cache where tour_id = %s",

// 		array(
// 		 $tour_id
// 		)
// 	);

//     $row = $wpdb->get_row($query, ARRAY_A);
    

//     if (empty($row) || empty($row['original_tour_id'])) {
//         return $tour_id;
//     }

//     return $row['original_tour_id'];
// }

/*function get_licensee_name($tour_id)
{
	global $wpdb;

    	$channel_id = get_original_channel_id($tour_id);
	if (empty($channel_id)) {
	   return 'Gray Line Worldwide';
	}

	$query =  $wpdb->prepare(

		"select channel_name from wp_senshi_glww_commission where channel_id = %s",

		array(
		 $tour_id
		)
	);

	$row = $wpdb->get_row($query, ARRAY_A);

	if (empty($row) || empty($row['channel_name'])) {
	   return 'Gray Line Worldwide';
	}

	return $row['channel_name'];
}*/

function add_to_cart() {

	if(isset($_POST["add_to_cart"]) || isset($_POST["add_to_cart"])) {
		$cart = new GrayLineTourCMSControllers\CartController();
		$cart->on_start();
		$cart->add();
	}
}

// function getAdyenToolsURL($adynenModel) {
// 	return $tourcms_plugin_namespace.$adynenModel;
// }

add_filter('grayline_tourcms_wp_search_tour', 'tour_search');

function tour_search() {  
	require_once "controllers/tour_search.php";

	$tourSearch = new GrayLineTourCMSControllers\TourSearchController();
	$result = $tourSearch->on_start();

	if(isset($result->script)) { 
		do_action('wp_head', $result->script);
	} 
	return $result;
}

function search_popular_tours($args) {

 	$grayLineTourCMSMainControllers = new GrayLineTourCMSControllers\MainController;
	// Call TourCMS Api's
	$results = $grayLineTourCMSMainControllers->call_tourcms_api("list", $qs. '&k_type=AND');
}

/*function get_tours_by_key($search_key) {

	if(empty($search_key)) {
		return;
	}

	$inputs = array();
	$inputs['search_filters'] = get_field("tour_search_input", "option");
	$inputs['cache_hours'] = get_field("tour_cache_hours", "option");
	$inputs['tour_count'] = get_field("tour_count", "option");
	$inputs['selected_tours'] = get_field("most_popular_top_tours", "option");
    
    	return get_tours($inputs);
     
}*/

function get_popular_trending_tours($args) {

	global $cache_result; 

	if(empty($args)) {
		return;
	}

	$country_json = get_option("countries");
	$iso_country_list = (array)json_decode($country_json);

	$key = $args['key'];
	if(!empty($args))
		$key .= "?".http_build_query($args);

	$cache_key = md5($key);

	if(empty($cache_result)) { 
		$cache_result = get_data_from_transient($cache_key); 
		if(!empty($cache_result)) {  
			$result = $cache_result; 
			return $result;
		} 
	}

	
	include(GRAYLINE_LICENSEE_WORDPRESS_TOURCMS_PLUGIN_PATH."libraries/3rdparty/tourcms/config.php");

	$tour_count = $args['tour_count'];
	$selected_tour_count = count($args['selected_tours']);

	$tour_details = array();

	if($tour_count == $selected_tour_count) {
		
		$tour_ids = implode(",", $args['selected_tours']);
		$qs = "tour_id=$tour_ids";

		if($args['key'] == "country-trending-tours") {
			$country = strtoupper($args['country']);
			if(in_array($country, $iso_country_list)) {
				$country_code = array_search($country, $iso_country_list);
				$qs .="&country=".$country_code;
			}
			
		} else if($args['key'] == "city-trending-tours") {
			$qs .="&location=".$args['city'];
		}

		if(isset($args['suitable_for_children'])) { $qs .="&suitable_for_children=1"; }
		if(isset($args['suitable_for_solo'])) { $qs .="&suitable_for_solo=1"; }
		if(isset($args['suitable_for_couples'])) { $qs .="&suitable_for_couples=1"; }

		$result = $tourcms->search_tours($qs, $channel_id);
		if(empty($result->error)) {
			// Try again to query the TourCMS API
			$result = $tourcms->search_tours($qs, $channel_id);
		}

		if($result->error=="OK") {
			foreach($result->tour as $tour) {
				$tour_details[] = $tour;
			}
		}

	} else {
		
		$tour_diff = $tour_count - $selected_tour_count; 
		if($tour_diff < 0) {
			$args['selected_tours'] = array_slice($args['selected_tours'], 0, $tour_count);
		}

		$loaded_tour = 0;

		if(isset($args['selected_tours']) && count($args['selected_tours']) >0) {
			$tour_ids = implode(",", $args['selected_tours']);
			$qs = "tour_id=".$tour_ids;

			if(isset($args['suitable_for_children'])) { $qs .="&suitable_for_children=1"; }
			if(isset($args['suitable_for_solo'])) { $qs .="&suitable_for_solo=1"; }
			if(isset($args['suitable_for_couples'])) { $qs .="&suitable_for_couples=1"; }

			$result = $tourcms->search_tours($qs, $channel_id); 
			if(empty($result->error)) {
				// Try again to query the TourCMS API
				$result = $tourcms->search_tours($qs, $channel_id);
			}

			if($result->error=="OK") {
				foreach($result->tour as $tour) {
					$tour_details[] = $tour;
					$loaded_tour ++;
				}
			}
		}

		// if selected tour not coming from TourCMS Api in that case we will fetch top trending tours from TourCMS as tour count or remaining tour count.
		$extra_tour_cnt = $selected_tour_count - $loaded_tour;
		$tour_diff = $tour_diff + $extra_tour_cnt;

		if($tour_diff > 0) {
			$qs2 = "per_page=25&page=1";
			if($args['key'] == "country-trending-tours") {
				$country = strtoupper($args['country']);
				if(in_array($country, $iso_country_list)) {

					$country_code = array_search($country, $iso_country_list);
					$qs2 .="&country=".$country_code;
				}
			} else if($args['key'] == "city-trending-tours" || $args['key'] == "destination-trending-tours") {
				$qs2 .="&location=".$args['city'];
			}

			if(isset($args['suitable_for_children'])) { $qs2 .="&suitable_for_children=1"; }
			if(isset($args['suitable_for_solo'])) { $qs2 .="&suitable_for_solo=1"; }
			if(isset($args['suitable_for_couples'])) { $qs2 .="&suitable_for_couples=1"; }

			$result2 = $tourcms->search_tours($qs2, $channel_id);
			
			if(empty($result2->error)) {
				// Try again to query the TourCMS API
				$result2 = $tourcms->search_tours($qs2, $channel_id);
			}

			if($result2->error=="OK") {
				$remining_count = 1;
				foreach($result2->tour as $tour2) {
					if(!in_array($tour2->tour_id, $args['selected_tours'])) {
						if($remining_count <= $tour_diff) {
							$tour_details[] = $tour2;
							$remining_count ++;
						} else {
							break;
						}
					}
				}
			}
		}

	}
	
	if(count($tour_details) > 0) { 
		// set TourCMS API response into transient
		$hours = (int)$args['cache_hours'];
		set_data_in_transient($cache_key, $tour_details, $hours*60*60);
	}


	$result = get_data_from_transient($cache_key); 
	return $result;
}

add_action('grayline_tourcms_wp_get_trending_tours', 'get_popular_trending_tours', 10, 1);

function grayline_tourcms_wp_post_id($key, $value) {
	global $wpdb;
	$results = $wpdb->get_row( "select post_id from $wpdb->postmeta where meta_value = $value and meta_key = '".$key."'", ARRAY_A );
	
	return isset($results['post_id'])?$results['post_id']:0;
}
add_action('grayline_tourcms_wp_post_id', 'grayline_tourcms_wp_post_id', 10, 2);


function wp_post_details($page_location, $post_count) {

	$args = array(
            'post_type' => 'post',
            'order'=> 'DESC', 
            'orderby' => 'post_modified',
            'numberposts' => $post_count
       	);

	if(strtolower($page_location) != "home") {
		$args['tax_query'] = array(
		            array(
		                'taxonomy'  => 'post_tag',
		                'field'     => 'slug',
		                'terms'     => sanitize_title( $page_location )
		            )
        			); 
	}
	
	$post_result = get_posts( $args ); 
	if(empty($post_result)) {
		unset($args['tax_query']);
		$post_result = get_posts( $args ); 
	}
	return $post_result;
}
add_action('grayline_tourcms_wp_post_details', 'wp_post_details', 10, 2);

function timeago($date) { 
  $timestamp = strtotime($date);

 

  $strTime = array("Just now", "m", "h", "d");
  $length  = array("60","60","24","30");

 

  $currentTime = time();
  
  if($currentTime >= $timestamp) { 
    $diff     = time()- $timestamp;
    for($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
      $diff = $diff / $length[$i];
    }

 

    $diff = round($diff);
    if($strTime[$i] == "Just now") {
      return $strTime[$i];
    } else return $diff.$strTime[$i];
  }
}
add_action('grayline_tourcms_wp_timeago', 'timeago', 10, 1);

function list_pagination($total_count, $per_page, $page, $adjacents = 6) { 
  	global $wp;
	if($total_count == 0 || $per_page == 0) {
		return; 
	}

	$total_pages = ceil($total_count / $per_page);
	$query_string = parse_url($_SERVER['QUERY_STRING']);
	parse_str($query_string['path'], $params);
	unset($params['pageno']);
  
  	if($total_pages > 1) {
	?>
      <nav aria-label="..." class="float-end pt-3">

        <ul class="pagination">
          <?php if($page < 2) { ?>
            <li class="page-item disabled">
              <span class="page-link"><?php _e('Previous'); ?></span>
            </li>
            <?php } else { 
              
              $current_page['pageno'] = $page - 1;
              $final_params = array_merge($current_page, $params);
              $previous_qs = http_build_query($final_params);
              $previous_url = home_url($wp->request."?".$previous_qs);
            ?>
              <li class="page-item">
                <a class="page-link" href="<?php echo $previous_url; ?>"><?php _e('Previous'); ?></a>
              </li>
          <?php } ?>

          <?php for($i = 1; $i <= $total_pages; $i++) { ?>

              <?php if($page == $i) { ?>
                <li class="page-item active" aria-current="page">
                  <span class="page-link"><?php echo $i; ?></span>
                </li>
              <?php } else { 
                $current_page['pageno'] = $i;
                $final_params2 = array_merge($current_page, $params);
                $page_qs = http_build_query($final_params2);
                $page_url = home_url($wp->request."?".$page_qs);
              ?>
                <li class="page-item"><a class="page-link" href="<?php echo $page_url; ?>"><?php echo $i; ?></a></li>
              <?php } ?>
          <?php } ?>

          <?php if($page < $total_pages) { 

            $current_page['pageno'] = $page + 1;
            $final_params = array_merge($current_page, $params);
            $next_qs = http_build_query($final_params);
            $next_url = home_url($wp->request."?".$next_qs);
          ?>
              <li class="page-item">
                <a class="page-link" href="<?php echo $next_url; ?>"><?php _e('Next'); ?></a>
              </li>
          <?php } else { ?>
              <li class="page-item disabled">
                <span class="page-link"><?php _e('Next'); ?></span>
              </li>
          <?php } ?>
        </ul>
      </nav>
      <?php } 
}


add_action('grayline_tourcms_wp_related_cities_by_country', 'related_cities_by_country', 10, 2);

function related_cities_by_country($country_name, $parent_city_page_id) { 

	if(empty($country_name) || empty($parent_city_page_id)) {
		return;
	}

	$args = array(
	    'post_type' => 'page',
	    'post_status' => 'publish',
	    'post_parent' => $parent_city_page_id,
	    'meta_query' => array(
	        'relation' => 'AND', 
	        array(
	            'key' => 'country',
	            'value' => $country_name,
	            'compare' => '='
	        )
	    )
	);

	$city_pages = new WP_Query( $args );

	return $city_pages;
}

function grayline_tourcms_get_currency() {
	require_once 'senshi/tools/get_currency.php'; 
}

function grayline_tourcms_get_currency_rates() { 
	require_once 'senshi/tools/get_currency_rates.php';
}

// Feefo service reviews start
function grayline_tourcms_get_feefo_service_reviews_daily() { 
	$args = array( false ); 
	//if ( ! wp_next_scheduled( 'grayline_tourcms_sync_daily_feefo_service_reviews', $args ) ) { 
		wp_schedule_event( time(), 'daily', 'grayline_tourcms_sync_daily_feefo_service_reviews', $args );
	//}
}
add_action('init', 'grayline_tourcms_get_feefo_service_reviews_daily', 1);
add_action( 'grayline_tourcms_sync_daily_feefo_service_reviews', 'grayline_tourcms_get_feefo_service_reviews' );

function grayline_tourcms_get_feefo_service_reviews() { 

	require_once 'jobs/cache_feefo_service_reviews.php';

	$feefo = new GrayLineFeefoReviewJobs\CacheFeefoServiceReviews();
	$feefo->run();
}
// Feefo service reviews end

// Feefo product reviews start
function grayline_tourcms_get_feefo_product_reviews_daily() {  
	$args = array( false );
	if ( ! wp_next_scheduled( 'grayline_tourcms_sync_daily_feefo_product_reviews', $args  ) ) { 
		wp_schedule_event( time(), 'daily', 'grayline_tourcms_sync_daily_feefo_product_reviews', $args  );
	}
}
add_action('init', 'grayline_tourcms_get_feefo_product_reviews_daily', 1);
add_action( 'grayline_tourcms_sync_daily_feefo_product_reviews', 'grayline_tourcms_get_feefo_product_reviews' );

function grayline_tourcms_get_feefo_product_reviews() { 

	require_once 'jobs/cache_feefo_product_reviews.php';

	$feefo = new GrayLineFeefoReviewJobs\CacheFeefoProductReviews();
	$feefo->run();
}

function grayline_tourcms_widget_ticket($request) {
	
	$query_params = $request->get_query_params();
	$tourSingle = new GrayLineTourCMSControllers\TourSingle();
	return $tourSingle->get_widget_ticket($query_params);
}

function grayline_tourcms_remove_cart($request) {
	$cartController = new GrayLineTourCMSControllers\CartController();
	$cartController->remove();
}
// Feefo product reviews end

/*function grayline_tourcms_get_deals_dates($requestprint_r();exit;) {

	$query_params = $request->get_query_params();
	print_r($query_params);exit;
	$tourSingle = new GrayLineTourCMSControllers\TourSingle();
	$deals = $tourSingle->get_deals_dates((int)get_post_meta($post->ID, 'grayline_tourcms_wp_tour_id', true ));
	$deal_date = $tourSingle->datesDeals($deals);
}*/

function tour_page_breadcrumbs() {
	global $post;
	$breadcrumbs_details = array();

	$location = get_post_meta($post->ID, 'grayline_tourcms_wp_location', true);
	$country = get_post_meta($post->ID, 'grayline_tourcms_wp_country_name', true);
	$tour_name = get_post_meta($post->ID, 'grayline_tourcms_wp_tour_name', true);

	// home
	$breadcrumbs_details[0]['name'] = 'Home';
	$breadcrumbs_details[0]['url'] = home_url("/");
	$breadcrumbs_details[0]['arrow'] = true;


	// country
	$breadcrumbs_details[1]['name'] = $country;
	$breadcrumbs_details[1]['url'] = home_url("/search?q=".$country);
	$breadcrumbs_details[1]['arrow'] = true;
	if(!empty($country)) {
		$country_data = get_page_url($country, 'country');

		if(isset($country_data['post_id'])) {
			$url = get_permalink($country_data['post_id']);
			$breadcrumbs_details[1]['url'] = $url;
		}
	} 

	// location
	$breadcrumbs_details[2]['name'] = $location;
	$breadcrumbs_details[2]['url'] = home_url("/search?q=".$location);
	$breadcrumbs_details[2]['arrow'] = false;
	if(!empty($location)) {
		$location_data = get_page_url($location, 'city');

		if(isset($location_data['post_id'])) {
			$url = get_permalink($location_data['post_id']);
			$breadcrumbs_details[2]['url'] = $url;
		}
	}

	// tour
	/*$breadcrumbs_details[3]['name'] = $tour_name;
	$breadcrumbs_details[3]['url'] = "#";*/
	return $breadcrumbs_details;
}

function search_page_breadcrumbs($breadcrumbs_details) {

   if(empty($breadcrumbs_details)) {
       return;
   }
   $breadcrumbs = array();
   $breadcrumbs['home'] = home_url("/");

   if(isset($breadcrumbs_details['search_keyword'])) {
       $breadcrumbs['search_keyword'] = $breadcrumbs_details['search_keyword'];
   } else if((isset($breadcrumbs_details['country']) && isset($breadcrumbs_details['location'])) || (isset($breadcrumbs_details['country']) && !isset($breadcrumbs_details['location']))) { 
       $country_data = get_page_url($breadcrumbs_details['country'], 'country');
       if(isset($country_data['post_id'])) {
           $url = get_permalink($country_data['post_id']);
           $breadcrumbs[$breadcrumbs_details['country']] = $url;
       } else {
           $url = home_url("/search?q=".$breadcrumbs_details['country']);
           $breadcrumbs[$breadcrumbs_details['country']] = $url;
       }
       
       if(isset($breadcrumbs_details['location'])) {
           $breadcrumbs['location'] = $breadcrumbs_details['location'];
       }
       
   }
   else if(!isset($breadcrumbs_details['country']) && isset($breadcrumbs_details['location'])) { 
       $breadcrumbs['location'] = $breadcrumbs_details['location'];
   }

   return $breadcrumbs;
}

function get_page_url($name, $type) {
        $args = array();
        if($type == "country") {
	        /*$args = array(
	            'meta_key' => '_wp_page_template',
	            'meta_value' => 'templates/country.php'
	        );*/
	        $args = array( 
			'post_type' => 'page', 
			'ignore_sticky_posts' => 1, 
			'meta_key' => '_wp_page_template', 
			'meta_value' => 'templates/country.php', 
			'meta_compare' => '=',
			'numberposts' => 100 
			);
	   }

	   if($type == "city") { 
	        /*$args = array(
	            'meta_key' => '_wp_page_template',
	            'meta_value' => 'templates/city.php'
	        );*/ 

	        $args = array( 
			'post_type' => 'page', 
			'ignore_sticky_posts' => 1, 
			'meta_key' => '_wp_page_template', 
			'meta_value' => 'templates/city.php', 
			'meta_compare' => '=',
			'numberposts' => 100  
			);
	}
	if(!isset($args)) {
		return;
	}

	$postlist = get_posts( $args );

	$ids = array();
	$post_ids = "";
	foreach ( $postlist as $page ) {

		$ids[] = $page->ID;
	}
	$post_ids = implode("', '",$ids);
	   
        
        global $wpdb;

        if($type == "country") {
	        $query = $wpdb->prepare("SELECT post_id, meta_value FROM wp_postmeta 
	                WHERE meta_key LIKE '%country' AND meta_value = '".$name."' AND post_id IN ('". $post_ids ."')");
	       
	        $result = $wpdb->get_row($query, ARRAY_A);  //print_r($result);exit;
	        return $result;
	   }

	   if($type == "city") { 
	   	$query = $wpdb->prepare("SELECT post_id, meta_value FROM wp_postmeta 
	                WHERE meta_key LIKE '%location' AND meta_value LIKE '%".$name."%' AND post_id IN ('". $post_ids ."')");
	     
        $result = $wpdb->get_row($query, ARRAY_A);  
        return $result;
	   }
    }

function common_page_breadcrumbs($post_id, $post_name) {
	$breadcrumbs_details = array();
	// home
	$breadcrumbs_details[0]['name'] = 'Home';
	$breadcrumbs_details[0]['url'] = home_url("/");
	$breadcrumbs_details[0]['arrow'] = true;

	$selected_page_detail = get_field('breadcrumb_link_page', $post_id);
	
	// selected page
	if(!empty($selected_page_detail)) {
		$breadcrumbs_details[1]['name'] = $selected_page_detail->post_title;
		$breadcrumbs_details[1]['url'] = get_permalink($selected_page_detail->ID);
		$breadcrumbs_details[1]['arrow'] = true;

		$breadcrumbs_details[2]['name'] = $post_name;
		$breadcrumbs_details[2]['arrow'] = false;
	} else {
		$breadcrumbs_details[1]['name'] = $post_name;
		$breadcrumbs_details[1]['arrow'] = false;
	}

	return $breadcrumbs_details;
}
