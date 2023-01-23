<?php

function attachment_exists( $filename ){
  WP_CLI::line('👉 Checking if attachment exists...');
  $attachment = get_page_by_title( $filename, OBJECT, 'attachment' );
  if( $attachment ){
    return $attachment->ID;
  } else {
    return false;
  }
}

function pmd_download_file( $file ){
  $status = false;
  $filename = basename( $file );
  $import_dir = trailingslashit( dirname( __FILE__ ) ) . 'imports/files/';
  if( file_put_contents( $import_dir . $filename, file_get_contents( $file ) ) ){
    WP_CLI::line( '⬇️  Downloaded `' . $filename . '`.');
    $status = true;
  } else {
    WP_CLI::line( '🚨 Could not download `' . $filename . '`.');
  }

  return $status;
}

/**
 * Import a file as an attachment.
 *
 * @param      string  $file   The full URL to the file hosted on the web (e.g. https://pickupmydonation.com/wp-content/uploads/2021/01/chhj-800x400.png).
 *
 * @return     int  The attachment ID.
 */
function pmd_import_attachment( $file ){
  WP_CLI::line('👉 Importing $file = ' . $file );
  $attachment_id = null;
  $wp_upload_dir = wp_upload_dir();

  $filename = basename( $file );
  $filetype = wp_check_filetype( $filename, null );

  $import_dir = trailingslashit( dirname( __FILE__ ) ) . 'imports/files/';
  $import_filename = $import_dir.$filename;

  /**
   * 1. Does the attachment exist?
   */
  $attachment_id = attachment_exists( $filename );

  /**
   * 2. The attachment DOES NOT exist:
   *
   * a. Check to see if we've downloaded it and download if not.
   * b. Create the WP attachment and get the ID.
   */
  if( ! $attachment_id ){
    WP_CLI::line( '🔔 No attachment for `' . $filename . '`.' );

    // 2a. Check to see if we've downloaded it and download if not:
    $downloaded = false;
    if( file_exists( $import_filename ) ){
      $downloaded = true;
    } else {
      $downloaded = pmd_download_file( $file );
    }

    // 2b. Create the WP attachment and get the ID:
    // First we must copy the file inside /wp-content/uploads/:
    copy( $import_filename, $wp_upload_dir['path'] . '/' . $filename );
    if( $downloaded ){
      $args = [
        'post_title'      => $filename,
        'post_mime_type'  => $filetype['type'],
        'guid'            => $wp_upload_dir['url'] . '/' . $filename,
        'post_status'     => 'inherit',
        'post_content'    => '',
      ];
      //WP_CLI::line('👉 importing with $args = ' . print_r( $args, true ) . ' and $import_filename = ' . $import_filename );
      $attachment_id = wp_insert_attachment( $args, $wp_upload_dir['path'] . '/' . $filename );

      require_once( ABSPATH . 'wp-admin/includes/image.php' );
      $attachment_data = wp_generate_attachment_metadata( $attachment_id, $wp_upload_dir['path'] . '/' . $filename );
      wp_update_attachment_metadata( $attachment_id, $attachment_data );
    }
  }

  return $attachment_id;
}