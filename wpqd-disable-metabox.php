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

class DisableMetabox {

    const FIELD_KEY = 'wpqd_disabled';
 
    /**
     * PHP class constructor
     */
    public function __construct() {
        if ( is_admin() ) {
            add_action( 'load-post.php',     array( $this, 'init' ) );
            add_action( 'load-post-new.php', array( $this, 'init' ) );
        }
 
    }
 
    /**
     * Meta box initialization
     * 
     * @return  void
     */
    public function init() {
        add_action( 'add_meta_boxes', array( $this, 'add_metabox'  )        );
        add_action( 'save_post',      array( $this, 'save_metabox' ), 10, 2 );
    }
 
    /**
     * Adds the meta box
     * 
     * @return  void
     */
    public function add_metabox() {
        add_meta_box(
            'wpqd-metafields',
            __( 'WP QuickDraw', 'wpqd' ),
            array( $this, 'render_metabox' ),
            array( 'post', 'page' ),
            'side',
            'default'
        );
 
    }
 
    /**
     * Renders the meta box.
     * 
     * @param   WP_Post $post
     * @return  void
     */
    public function render_metabox( $post ) {
        // Add nonce for security and authentication.
        wp_nonce_field( 'wpqd_nonce_action', 'wpqd_nonce' );
        $checked = ( (bool) get_post_meta( $post->ID, self::FIELD_KEY, true ) ) ? 'checked="checked"' : '';
        ?>
        <label class="selectit">
            <input id="<?php echo self::FIELD_KEY; ?>" name="<?php echo self::FIELD_KEY; ?>" type="checkbox" <?php echo $checked; ?> value="true" <?php disabled( ! current_user_can( 'edit_post', $post->ID ) ); ?> />
            <?php _e( 'Disable WP QuickDraw on this page', 'wpqd' ); ?>
        </label>
        <?php
    }
 
    /**
     * Handles saving the meta box.
     *
     * @param   int     $post_id
     * @param   WP_Post $post
     * @return  void
     */
    public function save_metabox( $post_id, $post ) {
        // Add nonce for security and authentication.
        $nonce_name   = isset( $_POST['wpqd_nonce'] ) ? $_POST['wpqd_nonce'] : '';
        $nonce_action = 'wpqd_nonce_action';
 
        // Check if nonce is set.
        if ( ! isset( $nonce_name ) ) {
            return;
        }
 
        // Check if nonce is valid.
        if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
            return;
        }
 
        // Check if user has permissions to save data.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
 
        // Check if not an autosave.
        if ( wp_is_post_autosave( $post_id ) ) {
            return;
        }
 
        // Check if not a revision.
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        $value = isset( $_POST[self::FIELD_KEY] ) ? (bool) $_POST[self::FIELD_KEY] : false;
        update_post_meta( $post_id, self::FIELD_KEY, $value );
    }
}
