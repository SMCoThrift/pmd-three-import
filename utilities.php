<?php

/**
* post_exists_by_slug
* @param $post_slug
* @param string $type
*
* @return mixed boolean false if no post exists; post ID otherwise.
*/
function post_exists_by_slug( $post_slug,$type = 'post') {
  $args_posts = array(
      'post_type'      => $type,
      'name'           => $post_slug,
      'posts_per_page' => 1,
  );
  $loop_posts = new WP_Query( $args_posts );
  if ( ! $loop_posts->have_posts() ) {
      return false;
  } else {
      $loop_posts->the_post();

      return $loop_posts->post->ID;
  }
}