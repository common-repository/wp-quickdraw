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

use \Exception;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class QuotaManager {

    const PERCENTAGE_REMAINING = 'wpqd_quota_percentage';

    /**
     * @var ApiConnector
     */
    protected $connector;

    /**
     * Singleton instance
     */
    protected static $instance;

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
        $this->connector = ApiConnector::get_instance();

        add_action( 'init', array( $this, 'maybe_update_quota' ) );
    }
    
    /**
     * Update quota remaining percentage if needed
     * 
     * @return  void
     */
    public function maybe_update_quota() {
        if ( ! $this->quota_needs_update() ) {
            return;
        }

        try {
            $quota = $this->connector->get_quota();
        } catch ( Exception $e ) {
            Logger::log_exception( $e );
            return;
        }

        $this->set_stored_quota( $this->get_quota_percentage( $quota ) );
    }

    /**
     * Calculate % of quota remaining (rounded to nearest tenth of percent)
     * 
     * @return  int
     */
    public function get_quota_percentage( $quota ) {
        $percent = round( 100 - ( ( $quota['currentQuota'] / $quota['maxQuota'] ) * 100 ), 1);
        if ( $percent < 0 ) {
            $percent = 0;
        }
        return $percent;
    }

    /**
     * Get quota % stored in DB, maybe HTML formatted
     * 
     * @param   bool $html
     * @return  bool|string
     */
    public function get_stored_quota_percentage( $html = true ) {
        $quota = $this->get_stored_quota();
        if ( ! $quota ) {
            return false;
        }

        if ( ! $html ) {
            return ( isset( $quota['percent'] ) ) ? $quota['percent'] : 0;
        }

        if ( ! isset( $quota['percent'] ) ) {
            return __( 'Error', 'wpqd' );
        }
        
        return $quota['percent'] . '<span class="percent">%</span>';
    }

    /**
     * Get quota info stored in DB
     * 
     * @return  array|bool
     */
    protected function get_stored_quota() {
        return get_option( self::PERCENTAGE_REMAINING, false );
    }

    /**
     * Update the quota info stored in DB
     * 
     * @param   float   $value
     * @return  bool
     */
    public function set_stored_quota( $value ) {
        return update_option( self::PERCENTAGE_REMAINING, array(
            'percent' => $value,
            'last_checked' => time()
        ) );
    }

    /**
     * Check if stored quota in DB needs updating (once per hour)
     * 
     * @return  bool
     */
    protected function quota_needs_update() {
        $quota = $this->get_stored_quota();

        if ( ! is_array( $quota ) || ! isset( $quota['last_checked'] ) ) {
            return true;
        }

        $expiry_time = strtotime( '-1 hour' );
        return (bool) ( $expiry_time > $quota['last_checked'] );
    }
}
