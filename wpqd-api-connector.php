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
defined( 'ABSPATH' ) or die( 'No script kiddies pretty please!' );

class ApiConnector {
    // Connects to the WPQD Image Generation Api

    /**
     * @var $this
     */
    protected static $instance;

    /**
     * WPQD API
     */
    const API_URL = 'http://api.wpquickdraw.com';

    /**
     * API endpoints
     */
    const KEY_ENDPOINT = '/v1/api/key';
    const TEST_ENDPOINT = '/v1/api/test';
    const RESIZE_ENDPOINT = '/v1/image/resize';
    const URL_ENDPOINT = '/v1/image/url';
    const QUOTA_ENDPOINT = '/v1/api/quota';

    const PPNG_ENDPOINT = '/v1/image/ppng/generate';
    const PPNG_URL_ENDPOINT = '/v1/image/ppng/url';

    /**
     * Image field key for multipart/form-data HTTP request
     */
    const IMAGE_KEY = 'image';

    /**
     * Delimiters for multipart/form-data HTTP requests
     */
    const FORM_BOUNDARY = '--boundary7MA4YWxkTrZu0gW';
    const FORM_EOL = "\r\n";

    /**
     * @var array
     */
    protected $options;

    /**
     * PHP class constructor
     */
    public function __construct() {
        $this->options = get_option( WPQD_OPTIONS );
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
     * Get new API key for site URL
     * 
     * @return  string|array
     * @throws  Exception
     */
    public function get_api_key() {
        $body = array(
            'site_url' => $this->get_host()
        );

        $response = $this->do_request( self::KEY_ENDPOINT, $body, false );
        if ( ! isset($response['message']) || empty($response['message']) ) {
            throw new Exception( __('Empty API response when requesting new key') );
        }

        if ( isset($response['error']) ) {
            return $response;
        }
        return $response['message'];
    }

    /**
     * Get image metadata from API
     * 
     * @param   string $uid
     * @return  array
     * @throws  Exception
     */
    public function get_image_data( $uid ) {
        $body = array(
            'site_url' => $this->get_host(),
            'uid' => $uid
        );

        $response = $this->do_request( self::URL_ENDPOINT, $body );
        if ( ! isset($response['message']) || empty($response['message']) ) {
            throw new Exception( sprintf(
                __('Empty API response when requesting image data for UID "%s"'),
                $uid
            ) );
        }

        if ( isset($response['error']) ) {
            return $response;
        }

        if ( isset($response['message']['error']) ) {
            return $response['message'];
        }

        return $response['message'][0];
    }

    /**
     * Send resize image request
     * 
     * @param   array $image_data
     * @return  array
     * @throws  Exception
     */

    public function do_resize( $image_data ) {
        $image_response = [];
        $resize_filter_response = [];
        
        $pre_resize_values = apply_filters('wpqd_pre_resize_images', $image_data);

        if ($pre_resize_values == $image_data) {
            // If filter had no effect, proceed as usual.
            $resize_images = $image_data['images'];
            $resize_meta = $image_data['meta'];
        } else {
            // If filter changed data, update values
            $resize_images = $pre_resize_values[1]['images'];
            $resize_meta = $pre_resize_values[1]['meta'];
            $resize_filter_response = $pre_resize_values[0];
        }

        $body = array(
            'site_url' => $this->get_host(),
            'api_key' => $this->get_stored_api_key(),
            'meta' => $resize_meta,
            self::IMAGE_KEY => $resize_images
        );

        $image_response = $this->do_request( self::RESIZE_ENDPOINT, $body );
        if ( ! isset($image_response['message']) || empty($image_response['message']) ) {
            throw new Exception( __('Empty API response when requesting image resize') );
        }

        // Combine Responses
        $response = array_merge($image_response, $resize_filter_response);
        
        return $response;
    }

    /**
     * Test request w/ auth headers
     * 
     * @return  array
     */
    public function do_auth_test() {
        try {
            $response = $this->do_request( self::TEST_ENDPOINT );
        } catch ( Exception $e ) {
            return array(
                'error' => true,
                'message' => $e->getMessage()
            );
        }

        if ( isset($response['message']) && ! isset($response['error']) ) {
            return array(
                'error' => false,
                'message' => $response['message']
            );
        }

        return array(
            'error' => true,
            'message' => __('Something went wrong', 'wpqd')
        );
    }

    /**
     * Get quota info from API
     * 
     * @return  array
     * @throws  Exception
     */
    public function get_quota() {
        $body = array(
            'api_key' => $this->get_stored_api_key()
        );
        $response = $this->do_request( self::QUOTA_ENDPOINT, $body );
        if ( ! isset($response['message']) || empty($response['message']) ) {
            throw new Exception( __('Empty API response when requesting quota info') );
        }
        return $response['message'];
    }

    /**
     * Get site URL without protocol or "www"
     * 
     * @return  string
     * @throws  Exception
     */
    public function get_host() {
        $url = get_site_url();
        preg_match( '/^(https?:\/\/)?(w{3}\.)?(.*)$/', $url, $matches );
        if ( empty($matches) ) {
            throw new Exception( __('Invalid site URL', 'wpqd') );
        }
        return end( $matches );
    }

    /**
     * Get header for HTTP basic auth in request
     * 
     * @return  array
     */
    protected function get_auth_header() {
        return array(
            'Authorization' => 'Basic ' . base64_encode( 
                $this->get_host() . ':' . $this->get_stored_api_key()
            )
        );
    }

    /**
     * Get API key saved in WP database
     * 
     * @return  string
     * @throws  Exception
     */
    public function get_stored_api_key() {
        $key = '';
        if ( isset($this->options['api_key']) && ! empty($this->options['api_key']) ) {
            $key = $this->options['api_key'];
        }

        if ( empty( $key ) ) {
            $state = PluginState::get_instance();
            $key = $state->create_api_key();
        }

        $filtered_key = apply_filters( 'wpqd_api_key', $key );
        if ( ! $filtered_key ) {
            throw new Exception( __( 'Missing API key', 'wpqd' ) );
        }
        return $filtered_key;
    }

    /**
     * Handle request to WP QuickDraw API
     * 
     * @param   string       $endpoint
     * @param   array|null   $body
     * @param   bool         $auth
     * @return  array
     * @throws  Exception
     */
    public function do_request( $endpoint, $body = null, $auth = true ) {
        $args = array(
            'timeout' => 30,
            'blocking' => true,
            'headers' => array(
                'Content-Type' => ( $endpoint === self::RESIZE_ENDPOINT || $endpoint === self::PPNG_ENDPOINT ) 
                    ? 'multipart/form-data; charset=utf-8; boundary=' . self::FORM_BOUNDARY 
                    : 'application/x-www-form-urlencoded'
            ),
            'body' => ( $endpoint === self::RESIZE_ENDPOINT || $endpoint === self::PPNG_ENDPOINT ) ? $this->get_multipart_body( $body ) : $body
        );

        if ( $auth ) {
            $args['headers'] = array_merge( $args['headers'], $this->get_auth_header() );
        }
        $response = wp_remote_post( self::API_URL . $endpoint, $args );

        if ( is_wp_error($response) || $response['response']['code'] > 200 ) {
            throw new Exception( __('There was a problem connecting to the WP QuickDraw API.', 'wpqd') );
        }
        return json_decode( $response['body'], true );
    }

    /**
     * Format body for use multipart/form-data HTTP request
     * 
     * @param   array $body
     * @return  string
     */
    protected function get_multipart_body( $body ) {
        $formatted_body = '';
        foreach ( $body as $key => $value ) {
            if ( $key === self::IMAGE_KEY ) {
                foreach ( $value as $image ) {
                    if ( ! file_exists( $image ) ) {
                        continue;
                    }
                    $filetype = wp_check_filetype( $image );
                    $formatted_body .= $this->get_form_boundary();
                    $formatted_body .= sprintf(
                        'Content-Disposition: form-data; name="%s"; filename="%s"%s',
                        self::IMAGE_KEY,
                        basename( $image ),
                        self::FORM_EOL
                    );
                    $formatted_body .= sprintf(
                        'Content-Type: %1$s%2$s%2$s',
                        $filetype['type'],
                        self::FORM_EOL
                    );
                    $formatted_body .= file_get_contents( $image ) . self::FORM_EOL;
                }
            } else {
                $formatted_body .= $this->get_form_boundary();
                $formatted_body .= sprintf(
                    'Content-Disposition: form-data; name="%s"%s',
                    $key,
                    self::FORM_EOL
                );
                if ( is_array( $value ) ) {
                    $formatted_body .= sprintf(
                        'Content-Type: application/json%s',
                        self::FORM_EOL
                    );
                    $value = json_encode( $value );
                }
                $formatted_body .= self::FORM_EOL . $value . self::FORM_EOL;
            }
        }
        $formatted_body .= '--' . self::FORM_BOUNDARY . '--';
        return $formatted_body;
    }

    /**
     * Get multipart/form-data boundary w/ required formatting
     * 
     * @return  string
     */
    protected function get_form_boundary() {
        return '--' . self::FORM_BOUNDARY . self::FORM_EOL;
    }
}
