<?php
require_once( trailingslashit( dirname( __FILE__ ) ) . 'utilities.php' );
$sep = str_repeat('-', 60 );

$dry_run = ( isset( $args[0] ) && in_array( $args[0], [ 'true', 'false' ] ) )? filter_var( $args[0], FILTER_VALIDATE_BOOL ) : true ;
if( $dry_run ){
  WP_CLI::line( $sep . "\n⚙️  \$dry_run is ON. The following only shows what would happen\nif running with \$dry_run OFF. To run with \$dry_run OFF, run\nthis file with the first positional argument set to `false`." . "\n" . $sep );
} else {
  WP_CLI::line( '✅ $dry_run is OFF. Processing file...' );
}

$limit = ( isset( $args[1] ) && is_numeric( $args[1] ) )? intval( $args[1] ) : 5 ;
WP_CLI::line( '⚙️  $limit is ' . $limit . "\n" . $sep );

$filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/stores.csv';
if( ! file_exists( $filename ) )
  WP_CLI::error( '🚨 File `' . $filename . '` not found!' );

$config_filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/.stores';
$config = json_decode( file_get_contents( $config_filename ), true );
if( ! is_array( $config ) )
  $config = [];
if( ! array_key_exists( 'last_row', $config ) )
  $config['last_row'] = 0;

$reset = ( isset( $args[2] ) && in_array( $args[2], [ 'true', 'false' ] ) )? filter_var( $args[2], FILTER_VALIDATE_BOOL ) : false ;
if( $reset ){
  WP_CLI::line( $sep . "\n⚙️  \$reset is ON. Setting `last_row` to `0`" . "\n" . $sep );
  $config['last_row'] = 0;
}

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

$counter = 0;
foreach( $rows as $key => $store ){
  if( $key === 0 || $key < $config['last_row'] )
    continue;

  if( $counter >= $limit )
    break;

  $exists = post_exists_by_slug( $store['post_name'], 'store' );
  if( $exists ){
    WP_CLI::line( '🟥 `' . $store['post_title'] . '` exists. Skipping...' );
  } else {
    WP_CLI::line( '✅ ' . ( $key + 1 ) . '. `' . $store['post_title'] . '`' );
    $post_args = [
      'post_type'     => 'store',
      'post_title'    => $store['post_title'],
      'post_name'     => $store['post_name'],
      'post_status'   => $store['post_status'],
    ];
    if( $dry_run ){
      // nothing
    } else {
      $ID = wp_insert_post( $post_args );
    }

    $transDeptObj = get_page_by_title( $store['trans_dept'], OBJECT, 'trans_dept' );
    $trans_dept_id = $transDeptObj->ID;

    $csv_to_meta_field_mapping = [
      'trans_dept'        => [ 'name' => 'trans_dept', 'key' => 'field_62a0a8a24da3b' ],
      'address'           => [ 'name' => 'address', 'key' => 'field_62a0a8c44da3c' ],
      'address_street'    => [ 'name' => 'address_street', 'key' => 'field_62a0a8da4da3d' ],
      'address_city'      => [ 'name' => 'address_city', 'key' => 'field_62a0a8e04da3e' ],
      'address_state'     => [ 'name' => 'address_state', 'key' => 'field_62a0a8ed4da3f' ],
      'address_zip_code'  => [ 'name' => 'address_zip_code', 'key' => 'field_62a0a8f34da40' ],
      'address_phone'     => [ 'name' => 'address_phone', 'key' => 'field_62a0a8fe4da41' ],
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
          case 'address_street':
          case 'address_city':
          case 'address_state':
          case 'address_zip_code':
          case 'address_phone':
            /**
             * We don't process these CSV columns here because they get
             * processed below under the `address` switch which is handling
             * the $meta_value array which we will feed to the
             * update_field() call for the parent ACF Repeater field.
             */
            // nothing
            break;

          case 'address':
            $meta_value = [
              'street'    => $store['address_street'],
              'city'      => $store['address_city'],
              'state'     => $store['address_state'],
              'zip_code'  => $store['address_zip_code'],
              'phone'     => $store['address_phone'],
            ];
            break;

          case 'trans_dept':
            $meta_value = $trans_dept_id;
            break;

          default:
            $meta_value = $store[ $csv_key ];
        }

        if( isset( $meta_value ) )
          update_field( $meta['key'], $meta_value, $ID );
        unset( $meta_value );
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