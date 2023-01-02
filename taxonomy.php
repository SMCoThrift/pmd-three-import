<?php

$taxonomy = $args[0];
if( empty( $taxonomy ) )
  WP_CLI::error( '🚨 Please provide a taxonomy as the first argument when calling this file.' );

if( ! taxonomy_exists( $taxonomy ) )
  WP_CLI::error( '🚨 No taxonomy found for `' . $taxonomy . '`!');

$filename = trailingslashit( dirname( __FILE__ ) ) . 'imports/' . $taxonomy . '.csv';
if( ! file_exists( $filename ) )
  WP_CLI::error( '🚨 File `' . $filename . '` not found!' );

$row = 0;
$rows = [];
if( ( $fp = fopen( $filename, 'r' ) ) !== FALSE ){
  while( ( $data = fgetcsv( $fp, 2048, "," ) ) !== FALSE ):
    if( 0 === $row ){
      $columns = $data;
      $rows[ $row ] = $columns;
      //WP_CLI::error( '🚨 $columns = ' . print_r( $columns, true ) );
    } else {
      //*
      $num = count( $data );
      for( $c = 0; $c < $num; $c++ ){
        $rows[ $row ][ $columns[$c] ] = $data[$c];
      }
      /**/
    }
    $row++;
  endwhile;
  fclose( $fp );
}
WP_CLI::line( '👉 Importing `' . $taxonomy . '` taxonomy.' );

//WP_CLI::success( '👉 $rows = ' . print_r( $rows, true ) );
$counter = 2;
if( $rows && is_array( $rows ) && 0 < count( $rows ) ):
  foreach( $rows as $key => $row ){
    if( 0 === $key ){
      $columns = $row;
    } else {
      if( ! term_exists( $row['slug'], $row['taxonomy'] ) ) {
        // import the term
        $term_array = wp_insert_term( $row['name'], $row['taxonomy'], [
          'description' => $row['description'],
          'slug'        => $row['slug'],
        ]);
        $term_id = $term_array['term_id'];

        if( is_int( $term_id ) && 4 < count( $row ) ){
          $field_id = $row['taxonomy'] . '_' . $term_id;
          WP_CLI::success( "\n" . '✅ Added "' . $row['name'] . '" (' . $field_id . ').' );
          foreach( $row as $key => $value ){
            if( in_array( $key, [ 'name', 'slug', 'taxonomy', 'description' ] ) )
              continue;
            //add_term_meta( $term_id, $key, $value );
            /*
            if( in_array( $value, [ 0,1,'yes','no' ] ) ){
              if( in_array( $value, [ 0, 'no' ] ) ){
                $value = 'false';
              } else if( in_array( $value, [ 1, 'yes' ] ) ){
                $value = 'true';
              }
            }
            */
            $success = update_field( $key, $value, $field_id );
            WP_CLI::line( "👉 update_field( $key, $value, $field_id ) = $success" );
          }
        }
      } else {
        WP_CLI::line( '🚨 ' . $counter . '. `' . $row['name'] . '` exists. Skipping...' );
      }
      $counter++;
    }

  }
endif;