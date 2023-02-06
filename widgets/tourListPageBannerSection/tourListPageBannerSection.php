<?php

class tourListPageBannerSection extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Tour list page banner', 'gray-line-licensee-wordpress-tourcms-plugin'), array('description' => __('Displays tour list page banner', 'gray-line-licensee-wordpress-tourcms-plugin')));	
	}

	function widget($args, $instance) {
		extract($args);
		echo $before_widget;
		?>
		<section class="page-banner" style="background: url(<?php echo $instance['image_uri']; ?>) no-repeat center;background-position: center;background-size: cover;">
		  <div class="container">
		    <div class="search-formbox">
		      <?php 
		        // Include search bar
		         get_search_form();
		      ?>
		    </div>
		  </div>
		</section>
		<?php
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
        $instance = $old_instance;
		$instance['image_uri'] = strip_tags( $new_instance['image_uri'] );
		
		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'image_uri' => '') );
			$image_uri = strip_tags($instance['image_uri']);
		?>
		<p>
	        <label for="<?= $this->get_field_id( 'image_uri' ); ?>"><?php echo _e('Banner Image', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: </label>
	        <img class="<?= $this->id ?>_img" src="<?= (!empty($image_uri)) ? $image_uri : ''; ?>" style="margin:0;padding:0;max-width:100%;display:block"/>
	        <input type="text" id="<?= $this->get_field_id( 'image_uri' ); ?>" class="widefat <?= $this->id ?>_url" name="<?= $this->get_field_name( 'image_uri' ); ?>" value="<?= $image_uri ?? ''; ?>" style="margin-top:5px;" />
	        <input type="button" id="<?= $this->id ?>" class="button button-primary banner_custom_upload_media" value="Add Image" style="margin-top:5px;" />
	    </p>
		
		<?php
	}

}

add_action('widgets_init', function( $output ) { return register_widget("tourListPageBannerSection"); } );
