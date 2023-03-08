<?php

$args = [
  'post_type'       => 'trans_dept',
  'post_status'     => 'publish',
  'posts_per_page'  => -1,
  'order'           => 'ASC',
  'orderby'         => 'title',
];

$trans_depts = get_posts( $args );

$counter = 0;
// Setup column headings
$column_headings = [
  0   => 'post_title',
  1   => 'post_name',
  2   => 'organization',
  3   => 'by-pass',
  4   => 'new_permalink',
];
$data[ $counter ] = $column_headings;
$counter++;

foreach( $trans_depts as $trans_dept ){
  $org = get_field( 'organization', $trans_dept->ID );
  if( $org ){
    $org_post_title = get_the_title( $org );

    $priority = get_field( 'pickup_settings_priority_pickup', $org );
    if( ! $priority ){
      WP_CLI::line( '✅ $org_post_title = ' . $org_post_title . ', Priority = ' . $priority );
      $data[ $counter ] = [
        'post_title'    => $trans_dept->post_title,
        'post_name'     => $trans_dept->post_name,
        'organization'  => $org_post_title,
        'by-pass'       => 'https://www.pickupmydonation.com/step-one/?oid=' . $org . '&tid=' . $trans_dept->ID,
        'new_permalink' => '/step-one/?oid=' . $org . '&tid=' . $trans_dept->ID,
      ];
      $counter++;
    }
  }
}
//*
$fp = fopen( trailingslashit( dirname( __FILE__ ) ) . 'imports/by-pass-links.csv', 'w' );
foreach( $data as $fields ){
  fputcsv( $fp, $fields );
}
fclose( $fp );
/**/