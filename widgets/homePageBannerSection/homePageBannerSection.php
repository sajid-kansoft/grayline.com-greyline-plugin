<?php

class homePageBannerSection extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('Home page banner', "gray-line-licensee-wordpress-tourcms-plugin"), array('description' => __('Displays home page banner', "gray-line-licensee-wordpress-tourcms-plugin")));	
	}

	function widget($args, $instance) {
		extract($args);
		echo $before_widget;
		?>
		<section class="hero-section">
			<?php if(!empty($instance['banner_url'])) : ?>
		    	<div id="initHp" class="initHp  play"><h2><span><?php echo _e('Watch Video'); ?></span> 
				<i class="bi bi-play-circle-fill"></i>
				<i class="bi bi-pause-circle-fill"></i>
			</h2></div>
			<?php endif; ?>
		    <div class="videoContainer">
		      <div class="senshi-bgV js-backstretch" id="HeaderBanner" data-image="<?php echo $instance['image_uri']; ?>">
		      	<!-- poster="<?php //echo $instance['image_uri']; ?>" -->
		        <video id="vimeoVV" loop=""  preload="none">
		          <source src="<?php if(!empty($instance['banner_url'])) echo $instance['banner_url']; ?>" type="video/mp4">
		        </video>		    	
		      </div>

		    </div>
			<div class="video-content">
				<div class="container">
					<?php if(!empty($instance['title']) || !empty($instance['title'])) { ?>
						<h1>
							<?php if(!empty($instance['title'])) {
								echo $instance['title'];
							} ?>
							<?php if(!empty($instance['sub_title'])) { ?>
								<span><?php echo $instance['sub_title']; ?></span>
							<?php } ?>
						</h1>
						<div class="search-formbox">
							<?php
								get_search_form();
							?>
						</div>
					<?php } ?>
				</div>
			</div>
		</section>	
		<?php
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
        $instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['sub_title'] = strip_tags($new_instance['sub_title']);
		$instance['image_uri'] = strip_tags( $new_instance['image_uri'] );
		$instance['banner_url'] = strip_tags( $new_instance['banner_url'] );

		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'sub_title' => '','image_uri' => '', 'banner_url' => '') );
		$title = strip_tags($instance['title']);
		$sub_title = strip_tags($instance['sub_title']);
		$image_uri = strip_tags($instance['image_uri']);
		$banner_url = strip_tags($instance['banner_url']);

		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php echo _e('Title'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('sub_title'); ?>"><?php echo _e('Sub Title'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('sub_title'); ?>" name="<?php echo $this->get_field_name('sub_title'); ?>" type="text" value="<?php echo esc_attr($sub_title); ?>" /></label>
		</p>
	    <p>
	        <label for="<?= $this->get_field_id( 'image_uri' ); ?>"><?php echo _e('Banner Image'); ?>: </label>
	        <img class="<?= $this->id ?>_img" src="<?= (!empty($image_uri)) ? $image_uri : ''; ?>" style="margin:0;padding:0;max-width:100%;display:block"/>
	        <input type="text" id="<?= $this->get_field_id( 'image_uri' ); ?>" class="widefat <?= $this->id ?>_url" name="<?= $this->get_field_name( 'image_uri' ); ?>" value="<?= $image_uri ?? ''; ?>" style="margin-top:5px;" />
	        <input type="button" id="<?= $this->id ?>" class="button button-primary banner_custom_upload_media" value="Add Image" style="margin-top:5px;" />
	    </p>
		<p>
			<label for="<?php echo $this->get_field_id('banner_url'); ?>"><?php echo _e('Banner url'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('banner_url'); ?>" name="<?php echo $this->get_field_name('banner_url'); ?>" type="text" value="<?php echo esc_attr($banner_url); ?>" /></label>
		</p>
		<?php
	}

}

add_action('widgets_init', function( $output ) { return register_widget("homePageBannerSection"); } );
