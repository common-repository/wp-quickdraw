<?php
/*
Plugin Name: WP QuickDraw
Description: Highly accelerated page loading and rendering for image-rich websites. Compatible with caching and image optimization plugins for the highest SEO.
Version:     1.5.9
Author:      HifiPix
Author URI:  https://www.wpquickdraw.com/
License:     GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Text Domain: wpqd

WP QuickDraw is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.

WP QuickDraw is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WP QuickDraw. If not, see http://www.gnu.org/licenses/gpl.html.
*/

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
define( 'WPQD_PLUGIN_FILE', __FILE__ );
define( 'WPQDROOT' , dirname( WPQD_PLUGIN_FILE ).'/' );
define( 'WPQD_URL', plugins_url( '/', WPQD_PLUGIN_FILE ) );
define( 'WPQD_NAME', 'WP QuickDraw');
define( 'WPQD_VERSION' , '1.5.9' );
define( 'WPQD_OPTIONS', 'wpqd_data');
define( 'WPQD_BASENAME', plugin_basename( WPQD_PLUGIN_FILE ) );
define( 'WPQD_UPLOADS_FOLDER', '/wpqd');

//-----------------------------------------
// Redux Framework
//-----------------------------------------
if ( ! class_exists( 'redux' ) && file_exists( WPQDROOT . 'framework/ReduxCore/framework.php' ) ) {
    require_once( WPQDROOT . 'framework/ReduxCore/framework.php' );
}

//-----------------------------------------
// Admin Redux Configuration
//-----------------------------------------
require_once( WPQDROOT . 'framework/ReduxCore/admin-config.php' );

//-----------------------------------------
// Custom logger
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-logger.php' );

//-----------------------------------------
// Include service API connector
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-api-connector.php' );

//-----------------------------------------
// Include plugin activate / deactivate / uninstall actions
//-----------------------------------------
include( WPQDROOT . 'wpqd-plugin-state.php' );
Wpqd\PluginState::get_instance();

//-----------------------------------------
// Include attachment image metadata handler
//-----------------------------------------
include( WPQDROOT . 'wpqd-image-metadata.php' );

//-----------------------------------------
// Include Media Library Functions
//-----------------------------------------
include( WPQDROOT . 'wpqd-media-library.php' );

//-----------------------------------------
// Include Pre-Render Buffer
//-----------------------------------------
include( WPQDROOT . 'wpqd-buffer.php' );
Wpqd\Buffer::get_instance();

//-----------------------------------------
// Require ImageGenerator
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-image-generator.php' );

//-----------------------------------------
// Require PluginUpdater
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-plugin-updater.php' );
Wpqd\PluginUpdater::get_instance();

//-----------------------------------------
// Require BatchImporter & Progress Indicator
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-batch-importer.php' );
require_once( WPQDROOT . 'wpqd-importer-progress.php' );

//-----------------------------------------
// Include Custom Metabox
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-disable-metabox.php' );
new Wpqd\DisableMetabox();

//-----------------------------------------
// Include Quota Manager
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-quota-manager.php' );
Wpqd\QuotaManager::get_instance();

//-----------------------------------------
// Include image status admin page functionality
//-----------------------------------------
include( WPQDROOT . 'wpqd-image-status-list.php' );
include( WPQDROOT . 'wpqd-image-status-page.php' );
require_once( WPQDROOT . 'wpqd-admin-dashboard.php' );
if ( is_admin() ) {
    Wpqd\AdminDashboard::get_instance();
}

//-----------------------------------------
// Include CDN Integration
//-----------------------------------------
require_once( WPQDROOT . 'wpqd-cdn-uploader.php' );

add_action( 'plugins_loaded', function() { 
    Wpqd\ImageStatusPage::get_instance(); 
    Wpqd\ImageGenerator::get_instance();
    Wpqd\BatchImporter::get_instance();
    Wpqd\ImporterProgress::get_instance();
    Wpqd\MediaLibrary::get_instance();
    Wpqd\CdnUploader::get_instance();
} );

/**
 * Delete plugin data on uninstall ("delete" button in admin)
 *
 * @return  void
 */
function wpqd_uninstall() {
    require_once WPQDROOT . 'wpqd-plugin-state.php';

    $plugin_state = Wpqd\PluginState::get_instance();
    $plugin_state->delete_settings();
    $plugin_state->delete_metadata();
}
register_uninstall_hook( __FILE__, 'wpqd_uninstall' );

/**
 * Include settings.js on front-end
 *
 * @return  void
 */
function wpqd_frontend_enqueue_scripts() {
    $options = get_option( WPQD_OPTIONS );

    if ( isset($options['enabled']) && ! $options['enabled'] ) {
        return;
    }

    $uploads = wp_upload_dir();
    $settings_path = 'wpqd/settings.js';

    wp_enqueue_script('wpqd-settings-json',
        trailingslashit($uploads['baseurl']) . $settings_path,
        array(),
        filemtime(trailingslashit($uploads['basedir']) . $settings_path)
    );

    wp_enqueue_script('wpqd-hifipix-js',
        trailingslashit(WPQD_URL) . 'assets/js/hifipix.js',
        array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'wpqd-settings-json' ),
        WPQD_VERSION
    );

    wp_enqueue_style('wpqd-transitions-css',
        trailingslashit(WPQD_URL) . 'assets/css/hifipix.css',
        array(),
        WPQD_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'wpqd_frontend_enqueue_scripts' );

//-----------------------------------------
// Controls Behavior of Pre-Render Buffer
//-----------------------------------------
include( WPQDROOT . 'wpqd-buffer-manager.php' );
new Wpqd\BufferManager( $_SERVER['HTTP_USER_AGENT'], $_GET );
