<?php

class tourlistblock extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Tour List Block', 'gray-line-licensee-wordpress-tourcms-plugin'), array('description' => __('Displays tour list block', 'gray-line-licensee-wordpress-tourcms-plugin')));	
	}

	function widget($args, $instance) {
		extract($args);
		echo $before_widget;
        
		$tour_list = tour_list($instance);
        
		if( !empty($tour_list->tour) ) {
			$tours = !is_array($tour_list->tour) ? [$tour_list->tour]: $tour_list->tour;
    		
			$feefo = feefo();
			?>
			<section class="product-section">
				<div class="container">
					<div class="row">
						<?php
						foreach($tours as $tour) {
							$feefo_image_url = $feefo->getLogoUrl('grayline-product-stars_en.png', GLWW_CHANNEL . "_" . $tour->tour_id);
							$a1 = is_object($tour->custom1) ? '' : (string)$tour->custom1;
             				$hasCancel = $a1 === 'false' ? false : true;

             				$special_offer = false; 
				            $special_price_orig = 0;
							if ( !empty($tour->soonest_special_offer) && ( (float)$tour->soonest_special_offer->price_1 <= (float)$tour->from_price) ) {
								$special_offer = true;
							
							}
							else if (!empty($tour->recent_special_offer) && ( (float)$tour->recent_special_offer->price_1 <= (float)$tour->from_price) ) {
								$special_offer = true;
							
							}

							$product_badge_code = apply_filters('grayline_tourcms_wp_custom_badge_code',$tour->custom2);
             				$product_badge_text = apply_filters('grayline_tourcms_wp_custom_badge_text',$tour->custom2);
						?>
							<div class="col-12 col-md-6 col-lg-4">
								<a href="<?php echo the_tour_permalink($tour->tour_name_long); ?>">
								    <div class="product-box">
								     	<img src="<?php echo $tour->thumbnail_image; ?>" alt="<?php echo $tour->tour_name_long; ?>" class="lazy">
								     	<div class="product-innerbox">
									       	<h3><?php echo $tour->tour_name; ?></h3>
									       	<?php if (get_option('grayline_tourcms_wp_feefo_show') == 1)
											{ ?>
									       	<div class="feefo-rating">
									         	<img src="<?php echo $feefo_image_url; ?>" alt="Rate from <?php echo $tour->tour_name_long; ?>">
									       	</div>
									       	<?php } ?>
									       	<div class="duration"><i class="bi bi-clock" aria-hidden="true"></i> <?php echo $tour->duration_desc; ?>  </div>

									       	<?php if ($hasCancel) { ?>
									       	<div class="ez-cancel">
									         	<span class="easycancelTourText"><i class="bi bi-check" aria-hidden="true"></i> <?php echo _e('Free Cancellation', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></span>
									         	<div class="freeCancellationMsg"><?php echo _e('Fully refundable when canceled up to 24 hours prior to departure', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</div>
									       	</div>
									        <?php } ?>

									        <?php if (!empty($product_badge_code) && !empty($product_badge_text)) { ?>
						                        <div class="badgeContainer of <?php echo $product_badge_code; ?>">
						                          <div class="badgeDefault of cs-badge <?php echo $product_badge_code; ?>">
						                            <?php echo $product_badge_text; ?>
						                          </div>
						                        </div>
						                    <?php } ?>
						                    
									        <?php if (!empty($special_offer))  { ?>
						                       <div class="badgeContainer  of">
						                          <div class="badgeDefault cs-badge-so of">
						                            <?php _e('Special Offer', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>
						                          </div>
						                      </div>
						                    <?php } ?>
									       	<div class="price">
									         	<h3>From <span data-price="<?php echo $tour->from_price; ?>" data-currency="<?php echo $tour->sale_currency ?>" data-round="true" ><?php echo $tour->from_price_display; ?></span></h3>
									       	</div>
							    	 	</div>
								   	</div>
							 	</a>
							</div>
						<?php
						}
						?>
					</div>
				</div>
			</section>
			<?php
		}

		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;

		$instance['ids'] = strip_tags($new_instance['ids']);
		$instance['category'] = strip_tags($new_instance['category']);
		$instance['location'] = strip_tags($new_instance['location']);
		$instance['special_offer'] = (!empty($new_instance['special_offer']) ? "true" : "");
		$instance['count'] = $new_instance['count'];

        return $instance;
	}

	function form($instance) {
		
		$instance = wp_parse_args( (array) $instance, 
						array( 
							'ids' => '', 
							'category' => '', 
							'location' => '', 
							'special_offer' => '', 
							'count' => '3'
						) 
						);

		$ids = strip_tags($instance['ids']);
		$category = strip_tags($instance['category']);
		$location = strip_tags($instance['location']);
		$special_offer = (!empty($new_instance['special_offer']) ? "true" : "false");
		$count = $instance['count'];
		?>
		<div>
			<ol>
				<li><?php _e('List of Tour IDs', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></li>
				<li><?php _e('TourCMS Category / Categories', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></li>
				<li><?php _e('Tour Location', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></li>
			</ol>
			<hr>
			<h3><?php _e('Method 1: Tour IDs', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p><?php _e('Output a list of tours based on their ID numbers', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></p>
			<p><?php _e('This allows you to specify exactly which tours you want displayed', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></p>
			<p><?php _e('E.g. 12, 13, 112 will show a block of 3 tours, whose ID values are 12, 13 and 112 respectively', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</p>
			<p>
				<input class="widefat" id="<?php echo $this->get_field_id('ids'); ?>" name="<?php echo $this->get_field_name('ids'); ?>" type="text" value="<?php echo esc_attr($ids); ?>" />
			</p>
			<br>
			<br>
			<hr>
			<h3><?php echo _e('Method 2: TourCMS Category / Categories', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p><?php echo _e('Examples', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: </p>
			<ol>
				<li><strong><?php echo _e('category=Rafting', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong> <?php echo _e('This searches for all Rafting tours', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</li>
				<li><strong><?php echo _e('category=San Francisco', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong> <?php echo _e('This searches for all tours tagged with the category San Francisco', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></li>
				<li><strong><?php echo _e('ORcategory=Rafting|Hiking', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong> <?php echo _e('This searches for either Rafting or Hiking tours', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</li>
				<li><strong><?php echo _e('ANDcategory=Rafting|Hiking', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong> <?php echo _e('This searches for tours tagged with both Rafting and Hiking', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</li>
				<li><strong><?php echo _e('category=Rafting&NOTcategory=Hiking', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong> <?php echo _e('This searches for all products that feature Rafting but not Hiking', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>....</li>
			</ol>
			<p>
				<input class="widefat" id="<?php echo $this->get_field_id('category'); ?>" name="<?php echo $this->get_field_name('category'); ?>" type="text" value="<?php echo esc_attr($category); ?>" />
			</p>
			<strong><?php echo _e('Note: Category name(s) must matches value(s) in TourCMS exactly, no typos. Do not use & in your category names', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>.</strong>
			<br>
			<br>
			<hr>
			<h3><?php echo _e('Method 3: Location', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p><?php echo _e('Note - outputs list where location matches the value set in the Primary Location field for tours', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></p>
			<p>
				<input class="widefat" id="<?php echo $this->get_field_id('location'); ?>" name="<?php echo $this->get_field_name('location'); ?>" type="text" value="<?php echo esc_attr($location); ?>" />
			</p>
			<br>
			<br>
			<hr>
			<h3><?php echo _e('Special Offer', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p>
				<input id="<?php echo $this->get_field_id('special_offer'); ?>" name="<?php echo $this->get_field_name('special_offer'); ?>" <?php if($special_offer == '1') echo 'checked = "checked"'; ?> type="checkbox" value="true" />
			</p>
			<br>
			<br>
			<hr>
			<h3><?php echo _e('Size of List (default is 3)', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h3>
			<p>
				<input id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo esc_attr($count); ?>" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');" />
			</p>
		</div>
		<?php
	}
}

add_action('widgets_init', function( $output ) { 
	return register_widget("tourlistblock"); 
} );
