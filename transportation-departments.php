<?php
$sep = str_repeat('-', 60 );

$dry_run = ( isset( $args[0] ) && in_array( $args[0], [ 'true', 'false' ] ) )? filter_var( $args[0], FILTER_VALIDATE_BOOL ) : true ;
if( $dry_run ){
  WP_CLI::line( $sep . "\n⚙️  \$dry_run is ON. The following only shows what would happen\nif running with \$dry_run OFF. To run with \$dry_run OFF, run\nthis file with the first positional argument set to `false`." . "\n" . $sep );
} else {
  WP_CLI::line( '✅ $dry_run is OFF. Processing file...' );
}

$limit = ( isset( $args[1] ) && is_numeric( $args[1] ) )? intval( $args[1] ) : 5 ;
WP_CLI::line( '⚙️  $limit is ' . $limit . "\n" . $sep );

$filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/transportation-departments.csv';
if( ! file_exists( $filename ) )
  WP_CLI::error( '🚨 File `' . $filename . '` not found!' );

$config_filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/.transportation-departments';
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
foreach( $rows as $key => $trans_dept ){
  if( $key === 0 || $key < $config['last_row'] )
    continue;

  if( $counter >= $limit )
    break;

  $exists = post_exists( $trans_dept['post_title'], null, null, 'trans_dept' );
  if( $exists ){
    WP_CLI::line( '🟥 `' . $trans_dept['post_title'] . '` exists. Skipping...' );
  } else {
    WP_CLI::line( '✅ ' . ( $key + 1 ) . '. `' . $trans_dept['post_title'] . '`' );
    $post_args = [
      'post_type'     => 'trans_dept',
      'post_title'    => $trans_dept['post_title'],
      'post_name'     => $trans_dept['post_name'],
      'post_content'  => $trans_dept['post_content'],
      'post_status'   => $trans_dept['post_status'],
    ];
    if( $dry_run ){
      // nothing
    } else {
      $ID = wp_insert_post( $post_args );
    }

    /**
     * FOR REFERENCE ONLY: DELETE AFTER csv_to_meta_field_mapping array is setup:
     */
    /*
    [ 'post_title', 'post_name', 'post_content', 'post_status', 'organization', 'contact_title', 'contact_name', 'contact_email', 'cc_emails', 'phone', 'ad_1_graphic', 'ad_1_link', 'ad_2_graphic', 'ad_2_link', 'ad_3_graphic', 'ad_3_link', 'pickup_codes' ];
    /**/

    $orgObj = get_page_by_title( $trans_dept['organization'], OBJECT, 'organization' );
    $org_id = $orgObj->ID;

    $csv_to_meta_field_mapping = [
      'organization'  => [ 'name' => 'organization', 'key' => 'field_626aa08347f2e' ],
      'contact_title' => [ 'name' => 'contact_title', 'key' => 'field_626aa2147c3ab' ],
      'contact_name'  => [ 'name' => 'contact_name', 'key' => 'field_626aa2257c3ac' ],
      'contact_email' => [ 'name' => 'contact_email', 'key' => 'field_626aa2547c3ad' ],
      'cc_emails'     => [ 'name' => 'cc_emails', 'key' => 'field_626aa2697c3ae' ],
      'phone'         => [ 'name' => 'phone', 'key' => 'field_626aa3e706d0d' ],
      'ads'           => [ 'name' => 'ads', 'key' => 'field_626aa4428ac24' ],
      /*
      'ad_1_graphic'  => $ad_repeater,
      'ad_1_link'     => $ad_repeater,
      'ad_2_graphic'  => $ad_repeater,
      'ad_2_link'     => $ad_repeater,
      'ad_3_graphic'  => $ad_repeater,
      'ad_3_link'     => $ad_repeater,
      /**/
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
          case 'ad_1_graphic':
          case 'ad_1_link':
          case 'ad_2_graphic':
          case 'ad_2_link':
          case 'ad_3_graphic':
          case 'ad_3_link':
            /**
             * We don't process these CSV columns here because they get
             * processed below under the `ads` switch which is handling
             * the $meta_value array which we will feed to the
             * update_field() call for the parent ACF Repeater field.
             */
            // nothing
            break;

          case 'ads':
            for ($i=1; $i < 4; $i++) {
              $graphic = $trans_dept['ad_' . $i . '_graphic'];
              $link = $trans_dept['ad_' . $i . '_link'];
              if( ! empty( $graphic ) ){
                // $graphic needs to be the full URL to a publically hosted
                // image file so that pmd_import_attachment() can download
                // the file to /imports/files/:
                $attachment_id = pmd_import_attachment( $graphic );
                // `ad` => `field_626aa4688ac25`, `ad_url` => field_626aa4f68ac26
                $meta_value[] = [ 'field_626aa4688ac25' => $attachment_id, 'field_626aa4f68ac26' => $link ];
              }
            }
            break;

          case 'organization':
            $meta_value = $org_id;
            break;

          default:
            $meta_value = $trans_dept[ $csv_key ];
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
      'pickup_codes' => 'pickup_code',
    ];
    foreach( $csv_to_taxonomy_mappings as $csv_key => $taxonomy ){
      if( $dry_run ){
        if( ! empty( $trans_dept[ $csv_key ] ) )
          WP_CLI::line( '🔔 Need to import `' . $taxonomy . '`: ' . $trans_dept[ $csv_key ] );
      } else {
        $slugs = ( stristr( $trans_dept[ $csv_key ], ',' ) )? explode( ',', $trans_dept[ $csv_key ] ) : [] ;
        if( 0 < count( $slugs ) ){
          WP_CLI::line( $org['post_title'] . ' 👉 ' . $taxonomy . ' 👉 ' . print_r( $slugs, true ) );
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