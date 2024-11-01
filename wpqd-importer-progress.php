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

class ImporterProgress {
    /**
     * Option names
     */
    const TRANSIENT_KEY = 'wpqd-batch-import-progress';
    const QUEUE_DOWNLOAD_MESSAGE = 'wpqd_importer_progress_download';
    const QUEUE_DOWNLOAD_COMPLETE = 'wpqd_importer_download_complete';
    const QUOTA_EXCEEDED_FLAG = 'wpqd_monthly_quota_exceeded';

    /**
     * Singleton instance
     */
    protected static $instance;

    /**
     * @var array
     */
    protected $status_data;

    /**
     * @var BatchImporter
     */
    protected $importer;

    /**
     * @var QuotaManager
     */
    protected $quota_manager;

    /**
     * Get singleton instance
     * 
     * @return  self
     */
    public static function get_instance() {
        if ( null == self::$instance ) {
        self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * PHP class constructor
     * 
     * @return  void
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'add_admin_notice' ) );
        add_action( 'wp_ajax_wpqd_dismiss_admin_import_success', array( $this, 'dismiss_admin_notice_ajax' ) );
        add_action( 'wp_ajax_wpqd_check_progress', array( $this, 'check_progress_ajax' ) );
        add_action( 'wp_ajax_wpqd_pause_resume_import', array( $this, 'pause_resume_import_ajax' ) );
    }

    /**
     * Set uploaded file message
     * 
     * @return  bool
     */
    public function set_upload_message( $file ) {
      return update_option( self::QUEUE_DOWNLOAD_MESSAGE, 'Processing: ' . $file );
  }

    /**
     * Set downloaded file message
     * 
     * @return  bool
     */
    public function set_download_message( $file ) {
        return update_option( self::QUEUE_DOWNLOAD_MESSAGE, 'Processing: ' . $file );
    }

    /**
     * Reset downloaded file message
     * 
     * @return  bool
     */
    public function reset_download_message() {
        return update_option( self::QUEUE_DOWNLOAD_MESSAGE, 0 );
    }

    /**
     * Set "downloading complete" flag to true
     * 
     * @return  bool
     */
    public function set_download_complete() {
      if ( ! $this->is_quota_exceeded() && $this->check_quota_exceeded() ) {
        $this->set_quota_exceeded();
      }
      return update_option( self::QUEUE_DOWNLOAD_COMPLETE, 1 );
    }

    /**
     * Check status of "downloading complete" flag
     * 
     * @return  bool
     */
    public function is_download_complete() {
      return (bool) get_option( self::QUEUE_DOWNLOAD_COMPLETE, 0 );
    }
    
    /**
     * Reset "downloading complete" flag
     * 
     * @return  bool
     */
    public function reset_download_complete() {
      return update_option( self::QUEUE_DOWNLOAD_COMPLETE, 0 );
    }

    /**
     * Set "quota exceeded" flag to true
     * 
     * @return  bool
     */
    public function set_quota_exceeded() {
      return update_option( self::QUOTA_EXCEEDED_FLAG, 1 );
    }

    /**
     * Check status of "quota exceeded" flag
     * 
     * @return  bool
     */
    public function is_quota_exceeded() {
      return (bool) get_option( self::QUOTA_EXCEEDED_FLAG, 0 );
    }

    /**
     * Check whether monthly quota is exceeded from API
     * 
     * @return  bool
     */
    protected function check_quota_exceeded() {
      return (bool) ( $this->get_quota_manager()->get_stored_quota_percentage() == 0 );
    }
    
    /**
     * Reset "quota exceeded" flag
     * 
     * @return  bool
     */
    public function reset_quota_exceeded() {
      return update_option( self::QUOTA_EXCEEDED_FLAG, 0 );
    }

    /**
     * Reset all progress bar related option values
     * 
     * @return int|bool
     */
    public function reset_progress_bar_data() {
      global $wpdb;
      
      $sql = sprintf(
        'UPDATE %s SET option_value = 0 WHERE option_name IN ("%s")',
        $wpdb->options,
        implode( '", "', array(
          self::QUEUE_DOWNLOAD_MESSAGE, 
          self::QUEUE_DOWNLOAD_COMPLETE,
          self::QUOTA_EXCEEDED_FLAG
        ) )
      );
      return $wpdb->query( $sql );
    }

  /**
   * Display admin notice with image import status
   * 
   * @return  void
   */
  public function add_admin_notice() {
    $updater = PluginUpdater::get_instance();
    if ( ! $this->is_in_progress() && ! $updater->is_update_required() ) {
        return;
    }

    $progress = $this->get_current_progress();    
    $message = sprintf( 
      'WP QuickDraw has detected changes to the Media Library or needs to regenerate images. Analyzing <span class="current">%s</span> file(s). <span class="wpqd-spinner-inline"></span>', 
      isset( $progress['countdown'] ) ? $progress['countdown'] : ''
    );
    $status_message = '';

    if ( $progress['quota_exceeded'] ) {
      $status_message = 'Monthly quota exceeded: some images were not processed.<br />Need more processing? <a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank">Get WP QuickDraw Pro</a>!';
    } elseif ( $progress['download_complete'] ) {
      $status_message = 'Image set generation complete!';
    } elseif ( $progress['download'] == 0 || ! $progress['download'] ) {
      $status_message = 'Queueing up images';
    } else {
      $status_message = $progress['download'];
    }

    $current_status = sprintf(
      '<li class="download-status"><span class="download">%s</span></li>',
      $status_message
    );
    ?>
    <div class="notice notice-warning is-dismissible hide-dismiss" id="wpqd-import-success">
        <p><strong><?php _e( 'WP QuickDraw Image Processing', 'wpqd' ); ?></strong></p>
        <p id="wpqd-progress-message"><?php _e( $message, 'wpqd' ); ?></p>
        <ul class="wpqd-current-status">
          <?php _e( $current_status, 'wpqd' ); ?>
        </ul>
    </div>
    <script>
      jQuery(document).ready( function($) {
        setTimeout( 
            function() { window.hifipixBatchImporter.checkProgress() },
            3000
        );
      });
    </script>
    <?php
  }

  /**
   * Delete transient when dismissing success message (on "X" icon click)
   *
   * @return  void
   */
  public function dismiss_admin_notice_ajax() {
    if ( delete_transient( self::TRANSIENT_KEY ) ) {
        echo true;
    } else {
        echo false;
    }
    wp_die();
  }

  /**
   * Get current number of items processed & failed
   * 
   * @return  array
   */
  protected function get_current_progress() {
    if ( is_null( $this->status_data ) ) {
      $this->get_status_data();
    }

    // Calculate 'countdown' value for progress bar display
    $countdown = (int) ( $this->status_data['totals']['total'] - $this->status_data['totals']['generated'] );
    if ( $countdown < 1 ) {
      $countdown = '';
    }

    return array(
      'processed' => array(
          'total' => $this->status_data['totals']['generated'],
          'image/jpeg' => $this->status_data['image/jpeg']['generated'],
          'image/gif' => $this->status_data['image/gif']['generated'],
          'image/png' => $this->status_data['image/png']['generated']
      ),
      'total' => $this->status_data['totals']['total'],
      'countdown' => $countdown,
      'download' => $this->status_data['download'],
      'download_complete' => $this->status_data['download_complete'],
      'quota_exceeded' => $this->status_data['quota_exceeded']
    );
  }

  /**
   * Query image processing status
   * 
   * @return  array
   */
  public function get_status_data() {
      if ( ! is_null( $this->status_data ) ) {
          return $this->status_data;
      }

      $this->status_data = array_merge(
        $this->get_image_tallies(),
        $this->get_progress_bar_data(),
        array(
          'quota_percent' => $this->get_quota_manager()->get_stored_quota_percentage()
        )
      );
      return $this->status_data;
  }

  /**
   * Tally image progress by type & calculate percentages
   * 
   * @return  array
   */
  protected function get_image_tallies() {
    $data = array(
        'image/jpeg' => array(
            'generated' => 0,
            'total' => 0
        ),
        'image/gif' => array(
            'generated' => 0,
            'total' => 0
        ),
        'image/png' => array(
            'generated' => 0,
            'total' => 0
        ),
        'totals' => array(
            'generated' => 0,
            'total' => 0
        )
    );
    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => implode( ',', BatchImporter::$valid_mime_types ),
        'posts_per_page' => -1,
        'orderby' => 'date'
    );
    $images = new WP_Query( $args );

    // Count images of each valid mimetype
    foreach ( $images->posts as $image ) {
        if ( ! isset( $data[$image->post_mime_type] ) ) {
            continue;
        }

        $metadata = new ImageMetadata( $image->ID );
        $data[$image->post_mime_type]['total']++;
        $enabled = $metadata->get_enabled();

        if ( $enabled || $metadata->get_failed() ) {
          $data['totals']['total']++;
        }       

        $generated = $metadata->get_imageset_generated();
        if ( ( $enabled && $generated ) || $metadata->get_failed() ) {
            $data['totals']['generated']++;
        }

        if ( $enabled && $generated ) {
            $data[$image->post_mime_type]['generated']++;
        }
      }

      // Calculate percentages
      foreach ( $data as $mimetype => $mimetype_data ) {
          if ( $mimetype_data['total'] === 0 ) {
              $data[$mimetype]['percent'] = 100;
              continue;
          }
          $data[$mimetype]['percent'] = round( ($mimetype_data['generated'] / $mimetype_data['total']) * 100 );
      }

      if ( $data['totals']['generated'] > 0 && $data['totals']['percent'] === 100 ) {
        $this->set_download_complete();
      }

      return $data;
    }

    /**
     * Query database for progress bar info
     * 
     * @return  array
     */
    protected function get_progress_bar_data() {
      global $wpdb;
      
      $data = array(
        'download' => '',
        'download_complete' => false,
        'quota_exceeded' => false
      );
      $sql = sprintf(
        'SELECT option_name, option_value FROM %s WHERE option_name IN ("%s")',
        $wpdb->options,
        implode( '", "', array(
          self::QUEUE_DOWNLOAD_MESSAGE, 
          self::QUEUE_DOWNLOAD_COMPLETE,
          self::QUOTA_EXCEEDED_FLAG
        ) )
      );
      
      foreach( $wpdb->get_results( $sql, 'ARRAY_A' ) as $row ) {
        switch ( $row['option_name'] ) {
          case self::QUEUE_DOWNLOAD_MESSAGE:
            $data['download'] = $row['option_value'];
            break;
          case self::QUEUE_DOWNLOAD_COMPLETE:
            $data['download_complete'] = (bool) $row['option_value'];
            break;
          case self::QUOTA_EXCEEDED_FLAG:
            $data['quota_exceeded'] = (bool) $row['option_value'];
            break;
        }
      }
      return $data;
    }

    /**
     * Check status of import progress transient data
     * 
     * @return  void
     */
    public function check_progress_ajax() {
      echo json_encode( $this->get_progress_stats() );
      wp_die();
    }

    /**
     * Merge transient & queue data; calculate percentage complete per mimetype
     * 
     * @return  array
     */
    public function get_progress_stats() {
      $progress = $this->get_transient_nocache();
      if ( ! $progress ) {
        $progress = array();
      }

      $status = $this->get_current_progress();
      $percentages = array(
        'total' => 0,
        'image/jpeg' => 0,
        'image/gif' => 0,
        'image/png' => 0
      );
      return array_merge( $progress, $status );
    }

    /**
     * AJAX-friendly pause or resume the current image generation process
     * 
     * @return  void
     */
    public function pause_resume_import_ajax() {
      echo json_encode( $this->pause_resume_import() );
      wp_die();
    }

    /**
     * Pause current image generation
     * 
     * @return  bool
     */
    public function pause_import() {
      wp_using_ext_object_cache( false );
      $queues = $this->get_importer()->get_queues( true );
      if ( ! empty( $queues ) ) {
        foreach ( $queues as $queue ) {
          wp_clear_scheduled_hook( BatchImporter::CRON_HOOK, array( $queue['option_name'] ) );
        }
      }

      $processed = $this->get_transient_nocache();
      if ( ! is_array($processed) || $this->is_download_complete() ) {
        return false;
      }

      if ( isset($processed['paused']) && $processed['paused'] == true ) {
        return true;
      }

      $processed['paused'] = true;
      return set_transient( self::TRANSIENT_KEY, $processed );
    }

    /**
     * Pause or resume the current image generation process
     * 
     * @return  array
     */
    protected function pause_resume_import() {
      $processed = $this->get_transient_nocache();

      if ( ! is_array($processed) || $this->is_download_complete() ) {
        return $processed;
      }

      if ( isset($processed['paused']) && $processed['paused'] == true ) {
        $processed['paused'] = false;
        set_transient( self::TRANSIENT_KEY, $processed );

        $this->get_importer()->schedule_all_queues();
      } else {
        $processed['paused'] = true;
        set_transient( self::TRANSIENT_KEY, $processed );

        $queues = $this->get_importer()->get_queues();
        foreach ( $queues as $queue ) {
          wp_clear_scheduled_hook( BatchImporter::CRON_HOOK, array( $queue['option_name'] ) );
        }
      }

      return $processed;
    }

    /**
     * Is image processing currently in progress?
     * 
     * @return  bool
     */
    public function is_in_progress() {
        return (bool) ! $this->is_download_complete();
    }

    /**
     * Is image processing currently paused?
     * 
     * @return  bool
     */
    public function is_paused() {
        $processed = $this->get_transient_nocache();
        if ( isset( $processed['paused'] ) && $processed['paused'] ) {
        return true;
        }
        return false;
    }

    /**
     * Initialize transient with data about image import status
     * 
     * @param   array $totals
     * @param   bool  $reset
     * @return  bool
     */
    public function init_transient( $totals, $reset = false ) {
      if ( $totals['total'] < 1 ) {
        return false;
      }
      
      wp_using_ext_object_cache( false );
      $value = $this->get_transient_nocache();

      if ( is_array($value) ) { // Another job already in progress
          if ( $reset ) {
            $this->reset_progress_bar_data();
            delete_transient( self::TRANSIENT_KEY );
            return set_transient( self::TRANSIENT_KEY, array() );
          }

          if ( $this->is_download_complete() ) {
            $this->reset_download_complete();
            $this->reset_download_message();
            $this->reset_quota_exceeded();
          }
          return set_transient( self::TRANSIENT_KEY, $value );
      }

      if ( $this->is_download_complete() ) {
        $this->reset_download_complete();
        $this->reset_download_message();
        $this->reset_quota_exceeded();
      }

      return set_transient( self::TRANSIENT_KEY, array() );
    }

    /**
     * Get progress indicator transient directly from database (bypasses WP cache)
     * 
     * @return  array
     */
    public function get_transient_nocache() {
      global $wpdb;

      $value = $wpdb->get_var( $wpdb->prepare( 
          "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 
          '_transient_' . self::TRANSIENT_KEY 
      ) );

      if ( $value ) {
          $value = unserialize( $value );
      }

      return $value;
    }

    /**
     * Get BatchImporter singleton instance
     * 
     * @return  BatchImporter
     */
    protected function get_importer() {
      if ( is_null( $this->importer ) ) {
        $this->importer = BatchImporter::get_instance();
      }
      return $this->importer;
    }

    /**
     * Get QuotaManager singleton instance
     * 
     * @return  QuotaManager
     */
    protected function get_quota_manager() {
      if ( is_null( $this->quota_manager ) ) {
        $this->quota_manager = QuotaManager::get_instance();
      }
      return $this->quota_manager;
    }
}
