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
use \Exception;
use \WP_Query;
use \Redux;
use \ReduxFrameworkInstances;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class PluginState {
    // Manages the state of the plugin

    /**
     * @var $this
     */
    protected static $instance;

    /**
     * Path to directory where settings.js is saved
     *
     * @var string
     */
    protected $path;

    /**
     * Compatibility check errors list
     *
     * @var array
     */
    protected $errors = [];

    /**
     * @var ApiConnector
     */
    protected $connector;

    /**
     * @var string
     */
    const TRANSIENT_KEY_COMPAT = 'wpqd-plugin-admin-error';

    /**
     * @var string
     */
    const TRANSIENT_KEY_FILESYSTEM = 'wpqd-filesystem-error';

    /**
     * @var string
     */
    const TRANSIENT_KEY_AUTH = 'wpqd-auth-error';

    /**
     * @var string
     */
    const TRANSIENT_KEY_WELCOME = 'wpqd-welcome';

    /**
     * @var string
     */
    const OPTION_KEY_INIT = 'wpqd_image_import_initialized';

    /**
     * Register init_hooks
     *
     * @return  void
     */
    public function __construct() {
        $uploads = wp_upload_dir();
        $this->path = trailingslashit($uploads['basedir'] . WPQD_UPLOADS_FOLDER);
        $this->connector = ApiConnector::get_instance();

        register_activation_hook( WPQD_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WPQD_PLUGIN_FILE, array( $this, 'deactivate' ) );

        add_filter( 'wpqd_admin_error_auth_buttons', array( $this, 'render_error_auth_buttons' ), 999, 1 );

        add_action( 'admin_notices', array( $this, 'admin_welcome') );
        add_action( 'admin_notices', array( $this, 'admin_error') );
        add_action( 'admin_notices', array( $this, 'admin_error_filesystem') );
        add_action( 'admin_notices', array( $this, 'admin_error_auth') );
        add_action( 'admin_notices', array( $this, 'wp_rocket_lazyload_check') );
        add_action( 'admin_notices', array( $this, 'w3tc_custom_file_cdn_check') );
        add_action( 'wp_ajax_wpqd_compatibility_check', array( $this, 'compatibility_check' ) );
        add_action( 'wp_ajax_wpqd_compatibility_check_ajax', array( $this, 'compatibility_check_ajax' ) );
        add_action( 'wp_ajax_wpqd_dismiss_admin_error', array( $this, 'dismiss_admin_error_ajax' ) );
        add_action( 'wp_ajax_wpqd_dismiss_admin_error_filesystem', array( $this, 'dismiss_admin_error_filesystem_ajax' ) );
        add_action( 'wp_ajax_wpqd_custom_file_cdn_update', array( $this, 'w3tc_custom_file_cdn_update' ) );
        add_action( 'wp_ajax_wpqd_create_api_key', array( $this, 'create_api_key_ajax' ) );
        add_action( 'wp_ajax_wpqd_test_api_key', array( $this, 'test_api_key_ajax' ) );
        
    }

    /**
     * Get singleton instance
     * 
     * @return  this
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * On plugin activation / install
     *
     * @return  void
     */
    public function activate() {
        if ( $this->compatibility_check() ) {
            return true;
        }
    }

    /**
     * On plugin deactivation
     * 
     * @return  void
     */
    public function deactivate() {
        // Don't allow deactivation of free plugin if Pro is activated.
        $active_plugins = apply_filters( 'active_plugins', get_option('active_plugins') );
        $activated_child_plugins = apply_filters('wpqd_register_child_plugin', array());

        foreach ($activated_child_plugins as $child_plugin_file) {
            if ( in_array( $child_plugin_file, $active_plugins ) ) {
                wp_die(
                    sprintf(
                        __( 'WP QuickDraw Pro requires the free %sWP QuickDraw%s plugin to be active. Please deactivate WP Quickdraw Pro before deactivating WP Quickdraw.', 'wpqd' ),
                        '<a href="https://wordpress.org/plugins/wp-quickdraw/" target="_blank">',
                        '</a>'
                    )
                );
                // Logger::log('Deactivating: ' . trailingslashit(WP_PLUGIN_DIR) . $child_plugin_file);
                // deactivate_plugins( trailingslashit(WP_PLUGIN_DIR) . $child_plugin_file );
            }
        }

        $importer = ImporterProgress::get_instance();
        $importer->pause_import();
    }

    /**
     * Check for PHP module: DOM Library (provides XML support)
     *
     * @return  bool
     */
    public static function has_dom() {
        return (bool) (extension_loaded('dom') || class_exists('DOMDocument'));
    }

    /**
     * Run plugin compatibility check
     *
     * @return bool
     */
    public function compatibility_check() {
        WP_Filesystem(); // Init global $wp_filesystem
        global $wp_filesystem;

        $errors = [];

        if ( ! self::has_dom() ) {
            $errors[] = 'Missing required PHP module: DOM Library';
        }

        if ( ! $wp_filesystem->is_dir( $this->path ) ) {
            if ( ! $wp_filesystem->mkdir( $this->path ) ) {
                $errors[] = sprintf(
                    'Unable to create directory: %1$s. Please check directory permissions.',
                    $this->path
                );
            }
        }

        if ( $cron_error = $this->get_wp_cron_error() ) {
            $errors[] = $cron_error;
        }

        $response = (bool) empty($errors);

        // After succesful compat check, initialize plugin and import
        if ($response) {
            try {
                $this->create_api_key();
            } catch ( Exception $e ) {
                Logger::log_exception( $e );
                $errors[] = $e->getMessage();
            }
            
            $this->create_settings_json();
            $this->init_image_import();
        }

        if ( ! empty($errors) ) {
            set_transient( self::TRANSIENT_KEY_COMPAT, $errors );
        } else {
            delete_transient( self::TRANSIENT_KEY_COMPAT );
        }
        $this->errors = $errors;
        return $response;
    }

    /**
     * Functionality for button in settings Tools > Compatibility Check
     *
     * @return  void
     */
    public function compatibility_check_ajax() {
        if ( $this->compatibility_check() ) {
            echo true;
        } else {
            echo json_encode( $this->errors );
        }
        wp_die();
    }

    /**
     * Delete transient when dismissing admin error (on "X" icon click)
     *
     * @return  void
     */
    public function dismiss_admin_error_ajax() {
        if ( delete_transient( self::TRANSIENT_KEY_COMPAT ) ) {
            echo true;
        } else {
            echo false;
        }
        wp_die();
    }

    /**
     * Delete transient when dismissing admin filesystem error (on "X" icon click)
     *
     * @return  void
     */
    public function dismiss_admin_error_filesystem_ajax() {
        if ( delete_transient( self::TRANSIENT_KEY_FILESYSTEM ) ) {
            echo true;
        } else {
            echo false;
        }
        wp_die();
    }

    /**
     * Print welcome message in admin upon activation
     *
     * @return void
     */
    public function admin_welcome() {
        $flag = get_transient( self::TRANSIENT_KEY_WELCOME );

        if ( ! $flag ) {
            return;
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e( 'Welcome to WP QuickDraw', 'wpqd' ); ?></strong></p>
            <p><?php _e( 'Image set processing will begin shortly. Please be patient as this process may take a while if you have a large image library.', 'wpqd' ); ?></p>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=wpqd_options_page' ); ?>" class="button button-primary button-large">
                    <?php _e( 'View Dashboard', 'wpqd' ); ?>
                </a>                
                <a href="https://www.wpquickdraw.com/documentation/" target="_blank" class="button button-primary button-large">
                    <?php _e( 'Read Documentation', 'wpqd' ); ?>
                </a>
            </p>
        </div>
        <?php
        delete_transient( self::TRANSIENT_KEY_WELCOME );
    }

    /**
     * Print error message in admin
     *
     * @return void
     */
    public function admin_error() {
        $errors = get_transient( self::TRANSIENT_KEY_COMPAT );

        if ( ! $errors ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible hide-dismiss" id="wpqd-warnings">
            <p><strong><?php _e( 'WP QuickDraw Issue(s):', 'wpqd' ); ?></strong></p>
            <ul id="wpqd-compatibility-check-errors">
            <?php foreach ( $errors as $error ): ?>
                <li><?php _e( $error, 'wpqd' ); ?></li>
            <?php endforeach; ?>
            </ul>
            <p>
                <a href="https://www.wpquickdraw.com/support/faq" target="_blank" class="button button-primary button-large">
                    <?php _e( 'Fix Errors', 'wpqd' ); ?>
                </a>                
                <a href="#" id="wpqd_compatibility_check" class="button button-primary button-large">
                    <?php _e( 'Recheck Compatibility', 'wpqd' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Print error message about filesystem settings in admin
     *
     * @return void
     */
    public function admin_error_filesystem() {
        $errors = get_transient( self::TRANSIENT_KEY_FILESYSTEM );

        if ( ! $errors ) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible" id="wpqd-warnings-filesystem">
            <p><strong><?php _e( 'WP QuickDraw Issue(s):', 'wpqd' ); ?></strong></p>
            <ul>
            <?php foreach ( $errors as $error ): ?>
                <li><?php _e( $error, 'wpqd' ); ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Print API key auth error message in admin
     *
     * @return void
     */
    public function admin_error_auth() {
        $error = $this->get_auth_errors();

        if ( ! $error ) {
            return;
        }

        // Set flag so we know this is a bad API key
        $options = get_option( WPQD_OPTIONS );
        $options['api_error'] = true;
        update_option( WPQD_OPTIONS, $options );
        ?>
        <div class="notice notice-error is-dismissible hide-dismiss" id="wpqd-warnings-api">
            <p><strong><?php _e( 'WP QuickDraw API Issue:', 'wpqd' ); ?></strong></p>
            <ul id="wpqd-api-errors">
                <li><?php _e( $error, 'wpqd' ); ?></li>
                <li><span class="wpqd-tip">
                    <?php _e( 'Have you recently changed your WordPress site URL? If so, you must click the button below to regenerate your API key.', 'wpqd' ); ?>
                </span></li>
            </ul>
            <p>
                <?php echo apply_filters( 'wpqd_admin_error_auth_buttons', array(
                    'wpqd_regen_api_key' => array(
                        'class' => 'button button-primary button-large',
                        'label' => 'Regenerate API Key'
                    ),
                    'wpqd_test_api_key' => array(
                        'class' => 'button button-primary button-large',
                        'label' => 'Retest API Authentication'
                    )
                ) ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render action buttons in API key author error message in admin
     * 
     * @param   $buttons array
     * @return  string
     */
    public function render_error_auth_buttons( $buttons ) {
        $output = '';

        if ( empty($buttons) ) {
            return $output;
        }

        foreach ( $buttons as $id => $button ) {
            $output .= sprintf( 
                '<a href="%s" id="%s" class="%s">%s</a>
                ',
                isset( $button['target'] ) ? $button['target'] : '#',
                $id,
                isset( $button['class'] ) ? $button['class'] : '',
                isset( $button['label'] ) ? __( $button['label'], 'wpqd' ) : ''
            );
        }
        return $output;
    }

    /**
     * @return  array
     */
    protected function get_auth_errors() {
        $response = $this->connector->do_auth_test();

        if ( ! $response['error'] ) {
            return false;
        }

        return $response['message'];        
    }

    /**
     * Delete meta values in `wp_postmeta`
     *
     * @return  void
     */
    public function delete_metadata() {
        global $wpdb;

        $table = _get_meta_table( 'post' );
        $query = $wpdb->prepare(
            "DELETE FROM $table WHERE meta_key LIKE '%s'",
            'wpqd_%'
        );

        $wpdb->query( $query );
    }

    /**
     * Delete WPQD settings from database & static file
     *
     * @return  void
     */
    public function delete_settings() {
        wp_using_ext_object_cache( false );
        
        // Force delete all queues
        $importer = BatchImporter::get_instance();
        $queues = $importer->reset_queues();

        // Delete plugin options
        delete_option( WPQD_OPTIONS );
        delete_option( self::OPTION_KEY_INIT );
        delete_transient( ImporterProgress::TRANSIENT_KEY );
        delete_transient( self::TRANSIENT_KEY_COMPAT );
        delete_transient( self::TRANSIENT_KEY_FILESYSTEM );

        // For site options in Multisite
        if ( is_multisite() ) {
            delete_site_option( WPQD_OPTIONS );
            delete_site_option( self::OPTION_KEY_INIT );
        }

        // Delete settings.js file
        $this->delete_settings_json();
    }

    /**
     * Check for images "LazyLoad" in WP Rocket plugin (conflicts with WPQD)
     * 
     * @return  void
     */
    public function wp_rocket_lazyload_check() {
        if ( ! is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
            return;
        }

        $settings = get_option( 'wp_rocket_settings' );
        if ( ! isset($settings['lazyload']) || $settings['lazyload'] != 1 ) {
            return;
        }
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong><?php _e( 'WP QuickDraw Warning:', 'wpqd' ); ?></strong></p>
            <p>
                <?php printf(
                    __( 'You are currently using WP Rocket\'s image "LazyLoad" feature, which conflicts with WP QuickDraw. Please <a href="%s">disable this setting</a> to enjoy the functionality of WP QuickDraw.', 'wpqd' ),
                    get_site_url() . '/wp-admin/options-general.php?page=wprocket#media'
                ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display admin warning about W3 Total Cache CDN "Custom File List"
     * 
     * @return  void
     */
    public function w3tc_custom_file_cdn_check() {
        if ( ! is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
            return;
        }
    
        $config = Dispatcher::config();
        if ( ! $config->get_boolean( 'cdn.enabled' ) ) {
            return;
        }

        $custom_files = $config->get_array( 'cdn.custom.files' );
        $found = false;

        foreach ( $custom_files as $path ) {
            if ( preg_match('/^.*uploads.*\/wpqd.*$/i', $path) !== 1 ) {
                continue;
            }
            $found = true;
            break;
        }

        if ( $found ) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong><?php _e( 'WP QuickDraw Warning:', 'wpqd' ); ?></strong></p>
            <p>
                <?php printf(
                    __( 'You are currently utilizing a CDN through W3 Total Cache. <a href="%s">Please update your "Custom File List"</a> to include this folder in your CDN export: %s', 'wpqd' ),
                    get_site_url() . '/wp-admin/admin.php?page=w3tc_cdn#cdn_custom_files',
                    '{uploads_dir}' . WPQD_UPLOADS_FOLDER . '/*'
                ); ?>
                <p>
                <a href="#" id="wpqd_custom_file_cdn" class="button button-primary button-large">
                    <?php _e( 'Update settings automatically', 'wpqd' ); ?>
                </a>
                </p>
                <div id="wpqd_custom_file_cdn_feedback"></div>
            </p>
        </div>
        <?php
    }

    /**
     * Add WPQD uploads folder to W3 Total Cache CDN "Custom File List" setting
     * 
     * @return  void
     */
    public function w3tc_custom_file_cdn_update() {
        if ( ! is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
            return;
        }
    
        $config = Dispatcher::config();
        $option_key = 'cdn.custom.files';

        $custom_files = $config->get_array( $option_key );
        $custom_files[] = '{uploads_dir}' . WPQD_UPLOADS_FOLDER . '/*';

        $config->set( $option_key, $custom_files );
        $config->save();

        echo json_encode( true );
        wp_die();
    }

    /**
     * Generate API key for this site URL
     * 
     * @return  string
     * @throws  Exception
     */
    public function create_api_key() {
        if ( $existing_key = $this->get_existing_api_key() ) {
            return $existing_key;   
        }
        $key = $this->connector->get_api_key();
        
        if ( is_array($key) && isset($key['error']) ) {
            throw new Exception( sprintf(
                __('API response error: %s', 'wpqd'),
                $key['message']
            ) );
        } else {
            $options = get_option( WPQD_OPTIONS );
            $options['api_key'] = $key;
            unset( $options['api_error'] );
            update_option( WPQD_OPTIONS, $options );
        }
        return $key;
    }

    /**
     * AJAX-friendly generate API key
     * 
     * @return  void
     */
    public function create_api_key_ajax() {
        try {
            $response = array(
                'key' => $this->create_api_key()
            );
        } catch ( Exception $e ) {
            Logger::log_exception( $e );
            $response = array(
                'error' => $e->getMessage()
            );
        }
        echo json_encode( $response );
        wp_die();
    }

    /**
     * AJAX-friendly test API key authorization
     * 
     * @return  void
     */
    public function test_api_key_ajax() {
        echo json_encode( $this->connector->do_auth_test() );
        wp_die();
    }

    /**
     * Get existing API key from `wpqd_data`
     * 
     * @return  string|bool
     */
    protected function get_existing_api_key() {
        $options = get_option( WPQD_OPTIONS );

        if ( isset( $options['api_error'] ) ) {
            return false;
        }

        if ( isset($options['api_key']) && ! empty($options['api_key']) ) {
            return $options['api_key'];
        }
        return false;
    }

    /**
     * Delete folder containing WPQD settings.js
     *
     * @return  void
     * @throws  Exception
     */
    protected function delete_settings_json() {
        WP_Filesystem(); // Init global $wp_filesystem
        global $wp_filesystem;

        if ( ! $wp_filesystem->delete( $this->path, true ) ) {
            throw new Exception( sprintf('Unable to delete: %1$s', $this->path) );
        }
    }

    /**
     * Create WPQD settings.js on plugin activation
     *
     * @return  void
     */
    protected function create_settings_json() {
        do_action( 'wpqd/redux/init' );
        Redux::loadRedux( WPQD_OPTIONS );

        $redux = ReduxFrameworkInstances::get_instance( WPQD_OPTIONS );
        $redux->_register_settings(); // Saves default settings to database

        wpqd_save_config_file( get_option( WPQD_OPTIONS ) );
    }

    /**
     * Import all existing media library images on plugin activation
     * 
     * @return  void
     */
    protected function init_image_import() {
        global $wpdb;

        if ( $this->has_previous_import() ) {
            return;
        }

        // Set flag to display welcome message on first-time activation
        set_transient( self::TRANSIENT_KEY_WELCOME, true );

        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => implode( ',', BatchImporter::$valid_mime_types ),
            'posts_per_page' => -1
        );

        $query_images = new WP_Query( $args );
        $image_rows = array();

        foreach ( $query_images->posts as $image ) {
            $image_rows[] = sprintf('(%1$s, "%2$s", %3$s)', 
                $image->ID,
                ImageMetadata::KEY_ENABLED,
                1
            );
        }

        // Delete existing values for WPQD enabled meta field
        $table = _get_meta_table( 'post' );
        $query_delete = $wpdb->prepare(
            "DELETE FROM $table WHERE meta_key = '%s'",
            ImageMetadata::KEY_ENABLED
        );
        $wpdb->query( $query_delete );

        if ( ! empty($image_rows) ) {
            // WPQD enable all images by default
            $query_insert = sprintf(
                'INSERT INTO %1$s (`post_id`, `meta_key`, `meta_value`) VALUES %2$s',
                $table,
                implode(', ', $image_rows)
            );
            $wpdb->query( $query_insert );

            // Initialize the image generation
            $batch_importer = BatchImporter::get_instance();
            $batch_importer->reimport_all();
        }
        
        update_site_option( self::OPTION_KEY_INIT, true );
    }

    /**
     * Check for a previous or paused import on plugin activation
     * 
     * @return  bool
     */
    protected function has_previous_import() {
        $importer = ImporterProgress::get_instance();
        if ( $importer->pause_import() ) {
            return true;
        }

        return (bool) get_site_option( self::OPTION_KEY_INIT );
    }

    /**
     * Send test POST request to wp-cron.php to check if cron is running properly
     * 
     * @return  string|bool
     */
    protected function get_wp_cron_error() {
        global $wp_version;

        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            return __( 'WP Cron is disabled. Please enable WP Cron so that image generation can proceed.', 'wpqd' );
        }
        
        $ssl_verify    = version_compare( $wp_version, 4.0, '<' );
        $doing_wp_cron = sprintf( '%.22F', microtime( true ) );
        $cron_request  = apply_filters( 'cron_request', array(
            'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
            'key'  => $doing_wp_cron,
            'args' => array(
                'timeout'   => 10,
                'blocking'  => true,
                'sslverify' => apply_filters( 'https_local_ssl_verify', $ssl_verify ),
            ),
        ) );
        $cron_request['args']['timeout'] = 10;  // Force longer 10s timeout

        $result = wp_remote_post( $cron_request['url'], $cron_request['args'] );
        if ( is_wp_error( $result ) ) {
            return __( 'There was a problem running WP Cron. WP Cron must be running properly for image generation to proceed.', 'wpqd' );
        }
        
        $response_code = intval( wp_remote_retrieve_response_code( $result ) );
        if ( $response_code >= 300 ) {
            return __( 'There was a problem running WP Cron. WP Cron must be running properly for image generation to proceed.', 'wpqd' );
        }
        
        return false;
    }
}
