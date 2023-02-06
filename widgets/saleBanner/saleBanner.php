<?php

class saleBanner extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Sale banner', 'gray-line-licensee-wordpress-tourcms-plugin'), array('description' => __('Displays the sale banner', 'gray-line-licensee-wordpress-tourcms-plugin')));
	}

	function widget($args, $instance) {

		if(!empty($instance['sale_start_date'])) {
			extract($args);
			echo $before_widget;

			$current_date = new DateTime(); // Today
			$sale_start_date_time = new DateTime($instance['sale_start_date'] . ' ' . $instance['sale_start_time']);
			$sale_end_date_time  = new DateTime($instance['sale_end_date'] . ' ' . $instance['sale_end_time']);

			if (
			  	$current_date->getTimestamp() > $sale_start_date_time->getTimestamp() && 
			  	$current_date->getTimestamp() < $sale_end_date_time->getTimestamp()){
			  
				if(!empty($instance['sale_banner_colour_code'])) {
					$background_color = $instance['sale_banner_colour_code'];
				} else {
					$background_color = '#e63945';
				}
				
				$txt = '';
				if(!empty($instance['sale_banner_hover_colour_code'])) {
					$txt = "onmouseover=\"this.style.background='".$instance['sale_banner_hover_colour_code']."';\"";
					$txt .= "onmouseout=\"this.style.background='".$background_color."';\"";
				}
				if(!empty($instance['banner_text']) || !empty($instance['sale_banner_link_text'])) {
				?>	
					<a href="<?php if(!empty($instance['sale_banner_link_to_url'])) echo $instance['sale_banner_link_to_url']; ?>" style="color:#fff">
						<div class="announcement-bar" style="background-color: <?php echo $background_color; ?>;<?php echo "cursor: pointer;"?>" <?php echo $txt ?>>
							<div class="container">
								<p>
						    		<?php if(!empty($instance['banner_text'])) echo $instance['banner_text']; ?>
						      		<?php if(!empty($instance['sale_banner_link_text'])) echo $instance['sale_banner_link_text']; ?>
						  		</p>
							</div>
						</div>
					</a>
			  	<?php
			  	}
			}
			echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;

		$instance['sale_start_date'] = strip_tags($new_instance['sale_start_date']);
		$instance['sale_start_time'] = strip_tags($new_instance['sale_start_time']);
		$instance['sale_end_date'] = strip_tags($new_instance['sale_end_date']);
		$instance['sale_end_time'] = strip_tags($new_instance['sale_end_time']);
		$instance['banner_text'] = strip_tags($new_instance['banner_text']);
		$instance['sale_banner_link_to_url'] = strip_tags($new_instance['sale_banner_link_to_url']);
		$instance['sale_banner_link_text'] = strip_tags($new_instance['sale_banner_link_text']);
		$instance['sale_banner_colour_code'] = strip_tags($new_instance['sale_banner_colour_code']);
		$instance['sale_banner_hover_colour_code'] = strip_tags($new_instance['sale_banner_hover_colour_code']);

		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'sale_start_date' => '', 'sale_start_time' => '', 'sale_end_date' => '', 'sale_end_time' => '', 'banner_text' => '', 'sale_banner_link_to_url' => '', 'sale_banner_link_text' => '', 'sale_banner_colour_code' => '', 'sale_banner_hover_colour_code' => '') );

		$banner_text = strip_tags($instance['banner_text']);
		$sale_start_date = strip_tags($instance['sale_start_date']);
		$sale_start_time = strip_tags($instance['sale_start_time']);
		$sale_end_time = strip_tags($instance['sale_end_time']);
		$sale_end_date = strip_tags($instance['sale_end_date']);
		$sale_banner_link_to_url = strip_tags($instance['sale_banner_link_to_url']);
		$sale_banner_link_text = strip_tags($instance['sale_banner_link_text']);
		$sale_banner_colour_code = strip_tags($instance['sale_banner_colour_code']);
		$sale_banner_hover_colour_code = strip_tags($instance['sale_banner_hover_colour_code']);

		?>

		<h2><?php _e('Server time zone', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h2>
	
		<?php
			$date = new DateTime();
			
			$timeZone = $date->getTimezone();
			$utc_offset = $date->format('Z');
			$sign = '';

			if (strpos($utc_offset, '-') !== false) {
				$sign = '-';
			}

			if (strpos($utc_offset, '+') !== false) {
				$sign = '+';
			}
			
		    $utc_offset_without_sign = str_replace($sign, "", $utc_offset);

			$time_zone_offset_str =  gmdate("H:i", $utc_offset_without_sign);

			echo $timeZone->getName() .' ('. $date->format('T') . ') (UTC' . $sign . $time_zone_offset_str . ')';
			
		?>

		<strong><?php _e("The times set below should be in the server's time zone, not your own.", 'gray-line-licensee-wordpress-tourcms-plugin'); ?></strong>

		<h2><?php _e("Sale Start Date", 'gray-line-licensee-wordpress-tourcms-plugin'); ?> </h2>
		<div style="margin-bottom:30px;">
			<input required id="<?php echo $this->get_field_id('sale_start_date'); ?>" name="<?php echo $this->get_field_name('sale_start_date'); ?>" value="<?php echo esc_attr($sale_start_date); ?>" type="date" >
		</div>
		<div>
			<input id="<?php echo $this->get_field_id('sale_start_time'); ?>" name="<?php echo $this->get_field_name('sale_start_time'); ?>" value="<?php echo esc_attr($sale_start_time); ?>" type="time">
		</div>


		<h2><?php _e("Sale End Date", 'gray-line-licensee-wordpress-tourcms-plugin'); ?></h2>
		<div style="margin-bottom: 30px;">
			<input id="<?php echo $this->get_field_id('sale_end_date'); ?>" name="<?php echo $this->get_field_name('sale_end_date'); ?>" value="<?php echo esc_attr($sale_end_date); ?>" type="date">
		</div>
		<div>
			<input id="<?php echo $this->get_field_id('sale_end_time'); ?>" name="<?php echo $this->get_field_name('sale_end_time'); ?>" value="<?php echo esc_attr($sale_end_time); ?>" type="time">
		</div>

		<h2><?php _e("Banner Text", "gray-line-licensee-wordpress-tourcms-plugin"); ?></h2>
		<input type="text" id="<?php echo $this->get_field_id('banner_text'); ?>" name="<?php echo $this->get_field_name('banner_text'); ?>" value="<?php echo esc_attr($banner_text); ?>" required>

		<h2><?php _e("Banner Link", "gray-line-licensee-wordpress-tourcms-plugin"); ?></h2>
		<p><?php _e("Link text will be added to end of banner text.", "gray-line-licensee-wordpress-tourcms-plugin"); ?></p>

		<div>
			<label><?php _e("Link to URL:", "gray-line-licensee-wordpress-tourcms-plugin"); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('sale_banner_link_to_url'); ?>" name="<?php echo $this->get_field_name('sale_banner_link_to_url'); ?>" value="<?php echo esc_attr($sale_banner_link_to_url); ?>">
		</div>

		<div>
			<label><?php _e("Link Text:", "gray-line-licensee-wordpress-tourcms-plugin"); ?></label>
			<input type="text" id="<?php echo $this->get_field_id('sale_banner_link_text'); ?>" name="<?php echo $this->get_field_name('sale_banner_link_text'); ?>" value="<?php echo esc_attr($sale_banner_link_text); ?>">
		</div>

		<h2><?php _e("Background Color", "gray-line-licensee-wordpress-tourcms-plugin"); ?></h2>
		<p><?php _e("You can enter a hex or rgba value here such as", "gray-line-licensee-wordpress-tourcms-plugin"); ?> <strong><?php _e("#F2F2F2", "gray-line-licensee-wordpress-tourcms-plugin"); ?></strong> <?php _e("or",   "gray-line-licensee-wordpress-tourcms-plugin"); ?> <strong><?php _e("rgba(123,123,233, 1)", "gray-line-licensee-wordpress-tourcms-plugin"); ?></strong><?php _e(". The default will be your brand red.", "gray-line-licensee-wordpress-tourcms-plugin"); ?></p>
		<input type="text" id="<?php echo $this->get_field_id('sale_banner_colour_code'); ?>" name="<?php echo $this->get_field_name('sale_banner_colour_code'); ?>" value="<?php echo esc_attr($sale_banner_colour_code); ?>">
		<p><?php _e("Select a color for the background when a user hovers", "gray-line-licensee-wordpress-tourcms-plugin"); ?></p>
		<input type="text" id="<?php echo $this->get_field_id('sale_banner_hover_colour_code'); ?>" name="<?php echo $this->get_field_name('sale_banner_hover_colour_code'); ?>" value="<?php echo esc_attr($sale_banner_hover_colour_code); ?>">
		<?php
	}
}
	
add_action('widgets_init', function( $output ) { return register_widget("saleBanner"); } );