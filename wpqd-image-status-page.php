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

class ImageStatusPage {

    /**
     * @var $this
     */
    protected static $instance;

    /**
     * @var ImageStatusList
     */
    protected $list;

    /**
     * PHP class constructor
     */
    public function __construct() {
        add_filter( 'set-screen-option', array( $this, 'set_screen' ), 10, 3 );
        add_action( 'admin_menu', array( $this, 'add_page' ) );
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
     * Add Image Status page in "Tools" submenu
     * 
     * @return  void
     */
    public function add_page() {
        $hook = add_management_page( 
            __('WPQD Image Status', 'wpqd'), 
            __('WPQD Image Status', 'wpqd'), 
            'edit_posts', 
            'wpqd_image_status_page', 
            array( $this, 'get_page_content' ) 
        );

        add_action( 'load-' . $hook, array( $this, 'init_page' ) );
    }

    /**
     * Set screen options for custom pagination
     * 
     * @return  string
     */
    public function set_screen( $status, $option, $value ) {
        return $value;
    }

    /**
     * Set up default screen options; load ImageStatusList
     * 
     * @return  void
     */
    public function init_page() {
        $args   = array(
            'label'   => 'Images',
            'default' => 20,
            'option'  => ImageStatusList::PAGENUM_OPTION
        );

        add_screen_option( 'per_page', $args );
        $this->list = new ImageStatusList();
    }

    /**
     * Render page content for Image Status page
     * 
     * @return  void
     */
    public function get_page_content() {
        $this->list->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php _e('WP QuickDraw Image Status', 'wpqd'); ?></h1>
            <div id="wpqd-image-status">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php $this->list->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear" />
            </div>
        </div>
        <?php
    }
}
