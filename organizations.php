<?php
require_once( trailingslashit( dirname( __FILE__ ) ) . 'attachments.php' );
$sep = str_repeat('-', 60 );

$dry_run = ( isset( $args[0] ) && in_array( $args[0], [ 'true', 'false' ] ) )? filter_var( $args[0], FILTER_VALIDATE_BOOL ) : true ;
if( $dry_run ){
  WP_CLI::line( $sep . "\n⚙️  \$dry_run is ON. The following only shows what would happen\nif running with \$dry_run OFF. To run with \$dry_run OFF, run\nthis file with the first positional argument set to `false`." . "\n" . $sep );
} else {
  WP_CLI::line( '✅ $dry_run is OFF. Processing file...' );
}

$limit = ( isset( $args[1] ) && is_numeric( $args[1] ) )? intval( $args[1] ) : 5 ;
WP_CLI::line( '⚙️  $limit is ' . $limit . "\n" . $sep );

$filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/organizations.csv';
if( ! file_exists( $filename ) )
  WP_CLI::error( '🚨 File `' . $filename . '` not found!' );

$config_filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/.organizations';
$config = json_decode( file_get_contents( $config_filename ), true );
if( ! is_array( $config ) )
  $config = [];
if( ! array_key_exists( 'last_row', $config ) )
  $config['last_row'] = 0;



$row = 0;
$rows = [];
if( ( $fp = fopen( $filename, 'r' ) ) !== FALSE ):
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
//if( array_key_exists( $config['last_row'] - 1, $row ) )
  //WP_CLI::line( '⚙️  Last row imported was ' . $config['last_row'] . ": " . $rows[ $config['last_row'] - 1 ]['post_title'] . "\n" . $sep . "\n" );

//*
$counter = 0;
foreach( $rows as $key => $org ){
  if( $key === 0 || $key < $config['last_row'] )
    continue;

  if( $counter >= $limit )
    break;

  $exists = post_exists( $org['post_title'], null, null, 'organization' );
  if( $exists ){
    WP_CLI::line( '🟥 `' . $org['post_title'] . '` exists. Skipping...' );
  } else {
    WP_CLI::line( '✅ ' . ( $key + 1 ) . '. `' . $org['post_title'] . '`' );
    $post_args = [
      'post_type'     => 'organization',
      'post_title'    => $org['post_title'],
      'post_name'     => $org['post_name'],
      'post_content'  => $org['post_content'],
      'post_status'   => $org['post_status'],
    ];
    if( $dry_run ){
      // nothing
    } else {
      $ID = wp_insert_post( $post_args );
    }

    if( ! $dry_run && ! empty( $org['post_thumbnail'] ) ){
      $attachment_id = pmd_import_attachment( $org['post_thumbnail'] );
      set_post_thumbnail( $ID, $attachment_id );
    }

    $csv_to_meta_field_mapping = [
      'contact_emails'                => [ 'name' => 'monthly_report_emails', 'key' => 'field_6255bd7b677f4' ],
      'website'                       => [ 'name' => 'website', 'key' => 'field_6255bddfb3b78' ],
      'pickup_settings'               => [ 'name' => 'pickup_settings', 'key' => 'field_6255be3bb3b79' ],
      'priority_pickup'               => [ 'name' => 'pickup_settings_priority_pickup', 'key' => 'field_6255be63b3b7a' ],
      'donation_routing'              => [ 'name' => 'pickup_settings_donation_routing', 'key' => 'field_6255beb5b3b7b' ],
      'skip_pickup_dates'             => [ 'name' => 'pickup_settings_skip_pickup_dates', 'key' => 'field_62615dc03f2ae' ],
      'pickup_days'                   => [ 'name' => 'pickup_settings_pickup_dates', 'key' => 'field_62615e2a3f2af' ], // Will this import a comma separated list?
      'minimum_scheduling_interval'   => [ 'name' => 'pickup_settings_minimum_scheduling_interval', 'key' => 'field_62615e713f2b0' ],
      'step_one_note'                 => [ 'name' => 'pickup_settings_step_one_notice', 'key' => 'field_62615f283f2b1' ],
      'provide_additional_details'    => [ 'name' => 'pickup_settings_provide_additional_details', 'key' => 'field_62615f5d3f2b2' ],
      'allow_user_photo_uploads'      => [ 'name' => 'pickup_settings_allow_user_photo_uploads', 'key' => 'field_62615f943f2b3' ],
      'pause_pickups'                 => [ 'name' => 'pickup_settings_pause_pickups', 'key' => 'field_62615fc53f2b4' ],
      'realtor_ad_standard_banner'    => [ 'name' => 'pickup_settings_realtor_ad_standard_banner', 'key' => 'field_62615ff13f2b5' ],
      'realtor_ad_medium_banner'      => [ 'name' => 'pickup_settings_realtor_ad_medium_banner', 'key' => 'field_6261605b3f2b6' ],
      'realtor_ad_link'               => [ 'name' => 'pickup_settings_realtor_ad_link', 'key' => 'field_626160923f2b7' ],
      'realtor_description'           => [ 'name' => 'pickup_settings_realtor_description', 'key' => 'field_626160ae3f2b8' ],
    ];

    /**
     * We setup 2 instances of the same array so we can interate
     * each with the array pointer starting at the first index.
     */
    $array_1 = $csv_to_meta_field_mapping;
    $array_2 = $csv_to_meta_field_mapping;
    foreach ( $array_1 as $csv_key => $meta ) {
      if( $dry_run ){
        // nothing
      } else {
        switch( $csv_key ){
          case 'priority_pickup':
          case 'donation_routing':
          case 'skip_pickup_dates':
          case 'pickup_days':
          case 'minimum_scheduling_interval':
          case 'step_one_note':
          case 'provide_additional_details':
          case 'allow_user_photo_uploads':
          case 'pause_pickups':
          case 'realtor_ad_standard_banner':
          case 'realtor_ad_medium_banner':
          case 'realtor_ad_link':
          case 'realtor_description':
            /**
             * We don't process these CSV columns here because they get
             * processed below under the `pickup_settings` switch which
             * is handling the $meta_value array which we will feed to
             * the update_field() call for the parent ACF Group field.
             */
            // nothing
            break;

          case 'pickup_settings':
            foreach( $array_2 as $key => $sub_field ){
              if( stristr( $sub_field['name'], 'pickup_settings_' ) ){
                $sub_field_name = str_replace( 'pickup_settings_', '', $sub_field['name'] );
                if( 'pickup_dates' == $sub_field_name ){
                  $raw_value = $org[ $key ];
                  if( ! empty( $raw_value ) ){
                    $pickup_dow_map = [ 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' ];
                    $raw_value_array = ( stristr( $raw_value , ',' ) )? explode( ',', $raw_value ) : [ $raw_value ] ;
                    $value = [];
                    foreach ( $raw_value_array as $dow_index ) {
                      $value[] = $pickup_dow_map[ $dow_index ];
                    }
                    $meta_value[ $sub_field_name ] = $value;
                  } else {
                    $meta_value[ $sub_field_name ] = '';
                  }
                } else {
                  $meta_value[ $sub_field_name ] = $org[ $key ];
                }
              }

            }
            //WP_CLI::line( '🔔 $meta_value = ' . print_r( $meta_value, true ) );
            break;

          default:
            $meta_value = $org[ $csv_key ];
        }

        if( isset( $meta_value ) )
          update_field( $meta['key'], $meta_value, $ID );
        unset( $meta_value );
      }
    }

    /**
     * Taxonomy import:
     */
    $csv_to_taxonomy_mappings = [
      'pickup_locations' => 'pickup_location',
      'donation_options' => 'donation_option',
      'pickup_times' => 'pickup_time',
      'screening_questions' => 'screening_question',
    ];
    foreach( $csv_to_taxonomy_mappings as $csv_key => $taxonomy ){
      if( $dry_run ){
        if( ! empty( $org[ $csv_key ] ) )
          WP_CLI::line( '🔔 Need to import `' . $taxonomy . '`: ' . $org[ $csv_key ] );
      } else {
        $slugs = ( stristr( $org[ $csv_key ], ',' ) )? explode( ',', $org[ $csv_key ] ) : [] ;
        if( 0 < count( $slugs ) ){
          WP_CLI::line( 'Importing `' . $org['post_title'] . '` 👉 ' . $taxonomy . 's 👉 ' . implode(', ', $slugs ) );
          wp_set_object_terms( $ID, $slugs, $taxonomy );
        }
      }
    }
  }
  $counter++;
}
$config['last_row'] = $key;

// Write the config:
$config_fp = fopen( $config_filename, 'w' );
$config = json_encode( $config );
fwrite( $config_fp, $config );
fclose( $config_fp );
/**/