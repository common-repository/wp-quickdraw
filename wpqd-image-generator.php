<?php
/**
 * WP QuickDraw is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * WP QuickDraw is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with WP QuickDraw. If not, see http://www.gnu.org/licenses/gpl.html.
 * 
 * @package     WP_QuickDraw
 * @author      HifiPix
 * @copyright   2020 HifiPix, Inc.
 * @license     http://www.gnu.org/licenses/gpl.html  GNU GPL, Version 3
 * @version     1.5.9
 */
 

namespace Wpqd;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies pretty please!' );

// Includes WP Filesystem
require_once(ABSPATH . 'wp-admin/includes/file.php');

class ImageGenerator {
  // Downloads images from WPQD API and stores them in Wordpress Media Library

  const AR_TOLERANCE = 0.01;

  private $sourceImage;

  /**
   * @var string
   */
  protected $inputFilePath;

  /**
   * @var string
   */
  protected $wpqdUploadsDir;

  /**
   * @var string
   */
  protected $newRootFilename;

  /**
   * @var array
   */
  protected $files;

  // A reference to an instance of this class.
  private static $instance;

  // Returns an instance of this class.
  public static function get_instance() {
    if (null == self::$instance) {
      self::$instance = new ImageGenerator();
    }
    return self::$instance;
  }

  public function __construct() {
    add_action( 'delete_attachment', array( $this, 'delete_sizes' ), 10, 1 );
  }

  /**
   * Delete WPQD-ified images when the attachment post is deleted
   * 
   * @param   int $attachment_id
   * @return  void
   */
  public function delete_sizes( $attachment_id ) {
    WP_Filesystem(); // Init global $wp_filesystem
    global $wp_filesystem;

    $image_metadata = new ImageMetadata( $attachment_id );
    $root_filename = $image_metadata->get_root_filename();
    if ( ! $root_filename ) {
      return;
    }
    $extension = $image_metadata->get_file_extension();
    $pattern = sprintf( '/^%1$s_(\d+_\d{1}_\d+|base)\.%2$s/', $root_filename, $extension );

    $upload_dir = wp_upload_dir();
    $dir = $upload_dir['basedir'] . trailingslashit(WPQD_UPLOADS_FOLDER) . $image_metadata->get_uid();
    $files = $wp_filesystem->dirlist( $dir );

    if ( ! empty($files) ) {
      foreach ( $files as $filename => $data ) {
        if ( preg_match($pattern, $filename) !== 1 ) {
          continue;
        }
        $wp_filesystem->delete( trailingslashit($dir) . $filename );
      }
    }
    $wp_filesystem->rmdir( $dir );
  }

  /**
   * Download WPQD-ified images from S3, save to filesystem
   * 
   * @param   array $result
   * @param   int $post_id
   * @return  bool
   */
  public function store_sizes( $result, $post_id ) {
    $upload_dir = wp_upload_dir();
    $metadata = new ImageMetadata( $post_id );
    $uid = $metadata->get_uid();
    $dir = $upload_dir['basedir'] . trailingslashit( WPQD_UPLOADS_FOLDER ) . $uid;

    if ( ! file_exists($dir) ) {
      mkdir( $dir, 0755, true );
    }

    if ( ! isset( $result['urls'] ) || empty( $result['urls'] ) ) {
      return false;
    }

    foreach ( $result['urls'] as $url ) {
      $new_path = trailingslashit( $dir ) . basename( $url );
      $this->files[] = $new_path;
      $fp = fopen( $new_path, 'w+' ); // open file handle
      $ch = curl_init( $url );
      curl_setopt( $ch, CURLOPT_FILE, $fp );  // output to file
      curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
      curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
      curl_exec( $ch );
      curl_close( $ch );  // closing curl handle
      fclose( $fp );      // closing file handle
    }

    if ( ! $this->validate_files( $result['urls'], $dir ) ) {
      $metadata->set_imageset_generated( false );
      $this->delete_sizes( $post_id );
      return false;
    }

    $attachment = $metadata->get_attachment_metadata();
    $metadata->set_file_extension( pathinfo( $attachment['file'], PATHINFO_EXTENSION ) );
    $metadata->set_root_filename( pathinfo( $attachment['file'], PATHINFO_FILENAME ) );

    // Store Aspect Ratio in Metadata
    $metadata->set_aspectratio( $result['metadata']['aspect_ratio'] );

    // Set largest possible size for image
    $metadata->set_breakpoints( $result['metadata']['breakpoints'] );

    if (isset($result['metadata']['wpqd_ppng_lead_color'])) {
      $metadata->set_ppng_leadcolor( $result['metadata']['wpqd_ppng_lead_color'] );
    }

    $metadata->set_imageset_generated( true );
    $metadata->set_generated_time();
    $metadata->set_enabled( true );
    $metadata->set_try_count();

    do_action( 'wpqd_store_sizes', $this->files );
    return true;
  }

  /**
   * Validate that the files we should have generated, are generated
   * 
   * @param array   $urls
   * @param string  $dir
   * @return  bool
   */
  protected function validate_files( $urls, $dir ) {
    foreach ( $urls as $url ) {
      $path = trailingslashit( $dir ) . basename( $url );
      if ( ! file_exists( $path ) ) {
        return false;
      }
    }
    return true;
  }
} // Class ImageGenerator
