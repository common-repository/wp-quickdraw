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

use \WP_List_Table;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class ImageStatusList extends WP_List_Table {

    const DATE_FORMAT = 'Y-m-d H:i:s';
    const PAGENUM_OPTION = 'wpqd_images_per_page';
    const DEFAULT_VALUE = '---';

    /**
     * PHP class constructor
     */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Image', 'wpqd' ),
			'plural'   => __( 'Images', 'wpqd' ),
			'ajax'     => false
        ) );
    }

    /**
     * Get table column identifiers + labels
     * 
	 * @return  array
	 */
	public function get_columns() {
		return array(
            'ID'                => __('ID', 'wpqd'),
            'post_title'        => __('Title', 'wpqd'),
            'root_filename'     => __('Root Filename', 'wpqd'),
            'filetype'          => __('Filetype', 'wpqd'),
            'dimensions'        => __('Dimensions', 'wpqd'),
            'enabled'           => __('WPQD Enabled', 'wpqd'),
            'generated'         => __('Imageset Generated', 'wpqd'),
            'breakpoints'       => __('Breakpoints', 'wpqd'),
            'tries'             => __('# of Tries', 'wpqd'),
            'try_start_time'    => __('Last Try Start Time', 'wpqd'),
            'generated_time'    => __('Image Generated Time', 'wpqd'),
            'notes'             => __('Notes', 'wpqd')
        );
    }
    
    /**
     * Prepare list table data
     * 
	 * @return  void
	 */
	public function prepare_items() {
        $this->_column_headers = $this->get_column_info();

        $per_page     = $this->get_items_per_page( self::PAGENUM_OPTION );
        $current_page = $this->get_pagenum();
        $total_items  = $this->get_record_count();

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ) );

        $this->items = $this->get_images( $per_page, $current_page );
    }
    
    /**
     * Runs database query to get media library images + relevant data
     * 
     * @param   int $per_page
     * @param   int $page_number
     * @return  array
     */
    protected function get_images( $per_page = 20, $page_number = 1  ) {
        global $wpdb;

        $sql = sprintf( 'SELECT * FROM `%1$sposts` p', $wpdb->prefix );
        $sql .= $this->get_images_join( $wpdb->prefix );
        $sql .= $this->get_images_where();

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY `' . esc_sql( $_REQUEST['orderby'] ) . '`';
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }

        $sql .= sprintf( ' LIMIT %s', $per_page );
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

        return $wpdb->get_results( $sql, 'ARRAY_A' );
    }

    /**
     * Output SQL "JOIN" clauses for image metadata fields
     * 
     * @param   string  $prefix
     * @return  string
     */
    protected function get_images_join( $prefix ) {
        $sql = '';
        $fields = array(
            'root_filename'     => ImageMetadata::KEY_ROOT_FILENAME,
            'filetype'          => ImageMetadata::KEY_FILE_EXT,
            'enabled'           => ImageMetadata::KEY_ENABLED,
            'generated'         => ImageMetadata::KEY_IMAGESET_GENERATED,
            'breakpoints'       => ImageMetadata::KEY_BREAKPOINTS,
            'tries'             => ImageMetadata::KEY_TRY_COUNT,
            'try_start_time'    => ImageMetadata::KEY_TRY_START_TIME,
            'generated_time'    => ImageMetadata::KEY_GENERATED_TIME,
            'under_min_limit'   => ImageMetadata::KEY_UNDER_MIN_LIMIT,
            'over_max_limit'    => ImageMetadata::KEY_OVER_MAX_LIMIT,
            'queue_position'    => ImageMetadata::KEY_QUEUE_POSITION,
            'queue_failed'      => ImageMetadata::KEY_QUEUE_FAILED,
            'message'           => ImageMetadata::KEY_MESSAGE,
            'wp_metadata'       => '_wp_attachment_metadata'
        );

        $i = 0;
        foreach ( $fields as $alias => $meta_key ) {
            ++$i;
            $sql .= sprintf('
                LEFT JOIN (SELECT `post_id`, `meta_value` AS `%1$s`
                    FROM `%2$spostmeta`
                    WHERE `meta_key` = "%3$s") AS pm%4$s
                ON p.`ID` = pm%4$s.`post_id`',
                $alias,
                $prefix,
                $meta_key,
                $i
            );
        }
        return $sql;
    }

    /**
     * "WHERE" clause for querying images from `wp_posts`
     * 
     * @return  string
     */
    protected function get_images_where() {
        return ' WHERE p.`post_type` = "attachment" 
        AND p.`post_mime_type` IN ("image/jpeg", "image/gif", "image/png")';
    }

    /**
     * Get total record count
     * 
     * @return  string
     */
    protected function get_record_count() {
        global $wpdb;
        return $wpdb->get_var( sprintf(
            'SELECT COUNT(*) FROM `%1$sposts` p%2$s',
            $wpdb->prefix,
            $this->get_images_where()
        ) );
    }

    /**
     * Define columns that are sortable & default sort order (true = "DESC")
     * 
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
            'ID'                => array( 'ID', false ),
            'post_title'        => array( 'post_title', false ),
            'root_filename'     => array( 'root_filename', false ),
            'filetype'          => array( 'filetype', false ),
            'enabled'           => array( 'enabled', true ),
            'generated'         => array( 'generated', true ),
            'breakpoints'       => array( 'breakpoints', false ),
            'tries'             => array( 'tries', true ),
            'try_start_time'    => array( 'try_start_time', true ),
            'generated_time'    => array( 'generated_time', true )
        );
    }
    
    /**
	 * Default method to render data for column in table
     * 
	 * @param object $item
	 * @param string $column_name
	 */
	protected function column_default( $item, $column_name ) {
        if ( ! isset( $item[$column_name] ) ) {
            return self::DEFAULT_VALUE;
        }

        return $item[$column_name];
    }

    /**
     * Display if image is WPQD enabled
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_enabled( $item ) {
        if ( ! isset( $item['enabled']) ) {
            return self::DEFAULT_VALUE;
        }

        if ( ! $item['enabled'] ) {
            return 'No';
        }
        return 'Yes';
    }

    /**
     * Display if image is WPQD generated
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_generated( $item ) {
        if ( ! isset( $item['generated']) ) {
            return self::DEFAULT_VALUE;
        }

        if ( ! $item['generated'] ) {
            return 'No';
        }
        return 'Yes';
    }

    /**
     * Display list of breakpoints applicable to image
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_breakpoints( $item ) {
        if ( ! isset( $item['breakpoints'] ) || ! $item['breakpoints'] ) {
            return self::DEFAULT_VALUE;
        }
        return json_encode( maybe_unserialize( $item['breakpoints'] ) );
    }

    /**
     * Add comments in "Notes" column
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_notes( $item ) {
        if ( isset($item['message']) && $item['message'] ) {
            return $item['message'];
        }

        if ( isset( $item['under_min_limit'] ) && $item['under_min_limit'] ) {
            return __('Image too small for WPQD', 'wpqd');
        }

        if ( isset( $item['over_max_limit'] ) && $item['over_max_limit'] ) {
            return __('Image too large for WPQD', 'wpqd');
        }

        if ( isset( $item['tries'] ) && $item['tries'] >= BatchImporter::MAX_TRIES ) {
            return __('Image skipped due to too many failed attempts', 'wpqd');
        }

        if ( isset( $item['queue_failed'] ) && $item['queue_failed'] ) {
            return __('Unable to queue image for resize through API', 'wpqd');
        }

        if ( ! $item['generated'] && isset( $item['queue_position'] ) && is_numeric( $item['queue_position'] ) ) {
            return __('Image currently waiting in queue', 'wpqd');
        }

        return self::DEFAULT_VALUE;
    }

    /**
     * Format "Dimensions" width & height values
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_dimensions( $item ) {
        if ( ! isset($item['wp_metadata']) || ! $item['wp_metadata'] ) {
            return self::DEFAULT_VALUE;
        }

        if ( $metadata = unserialize($item['wp_metadata']) ) {
            if ( ! isset($metadata['width']) || ! isset($metadata['height']) ) {
                return self::DEFAULT_VALUE;
            }
            return sprintf( '%s x %s', $metadata['width'], $metadata['height'] );
        }

        return self::DEFAULT_VALUE;
    }

    /**
     * Format "Try Start Time" timestamp
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_try_start_time( $item ) {
        return $this->format_date( $item, 'try_start_time' );
    }

    /**
     * Format "Image Generated" timestamp
     * 
     * @param   array   $item
     * @return  string
     */
    protected function column_generated_time( $item ) {
        return $this->format_date( $item, 'generated_time' );
    }
    
    /**
     * Convert UNIX timestamp field to formatted date
     * 
     * @param   array   $item
     * @param   string  $column_name
     * @return  string
     */
    protected function format_date( $item, $column_name ) {
        if ( ! isset( $item[$column_name] ) || ! $item[$column_name] ) {
            return self::DEFAULT_VALUE;
        }
        return date( self::DATE_FORMAT, $item[$column_name] );
    }
}
