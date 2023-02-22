<?php

use function DonationManager\organizations\{get_default_organization};
use function DonationManager\donations\{add_orphaned_donation,orphaned_donation_exists};
use function DonationManager\orphanedproviders\{get_orphaned_provider_contact,get_orphaned_donation_contacts};

$default_org = get_default_organization();

$start_date = $args[0];
if( ! empty( $start_date ) ){
  //WP_CLI::error( '🚨 Please provide a start_date in YYYY-MM-DD format as the first argument when calling this file.' );
  if( ! stristr( $start_date, '-' ) )
    WP_CLI::error( '🚨  start_date must be in YYYY-MM-DD format.' );
  $start_date_array = explode( '-', $start_date );
  if( 3 != count( $start_date_array ) )
    WP_CLI::error( '🚨  start_date must be in YYYY-MM-DD format.' );
}

$query_args = [
  'post_type'   => 'donation',
  'numberposts' => -1,
  'post_status' => 'publish',
  'meta_key'    => 'organization',
  'meta_value'  => $default_org['id'],
  'meta_type'   => 'NUMERIC',
  'orderby'     => 'date',
  'order'       => 'ASC',
];

if( ! empty( $start_date ) ){
  $query_args['date_query'] = [
    'after'     => $start_date,
    'inclusive' => true,
  ];
}

$orphaned_pickup_radius = get_field( 'orphaned_pickup_radius', 'option' );
$radius = ( is_numeric( $orphaned_pickup_radius ) )? $orphaned_pickup_radius : 15 ;

$donations = get_posts( $query_args );
if( $donations ):
  foreach ( $donations as $donation ) {
    WP_CLI::line( '# ' . $donation->ID . ' [' . $donation->post_date . '] ' . $donation->post_title );

    $pickup_codes = wp_get_post_terms( $donation->ID, 'pickup_code', ['fields' => 'names'] );
    $pickup_code = '';
    if( 1 === count( $pickup_codes ) )
      $pickup_code = $pickup_codes[0];
    WP_CLI::line( "\t" . '🔔 $pickup_code = ' . $pickup_code );
    $orphaned_donation_contacts = get_orphaned_donation_contacts([ 'pcode' => $pickup_code, 'limit' => 50, 'radius' => $radius ]);

    if( is_array( $orphaned_donation_contacts ) && 0 < count( $orphaned_donation_contacts ) ){
      foreach ( $orphaned_donation_contacts as $contact_id => $email ) {
        //$exists = orphaned_donation_exists( [ 'contact_id' => $contact_id, 'donation_id' => $donation->ID ] );
        //WP_CLI::line('👉 $exists = ' . $exists );
        add_orphaned_donation( [ 'contact_id' => $contact_id, 'donation_id' => $donation->ID, 'timestamp' => date( "Y-m-d H:i:s", strtotime( $donation->post_date ) ) ] );
      }
    }
  }
endif;