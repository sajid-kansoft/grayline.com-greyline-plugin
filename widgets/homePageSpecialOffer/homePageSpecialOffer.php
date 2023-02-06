<?php

class homePageSpecialOffer extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Home page special offer', "gray-line-licensee-wordpress-tourcms-plugin"), array('description' => __('Displays home page spaecial offer', "gray-line-licensee-wordpress-tourcms-plugin")));	
	}

	function widget($args, $instance) {
		if(!empty($instance['offer_status']) && $instance['offer_status'] == 'Visible') {
		extract($args);
		echo $before_widget;
		?>
			<section class="special-offers" style="background: url(<?php echo $instance['image_uri']; ?>) no-repeat center;">
			<div class="container">
				
				<?php if(!empty($instance['title_text'])) { ?>
				<h2><?php echo $instance['title_text']; ?></h2>
				<?php } ?>
				
				<?php  if(!empty($instance['sub_title_text'])) { ?>
				<p><?php echo $instance['sub_title_text']; ?></p>
				<?php } ?>

				<?php if(!empty($instance['link_to_url'])) { ?>
				<p class="text-center mt-4"><a href="<?php if(!empty($instance['link_to_url'])) echo home_url($instance['link_to_url']); ?>" class="btn"><?php echo $instance['link_text']; ?></a></p>
				<?php } ?>
			</div>
			</section>
		<?php
		echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
        $instance = $old_instance;
		$instance['title_text'] = strip_tags($new_instance['title_text']);
		$instance['sub_title_text'] = strip_tags($new_instance['sub_title_text']);
        $instance['image_uri'] = strip_tags( $new_instance['image_uri'] );
        $instance['link_to_url'] = strip_tags( $new_instance['link_to_url'] );
        $instance['link_text'] = strip_tags( $new_instance['link_text'] );
        $instance['offer_status'] = strip_tags( $new_instance['offer_status'] );

        return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title_text' => '', 'sub_title_text' => '', 'image_uri' => '', 'link_to_url' => '', 'link_text' => '', 'offer_status' => '') );
		$title_text = strip_tags($instance['title_text']);
		$sub_title_text = strip_tags($instance['sub_title_text']);
		$image_uri = strip_tags($instance['image_uri']);
		$link_to_url = strip_tags($instance['link_to_url']);
		$link_text = strip_tags($instance['link_text']);
		$offer_status = strip_tags($instance['offer_status']);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title_text'); ?>"><?php echo _e('Text Title', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title_text'); ?>" name="<?php echo $this->get_field_name('title_text'); ?>" type="text" value="<?php echo esc_attr($title_text); ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('sub_title_text'); ?>"><?php echo _e('Text Title', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: <input class="widefat" id="<?php echo $this->get_field_id('sub_title_text'); ?>" name="<?php echo $this->get_field_name('sub_title_text'); ?>" type="text" value="<?php echo esc_attr($sub_title_text); ?>" /></label>
		</p>
	    <p>
	        <label for="<?= $this->get_field_id( 'image_uri' ); ?>"><?php echo _e('Image', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: </label>
	        <img class="<?= $this->id ?>_img" src="<?= (!empty($image_uri)) ? $image_uri : ''; ?>" style="margin:0;padding:0;max-width:100%;display:block"/>
	        <input type="text" id="<?= $this->get_field_id( 'image_uri' ); ?>" class="widefat <?= $this->id ?>_url" name="<?= $this->get_field_name( 'image_uri' ); ?>" value="<?= $image_uri ?? ''; ?>" style="margin-top:5px;" />
	        <input type="button" id="<?= $this->id ?>" class="button button-primary js_custom_upload_media" value="<?php _e('Upload Image', "gray-line-licensee-wordpress-tourcms-plugin"); ?>" style="margin-top:5px;" />
	    </p>
	    <p>
	    	<label><?php echo _e('Offer Link', "gray-line-licensee-wordpress-tourcms-plugin"); ?></label>
	    	<div>
	    		<label for="<?= $this->get_field_id( 'link_to_url' ); ?>"><?php echo _e('Link to URL', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: 
	    		<input class="widefat" id="<?php echo $this->get_field_id('link_to_url'); ?>" name="<?php echo $this->get_field_name('link_to_url'); ?>" type="text" value="<?php echo esc_attr($link_to_url); ?>" /></label>
	    	</div>
	    	<div>
	    		<label for="<?= $this->get_field_id( 'link_text' ); ?>"><?php echo _e('Link Text', "gray-line-licensee-wordpress-tourcms-plugin"); ?>: 
	    		<input class="widefat" id="<?php echo $this->get_field_id('link_text'); ?>" name="<?php echo $this->get_field_name('link_text'); ?>" type="text" value="<?php echo esc_attr($link_text); ?>" /></label>
	    	</div>
	    </p>
	    <p>
	    	<label for="<?php echo $this->get_field_id('offer_status'); ?>"><?php echo _e('Offer Status', "gray-line-licensee-wordpress-tourcms-plugin"); ?>:
				<select class="widefat" id="<?php echo $this->get_field_id('offer_status'); ?>" name="<?php echo $this->get_field_name('offer_status'); ?>">
					<?php
						$offer_status_arr = ['Visible', 'Hidden'];
						foreach ($offer_status_arr as $value) {
							echo '<option value="'.$value.'"';
							if($value==$offer_status)
								echo ' selected="selected"';
							echo '>'.$value;
							echo '</option>';
						}
					?>
				</select>
			</label>
	    </p>
    	<?php
	}
}

add_action('widgets_init', function( $output ) { return register_widget("homePageSpecialOffer"); } );
