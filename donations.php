<?php
require_once( trailingslashit( dirname( __FILE__ ) ) . 'utilities.php' );
$sep = str_repeat('-', 60 );

$dry_run = ( isset( $args[0] ) && in_array( $args[0], [ 'true', 'false' ] ) )? filter_var( $args[0], FILTER_VALIDATE_BOOL ) : true ;
if( $dry_run ){
  WP_CLI::line( $sep . "\n⚙️  \$dry_run is ON. The following only shows what would happen\nif running with \$dry_run OFF. To run with \$dry_run OFF, run\nthis file with the first positional argument set to `false`." . "\n" . $sep );
} else {
  WP_CLI::line( '✅ $dry_run is OFF. Processing file...' );
}

$filename = $args[1];
if( empty( $filename ) )
  WP_CLI::error( '🚨 Filename empty! Please enter a CSV file of donations as the 2nd positional argument.' );

$file = trailingslashit( dirname( __FILE__ ) ) . 'imports/' . $filename;
if( ! file_exists( $file ) )
  WP_CLI::error( '🚨 File `' . $filename . '` not found! Please check the /imports/ directory for a donations import file.' );

$row = 0;
$rows = [];
if( ( $fp = fopen( $file, 'r' ) ) !== FALSE ):
  while( ( $data = fgetcsv( $fp, 2048, "," ) ) !== FALSE ):
    if( 0 === $row ){
      $columns = $data;
      $rows[ $row ] = $columns;
    } else {
      $num = count( $data );
      for( $c = 0; $c < $num; $c++ ){
        $rows[ $row ][ $columns[$c] ] = $data[$c];
      }
    }
    $row++;
  endwhile;
  fclose( $fp );
endif;

foreach( $rows as $key => $donation ){
  if( ! is_array( $donation ) || ! array_key_exists( 'post_name', $donation ) || '' == $donation['post_name'] )
    continue;

  $exists = post_exists_by_slug( $donation['post_name'], 'donation' );
  if( $exists ){
    $donation_id = $exists;
    WP_CLI::line( '👉 Donation #' . $donation_id . ' `' . $donation['post_title'] . '` exists. Updating...' );

    // Donor Info
    update_field( 'field_629f706dabec9', [ 'name' => $donation['donor_name'] ], $donation_id );
    update_field( 'field_629f706dabec9', [ 'email' => $donation['donor_email'] ], $donation_id );
    update_field( 'field_629f706dabec9', [ 'phone' => $donation['donor_phone'] ], $donation_id );
    // Address
    update_field( 'field_6320887a3c4b3', [ 'company' => $donation['donor_company'] ], $donation_id );
    update_field( 'field_6320887a3c4b3', [ 'street' => $donation['donor_address'] ], $donation_id );
    update_field( 'field_6320887a3c4b3', [ 'city' => $donation['donor_city'] ], $donation_id );
    update_field( 'field_6320887a3c4b3', [ 'state' => $donation['donor_state'] ], $donation_id );
    update_field( 'field_6320887a3c4b3', [ 'zip' => $donation['donor_zip'] ], $donation_id );
    // Referrer
    update_field( 'field_629f71e08b995', $donation['referrer'], $donation_id );
  } else {
    WP_CLI::line( '✅ ' . ( $key ) . '. `' . $donation['post_title'] . '`' );
    $post_date = date( 'Y-m-d H:i:s', strtotime( $donation['post_date'] ) );
    //WP_CLI::line( '🗓 $post_date = ' . $post_date . "\n👉 \$donation[post_date] = " . $donation['post_date'] );
    $post_args = [
      'post_type'     => 'donation',
      'post_title'    => $donation['post_title'],
      'post_name'     => $donation['post_name'],
      'post_status'   => $donation['post_status'],
      'post_date'     => $post_date,
    ];
    if( $dry_run ){
      // nothing
    } else {
      $ID = wp_insert_post( $post_args );

      // Set the pickup_code
      wp_set_object_terms( $ID, $donation['pickup_code'], 'pickup_code' );

      $csv_to_meta_field_mapping = [
        'org_post_title'      => [ 'name' => 'organization', 'key' => 'field_629f7024abec7' ],
        'pickup_description'  => [ 'name' => 'pickup_description', 'key' => 'field_629f716bc64d6' ],
      ];
      foreach ( $csv_to_meta_field_mapping as $csv_key => $meta ) {
        if( $dry_run ){
          // nothing
        } else {
          switch( $csv_key ){
            case 'org_post_title':
              $org = get_page_by_path( $donation['org_post_name'], OBJECT, 'organization' );
              $meta_value = $org->ID;
              break;
            case 'pickup_description':
              $meta_value = $donation[ $csv_key ];
              break;
          }
          if( isset( $meta_value ) )
            update_field( $meta['key'], $meta_value, $ID );
          unset( $meta_value );
        }
      }
    }

    /**
     * Process these fields:
     *
     * - pickup_code
     * - pickup_description
     * - org_post_title, org_post_name
     */


  }
}