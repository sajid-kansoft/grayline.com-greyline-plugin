<?php

class destinationLandingImageBlock extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('TourCMS Destination Landing Image Block', "gray-line-licensee-wordpress-tourcms-plugin"), array('description' => __('Displays destination landing image block.', "gray-line-licensee-wordpress-tourcms-plugin")));	
	}

	function widget($args, $instance) {
		extract($args);
		
		$image_id = attachment_url_to_postid($instance['image_uri']);
		$src_arr = wp_get_attachment_image_src($image_id, 'full');
		$src = $src_arr[0];
    $image_srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
    $image_sizes = wp_get_attachment_image_sizes( $image_id, 'full' );
		?>

			<div class="image-block">
				<div class="inner">
					<div class="info">

						<a class="hover_link" href="<?php echo $instance['link']; ?>"></a>
						<h2><?php echo $instance['title']; ?></h2>
						<p><?php echo $instance['content']; ?></p>

						<a href="<?php echo $instance['link']; ?>"><?php _e('Are you in?', 'gray-line-licensee-wordpress-tourcms-plugin'); ?></a>
					</div>
					<img src="<?php echo $src; ?>" srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="<?php echo esc_attr( $image_sizes );?>"  alt="<?php echo $instance['title']; ?>">
				</div>
			</div>
    <?php  
			echo $after_widget;
		?>
    <?php
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['sub_title'] = strip_tags( $new_instance['sub_title'] );
		$instance['content'] = strip_tags( $new_instance['content'] );
		$instance['image_uri'] = strip_tags( $new_instance['image_uri'] );
		$instance['link'] = strip_tags( $new_instance['link'] );

		return $instance;
	}

	function form($instance) {
		    $instance = wp_parse_args( (array) $instance, array( 'title' => '', 'sub_title' => '', 'content' => '', 'image_uri' => '', 'link' => '') );
				$title     = strip_tags($instance['title']);
				$sub_title = strip_tags( $instance['sub_title'] );
				$content   = strip_tags( $instance['content'] );
				$image_uri = strip_tags( $instance['image_uri'] );
				$link      = strip_tags( $instance['link'] );
        
				?>
				<p>
					<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id('sub_title'); ?>"><?php _e('Sub Title', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('sub_title'); ?>" name="<?php echo $this->get_field_name('sub_title'); ?>" type="text" value="<?php echo esc_attr($sub_title); ?>" /></label>
				</p>
				<p>
	        <label for="<?= $this->get_field_id( 'image_uri' ); ?>"><?php _e('Image', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: </label>
	        <img class="<?= $this->id ?>_img" src="<?= (!empty($image_uri)) ? $image_uri : ''; ?>" style="margin:0;padding:0;max-width:100%;display:block"/>
	        <input type="text" id="<?= $this->get_field_id( 'image_uri' ); ?>" class="widefat <?= $this->id ?>_url" name="<?= $this->get_field_name( 'image_uri' ); ?>" value="<?= $image_uri ?? ''; ?>" style="margin-top:5px;" />
	        <input type="button" id="<?= $this->id ?>" class="button button-primary banner_custom_upload_media" value="Add Image" style="margin-top:5px;" />
	    </p>
	    <p>
				<label for="<?php echo $this->get_field_id('link'); ?>"><?php _e('Link', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('link'); ?>" name="<?php echo $this->get_field_name('link'); ?>" type="text" value="<?php echo esc_attr($link); ?>" /></label>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('content'); ?>"><?php _e('Content', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>: <textarea class="widefat" id="<?php echo $this->get_field_id('content'); ?>" name="<?php echo $this->get_field_name('content'); ?>"><?php echo esc_attr($content); ?></textarea></label>
			</p>
      <?php 
	}
}
	
add_action('widgets_init', function( $output ) { return register_widget("destinationLandingImageBlock"); } );
