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

use \WP_Query;
use \Exception;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies pretty please!' );

class AdminDashboard {
    //Generate and Control Admin Dashboard

    /**
     * @var $this
     */
    protected static $instance;

    /**
     * @var ApiConnector
     */
    protected $connector;

    /**
     * @var ImporterProgress
     */
    protected $importer;

    /**
     * @var QuotaManager
     */
    protected $quota;

    /**
     * PHP class constructor
     */
    public function __construct() {
        $this->connector = ApiConnector::get_instance();
        $this->importer = ImporterProgress::get_instance();
        $this->quota = QuotaManager::get_instance();

        add_action( 'wp_ajax_wpqd_get_status_data', array( $this, 'get_status_data_ajax' ) );

        // General Info
        add_filter( 'wpqd_general_info', array( $this, 'get_general_info' ) );
        add_filter( 'wpqd_general_info_account', array( $this, 'get_account_tier' ) );
        add_filter( 'wpqd_general_info_bandwidth', array( $this, 'get_remaining_bandwidth' ) );
        add_filter( 'wpqd_general_info_hostname', array( $this, 'get_hostname' ) );

        // Image Library Status
        add_filter( 'wpqd_library_status', array( $this, 'get_library_status' ) );
        add_filter( 'wpqd_library_status_data', array( $this, 'get_library_status_data' ) );

        // Processing Status
        add_filter( 'wpqd_processing_status', array( $this, 'get_processing_status' ) );
        add_filter( 'wpqd_processing_status_data', array( $this, 'get_processing_status_data' ) );

        // Sections Below Settings
        add_action( 'redux/page/' . WPQD_OPTIONS . '/section/after', array( $this, 'get_cache_notice' ), 10, 1 );
        add_action( 'redux/page/' . WPQD_OPTIONS . '/section/after', array( $this, 'get_pro_upgrade_section' ), 20, 1 );
        
        // High Charts Scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_highcharts_scripts' ) );
    }

    /**
     * Get singleton
     * 
     * @return  $this
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * "General Info" dashboard section
     * 
     * @return  string
     */
    public function get_general_info() {
        return '<h2 class="wpqd-setting-header">General Info</h2>
        <ul class="wpqd-general-info ' . apply_filters( 'wpqd_general_info_css_class', '' ) . '">
            <li>
                <div class="wpqd-hostname-wrapper">' . apply_filters( 'wpqd_general_info_hostname', 'placeholder' ) . '</div>
            </li>
            <li>
                <div class="wpqd-setting-label">Account Tier:</div>
                <div class="wpqd-setting">' . apply_filters( 'wpqd_general_info_account', 'Basic' ) . '</div>
            </li>
            ' . apply_filters( 'wpqd_general_info_account_extra', '' ) . '
            <li>
                <div class="wpqd-setting-label">Remaining Monthly Processing Bandwidth:</div>
                <div class="wpqd-setting bandwidth">' . apply_filters( 'wpqd_general_info_bandwidth', '100%' ) . '</div>
            </li>
        </ul>';
    }

    /**
     * Get remaining quota as a percentage
     * 
     * @return  string
     */
    public function get_remaining_bandwidth() {
        return $this->quota->get_stored_quota_percentage();
    }

    /**
     * Get this site's hostname
     * 
     * @return  string
     */
    public function get_hostname() {
        return $this->connector->get_host();    
    }

    /**
     * Display "Basic" account tier w/ upgrade button
     * 
     * @param   string $tier
     * @return  string
     */
    public function get_account_tier( $tier ) {
        return $tier . '<a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank" class="wpqd-btn wpqd-upgrade-btn">Upgrade</a>';
    }

    /**
     * "Image Library Status" dashboard section
     * 
     * @return  string
     */
    public function get_library_status() {
        return '<h2 class="wpqd-setting-header">Media Library Image Status</h2>
        <div class="wpqd-library-status-outer">
            <div id="container-png" class="wpqd-chart-container"></div>
            <div id="container-jpg" class="wpqd-chart-container"></div>
            <div id="container-gif" class="wpqd-chart-container"></div>
        </div>
        <script>' . apply_filters( 'wpqd_library_status_data', '' ) . '</script>';
    }

    /**
     * Get JSON-encoded data for "Image Library Status" charts
     * 
     * @return  string
     */
    public function get_library_status_data() {
        return 'window.wpqdHighcharts = ' . json_encode( $this->importer->get_status_data() );
    }

    /**
     * "Processing Status" dashboard section
     * 
     * @return  string
     */
    public function get_processing_status() {
        $data = $this->importer->get_status_data();
        $html = '<h2 class="wpqd-setting-header">Processing Status</h2>
        <div class="wpqd-processing-status-outer">
            <div id="wpqd-queue-totals" class="wpqd-queue-totals-outer">
                <h3>Queue:</h3>
                <span class="wpqd-queue-processed current">' . $data['totals']['generated'] . '</span> of<br />
                <span class="wpqd-queue-total total">' . $data['totals']['total'] . '</span> files
            </div>
            <div class="wpqd-progress-bar-outer">
                <h3>Progress:</h3>
                <div id="wpqd-progress-bar"></div>';
        
        if ( $this->importer->is_in_progress() ) {
            $button_text = ( $this->importer->is_paused() ) ? __( 'Resume Import', 'wpqd' ) :
                __( 'Pause Import', 'wpqd' );
            $html .= '<p>
                <a href="#" class="button button-primary button-large wpqd-progress-pauser">' . $button_text . '</a>
            </p>';
        }

        $html .= '</div>
        </div>
        <script>' . apply_filters( 'wpqd_processing_status_data', '' ) . '</script>';
        return $html;
    }

    /**
     * Get URL to progress bar background fill
     * 
     * @return  string
     */
    public function get_processing_status_data() {
        return 'window.wpqdHighcharts.progressBg = "' . WPQD_URL . 'assets/images/progress-stripes.png"';
    }

    /**
     * Display notice about clearing cache in dashboard
     * 
     * @param   array $section
     * @return  void
     */
    public function get_cache_notice( $section ) {
        if ( $section['id'] !== 'dashboard' ) {
            return;
        }
        ?>
        <div class="wpqd-cache-notice">
            <p><?php _e('<strong>*</strong>Remember to clear any plugin or browser caches to apply changes made to WP QuickDraw settings in your Dashboard, Media Library images, or webpages.', 'wpqd'); ?></p>
        </div>
        <?php
    }

    /**
     * Output Pro Upgrade call-to-action
     * 
     * @param   array $section
     * @return  void
     */
    public function get_pro_upgrade_section( $section ) {
        if ( $section['id'] !== 'dashboard' ) {
            return;
        }
        ?>
        <div class="wpqd-pro-upgrade-outer not-upgraded">
            <div class="wpqd-pro-upgrade">
                <h1 class="wpqd-upgrade-header">Upgrade to <span class="wp">WP</span> <span class="qd">QuickDraw Pro</span></h1>
                <p><?php _e('Purchase now for full support, upgrades, forum access AND the incredible new features for just $49.95 per year!', 'wpqd'); ?></p>
                <p><?php _e('30-day money back guarantee.', 'wpqd'); ?></p>
                <a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank" class="wpqd-btn wpqd-upgrade-btn"><?php _e('Buy Pro License', 'wpqd'); ?></a>
                <?php echo apply_filters( 'wpqd_upgrade_buttons', '' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue High Charts Scripts
     * 
     * @param   string $hook
     * @return  void
     */
    public function enqueue_highcharts_scripts( $hook ) {
        if ( 'toplevel_page_wpqd_options_page' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'wpqd-admin-highcharts-main', 'https://code.highcharts.com/highcharts.js', array( 'jquery' ), WPQD_VERSION, true );
        wp_enqueue_script( 'wpqd-admin-highcharts-more', 'https://code.highcharts.com/highcharts-more.js', array( 'jquery' ), WPQD_VERSION, true );
        wp_enqueue_script( 'wpqd-admin-highcharts-solid-gauge', 'https://code.highcharts.com/modules/solid-gauge.js', array( 'jquery' ), WPQD_VERSION, true );
        wp_enqueue_script( 'wpqd-admin-highcharts-pattern-fill', 'https://code.highcharts.com/modules/pattern-fill.js', array( 'jquery' ), WPQD_VERSION, true );
        wp_enqueue_script( 'wpqd-admin-highcharts-custom', WPQD_URL . 'assets/js/admin-highcharts.js', array( 'jquery', 'wpqd-admin-plugin-state' ), WPQD_VERSION, true);
    }

    /**
     * AJAX-friendly query image processing status
     * 
     * @return  void
     */
    public function get_status_data_ajax() {
        echo json_encode( $this->importer->get_status_data() );
        wp_die();
    }
}
