<?php

class featuredPost extends WP_Widget {
	function __construct() {
		parent::__construct(false, __('TourCMS Featured Post', "gray-line-licensee-wordpress-tourcms-plugin"), array('description' => __('Displays a list of tours.', "gray-line-licensee-wordpress-tourcms-plugin")));	
	}

	function widget($args, $instance) {
		extract($args);
		$featured_post = apply_filters( 'featured_post', $instance['featured_post'] );
		$args = array(
            'p' => $featured_post,
            'post_type' => 'post');
		$featured_post_result = new WP_Query( $args);

		if ( $featured_post_result->have_posts() ) :
		echo $before_widget;
    while ( $featured_post_result->have_posts() ) : $featured_post_result->the_post();

				if(has_post_thumbnail( get_the_ID() )) {
					$image_id = get_post_thumbnail_id();
					$src_arr = wp_get_attachment_image_src($image_id, 'full');
					$src = $src_arr[0];
					$featured_post_image = wp_get_attachment_url( get_post_thumbnail_id(get_the_ID()), 'thumbnail' );
			    $image_srcset = wp_get_attachment_image_srcset( $image_id, 'full' );
			    $image_sizes = wp_get_attachment_image_sizes( $image_id, 'full' );
				}
			?>

			<section class="news-section">
				<div class="container">
				  <div class="row">
				    <div class="col-12 col-md-7">
				      <a href="#">
				      	<?php	if(!empty($featured_post_image)) { ?>
				      	<img src="<?php echo esc_attr( $featured_post_image );?>" srcset="<?php echo esc_attr( $image_srcset ); ?>" sizes="<?php echo esc_attr( $image_sizes );?>" alt="<?php _e('Gray Line Magazine', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>" title="<?php _e('Gray Line Magazine', 'gray-line-licensee-wordpress-tourcms-plugin'); ?>" width="650" height="433" class="featured-img"></a>
				      	<?php } ?>
				    </div>
				    <div class="col-12 col-md-5">
				      <div class="blog-content">
				        <h3><?php echo _e('Gray Line Magazine', "gray-line-licensee-wordpress-tourcms-plugin"); ?></h3>
				        <h2><?php the_title(); ?></h2>
				        <p><?php the_excerpt(); ?></p>
								<a href="<?php echo esc_url( get_permalink() ) ?>" class="read-more"><?php echo _e('Read the story', "gray-line-licensee-wordpress-tourcms-plugin"); ?> <i class="bi bi-chevron-right"></i></a>
				      </div>
				    </div>
				  </div>
				</div>
			</section>
        <?php endwhile; 
					echo $after_widget;
      	endif;
				?>
        <?php
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['featured_post'] = strip_tags( $new_instance['featured_post'] );

		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'featured_post' => '') );
		$featured_post = $instance['featured_post'];

        $args = array(
          'post_type' => 'post',
          'posts_per_page' => '-1'
        );

        $posts = new WP_Query( $args );
				extract($args);
				
        ?>
				<p>
	        <fieldset>
	        <label for="<?php echo $this->get_field_id( 'featured_post' ); ?>"><?php echo _e('Select a  post'); ?>:</label>
	        <select id="<?php echo $this->get_field_id( 'featured_post' ); ?>" name="<?php echo $this->get_field_name('featured_post');?> ">
	            <?php if ($posts->have_posts()) : while ($posts->have_posts()) : $posts->the_post();?>
	            <option value="<?php the_ID(); ?>" <?php selected( $featured_post, get_the_ID()); ?>><?php the_title();?></option>
	            <?php endwhile; endif; ?>
	        </select>
	        </fieldset>
				</p>
      <?php 
	}
}
	
add_action('widgets_init', function( $output ) { return register_widget("featuredPost"); } );
