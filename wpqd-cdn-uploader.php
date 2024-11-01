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

use \W3TC\Dispatcher;

class CdnUploader {

  /**
   * @var $this
   */
  protected static $instance;

  /**
   * Get singleton instance
   * 
   * @return  $this
   */
  public static function get_instance() {
    if (null == self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Class constructor
   */
  public function __construct() {
    add_action( 'wpqd_store_sizes', array( $this, 'upload' ) );
  }

  /**
   * Push generated images to CDN through W3 Total Cache (requires that plugin)
   * 
   * @param   array $files
   * @return  bool
   * @see     W3TC\Cdn_Core::upload
   */
  public function upload( $files ) {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); // Needed for is_plugin_active()
    if ( ! is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
      return;
    }

    $config = Dispatcher::config();
    if ( ! $config->get_boolean( 'cdn.enabled' ) ) {
      return;
    }

    $common = Dispatcher::component( 'Cdn_Core' );
    $cdn = $common->get_cdn();
    $upload = array();
    $results = array();

    foreach ( $files as $file ) {
      $relative_path = str_replace( ABSPATH, '', $file );
      $remote_path = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $relative_path ) );
      $d = $common->build_file_descriptor( $file, $remote_path );
      $d['_original_id'] = $relative_path;
      $upload[] = $d;
    }

    @set_time_limit( $config->get_integer( 'timelimit.cdn_upload' ) );

    // $force_rewrite == true in case images are regenerated with new settings
    $return = $cdn->upload( $upload, $results, true );

    if ( ! $return ) {
      foreach ( $results as $result ) {
        if ( $result['result'] == W3TC_CDN_RESULT_OK ) {
          continue;
        }
        $common->queue_add( $result['local_path'], $result['remote_path'], W3TC_CDN_COMMAND_UPLOAD, $result['error'] );
      }
    }

    return $return;
  }
}
