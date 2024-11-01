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

class BufferManager {

    /**
     * User agent string from header
     *
     * @var string
     */
    protected $user_agent;

    /**
     * $_GET request variables
     *
     * @var array
     */
    protected $data;

    /**
     * Plugin admin settings
     * 
     * @var array
     */
    protected $options;

    /**
     * PHP class constructor
     *
     * @param   string  $user_agent
     * @param   array   $data
     */
    public function __construct( $user_agent, $data ) {
        $this->user_agent = $user_agent;
        $this->data = $data;
        $this->options = get_option(WPQD_OPTIONS);
        $this->init();
    }

    /**
     * Redirect current URL to "?wpqd=off"
     *
     * @return   void
     */
    public function redirect_wpqd_off() {
        if ( wp_redirect( add_query_arg( array( 'wpqd' => 'off' ) ) ) ) {
            exit;
        }
    }

    /**
     * Initialize buffer manager behavior
     *
     * @return  void
     */
    protected function init() {
        if ( $this->is_wpqd_off() ) {
            $this->disable_buffer();
            $this->disable_scripts();
            return;
        }

        if ( $this->is_bot() ) {
            add_action( 'plugins_loaded', array( $this, 'redirect_wpqd_off' ) );
        }

        add_action( 'wp_footer', array( $this, 'add_init_javascript' ) );
        add_action( 'after_setup_theme', array( $this, 'check_page_wpqd_off' ), 1 );
    }

    /**
     * Detect whether WPQD is disabled for this page / post
     * 
     * @return  void
     */
    public function check_page_wpqd_off() {
        // Don't process disallowed extensions. Prevents wp-cron.php, xmlrpc.php, etc.
        $file_extension = $_SERVER['REQUEST_URI'];
        $file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $file_extension );
        $file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

        if ( in_array( $file_extension, array( 'php', 'xml', 'xsl' ) ) ) {
            $this->disable_buffer();
            $this->disable_scripts();
            return;
        }

        // Get page URL
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $current_url = "https";
        else
            $current_url = "http";
        $current_url .= "://";
        $current_url .= $_SERVER['HTTP_HOST'];
        $current_url .= $_SERVER['REQUEST_URI'];
        $current_url;

        // Get POST ID from URL
        $post_id = url_to_postid($current_url);
        $disabled = false;
        
        if ( $post_id ) {
            $disabled = (bool) get_post_meta( $post_id, DisableMetabox::FIELD_KEY, true );
        }

        if ( $disabled ) {
            $this->disable_buffer();
            $this->disable_scripts();
        }
    }


    /**
     * Detect whether user is bot crawler by user agent string in header
     *
     * @return  bool
     */
    protected function is_bot() {
        if ( !isset( $this->options['seo_user_agent'] ) || empty( $this->options['seo_user_agent'] ) ) {
            return false;
        }

        $user_agents = explode( "\n", $this->options['seo_user_agent'] );

        if ( empty( $user_agents ) ) {
            return false;
        }

        foreach( $user_agents as $i => $ua_string ) {
            $compare_value = strtolower( trim($ua_string) );
            if ( $compare_value === 'industry standard bots' ) {
                if ( preg_match( '/bot|crawl|slurp|spider|mediapartners/i', $this->user_agent) ) {
                    return true;
                }
                continue;
            }

            if ( strpos( $this->user_agent, $compare_value) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether "wpqd=off" query parameter is set
     *
     * @return  bool
     */
    protected function is_wpqd_off() {
        $plugin_enabled = (bool) ( isset($this->options['enabled']) && $this->options['enabled'] == true );
        if ( ! $plugin_enabled ) {
            return true;
        }
        return (bool) ( isset( $this->data['wpqd'] ) && $this->data['wpqd'] === 'off' );
    }

    /**
     * Add call to initialize hifipix in page footer
     * 
     * @return  void
     */
    public function add_init_javascript() {
        if ( $this->is_wpqd_off() ) {
            return;
        }

        echo  '<script type="text/javascript">
                jQuery(document).ready(function() {
                  initializeHifiPix();
                });
              </script>';
    }

    /**
     * Remove buffer filter actions to disable img src replacements
     *
     * @return  void
     */
    protected function disable_buffer() {
        $bufferObject = Buffer::get_instance();
        remove_action( 'after_setup_theme', array( $bufferObject, 'buffer_start' ));
        remove_action( 'shutdown', array( $bufferObject, 'buffer_end' ) );
    }

    /**
     * Remove WPQD frontend scripts
     *
     * @return  void
     */
    protected function disable_scripts() {
        remove_action( 'wp_enqueue_scripts', 'wpqd_frontend_enqueue_scripts' );
    }
}
