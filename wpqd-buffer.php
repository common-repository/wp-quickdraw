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

use \DOMDocument;
use \WP_Query;

//-----------------------------------------
// Disallow Direct Plugin Browsing
//-----------------------------------------
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Buffer {
	// WPQD Buffer class which marks up img tags with WPQD attributes
	
	const IMAGESIZE_MIN = 256;
	const AR_TOLERANCE = 1.5;

	/**
	 * @var	$this
	 */
	private static $instance;

	/**
	 * @var	array
	 */
	protected $options;

	/**
	 * @var	DOMDocument
	 */
	protected $dom;

	/**
	 * PHP class constructor
	 * 
	 * @return	void
	 */
	public function __construct() {
		$this->options = get_option( WPQD_OPTIONS );

		if ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin' ) === false ) {
			add_action( 'after_setup_theme', array( $this, 'buffer_start' ) );
			add_action( 'shutdown', array( $this, 'buffer_end' ) );
		}
	}

	/**
	 * Get singleton
	 * 
	 * @return	this
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Begin buffer output
	 * 
	 * @return 	void
	 */
	public function buffer_start() { 
		@ob_start( array( $this, 'buffer_callback' ) ); 
	}

	/**
	 * End buffer output
	 * 
	 * @return	void
	 */
	public function buffer_end() { 
		@ob_end_flush(); 
	}

    /**
	 * Get Attachment ID From Image URL
	 * 
	 * @param	string	$url
	 * @return	int
	 */
	protected function get_attachment_id( $url ) {
		$attachment_id = 0;
		$uploads = wp_upload_dir();

		if ( preg_match( '/^data:/', $url ) === 1 ) {
			return $attachment_id;
		}

		if ( preg_match( '/^https?:\/\//', $url ) !== 1 ) {
			$url = sprintf( 
				'%s/%s', 
				untrailingslashit( get_site_url() ), 
				ltrim( $url, '/' )
			);
		}

		if ( false === strpos( $url, $uploads['baseurl'] ) ) { // Is URL in uploads directory?
			return $attachment_id;
		}
		
		$file = ltrim( str_replace( $uploads['baseurl'], '', $url ), '/' );
		$file_parts = preg_match( '/(.*)-(\d+)x(\d+)(\..{3,4})/', $file, $matches );

		if ( count( $matches ) >= 5 ) {
			if ( $matches[2] < self::IMAGESIZE_MIN ) {
				return $attachment_id;
			}
			$file = $matches[1] . $matches[4];
		}

		$query_args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'value'   => $file,
					'compare' => 'LIKE',
					'key'     => '_wp_attachment_metadata',
				),
			)
		);

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			return $attachment_id;
		}

		foreach ( $query->posts as $post_id ) {
			$attachment_id = $post_id;
			break;
		}

		return $attachment_id;
	}

	/**
	 * Check for "JavaScript Support Load Message" plugin setting status
	 * 
	 * @return	bool
	 */
	protected function is_enabled_noscript() {
		return (bool) ( isset( $this->options['js_support_msg'] ) && $this->options['js_support_msg'] == 1 );
	}

	/**
	 * Check if WPQD is enabled for this image's file type
	 * 
	 * @param	string	$src
	 * @return 	bool
	 */
	protected function is_enabled_filetype( $src ) {
		if ( ! isset( $this->options['file_types'] ) ) {
			return false;
		}

		// Get enabled file types from admin settings
		$enabled_types = array_keys( $this->options['file_types'], 1 );
		if ( in_array( 'jpg', $enabled_types ) ) {
			$enabled_types[] = 'jpeg';
		}

		$image_parts = pathinfo( $src );

		return (bool) ( in_array( strtolower($image_parts['extension']), $enabled_types ) );
	}
	
	/**
	 * Create <noscript> tag for insertion in HTML body
	 * 
	 * @return	DOMNode
	 */
	protected function get_noscript_tag() {
		$noscript = $this->dom->createElement( 'noscript', '' );

		$headline = $this->dom->createElement(
			'h1',
			'It appears that Javascript is disabled in your browser. For a better viewing experience, '
		);
		$headline->setAttribute( 'style', 'text-align:center;margin:3em 0;' );

		$link = $this->dom->createElement(
			'a',
			'click here'
		);
		$link->setAttribute( 'href', sprintf( '%1$s?wpqd=off', $_SERVER['REQUEST_URI'] ) );
		$link->setAttribute( 'style', 'text-decoration:underline;' );

		$headline->appendChild( $link );
		$noscript->appendChild( $headline );
		return $noscript;
	}

	/**
	 * Get data for placeholder image per admin settings
	 * 
	 * @param	ImageMetadata	$image_metadata
	 * @return	string|array
	 */
	protected function get_placeholder( $image_metadata ) {
		if ( ! isset($this->options['placeholder']) || empty($this->options['placeholder']) ) {
			return $this->get_base_image( $image_metadata );
		}

		switch( $this->options['placeholder'] ) {
			case 'placeholder':
				// Can't load a nonexistent placeholder image
				if ( ! isset($this->options['placeholder_img']) 
					|| empty($this->options['placeholder_img']) 
					|| ! isset($this->options['placeholder_img']['url']) 
					|| empty($this->options['placeholder_img']['url']) ) {
					return $this->get_base_image( $image_metadata );
				}
				return array(
					'spacer' => $this->get_spacer_gif(),
					'placeholder' => $this->get_placeholder_image( $this->options['placeholder_img']['url'] )
				);
				break;
			case 'transparent':
				return $this->get_spacer_gif();
				break;
			default:
				return $this->get_base_image( $image_metadata );
				break;
		}
	}

	/**
	 * Get data for "base" WPQD image
	 * 
	 * @param	ImageMetadata	$image_metadata
	 * @return	string
	 */
	protected function get_base_image( $image_metadata ) {
		$uploads = wp_upload_dir();
		$base_file = sprintf('%1$s%2$s%3$s%4$s_base.%5$s', 
			$uploads['basedir'],
			trailingslashit( WPQD_UPLOADS_FOLDER ),
			trailingslashit( $image_metadata->get_uid() ),
			$image_metadata->get_root_filename(),
			$image_metadata->get_file_extension()
		);
		$base_file_data = base64_encode( file_get_contents($base_file) );

		return sprintf(
			'data:%1$s;base64,%2$s',
			$image_metadata->get_mime_type(),
			$base_file_data
		);
	}

	/**
	 * Get data for transparent placeholder gif
	 * 
	 * @return	string
	 */
	protected function get_spacer_gif() {
		$file = WPQDROOT . 'assets/images/spacer.gif';
		$file_data = base64_encode( file_get_contents($file) );
		return sprintf(
			'data:image/gif;base64,%1$s',
			$file_data
		);
	}

	/** 
	 * Get data for custom placeholder image
	 * 
	 * @return	string|null
	 */
	protected function get_placeholder_image( $img_url ) {
		$attachment_id = $this->get_attachment_id( $img_url );

		if ( ! $attachment_id ) {
			return null;
		}

		$image_metadata = new ImageMetadata( $attachment_id );
		$file_data = base64_encode( file_get_contents($image_metadata->get_filepath()) );
		return sprintf(
			'data:%1$s;base64,%2$s',
			$image_metadata->get_mime_type(),
			$file_data
		);
	}

	/**
	 * Get width, height, aspect ratio of original image src
	 * 
	 * @param	string	$src
	 * @param	ImageMetadata	$image_metadata
	 * @return	array
	 */
	protected function get_img_dimensions( $src, $image_metadata ) {
		$filename = basename( $src );
		$metadata = $image_metadata->get_attachment_metadata();
		$orig_ar = $metadata['width'] / $metadata['height'];

		// If full-sized image
		if ( basename( $metadata['file'] ) === $filename ) {
			return array(
				'width' => $metadata['width'],
				'height' => $metadata['height'],
				'ar' => $orig_ar,
				'orig_ar' => $orig_ar
			);
		}

		// Iterate through smaller images
		foreach ( $metadata['sizes'] as $size ) {
			if ( $size['file'] === $filename ) {
				return array(
					'width' => $size['width'],
					'height' => $size['height'],
					'ar' => $size['width'] / $size['height'],
					'orig_ar' => $orig_ar
				);
			}
		}

		return false;
	}

	/**
	 * Check if this image matches the original upload image's aspect ratio
	 * 
	 * @param	ImageMetadata	$metadata
	 * @param	array	$dimensions
	 * @return	bool
	 */
	protected function is_matching_aspectratio( $metadata, $dimensions ) {
		$orig_difference = abs( $dimensions['orig_ar'] - $metadata->get_aspectratio() );
		$difference = abs( $dimensions['ar'] - $metadata->get_aspectratio() );
		return (bool) ( $difference <= ( $orig_difference * self::AR_TOLERANCE) );
	}

    /**
	 * Massage Output In Pre-Render Buffer
	 * 
	 * @param	string	$buffer
	 * @return	string
	 */
	public function buffer_callback( $buffer ) {
		libxml_use_internal_errors(true);

		if ( ! $this->is_html( $buffer ) ) {
			return $buffer;
		}

		$this->dom = new DOMDocument();

		// Parse HTML
		$this->dom->loadHTML('<?xml encoding="utf-8" ?>' . $buffer);

		foreach( libxml_get_errors() as $error ) {
			if ( $error->code === 9 ) { // Invalid character
				return $buffer;
			}
		}
		
		// Add WPQD HTML comment
		$body = $this->dom->getElementsByTagName('body')->item(0);
		if ( ! is_null( $body ) ) {
			$wpqd_comment = $this->dom->createComment(PHP_EOL . 'Images supercharged by ' . WPQD_NAME . ' v' . WPQD_VERSION . '. (c)' . date('Y') . ' HifiPix, Inc. Learn more: https://www.wpquickdraw.com' . PHP_EOL);
			$body->appendChild($wpqd_comment);
		}

		// Inject <noscript> tag at beginning of <body>
		if ( $this->is_enabled_noscript() ) {
			$noscript = $this->get_noscript_tag();
			if ( ! is_null( $body ) ) {
				$body->insertBefore( $noscript, $body->firstChild );
			}
		}
    
		$img_cnt = 1;
		foreach ( $this->dom->getElementsByTagName('img') as $img ) {
            // Store original image
			$orig_img_src = $img->getAttribute('src');
			$attachment_id = $this->get_attachment_id( $orig_img_src );

			// Is image in media library?
			if ( $attachment_id === 0 ) {
				continue;
			}

			if ( ! $this->is_enabled_filetype( $orig_img_src ) ) {
				continue;
			}

			// Get image metadata
			$image_metadata = new ImageMetadata( $attachment_id );

			if ( ! $image_metadata->get_enabled() || ! $image_metadata->get_imageset_generated() ) {
				continue;
			}
			
			$dimensions = $this->get_img_dimensions( $orig_img_src, $image_metadata );
			if ( ! $dimensions || ! isset($dimensions['ar']) ) {
				continue;
			}

			// Skip image sizes with a different aspect ratio than the WPQD image set
			if ( ! $this->is_matching_aspectratio( $image_metadata, $dimensions ) ) {
				continue;
			}
			
			// Don't WPQD-ify images with certain CSS classes applied
			$parent_classes = explode( ' ', $img->parentNode->getAttribute('class') );
			$img_classes = explode( ' ',  $img->getAttribute('class') );
			$ignore_classes = array( 'rev-slidebg', 'wpqd-disabled' );

			if ( ! empty( array_intersect($ignore_classes, $img_classes) ) ||
				 ! empty( array_intersect($ignore_classes, $parent_classes) ) ) {
				continue;
			}

			// Get placeholder image URL
			$placeholder_img = null;
			$base_file = $this->get_placeholder( $image_metadata );

			if ( is_array($base_file) ) {
				$placeholder_img = $base_file['placeholder'];
				$base_file = $base_file['spacer'];	
			}
			
			// Set new image src
			$img->setAttribute( 'src', $base_file );
			$img->removeAttribute('srcset');
			// $img->removeAttribute('width');

			$classes = $img->getAttribute('class');
			$classes .= ' wpqd';
			if ( ! is_null($placeholder_img) ) {
				$classes .= ' wpqd-placeholder';
				$inline_style = $img->getAttribute('style');
				$inline_style .= ( ! empty($inline_style) ) ? ';' : '';
				$inline_style .= sprintf( 'background-image:url(%1$s);', $placeholder_img );
				$img->setAttribute( 'style', $inline_style );
			}
			$classes = $img->setAttribute('class', $classes);

			if ( ! $img->getAttribute( 'width' ) ) {
				if ( isset( $dimensions['width'] ) ) {
					$img->setAttribute( 'width', $dimensions['width'] );
				}
			}

			// Add image metadata as attributes
			$img->setAttribute( 'wpqd-imageset', $image_metadata->get_imageset_json() );
			$img->setAttribute( 'wpqd-ar', $image_metadata->get_aspectratio() );
			$img->setAttribute( 'wpqd-breakpoints', $image_metadata->get_breakpoints_json() );

			// Set ID attribute
			//$img->setAttribute('wpqd-id', $this->get_attachment_id($orig_img_src));
			$img->setAttribute ('wpqd-id', $img_cnt );

			// Store original image src as attribute
			$img->setAttribute( 'orig_src', $orig_img_src );


			do_action('wpqd_update_buffer_attributes', $img, $image_metadata, $orig_img_src);

			$img_cnt++;
		}

		$buffer = $this->dom->saveHTML();
		return $buffer;
	}

	/**
	 * Validate if buffer content is HTML
	 * 
	 * @param	string	$content
	 * @return	bool
	 */
	protected function is_html( $content ) {
		if ( strlen( $content ) > 1000 ) {
			$content = substr( $content, 0, 1000 );
		}
		if ( strstr( $content, '<!--' ) !== false ) {
			$content = preg_replace( '~<!--.*?-->~s', '', $content );
		}
		$content = ltrim( $content, "\x00\x09\x0A\x0D\x20\xBB\xBF\xEF" );
		return stripos( $content, '<html' ) === 0 || stripos( $content, '<!DOCTYPE' ) === 0;
	}
}
