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


class Logger {
    // WPQD Custom Logging

    const LOGFILE_FILENAME = 'wpqd.log';

    /**
     * @var string
     */
    protected static $logfile;

    /**
     * Log exception to custom log file
     * 
     * @param   Exception $e
     * @return  int|bool
     */
    public static function log_exception( $e ) {
        if ( ! $e instanceof Exception ) {
            return false;
        }

        return self::log( array(
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ) );
    }

    /**
     * Write data to custom log file
     * 
     * @param   mixed   $data
     * @return  int|bool
     */
    public static function log( $data ) {
        return file_put_contents(
            self::get_logfile(),
            print_r( array(
                'timestamp' => date('c'),
                'data' => $data,
                // 'stack_trace' => debug_backtrace()
            ), true ),
            FILE_APPEND
        );
    }

    /**
     * Get full path to custom log file
     * 
     * @return  string
     */
    protected static function get_logfile() {
        if ( ! is_null( self::$logfile ) ) {
            return self::$logfile;
        }
        $upload_dir = wp_upload_dir();
        self::$logfile = trailingslashit( $upload_dir['basedir'] ) . trailingslashit( WPQD_UPLOADS_FOLDER ) . self::LOGFILE_FILENAME;
        return self::$logfile; 
    }
}
