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
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use \WP_Query;
use \Exception;


class BatchImporter {
  // Imports media to WPQD

  const BATCH_PROCESS_LIMIT = 5;
  const MAX_TRIES = 10000;
  const CRON_HOOK = 'wpqd_import_image';
  const HEALTHCHECK_CRON_HOOK = 'wpqd_import_healthcheck';
  const QUEUE_OPTION_KEY = 'wpqd_batch_import_list';

  /**
   * Queue job types
   */
  const UPLOAD_QUEUE = 'upload';
  const DOWNLOAD_QUEUE = 'download';

  /**
   * Image size constraints
   */
  const MIN_WIDTH = 768;
  const MIN_HEIGHT = 768;
  const MAX_WIDTH = 9600;
  const MAX_HEIGHT = 9600;
  const BREAKPOINT_MIN = 768;

  public static $valid_mime_types = array(
    'image/jpeg',
    'image/gif',
    'image/png'
  );
  protected $start_time = 0;

  /**
   * @var ApiConnector
   */
  protected $connector;

  /**
   * @var ImporterProgress
   */
  protected $progress;

  /**
   * @var PluginUpdater
   */
  protected $updater;

  /**
   * @var QuotaManager
   */
  protected $quota;

  /**
   * @var array
   */
  protected $queues;

  // A reference to an instance of this class.
  private static $instance;

  // Returns an instance of this class.
  public static function get_instance() {
    if (null == self::$instance) {
      self::$instance = new BatchImporter();
    }

    return self::$instance;
  }

  public function __construct() {
    $this->connector = ApiConnector::get_instance();
    $this->progress = ImporterProgress::get_instance();
    $this->quota = QuotaManager::get_instance();
    $this->queues = array();

    add_action( 'init', array( $this, 'schedule_healthcheck_cron' ) );
    add_action( 'redux/options/' . WPQD_OPTIONS . '/saved', array( $this, 'maybe_import_all_media' ), 10, 2 );
    add_action( 'wp_ajax_wpqd_reimport_all_ajax', array( $this, 'reimport_all_ajax' ) );
    add_action( self::CRON_HOOK, array( $this, 'process_queue' ), 10, 1 );
    add_action( self::HEALTHCHECK_CRON_HOOK, array( $this, 'perform_healthcheck' ), 10, 2 );
    add_action( 'wp_ajax_wpqd_reset_queue', array( $this, 'reset_queue_ajax' ) );
    add_filter( 'wp_generate_attachment_metadata', array( $this, 'schedule_import' ), 100, 2 );
    add_filter( 'cron_schedules', array( $this, 'healthcheck_cron_schedules' ) );
  }

  public function healthcheck_cron_schedules( $schedules ) {
    if( ! isset( $schedules["5min"] ) ){
        $schedules["5min"] = array(
            // 'interval' => 5*60,
            'interval' => 60, // run faster for testing
            'display' => __('Once every 5 minutes'));
    }
    return $schedules;
  }

  /**
   * Import all images if enabled in plugin settings
   * 
   * @param array $plugin_settings
   * @param array $changed_values
   * @param bool  $force
   * @param bool  $verbose
   * @return  int|bool
   */
  public function maybe_import_all_media( $plugin_settings, $changed_values, $force = false, $verbose = false ) {
    if ( ! $plugin_settings['enabled'] ) {
      return false;
    }

    // Force imageset regeneration when certain admin settings have changed
    if ( $changed_values && ! empty($changed_values) ) {
      $needs_regenerate = array( 'crop_trim' );
      $common_values = array_intersect( $needs_regenerate, array_keys($changed_values) );
      if ( ! empty($common_values) ) {
        $force = true;
      }
    }
    return $this->import_media( $force, $verbose );
  }

  /**
   * Ajax-friendly force reimport of all images; output # of images regenerated
   * 
   * @return  void
   */
  public function reimport_all_ajax() {
    $result = $this->reimport_all( true );
    $this->get_updater()->update_db_version();
    echo json_encode( $result );
    wp_die();
  }

  /**
   * AJAX-friendly force clean image queue
   * 
   * @return  void
   */
  public function reset_queue_ajax() {
    echo json_encode( $this->reset_queues() );
    wp_die();
  }

  /**
   * Force clean all image queues
   * 
   * @return  int
   */
  public function reset_queues() {
    // Save current state before deleting queues
    $progress = $this->progress->get_transient_nocache();
    if ( is_array( $progress ) ) {
      set_transient( ImporterProgress::TRANSIENT_KEY, $this->progress->get_progress_stats() );
    }

    // Reset progress bar msgs
    $this->progress->reset_download_message();

    // Force delete all queues
    $queues = $this->get_queues( true );
    $rows = 0;
    
    foreach ( $queues as $queue ) {
      $rows += (int) $this->clean_queue( $queue['option_name'] );
    }
    return $rows;
  }

  /**
   * Force reimport all images; return if import ran (non-verbose) or # of images regenerated (verbose)
   * 
   * @param bool  $verbose
   * @return  int|bool
   */
  public function reimport_all( $verbose = false ) {
    $options = get_option( WPQD_OPTIONS );
    return $this->maybe_import_all_media( $options, false, true, $verbose );
  }


  /**
   * Process queue from cron hook
   * 
   * @param   string $queue
   * @return  void
   */
  public function process_queue( $queue ) {
    if ( $empty = $this->is_queue_empty( $queue ) ) {
      $this->clean_queue( $queue );
      return;
    }
    
    switch ( $this->get_queue_type( $queue ) ) {
      case self::UPLOAD_QUEUE:
        $this->export_images( $queue );
        break;
      case self::DOWNLOAD_QUEUE:
        $this->import_image( $queue );
        break;
    }
  }

  /**
   * Import images from WPQD API
   * 
   * @param   string $queue
   * @param   bool  $final_batch
   * @return  void
   */
  public function import_image( $queue ) {
    $images = $this->get_queue_images( $queue );
    if ( empty($images) ) {
        if ( $this->are_queues_empty() ) {
          // Update progress bar status if finished
          $this->progress->set_download_complete();
          $this->reset_queues();
        }
        return;
    }

    $generator = ImageGenerator::get_instance();
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];

    // Get image data
    $post_id = key($images);
    $values = array_values($images);
    $path = array_shift( $values );
    $metadata = new ImageMetadata( $post_id );
    $uid = $metadata->get_uid();
    $tries = (int) $metadata->get_try_count();
    
    if ( ! $uid ) {
      $this->remove_queue_item( $queue, $post_id );
      return;
    }

    try {
      $result = apply_filters('wpqd_pre_download_image', $metadata);

      // If filter had no effect, proceed as usual.
      if ($result == $metadata) {
        $result = $this->connector->get_image_data( $uid );
      }
    } catch ( Exception $e ) {
      $metadata->set_try_count( ++$tries );
      if ( $tries < self::MAX_TRIES ) {
        $this->schedule_next_item( $queue, $metadata );
        return;
      }
      Logger::log_exception( $e );
    }
    
    if ( isset($result['error']) ) {
      if ( $result['message'] === 'The specified key does not exist.' ) {
        // Image has not yet been generated by API
        $metadata->set_try_count( ++$tries );
        if ( $tries < self::MAX_TRIES ) {
          $this->schedule_next_item( $queue, $metadata );
          return;
        }
      } else {
        Logger::log( $result );
        $message = __( 'Something went wrong during image processing', 'wpqd' );
        if ( isset( $result['message'] ) ) {
          $message = $result['message'];
        }
        $metadata->set_message( $message );
        $this->remove_queue_item( $queue, $post_id );
        return;
      }
    }

    if ( $tries >= self::MAX_TRIES ) {
      $this->remove_queue_item( $queue, $post_id );
      return;
    }

    if ( $generator->store_sizes( $result, $post_id ) ) {
      $this->remove_queue_item( $queue, $post_id );
    } else {
      $metadata->set_try_count( ++$tries );
      $this->schedule_next_item( $queue );
    }
  }

  /**
   * Export images to WPQD API
   * 
   * @param   string $queue
   * @return  void
   */
  public function export_images( $queue ) {
    $image_data = $this->validate_images( $queue );
    if ( empty( $image_data['images'] ) ) {
      return false;
    }

    try {
      $result = $this->connector->do_resize( $image_data );
    } catch ( Exception $e ) {
      foreach ( $image_data['images'] as $id => &$image ) {
        $metadata = new ImageMetadata( $id );
        $metadata->set_queue_failed( true );
        $this->remove_queue_item( $queue, $id );
      }

      Logger::log_exception( $e );
      return;
    }

    if ( isset( $result['quota'] ) ) {
      $this->quota->set_stored_quota(
        $this->quota->get_quota_percentage( $result['quota'] )
      );
    }

    if ( isset($result['error']) ) {
      foreach ( $this->get_queue_images( $queue ) as $id => $image ) {
          $metadata = new ImageMetadata( $id );
          $metadata->set_queue_failed( true );
          $metadata->set_message( sprintf(
            __('Error returned from API: %s', 'wpqd'),
            $result['message']
          ) );
          $this->remove_queue_item( $queue, $id );
      }
      return;
    }

    if ( isset($result['errors']) ) {
      foreach ( $this->get_queue_images( $queue ) as $id => $image ) {
        foreach ( $result['errors'] as $error ) {
          if ( basename($image) !== $error['filename'] ) {
              continue;
          }

          $metadata = new ImageMetadata( $id );
          $metadata->set_queue_failed( true );
          $metadata->set_message( sprintf(
            __('Error returned from API: %s', 'wpqd'),
            $error['message']
          ) );
          $this->remove_queue_item( $queue, $id );

          if ( $error['error'] === 'quota_exceeded' ) {
            $this->progress->set_quota_exceeded();
          }
          
          if ( $error['error'] === 'image_too_small' ) {
            $metadata->set_enabled( false );
            $metadata->set_failed( true );
            $metadata->set_under_min_limit( true );
          }

          if ( $error['error'] === 'image_too_large' ) {
            $metadata->set_enabled( false );
            $metadata->set_failed( true );
            $metadata->set_over_max_limit( true );
          }
        } 
      }
    }

    if ( $this->is_queue_empty( $queue ) ) {
      $this->clean_queue( $queue );
      return;
    }

    if ( isset ( $result['images'] ) ) {
      foreach ( $this->get_queue_images( $queue ) as $id => $image ) {
        foreach ( $result['images'] as $image_result ) {
          if ( basename($image) !== $image_result['filename'] ) {
              continue;
          }

          $metadata = new ImageMetadata( $id );
          $metadata->set_uid( $image_result['uid'] );
          $metadata->set_queue_position( $image_result['position'] );
          $this->progress->set_upload_message( $image_result['filename'] );
          break;
        }
      }
    }
    $this->update_queue_type( $queue, self::DOWNLOAD_QUEUE );
    $this->schedule_next_item( $queue );
  }

  /**
   * Schedule healthcheck cron
   * 
   * @return  void
   */
  public function schedule_healthcheck_cron() {
    if ( ! wp_next_scheduled( self::HEALTHCHECK_CRON_HOOK ) ) {
      wp_schedule_event( time(), '5min', self::HEALTHCHECK_CRON_HOOK );
    } 
  }

  /**
   * Do healthcheck cron
   * 
   * @return  void
   */
  public function perform_healthcheck() {
    if ( $this->progress->is_paused() ) {
      return;
    }

    $queues = $this->get_queues();
    if ( empty( $queues ) ) {
      $options = get_option( WPQD_OPTIONS );
      $this->maybe_import_all_media( $options, false );

      if ( $this->progress->is_in_progress() && empty( $this->get_queues() ) ) {
        $this->progress->set_download_complete();
        $this->reset_queues();
      }

      return;
    }

    foreach ( $queues as $queue ) {
      if ( $this->is_queue_empty( $queue['option_name'] ) ) {
        $this->clean_queue( $queue );
        continue;
      }

      // Schedule First Image to process
      $this->schedule_next_item( $queue['option_name'] );
    } 
  }

  /**
   * @return void
   */
  public function schedule_all_queues() {
    $queues = $this->get_queues();
    foreach ( $queues as $queue ) {
      $this->schedule_next_item( $queue['option_name'] );
    }
  }

  /**
   * Schedule import_image() cron event for given queue
   * 
   * @param   string $queue
   * @param   ImageMetadata|null $metadata
   * @param   int $position
   * @return  void
   */
  public function schedule_next_item( $queue, $metadata = null, $position = 1 ) {
     if ( wp_next_scheduled( self::CRON_HOOK, array( $queue ) ) ) {
      return;
    }

    $offset = (int) $position * 30;
    if ( $metadata instanceof \Wpqd\ImageMetadata ) {
      // Schedule retry based on queue position & current try count
      $position = ( (int) $metadata->get_queue_position() ) + 1;
      $tries = $metadata->get_try_count();
      $offset = (int) ( ( $position * 30 ) + (2 * $tries) );
    }

    $timestamp = strtotime( sprintf('+%d seconds', $offset) );
    wp_schedule_single_event( 
      current_time( $timestamp ), 
      self::CRON_HOOK,
      array( $queue )
    );
  }

  /**
   * Schedule import of newly-uploaded image
   * 
   * @param   array   $attachment_metadata
   * @param   int     $attachment_id
   * @return  array
   */
  public function schedule_import( $attachment_metadata, $attachment_id ) {
    $metadata = new ImageMetadata( $attachment_id );
    if ( ! $metadata->get_enabled() ) {
      return $attachment_metadata;
    }

    $images = array(
      $attachment_id => get_attached_file( $attachment_id )
    );

    $queue = $this->add_queue( $images, self::UPLOAD_QUEUE );
    if ( ! $queue ) {
      return $attachment_metadata;
    }

    $totals = array(
      'total' => count($images)
    );

    $mimetype = get_post_mime_type( $attachment_id );
    if ( ! in_array( $mimetype, self::$valid_mime_types ) ) {
      return $attachment_metadata;
    }

    $totals[$mimetype] = count($images);
    $can_init = $this->progress->init_transient( $totals );
    if ( ! $can_init ) {
      return $attachment_metadata;
    }

    $this->schedule_next_item( $queue );
    return $attachment_metadata;
  }

  /**
   * Get list of all WPQD queues in `wp_options`
   * 
   * @param   bool $include_empty
   * @return  array|bool
   */
  public function get_queues( $include_empty = false ) {
    global $wpdb;

    $sql = sprintf( 'SELECT `option_name`, `option_value` FROM `%1$soptions`
      WHERE `option_name` LIKE "%2$s_%%"
      ORDER BY SUBSTRING_INDEX(`option_name`, "_", -1) ASC', 
      $wpdb->prefix,
      self::QUEUE_OPTION_KEY
    );

    $queues = array();
    foreach ( $wpdb->get_results( $sql, 'ARRAY_A' ) as $result ) {
      $result['value'] = unserialize( $result['option_value'] );
      if ( ! $include_empty && empty( $result['value']['images'] ) ) {
        continue;
      }
      unset( $result['option_value'] );
      $queues[] = $result;
    }

    return $queues;
  }

  /**
   * Get a single queue by option name
   * 
   * @param   string $name
   * @return  array|bool
   */
  public function get_queue( $name ) {
    if ( isset( $this->queues[$name] ) ) {
      return $this->queues[$name];
    }

    $queue = get_site_option( $name );
    $this->queues[$name] = $queue;
    return $this->queues[$name];
  }

  /**
   * Get queue images by option name
   * 
   * @param   string $name
   * @return  array|bool
   */
  protected function get_queue_images( $name ) {
    global $wpdb;
    $queue = $this->get_queue( $name );

    if ( ! isset( $queue['images'] ) || empty( $queue['images'] ) ) {
      return false;
    }

    if ( ! is_numeric( reset( $queue['images'] ) ) ) {
      return $queue['images'];
    }

    $results = $wpdb->get_results( sprintf(
      'SELECT post_id, meta_value FROM %s
      WHERE post_id IN (%s)
      AND meta_key = "_wp_attached_file"',
      $wpdb->postmeta,
      implode( ', ', $queue['images'] )
    ) );

    foreach ( $results as $image ) {
      $file = $image->meta_value;
      
      // If the file is relative, prepend upload dir.
      if ( $file && 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ) {
        $uploads = wp_get_upload_dir();
        if ( false === $uploads['error'] ) {
            $file = $uploads['basedir'] . "/$file";
        }
      }
      $image_paths[$image->post_id] = $file;
    }
    return $image_paths;
  }

  /**
   * Get queue type of given queue
   * 
   * @param   string $name
   * @return  string|bool
   */
  protected function get_queue_type( $name ) {
    $queue = $this->get_queue( $name );

    if ( ! isset( $queue['job'] ) ) {
      return false;
    }
    return $queue['job'];
  }

  /**
   * Update queue job type; leave other data unchanged
   * 
   * @param   string $name
   * @param   string $queue_type
   * @return  void
   */
  protected function update_queue_type( $name, $queue_type ) {
    $queue = $this->get_queue( $name );
    $queue['job'] = $queue_type;
    $this->queues[$name] = $queue;
    update_site_option( $name, $queue );
  }

  /**
   * Update existing or create new batch process queue
   * 
   * @param   string $name
   * @param   array $images
   * @param   string|null $queue_type
   * @return  void
   */
  protected function update_queue( $name, $images, $queue_type = null ) {
    $current_queue = $this->get_queue_images( $name );
    if ( ! $current_queue ) {
      return $this->add_queue( $images, self::UPLOAD_QUEUE );
    }

    if ( ! $queue_type ) {
      $queue_type = $this->get_queue_type( $name );
    }

    $data = array(
      'job' => $queue_type,
      'images' => $images
    );
    $this->queues[$name] = $data;
    update_site_option( $name, $data );
  }

  /**
   * Remove an image from the given queue
   * 
   * @param   string $queue
   * @param   int $post_id
   */
  protected function remove_queue_item( $queue, $post_id ) {
      $images = $this->get_queue_images( $queue );
      $queue_type = $this->get_queue_type( $queue );

      switch ( $queue_type ) {
        case self::UPLOAD_QUEUE:
          $this->progress->set_upload_message( basename( $images[$post_id] ) );
          break;
        case self::DOWNLOAD_QUEUE:
          $this->progress->set_download_message( basename( $images[$post_id] ) );
          break;
      }
      unset( $images[$post_id] );

      $this->update_queue( $queue, $images );

      if ( $this->is_queue_empty( $queue ) ) {
        $this->clean_queue( $queue );

        if ( $this->are_queues_empty() ) {
          // Update progress bar status if finished
          $this->progress->set_download_complete();
          $this->reset_queues();
        }
      } else {
        $this->schedule_next_item( $queue );
      }
  }

  /**
   * Push images to the WPQD API resize queue
   * 
   * @param   array $images
   * @param   string $queue_type
   * @param   bool $save
   * @param   int $offset
   * @return  string|bool
   */
  public function add_queue( $images, $queue_type, $save = true, $offset = 0 ) {
    $key = sprintf( '%1$s_%2$s_%3$s', self::QUEUE_OPTION_KEY, microtime( true ), $offset );

    if ( $save ) {
      update_site_option( $key, array(
        'job' => $queue_type,
        'images' => $images,
        'processed' => array(
            'total' => 0,
            'image/jpeg' => 0,
            'image/gif' => 0,
            'image/png' => 0
          )
      ) );
    }
    
    return $key;
  }

  /**
   * Add multiple queues at once for improved efficiency
   * 
   * @param array $queues
   * @return int|bool
   */
  protected function add_multiple_queues( $queues ) {
    global $wpdb;

    $insert_rows = array();
    foreach ( $queues as $queue ) {
      $insert_rows[] = sprintf('(\'%1$s\', \'%2$s\')', 
          $queue['key'],
          serialize( array(
            'job' => $queue['type'],
            'images' => $queue['images'],
            'processed' => array(
                'total' => 0,
                'image/jpeg' => 0,
                'image/gif' => 0,
                'image/png' => 0
              )
          ) )
      );
    }

    $result = false;
    if ( ! empty($insert_rows) ) {
      $query_insert = sprintf(
          'INSERT INTO %1$s (`option_name`, `option_value`) VALUES %2$s',
          $wpdb->options,
          implode(', ', $insert_rows)
      );
      $result = $wpdb->query( $query_insert );
    }
     
    return $result;
  }

  /**
   * Check if batch process queue is empty
   * 
   * @param   string $queue
   * @return  bool
   */
  public function is_queue_empty( $queue ) {
    return empty( $this->get_queue_images( $queue ) );
  }

  /**
   * Check if all batch process queues are empty
   * 
   * @return  bool
   */
  protected function are_queues_empty() {
    if ( ! $this->get_queues() ) {
      return true;
    }
    return false;
  }

  /**
   * Delete empty batch process queues from `wp_options`
   * 
   * @param   string $queue
   * @return  int
   */
  public function clean_queue( $queue ) {
    global $wpdb;
    $result = 0;

    $sql = sprintf( 'DELETE FROM `%1$soptions`
      WHERE `option_name` = "%2$s"',
      $wpdb->prefix,
      $queue
    );
    $result = $wpdb->query( $sql );

    wp_clear_scheduled_hook( self::CRON_HOOK, array( $queue ) );

    if ( $this->are_queues_empty() ) {
      $this->reset_queues();
      $this->progress->set_download_complete();
    }
    return $result;
  }

/**
   * Force reimport some images by type; return if import ran (non-verbose) or # of images regenerated (verbose)
   * 
   * @param bool  $verbose
   * @return  int|bool
   */
  public function reimport_types( $verbose = false, $types = 'all' ) {
    $options = get_option( WPQD_OPTIONS );
    return $this->maybe_import_media_types( $options, false, true, $verbose, $types );
  }

  /**
   * Import some images by type if enabled in plugin settings
   * 
   * @param array $plugin_settings
   * @param array $changed_values
   * @param bool  $force
   * @param bool  $verbose
   * @return  int|bool
   */
  public function maybe_import_media_types( $plugin_settings, $changed_values, $force = true, $verbose = false, $types = 'all' ) {
    if ( ! $plugin_settings['enabled'] ) {
      return false;
    }

    return $this->import_media( $force, $verbose, $types );
  }

 /**
   * Import all, not forced so each image is replaced one by one, if enabled in plugin settings
   * 
   * @param array $plugin_settings
   * @param array $changed_values
   * @param bool  $force
   * @param bool  $verbose
   * @return  int|bool
   */
  public function maybe_import_each( $plugin_settings, $verbose = false) {
    if ( ! $plugin_settings['enabled'] ) {
      return false;
    }

    return $this->import_media( false, $verbose);
  }

  
  /**
   * Import images into WPQD
   * 
   * @param   bool  $force
   * @param   bool  $verbose
   * @return  array|bool
   *   
   * @TODO: store somewhere if and when full import happened
   */
  protected function import_media( $force = false, $verbose = false, $types = 'all' ) {
    if ( $types === 'all' ) {
      $images_result = self::get_all_media_paths( $force );
    } else {
      $images_result = self::get_all_media_paths( $force, $types );
    }

    $can_init = $this->progress->init_transient( $images_result['totals'], true );  
    if ( ! $can_init ) {
      return false;
    }

    $ids = $images_result['images'];

    if ( ! empty( $ids ) ) {
      $this->reset_meta_values( $ids );
    }

    if ( $force ) {
      $this->progress->reset_progress_bar_data();
      $generator = ImageGenerator::get_instance();
      foreach ( $ids as $id ) {
        $generator->delete_sizes( $id );
      }
    } else {
      $this->progress->reset_quota_exceeded();
      $this->progress->reset_download_complete();
    }

    // Add to queue, schedule action with images
    $queues = array();
    foreach( array_chunk( $ids, 5, true ) as $i => $image_batch ) {
      $queue_name = $this->add_queue( $image_batch, self::UPLOAD_QUEUE, false, $i );
      if ( ! $queue_name ) {
        continue;
      }

      $queues[] = array(
        'key' => $queue_name,
        'images' => $image_batch,
        'type' => self::UPLOAD_QUEUE
      );
    }

    if ( ! empty( $queues ) ) {
      $result = $this->add_multiple_queues( $queues );

      if ( $result ) {
        foreach ( $queues as $i => $queue ) {
          $this->schedule_next_item( $queue['key'], null, $i );
        }
      }
    }

    if ( $verbose ) {
        return array(
          'images' => $images_result['totals']['total']
        );
    }

    return true;
  }

  /**
   * Reset image metadata values before regeneration
   * 
   * @param   array $ids
   * @return  int|bool
   */
  protected function reset_meta_values( $ids ) {
    global $wpdb;
    $fields = array(
      ImageMetadata::KEY_IMAGESET_GENERATED,
      ImageMetadata::KEY_TRY_COUNT,
      ImageMetadata::KEY_BREAKPOINTS,
      ImageMetadata::KEY_GENERATED_TIME,
      ImageMetadata::KEY_QUEUE_POSITION,
      ImageMetadata::KEY_QUEUE_FAILED,
      ImageMetadata::KEY_MESSAGE
    );

    $query = sprintf(
        'UPDATE `%1$s` SET `meta_value` = ""
        WHERE `meta_key` IN (%2$s) AND `post_id` IN (%3$s)',
        _get_meta_table( 'post' ),
        '"' . implode( '","', $fields ) . '"',
        implode( ',', $ids )
    );
    return $wpdb->query( $query );
  }

  /**
   * Validate images can be WPQD-ified
   * 
   * @param   string|null $queue
   * @return  array
   */
  protected function validate_images( $queue ) {
    $images = $this->get_queue_images( $queue );
    $images_meta = array();
    $options = get_option( WPQD_OPTIONS );

    foreach ( $images as $id => $image ) {
      $metadata = new ImageMetadata( $id );

      if ( ! file_exists( $image ) ) {
        $metadata->set_enabled( false );
        $metadata->set_failed( true );
        $this->remove_queue_item( $queue, $id );
        unset( $images[$id] );
        continue;
      }

      if ( ! in_array( $metadata->get_mime_type(), self::$valid_mime_types ) ) {
        $metadata->set_enabled( false );
        $metadata->set_failed( true );
        $this->remove_queue_item( $queue, $id );
        unset( $images[$id] );
        continue;
      }

      $attachment = $metadata->get_attachment_metadata();
      $width = $attachment['width'];
      $height = $attachment['height'];

      if ( $width < self::BREAKPOINT_MIN ) {
        $metadata->set_enabled( false );
        $metadata->set_failed( true );
        $metadata->set_under_min_limit( true );
        $this->remove_queue_item( $queue, $id );
        unset( $images[$id] );
        continue;
      }

      if ( $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT ) {
        $metadata->set_enabled( false );
        $metadata->set_failed( true );
        $metadata->set_over_max_limit( true );
        $this->remove_queue_item( $queue, $id );
        unset( $images[$id] );
        continue;
      }

      $filename = basename( $attachment['file'] );
      $images_meta[$filename] = array(
        'width' => $width,
        'height' => $height,
        'pixels' => (int) ( $width * $height ),
        'mimetype' => get_post_mime_type( $id )
      );
    }
    return array(
      'images' => $images,
      'meta' => $images_meta
    );
  }

  /**
   * Get image paths that require import
   * 
   * @param   bool  $force
   * @return  array
   */
  protected static function get_all_media_paths( $force = false, $types = 'all') {
    $mime_types = implode( ',', self::$valid_mime_types );
    if ($types !== 'all' && is_array($types)) {
      $mime_types = implode( ',', $types);
    }

    $args = array(
      'post_type' => 'attachment',
      'post_mime_type' =>'image',
      'post_status' => 'inherit',
      'post_mime_type' => $mime_types,
      'posts_per_page' => -1,
      'orderby' => 'date',
      'meta_query' => array(
        array(
          'key' => ImageMetadata::KEY_ENABLED,
          'value' => 1,
          'compare' => '='
        )
      )
    );

    if ( ! $force ) {
      // Exclude imagesets that have already been generated
      $args['meta_query'][] = array( 'relation' => 'OR',
          array(
            'key' => ImageMetadata::KEY_IMAGESET_GENERATED,
            'value' => '',
            'compare' => 'NOT EXISTS'
          ),
          array(
            'key' => ImageMetadata::KEY_IMAGESET_GENERATED,
            'value' => '',
            'compare' => '=',
          ),
          array(
            'key' => ImageMetadata::KEY_IMAGESET_GENERATED,
            'value' => NULL,
            'compare' => '=',
          )
      );
    }

    $query_images = new WP_Query( $args );
    $images = array();
    $totals = array(
      'total' => $query_images->found_posts,
      'image/jpeg' => 0,
      'image/gif' => 0,
      'image/png' => 0
    );

    foreach ( $query_images->posts as $image ) {
      $images[$image->ID] = $image->ID;
      $totals[$image->post_mime_type]++;
    }

    return array(
      'images' => $images,
      'totals' => $totals
    );
  }

  /**
   * Get plugin updater singleton
   * 
   * @return  PluginUpdater
   */
  protected function get_updater() {
    if ( is_null( $this->updater ) ) {
      $this->updater = PluginUpdater::get_instance();
    }
    return $this->updater;
  }
}
