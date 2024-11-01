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

class PluginUpdater {
    /**
     * WPQD version stored in `options` table
     */
    const WPQD_DB_VERSION = 'wpqd_version';

    /**
     * Singleton instance
     */
    protected static $instance;

    /**
     * @var BatchImporter
     */
    protected $importer;

    /**
     * WPQD versions requiring an update
     * 
     * @var array
     */
    protected $update_required_versions = array(
        '1.5.6'
    );

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
        add_action( 'init', array( $this, 'maybe_run_update' ) );
    }

    /**
     * Check whether a plugin update is required
     * 
     * @return  bool
     */
    public function is_update_required() {
        $db_version = $this->get_db_version();
        
        if ( version_compare( WPQD_VERSION, $db_version ) !== 1 ) {
            return false;
        }

        $needs_update = false;
        foreach ( $this->update_required_versions as $version ) {
            if ( version_compare( $version, $db_version ) !== 1 ) {
                continue;
            }
            $needs_update = true;
            break;
        }

        if ( $db_version === '0' ) {
            $needs_update = false;
        }
         
        if ( ! $needs_update ) {
            $this->update_db_version();
            return false;
        }
        return true;
    }

    /**
     * Display update notice with button in admin
     * 
     * @return  void
     */
    public function maybe_run_update() {
        if ( ! $this->is_update_required() ) {
            return;
        }
        $this->get_importer()->reimport_all();
        $this->update_db_version();
    }

    /**
     * Update the plugin DB version to match version in codebase
     * 
     * @return  bool
     */
    public function update_db_version() {
        return update_option( self::WPQD_DB_VERSION, WPQD_VERSION );
    }

    /**
     * Get plugin version from database
     * 
     * @return  string
     */
    protected function get_db_version() {
        return get_option( self::WPQD_DB_VERSION, '0' );
    }

    /**
     * Get BatchImporter singleton
     * 
     * @return  BatchImporter
     */
    protected function get_importer() {
        if ( is_null( $this->importer ) ) {
            $this->importer = BatchImporter::get_instance();
        }
        return $this->importer;
    }
}
