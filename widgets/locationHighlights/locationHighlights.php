<?php

class locationHighlights extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Location Highlights', "gray-line-licensee-wordpress-tourcms-plugin"), array('description' => __('Displays location highlights', "gray-line-licensee-wordpress-tourcms-plugin")));	
	}

	function widget($args, $instance) {
		extract($args);
		echo $before_widget;
		$image_id = attachment_url_to_postid($instance['image_uri']);
		$src_arr = wp_get_attachment_image_src($image_id, 'full');
		$src = $src_arr[0];
    $image_srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
    $image_sizes = wp_get_attachment_image_sizes( $image_id, 'full' );
    $alt = !empty($instance['location_text']) ? $instance['location_text'] : "Location Highlights";
		?>
        <div class="col-12 col-md-4">
        	<a href="<?php if(!empty($instance['link_to_url'])) echo home_url($instance['link_to_url']); ?>">
          <div class="tour-box">
          	
	            <div class="tour-imgbox">
	              <img src="<?php echo $src; ?>" srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="<?php echo esc_attr( $image_sizes );?>" alt="<?php _e($alt, "gray-line-licensee-wordpress-tourcms-plugin"); ?>" />
	            </div>
	            <?php if(!empty($instance['location_text'])) { ?>
	            <h3><?php echo $instance['location_text']; ?></h3>
	        	<?php } ?>
        	
          </div>
          </a>
        </div>
		<?php
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
        $instance = $old_instance;
				$instance['location_text'] = strip_tags($new_instance['location_text']);
				$instance['image_uri'] = strip_tags( $new_instance['image_uri'] );
        $instance['link_to_url'] = strip_tags( $new_instance['link_to_url'] );
        $instance['link_text'] = strip_tags( $new_instance['link_text'] );

		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'location_text' => '', 'image_uri' => '', 'link_to_url' => '', 'link_text' => '') );
		$location_text = strip_tags($instance['location_text']);
		$image_uri = strip_tags($instance['image_uri']);
		$link_to_url = strip_tags($instance['link_to_url']);
		$link_text = strip_tags($instance['link_text']);
		?>
	    <p>
	        <label for="<?= $this->get_field_id( 'image_uri' ); ?>"><?php echo _e('Location Image', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: </label>
	        <img class="<?= $this->id ?>_img" src="<?= (!empty($image_uri)) ? $image_uri : ''; ?>" style="margin:0;padding:0;max-width:100%;display:block"/>
	        <input type="text" id="<?= $this->get_field_id( 'image_uri' ); ?>" class="widefat <?= $this->id ?>_url" name="<?= $this->get_field_name( 'image_uri' ); ?>" value="<?= $image_uri ?? ''; ?>" style="margin-top:5px;" />
	        <input type="button" id="<?= $this->id ?>" class="button button-primary js_custom_upload_media" value="Upload Image" style="margin-top:5px;" />
	    </p>
		<p>
			<label for="<?php echo $this->get_field_id('location_text'); ?>"><?php echo _e('Location Text', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: <input class="widefat" id="<?php echo $this->get_field_id('location_text'); ?>" name="<?php echo $this->get_field_name('location_text'); ?>" type="text" value="<?php echo esc_attr($location_text); ?>" /></label>
		</p>
	    <p>
	    	<label>Location Url: </label>
	    	<div>
	    		<label for="<?= $this->get_field_id( 'link_to_url' ); ?>"><?php echo _e('Link to URL', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: 
	    		<input class="widefat" id="<?php echo $this->get_field_id('link_to_url'); ?>" name="<?php echo $this->get_field_name('link_to_url'); ?>" type="text" value="<?php echo esc_attr($link_to_url); ?>" /></label>
	    	</div>
	    	<div>
	    		<label for="<?= $this->get_field_id( 'link_text' ); ?>"><?php echo _e('Link Text', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: 
	    		<input class="widefat" id="<?php echo $this->get_field_id('link_text'); ?>" name="<?php echo $this->get_field_name('link_text'); ?>" type="text" value="<?php echo esc_attr($link_text); ?>" /></label>
	    	</div>
	    </p>
		<?php
	}
}

add_action('widgets_init', function( $output ) { return register_widget("locationHighlights"); } );
