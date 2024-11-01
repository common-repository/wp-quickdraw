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

class ImageMetadata {

    /**
     * @var bool
     */
    const KEY_ENABLED = 'wpqd_enabled';

    /**
     * @var bool
     */
    const KEY_IMAGESET_GENERATED = 'wpqd_generated';

    /**
     * @var bool
     */
    const KEY_QUEUE_FAILED = 'wpqd_queue_failed';

    /**
     * @var string
     */
    const KEY_UID = 'wpqd_uid';

    /**
     * @var int
     */
    const KEY_QUEUE_POSITION = 'wpqd_queue_position';

    /**
     * @var string
     */
    const KEY_ROOT_FILENAME = 'wpqd_root_filename';

    /**
     * @var string
     */
    const KEY_FILE_EXT = 'wpqd_file_extension';

    /**
     * @var string
     */
    const KEY_ASPECT_RATIO = 'wpqd_aspectratio';

    /**
     * @var array
     */
    const KEY_BREAKPOINTS = 'wpqd_breakpoints';

    /**
     * @var bool
     */
    const KEY_UNDER_MIN_LIMIT = 'wpqd_under_min_limit';

    /**
     * @var bool
     */
    const KEY_OVER_MAX_LIMIT = 'wpqd_over_max_limit';

    /**
     * @var string
     */
    const KEY_TRY_COUNT = 'wpqd_try_count';

     /**
     * @var string
     */
    const KEY_TRY_START_TIME = 'wpqd_try_start_time';

     /**
     * @var string
     */
    const KEY_GENERATED_TIME = 'wpqd_generated_time';

     /**
     * @var string
     */
    const KEY_PPNG_LEADCOLOR = 'wpqd_ppng_leadcolor';

    /**
     * @var string
     */
    const KEY_IMG_CROPPED = 'wpqd_img_cropped';

    /**
     * @var string
     */
    const KEY_MESSAGE = 'wpqd_messages';

    /**
     * @var string
     */
    const KEY_FAILED = 'wpqd_failed';

    /**
     * @var array
     */
    protected $key_map;

    /**
     * @var int
     */
    protected $attachment_id;

    /**
     * PHP class constructor
     *
     * @param   int $attachment_id
     */
    public function __construct( $attachment_id ) {
        $this->attachment_id = $attachment_id;
        $this->key_map = array(
            self::KEY_ENABLED => 'set_enabled',
            self::KEY_IMAGESET_GENERATED => 'set_imageset_generated',
            self::KEY_QUEUE_FAILED => 'set_queue_failed',
            self::KEY_UID => 'set_uid',
            self::KEY_QUEUE_POSITION, 'set_queue_position',
            self::KEY_ROOT_FILENAME => 'set_root_filename',
            self::KEY_FILE_EXT => 'set_file_extension',
            self::KEY_ASPECT_RATIO => 'set_aspectratio',
            self::KEY_BREAKPOINTS => 'set_breakpoints',
            self::KEY_UNDER_MIN_LIMIT => 'set_under_min_limit',
            self::KEY_OVER_MAX_LIMIT => 'set_over_max_limit',
            self::KEY_TRY_COUNT => 'set_try_count',
            self::KEY_TRY_START_TIME => 'set_try_start_time',
            self::KEY_GENERATED_TIME => 'set_generated_time',
            self::KEY_PPNG_LEADCOLOR => 'set_ppng_leadcolor',
            self::KEY_IMG_CROPPED => 'set_cropped',
            self::KEY_FAILED => 'set_failed'
        );
    }

    /**
     * Update multiple meta values
     *
     * @param   array   $values
     * @return  void
     */
    public function update( $values ) {
        foreach ( $values as $key => $value ) {
            if ( in_array( $key, array_keys($this->key_map) ) ) {
                call_user_func_array( array($this, $this->key_map[$key]), array($value) );
                continue;
            }
            update_post_meta( $this->attachment_id, $key, $value );
        }
    }

    /**
     * Set 'uid' meta value (used to reference image in WPQD API)
     * 
     * @param   string $value
     * @return  int|bool
     */
    public function set_uid( $value ) {
        if ( empty($value) ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_UID, (string) $value );
    }

    /**
     * Get 'uid' meta value
     *
     * @return  string
     */
    public function get_uid() {
        return get_post_meta( $this->attachment_id, self::KEY_UID, true );
    }

    /**
     * Set 'queue_position' meta value (refers to position in Amazon SQS queue)
     * 
     * @param   int $value
     * @return  int|bool
     */
    public function set_queue_position( $value ) {
        if ( ! is_numeric($value) ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_QUEUE_POSITION, (int) $value );
    }

    /**
     * Get 'queue_position' meta value
     *
     * @return  int
     */
    public function get_queue_position() {
        return get_post_meta( $this->attachment_id, self::KEY_QUEUE_POSITION, true );
    }

    /**
     * Set 'root_filename' meta value
     *
     * @param   string  $filename
     * @return  int|bool
     */
    public function set_root_filename( $filename ) {
        if ( empty($filename) ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_ROOT_FILENAME, $filename );
    }

    /**
     * Get 'root_filename' meta value
     *
     * @return  string
     */
    public function get_root_filename() {
        return get_post_meta( $this->attachment_id, self::KEY_ROOT_FILENAME, true );
    }

    /**
     * Set 'file_extension' meta value
     *
     * @param   string  $extension
     * @return  int|bool
     */
    public function set_file_extension( $extension ) {
        // Validate the value before saving
        if ( ! preg_match('/^png|jpe?g|gif$/i', $extension) ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_FILE_EXT, $extension );
    }

    /**
     * Get 'file_extension' meta value
     *
     * @return  string
     */
    public function get_file_extension() {
        return get_post_meta( $this->attachment_id, self::KEY_FILE_EXT, true );
    }

    /**
     * Set 'aspectratio' meta value
     *
     * @param   mixed  $value
     * @return  int|bool
     */
    public function set_aspectratio( $value ) {
        if ( ! is_numeric($value) && ! is_array($value) ) {
            return false;
        }

        if ( is_array($value) ) {
            list( $width, $height ) = $value;
            $ratio = (float) $width / $height;
        } else {
            $ratio = (float) $value;
        }

        return update_post_meta( $this->attachment_id, self::KEY_ASPECT_RATIO, $ratio );
    }

    /**
     * Get 'aspectratio' meta value
     *
     * @return  string
     */
    public function get_aspectratio() {
        return get_post_meta( $this->attachment_id, self::KEY_ASPECT_RATIO, true );
    }

    /**
     * Set 'wpqd_enabled' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_enabled( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_ENABLED, (bool) $value );
    }

    /**
     * Get 'wpqd_enabled' meta value
     *
     * @return  bool
     */
    public function get_enabled() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_ENABLED, true );
    }

    /**
     * Set 'wpqd_generated' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_imageset_generated( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_IMAGESET_GENERATED, (bool) $value );
    }

    /**
     * Get 'wpqd_generated' meta value
     *
     * @return  bool
     */
    public function get_imageset_generated() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_IMAGESET_GENERATED, true );
    }

    /**
     * Set 'wpqd_queue_failed' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_queue_failed( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_QUEUE_FAILED, (bool) $value );
    }

    /**
     * Get 'wpqd_queue_failed' meta value
     *
     * @return  bool
     */
    public function get_queue_failed() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_QUEUE_FAILED, true );
    }

    /**
     * Set 'wpqd_breakpoints' meta value
     *
     * @param   array  $value
     * @return  int|bool
     */
    public function set_breakpoints( $value ) {
        if ( ! $value ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_BREAKPOINTS, (array) $value );
    }

    /**
     * Get 'wpqd_breakpoints' meta value
     *
     * @return  array
     */
    public function get_breakpoints() {
        return (array) get_post_meta( $this->attachment_id, self::KEY_BREAKPOINTS, true );
    }

    /**
     * Get JSON-encoded 'wpqd_breakpoints' meta value
     *
     * @return  string
     */
    public function get_breakpoints_json() {
        return json_encode( $this->get_breakpoints() );
    }

    /**
     * Set 'wpqd_under_min_limit' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_under_min_limit( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_UNDER_MIN_LIMIT, (bool) $value );
    }

    /**
     * Get 'wpqd_under_min_limit' meta value
     *
     * @return  bool
     */
    public function get_under_min_limit() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_UNDER_MIN_LIMIT, true );
    }

    /**
     * Set 'wpqd_over_max_limit' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_over_max_limit( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_OVER_MAX_LIMIT, (bool) $value );
    }

    /**
     * Get 'wpqd_over_max_limit' meta value
     *
     * @return  bool
     */
    public function get_over_max_limit() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_OVER_MAX_LIMIT, true );
    }

    /**
     * Set 'wpqd_try_count' meta value
     *
     * @param   int  $value
     * @return  int|bool
     */
    public function set_try_count( $value = false ) {
        if ( ! $value ) {
            $value = 0;
        }
        return update_post_meta( $this->attachment_id, self::KEY_TRY_COUNT, (int) $value );
    }

    /**
     * Get 'wpqd_try_count' meta value
     *
     * @return  int
     */
    public function get_try_count() {
        return (int) get_post_meta( $this->attachment_id, self::KEY_TRY_COUNT, true );
    }

    /**
     * Set 'wpqd_try_start_time' meta value
     *
     * @param   int  $value
     * @return  int|bool
     */
    public function set_try_start_time( $value = false ) {
        if ( ! $value ) {
            $value = time();
        }
        return update_post_meta( $this->attachment_id, self::KEY_TRY_START_TIME, (int) $value );
    }

    /**
     * Get 'wpqd_try_start_time' meta value
     *
     * @return  int
     */
    public function get_try_start_time() {
        return (int) get_post_meta( $this->attachment_id, self::KEY_TRY_START_TIME, true );
    }

    /**
     * Set 'wpqd_try_start_time' meta value
     *
     * @param   int  $value
     * @return  int|bool
     */
    public function set_generated_time( $value = false ) {
        if ( ! $value ) {
            $value = time();
        }
        return update_post_meta( $this->attachment_id, self::KEY_GENERATED_TIME, (int) $value );
    }

    /**
     * Get 'wpqd_try_start_time' meta value
     *
     * @return  int
     */
    public function get_generated_time() {
        return (int) get_post_meta( $this->attachment_id, self::KEY_GENERATED_TIME, true );
    }


    /**
     * Get 'wpqd_ppng_leadcolor' meta value
     *
     * @return  int
     */
    public function get_ppng_leadcolor() {
        return get_post_meta( $this->attachment_id, self::KEY_PPNG_LEADCOLOR, true );
    }

    /**
     * Set 'wpqd_ppng_leadcolor' meta value
     *
     * @param   int  $value
     * @return  int|bool
     */
    public function set_ppng_leadcolor( $value = false ) {
        if ( ! $value ) {
            $value = time();
        }
        return update_post_meta( $this->attachment_id, self::KEY_PPNG_LEADCOLOR, $value );
    }

    /**
     * Set 'wpqd_img_cropped' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_cropped( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_IMG_CROPPED, (bool) $value );
    }

    /**
     * Get 'wpqd_img_cropped' meta value
     *
     * @return  bool
     */
    public function get_cropped() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_IMG_CROPPED, true );
    }

    /**
     * Set 'wpqd_messages' meta value
     * 
     * @param   string  $value
     * @return  bool
     */
    public function set_message( $value = '' ) {
        return update_post_meta( $this->attachment_id, self::KEY_MESSAGE, (string) $value );
    }

    /**
     * Get 'wpqd_messages' meta value
     * 
     * @return  string
     */
    public function get_message() {
        return get_post_meta( $this->attachment_id, self::KEY_MESSAGE, true );
    }

    /**
     * Get imageset attribute data in JSON-encoded format
     *
     * @return  string
     */
    public function get_imageset_json() {
        return json_encode( $this->get_imageset_data() );
    }

    /**
     * Wrapper function for get_post_mime_type()
     * 
     * @return  bool|string
     */
    public function get_mime_type() {
        return get_post_mime_type( $this->attachment_id );
    }

    /**
     * Wrapper function for get_attached_file()
     * 
     * @return bool|string
     */
    public function get_filepath() {
        return get_attached_file( $this->attachment_id, false );
    }

    /**
     * Wrapper function for wp_get_attachment_metadata()
     * 
     * @return  bool|array
     */
    public function get_attachment_metadata() {
        return wp_get_attachment_metadata( $this->attachment_id, true );
    }

    /**
     * Set 'wpqd_failed' meta value
     *
     * @param   bool  $value
     * @return  int|bool
     */
    public function set_failed( $value ) {
        if ( $value === '' ) {
            return false;
        }
        return update_post_meta( $this->attachment_id, self::KEY_FAILED, (bool) $value );
    }

    /**
     * Get 'wpqd_failed' meta value
     *
     * @return  bool
     */
    public function get_failed() {
        return (bool) get_post_meta( $this->attachment_id, self::KEY_FAILED, true );
    }

    /**
     * Get imageset attribute data
     *
     * @return  array
     */
    protected function get_imageset_data() {
        $data = array();
        $post_meta = get_post_meta( $this->attachment_id );
        $fields = array( self::KEY_ROOT_FILENAME, self::KEY_FILE_EXT, self::KEY_UID );

        foreach ( $fields as $attribute ) {
            if ( ! isset($post_meta[$attribute]) || empty( $post_meta[$attribute] ) ) {
                continue;
            }
            $data[$attribute] = array_shift( $post_meta[$attribute] );
        }
        return $data;
    }
}
