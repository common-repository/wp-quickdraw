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

class MediaLibrary {
	// Wordpress Media Library Integration

	/**
	 * @var	$this
	 */
	private static $instance;

	/**
	 * PHP class constructor
	 * 
	 * @return	void
	 */
	public function __construct() {
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_media_fields' ), null, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_enabled' ), null, 2 );
		add_action( 'edit_attachment', array( $this, 'save_attachment' ), 15, 1 );
		add_action( 'add_attachment', array( $this, 'enable_by_default' ) );
		add_action( 'print_media_templates', array( $this, 'media_image_widget_options' ) );

		// Disable big image threshold, which causes scale down
		add_filter( 'big_image_size_threshold', '__return_false' );
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

	//-----------------------------------------
	// Add Fields To Media Library
	//-----------------------------------------
	
	public function add_media_fields( $form_fields, $post ) {
		
		$image_metadata = new ImageMetadata( $post->ID );

		if ( ! in_array( $image_metadata->get_mime_type(), BatchImporter::$valid_mime_types ) ) {
			return;
		}

		//-----------------------------------------
		// WPQD Enabled
		//-----------------------------------------
		$wpqd_enabled = $image_metadata->get_enabled();
		$wpqd_incompatible = $image_metadata->get_under_min_limit() || $image_metadata->get_over_max_limit();
		$enabled_html = sprintf(
			'<label%7$s><input type="checkbox"%1$s value="1" name="attachments[%2$s][%3$s]"%4$s> %5$s %6$s</label><br/>',
			( $wpqd_enabled == 1 ) ? ' checked' : '',
			$post->ID,
			ImageMetadata::KEY_ENABLED,
			( $wpqd_incompatible ) ? ' disabled' : '',
			( $wpqd_incompatible ) ? 'Disabled' : 'Enabled',
			( $wpqd_incompatible ) ? ' (Image outside WPQD parameters)' : '<span class="wpqd_ml_advanced_toggle wpqd_ml_advanced_toggle_closed">Show Advanced</span>',
			( $wpqd_incompatible ) ? ' class="wpqd-incompatible"' : ''
		);
		$form_fields[ImageMetadata::KEY_ENABLED] = array(
			'label' => __( 'WP QuickDraw' ),
			'input'  => 'html',
			'html' => $enabled_html
		);

		//-----------------------------------------
		// Aspect Ratio
		//-----------------------------------------
		$aspect_ratio = $image_metadata->get_aspectratio();
		$form_fields[ImageMetadata::KEY_ASPECT_RATIO] = array(
			'label' => __( 'WPQD Aspect Ratio' ),
			'input'  => 'html',
			'html' => ( $aspect_ratio ) ? $aspect_ratio : ''
		);

		//-----------------------------------------
		// Imageset Generated
		//-----------------------------------------
		$form_fields[ImageMetadata::KEY_IMAGESET_GENERATED] = array(
			'label' => __( 'Imageset Generated' ),
			'input'  => 'html',
			'html' => ( $image_metadata->get_imageset_generated() ) ? 'Yes' : 'No'
		);

		//-----------------------------------------
		// WPQD Metadata - Root Filename
		//-----------------------------------------
		$field_value = $image_metadata->get_root_filename();
		$form_fields[ImageMetadata::KEY_ROOT_FILENAME] = array(
			'value' => $field_value ? $field_value : '',
			'label' => __( 'WPQD Root Filename' )
		);

		//-----------------------------------------
		// WPQD Metadata - File extension
		//-----------------------------------------
		$field_value = $image_metadata->get_file_extension();
		$form_fields[ImageMetadata::KEY_FILE_EXT] = array(
			'value' => $field_value ? $field_value : '',
			'label' => __( 'WPQD File Extension' )
		);

		return $form_fields;
	}

	//-----------------------------------------
	// Save Attachment Metadata On Edit
	//-----------------------------------------
	public function save_attachment( $attachment_id ) {
		$image_metadata = new ImageMetadata( $attachment_id );

		if ( ! in_array( $image_metadata->get_mime_type(), BatchImporter::$valid_mime_types ) ) {
			return;
		}

		//-----------------------------------------
		// WPQD Metadata - Root Filename
		//-----------------------------------------
		if ( isset( $_REQUEST['attachments'][ $attachment_id ][ImageMetadata::KEY_ROOT_FILENAME] ) ) {
			$filename = $_REQUEST['attachments'][ $attachment_id ][ImageMetadata::KEY_ROOT_FILENAME];
			$image_metadata->set_root_filename( $filename );
		}

		//-----------------------------------------
		// WPQD Metadata - File extension
		//-----------------------------------------
		if ( isset( $_REQUEST['attachments'][ $attachment_id ][ImageMetadata::KEY_FILE_EXT] ) ) {
			$extension = $_REQUEST['attachments'][ $attachment_id ][ImageMetadata::KEY_FILE_EXT];
			$image_metadata->set_file_extension( $extension );
		}
	}

	// Save enabled field
	public function save_enabled($post, $attachment) {
		$image_metadata = new ImageMetadata( $post['ID'] );

		//-----------------------------------------
		// WPQD Enabled
		//-----------------------------------------
		if ( isset( $attachment[ImageMetadata::KEY_ENABLED] ) ) {
			$image_metadata->set_enabled( true );
		} else {
			$image_metadata->set_enabled( false );
		}
	}



	//-----------------------------------------
	// Set ImageMetadata::KEY_ENABLED To True For New Images
	//-----------------------------------------
	public function enable_by_default( $post_ID ) {
		add_post_meta($post_ID, ImageMetadata::KEY_ENABLED, 1);
	}

	/**
	 * Adds functionality to "Image Details" widget to toggle "wpqd-disabled" CSS class
	 * 
	 * @return	void
	 */
	public function media_image_widget_options() {
		?>
		<script type="text/html" id="tmpl-wpqd-image-details">
		<div class="wpqd-section">
			<h2><?php _e( 'WP QuickDraw Options' ); ?></h2>
			<div class="wpqd-settings">
				<div class="advanced-link">
					<div class="setting link-target">
						<label>
							<input type="checkbox" data-setting="wpqdDisabledInContext" value="wpqd-disabled" <# if ( data.model.wpqdDisabledInContext ) { #>checked="checked"<# } #>>
							<?php _e( 'Disable WPQD for this image on this page' ); ?>
						</label>
					</div>
				</div>
			</div>
		</div>
		</script>

		<script>
		jQuery( document ).ready( function () {
			// Insert form HTML into default template
			wp.media.view.ImageDetails = wp.media.view.ImageDetails.extend({
				template: function(view){
					var $template = jQuery( wp.template( 'image-details' )(view) );
					$template
						.find( '.advanced-section' )
						.after( wp.template( 'wpqd-image-details' )(view) );
					return $template.html();
				}
			});

			// When image is edited
			wp.media.events.on('editor:image-edit', function(data) {
				var classes = data.editor.dom.getAttrib( data.image, 'class' ).split(' ');
				data.metadata.wpqdDisabledInContext = classes.includes( 'wpqd-disabled' );
			});

			// When image is updated
			wp.media.events.on('editor:image-update', function(data) {
				var classes = data.editor.dom.getAttrib( data.image, 'class' ).split(' ');
				if ( data.metadata.wpqdDisabledInContext ) {
					classes.push( 'wpqd-disabled' );
				} else {
					var index = classes.indexOf( 'wpqd-disabled' );
					if ( index !== -1 ) {
						classes.splice( index, 1 );
					}
				}

				data.editor.dom.setAttrib( data.image, 'class', classes.join(' ') );
			});
		});
		</script>
		<?php
	}
}
