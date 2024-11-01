<?php

/* ------------------------------------------------------------------------ */
/* Redux Configuration
/* ------------------------------------------------------------------------ */
if ( ! class_exists( 'Redux' ) ) {
    return;
}

// This is your option name where all the Redux data is stored.
global $wpqd_opt_name;
$wpqd_opt_name = WPQD_OPTIONS;

$args = array(
    'opt_name'          => $wpqd_opt_name,          // This is where your data is stored in the database and also becomes your global variable name.
    'display_name'      => '<div class="wpqd-panel-title-outer">
            <img src="' . WPQD_URL . 'assets/images/hfp-settings-whiteout.png" alt="WP QuickDraw" />
            <div class="wpqd-panel-title">WP<br /><strong>QuickDraw</strong></div>
        </div>',                                    // Name that appears at the top of your panel
    'display_version'   => null,                    // Version that appears at the top of your panel
    'menu_type'         => 'menu',                  // Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only)
    'allow_sub_menu'    => false,                   // Show the sections below the admin menu item or not
    'menu_title'        => __(WPQD_NAME, 'wpqd'),
    'page_title'        => __(WPQD_NAME, 'wpqd'),
    'save_defaults'     => true,
    'async_typography'  => true,                     // Use a asynchronous font on the front end or font string
    'admin_bar'         => false,                    // Show the panel pages on the admin bar
    'dev_mode'          => false,                    // Show the time the page took to load, etc
    'customizer'        => false,                    // Enable basic customizer support
    'page_slug'         => 'wpqd_options_page',
    'system_info'       => false,
    'disable_save_warn' => true,                    // Disable the save warning when a user changes a field
    'menu_icon'         => 'none',
    'class'             => 'wpqd-settings',
    'show_import_export' => false,
    'global_variable'   => $wpqd_opt_name,
    'footer_credit'		=> '&nbsp;',
    'hide_reset'        => true,
    'templates_path'    => WPQDROOT . 'framework/templates'
);

Redux::setArgs( $wpqd_opt_name, $args );

/**
 * Initialize sections & settings
 * 
 * @return  void
 */
function wpqd_settings_init() {
    global $wpqd_opt_name;
    $options = get_option( WPQD_OPTIONS );

    /**
     * Dashboard
     */
    Redux::setSection( $wpqd_opt_name, array(
        'title'     => __('Dashboard', 'wpqd'),
        'heading'   => '',
        'icon'      => 'el el-home',
        'class'     => 'wpqd-dashboard-section',
        'desc'      => '<div class="wpqd-dashboard-header-outer">
            <h1 class="wpqd-dashboard-header">The <span class="wp">WP</span> <span class="qd">QuickDraw</span> Dashboard</h1>
            <a href="https://www.wpquickdraw.com/documentation/" target="_blank" class="wpqd-btn">Go to the Documentation</a>
        </div>',
        'fields'    => array(
            array(
                'id'       => 'wpqd-general-info',
                'type'     => 'raw',
                'title'    => '',
                'class'    => 'wpqd-general-info-wrap col-1',
                'content'  => apply_filters( 'wpqd_general_info', '' )
            ),
            array(
                'id'    => 'wpqd-header-settings',
                'type'  => 'raw',
                'class' => 'wpqd-settings-header col-1',
                'content' => '<h2 class="wpqd-setting-header">' . __('Settings', 'wpqd') . '</h2>'
            ),
            array(
                'id'       => 'enabled',
                'class'    => 'wpqd-enabled wpqd-setting col-1',
                'type'     => 'switch',
                'title'    => __('WP QuickDraw Features:', 'wpqd'),
                'subtitle' => __(
                    '<a href="https://www.wpquickdraw.com/documentation/settings/#enableplugin" class="wpqd-setting-info" target="_blank"><img src="' . WPQD_URL . 'assets/images/info.png" alt="Learn More" title="Learn More"></a>', 
                    'wpqd'
                ),
                'default'  => true,
            ),
            array(
                'id'       => 'deferred_load',
                'class'    => sprintf( 'wpqd-deferred-load wpqd-setting col-1%s', ( ! $options['enabled'] ) ? ' processing-disabled' : '' ),
                'type'     => 'switch',
                'title'    => __('Lazy Loading:', 'wpqd'), 
                'subtitle' => sprintf( __(
                    '<a href="https://www.wpquickdraw.com/documentation/settings/#deferredload" class="wpqd-setting-info" target="_blank"><img src="%sassets/images/info.png" alt="Learn More" title="Learn More"></a>', 
                    'wpqd'
                ), WPQD_URL ),
                'default'  => true,
            ),
            array(
                'id'    => 'pro_info_toggles',
                'type'  => 'raw',
                'class'	=> 'wpqd-pro-info-toggles wpqd-setting col-1',
                'priority' => '8',
                'content'  => __( '<tr class=" wpqd-setting col-1 pro-disabled wpqd-pro-info-toggle"><th scope="row"><div class="redux_field_th">ClearView Rendering:<span class="description"><a href="https://www.wpquickdraw.com/documentation/settings/#clearviewrendering" class="wpqd-setting-info" target="_blank"><img src="' . WPQD_URL . 'assets/images/info.png" alt="Learn More" title="Learn More"></a></span></div></th><td><fieldset id="wpqd_data-progressive_fade_hidden" class="redux-field-container redux-field redux-container-switch" data-id="progressive_fade_hidden" data-type="switch"><div class="switch-options"><label class="cb-enable" data-id="progressive_fade_hidden"><span>On</span></label><label class="cb-disable selected" data-id="progressive_fade_hidden"><span>Off</span></label><input type="hidden" class="checkbox checkbox-input  wpqd-setting col-1 pro-disabled" id="progressive_fade_hidden" name="wpqd_data[progressive_fade]" value=""></div><div class="description field-desc"><span>Pro Only</span><br><a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank">Learn More</a></div></fieldset></td></tr>

                <tr class=" wpqd-setting col-1 pro-disabled wpqd-pro-info-toggle"><th scope="row"><div class="redux_field_th">PNG to pPNG Conversion:<span class="description"><a href="https://www.wpquickdraw.com/documentation/settings/#ppng" class="wpqd-setting-info" target="_blank"><img src="' . WPQD_URL . 'assets/images/info.png" alt="Learn More" title="Learn More"></a></span></div></th><td><fieldset id="wpqd_data-ppng_hidden" class="redux-field-container redux-field redux-container-switch" data-id="ppng_hidden" data-type="switch"><div class="switch-options"><label class="cb-enable" data-id="ppng_hidden"><span>On</span></label><label class="cb-disable selected" data-id="ppng_hidden"><span>Off</span></label><input type="hidden" class="checkbox checkbox-input  wpqd-setting col-1 pro-disabled" id="ppng_hidden" name="wpqd_data[ppng]" value=""></div><div class="description field-desc"><span>Pro Only</span><br><a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank">Learn More</a></div></fieldset></td></tr>

                <tr class=" wpqd-setting col-1 pro-disabled wpqd-pro-info-toggle"><th scope="row"><div class="redux_field_th">TrueZoom:<span class="description"><a href="https://www.wpquickdraw.com/documentation/settings/#truezoom" class="wpqd-setting-info" target="_blank"><img src="' . WPQD_URL . 'assets/images/info.png" alt="Learn More" title="Learn More"></a></span></div></th><td><fieldset id="wpqd_data-zoom_hidden" class="redux-field-container redux-field redux-container-switch" data-id="zoom_hidden" data-type="switch"><div class="switch-options"><label class="cb-enable" data-id="zoom_hidden"><span>On</span></label><label class="cb-disable selected" data-id="zoom_hidden"><span>Off</span></label><input type="hidden" class="checkbox checkbox-input  wpqd-setting col-1 pro-disabled" id="zoom_hidden" name="wpqd_data[zoom]" value=""></div><div class="description field-desc"><span>Pro Only</span><br><a href="https://www.wpquickdraw.com/plugins/wp-quickdraw-pro/" target="_blank">Learn More</a></div></fieldset></td></tr>', 'wpqd' )
                ),
            array(
                'id'       => 'wpqd-settings-save',
                'type'     => 'raw',
                'title'    => '',
                'class'    => 'wpqd-settings-save-btn wpqd-setting col-1',
                'priority' => '50',
                'content'  => '<div class="wpqd-settings-save-outer">
                    <div class="redux_field_th">' . __('Save Settings:', 'wpqd')  . '</div>
                    <div class="wpqd-button-wrap">
                        <div class="redux-action_bar">
                            <span class="spinner"></span>
                            <input type="submit" name="redux_save" id="redux_save-wpqd-settings" class="button button-primary" value="Save" />
                        </div>
                    </div>
                </div>'
            ),
            array(
                'id'       => 'wpqd-status',
                'type'     => 'raw',
                'title'    => '',
                'class'    => 'wpqd-status-wrap col-2',
                'priority' => '60',
                'content'  => apply_filters( 'wpqd_library_status', '' )
            ),
            array(
                'id'       => 'wpqd-processing-status',
                'type'     => 'raw',
                'title'    => '',
                'class'    => 'wpqd-processing-wrap col-2',
                'priority' => '70',
                'content'  => apply_filters( 'wpqd_processing_status', '' )
            ),
        )
    ) );

    /**
     * Behavior
     */
    Redux::setSection( $wpqd_opt_name, array(
        'title'     => __('Behavior', 'wpqd'),
        'heading'   => __('General Settings', 'wpqd'),
        'icon'      => 'el el-screen',
        'fields'    => array(
            array(
                'id'        => 'file_types',
                'type'      => 'checkbox',
                'title'     => __('Apply To File Types', 'wpqd'),
                'subtitle'  => __('Check the image types you would like to QuickDraw', 'wpqd'),
                'options'   => array(
                    'png' => 'PNG',
                    'jpg' => 'JPEG',
                    'gif' => 'GIF'
                ),
                'default'   => array(
                    'png' => '1',
                    'jpg' => '1',
                    'gif' => '1'
                ),
                'class' => 'hidden'
            ),

            array(
                'id'    => 'behavior-header',
                'type'  => 'info',
                'title' => __('Behavior', 'wpqd')
            ),

            array(
                'id'        => 'enhancement_behavior',
                'type'      => 'slider',
                'title'     => __('Image Enhancement Behavior', 'wpqd'),
                'subtitle'  => __('Select an image enhancement behavior: Aggressive, Optimized (recommended), or Lazy.<br><a href="https://www.wpquickdraw.com/documentation/settings/#enhancement" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'desc'      => '<ul class="behavior-labels"><li id="behavior-label-1">Lazy</li><li id="behavior-label-2">Optimized</li><li id="behavior-label-3">Aggressive</li></ul>
                <div id="wpqd_custom_behavior">Custom Behavior Settings Enabled</div>',
                'default'   => 2,
                'min'       => 1,
                'step'      => 1,
                'max'       => 3,
                'display_value' => 'label'
            ),

            array(
                'id'       => 'enhancement_behavior_custom',
                'type'     => 'switch',
                'title'    => __('Custom Behavior Settings Enabled', 'wpqd'),
                'default'  => false,
                'class'    => 'hidden'
            ),

            array(
                'id'       => 'progress',
                'type'     => 'switch',
                'title'    => __('Progress Indicator', 'wpqd'),
                'subtitle' => __('Toggle this setting OFF to hide the progress indicator as images enhance<br><a href="https://www.wpquickdraw.com/documentation/settings/#progressindicator" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'default'  => false,
            ),
        ),
    ) );

    /**
     * Advanced Configuration
     */
    Redux::setSection( $wpqd_opt_name, array(
        'title'     => __('Advanced Configuration', 'wpqd'),
        'icon'      => 'el el-cogs',
        'id'        => 'advanced-config',
        'class'     => 'advanced-config',
        'fields'    => array(
            array(
                'id'       => 'resize',
                'type'     => 'switch',
                'title'    => __('Intelligent Resize', 'wpqd'),
                'subtitle' => __('Toggle this setting ON to enable intelligent resizing of images when a browser resizes<br><a href="https://www.wpquickdraw.com/documentation/settings/#intelligentresize" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'default'  => true,
            ),

            array(
                'id'       => 'trigger',
                'type'     => 'select',
                'title'    => __('Trigger Type', 'wpqd'),
                'subtitle' => __('<a href="https://www.wpquickdraw.com/documentation/settings/#triggertype" class="wpqd_documentation" target="_blank">Learn More</a>'),
                'options'  => array(
                    'scroll' => 'Scroll',
                    'hover' => 'Hover'
                ),
                'default'  => 'scroll',
            ),

            array(
                'id'             => 'scroll_sensitivity',
                'type'           => 'text',
                'validate_callback' => 'wpqd_validate_scroll_sensitivity',
                'title'          => __('Scroll Load Sensitivity', 'wpqd'),
                'subtitle'       => __(
                    'Defines the threshold from the bottom of the viewport to the top of image when image enhancement starts during scroll. Enter a decimal value between 0 and 1.<br><a href="https://www.wpquickdraw.com/documentation/settings/#scrollload" class="wpqd_documentation" target="_blank">Learn More</a>', 
                    'wpqd'
                ),
                'default'        => '0.25'
            ),

            array(
                'id'       => 'placeholder',
                'type'     => 'select',
                'title'    => __('Initial Image State', 'wpqd'),
                'subtitle' => __('<a href="https://www.wpquickdraw.com/documentation/settings/#initialimage" class="wpqd_documentation" target="_blank">Learn More</a>'),
                'options'  => array(
                    'low' => 'Low Resolution Image',
                    'placeholder' => 'Placeholder Image',
                    'transparent' => 'Transparent (Spacer GIF)'
                ),
                'default'  => 'low',
            ),

            array(
                'id'       => 'placeholder_img',
                'type'     => 'media',
                'url'      => false,
                'title'    => __('Placeholder Image', 'wpqd'),
            ),

            array(
                'id'       => 'js_support_msg',
                'type'     => 'switch',
                'title'    => __('Javascript Support Load Message', 'wpqd'),
                'subtitle' => __('<a href="https://www.wpquickdraw.com/documentation/settings/#javascriptsupport" class="wpqd_documentation" target="_blank">Learn More</a>'),
                'default'  => true,
            ),

            array(
                'id'       => 'crop_trim',
                'type'     => 'select',
                'title'    => __('Crop/Trim Settings', 'wpqd'),
                'options'  => array(
                    'trim' => 'Trim',
                    'trans_pixels' => 'Add Transparent Pixels',
                ),
                'default'  => 'trim',
                'class' => 'hidden'
            ),

            array(
                'id'       => 'seo_user_agent',
                'type'     => 'textarea',
                'title'    => __('Custom SEO Bot User Agent Strings', 'wpqd'),
                'subtitle' => __('One value per line (default value "Industry Standard bots" will include most search engine crawlers)<br><a href="https://www.wpquickdraw.com/documentation/settings/#customseo" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'default'  => 'Industry Standard bots',
            ),

        ),
    ) );
    Redux::hideSection( $wpqd_opt_name, 'advanced-config', true );

    /**
     * API Key
     */
    Redux::setSection( $wpqd_opt_name, array(
        'title'     => __('API Key', 'wpqd'),
        'icon'      => 'el el-upload',
        'id'        => 'api-key',
        'class'     => 'api-key',
        'fields'    => array(
            array(
                'id'             => 'api_key',
                'type'           => 'text',
                'title'          => __('API Key', 'wpqd'),
                'subtitle'       => __(
                    'Your WPQD API key is associated with your site URL.', 
                    'wpqd'
                )
            ),

            array(
                'id'       => 'generate_api_key',
                'type'     => 'raw',
                'title'    => __('Generate API Key', 'wpqd'),
                'subtitle' => __('Has your site URL changed? If so, you must generate a new API key.', 'wpqd'),
                'content'  => '<div class="button button-primary">Generate New Key</div>',
                'full_width' => false
            ),

            array(
                'id'       => 'test_api_key',
                'type'     => 'raw',
                'title'    => __('Test API Connection', 'wpqd'),
                'subtitle' => __('Send a test request to the WPQD API.', 'wpqd'),
                'content'  => '<div class="button button-primary">Test Connection</div>',
                'full_width' => false
            ),
        )
    ) );

    /**
     * Tools
     */
    Redux::setSection( $wpqd_opt_name, array(
        'title'     => __('Tools', 'wpqd'),
        'icon'      => 'el el-wrench',
        'fields'    => array(

            array(
                'id'       => 'compatibility_check',
                'type'     => 'raw',
                'title'    => __('Compatibility Check', 'wpqd'),
                'subtitle' => __('Perform compatibility check on this WordPress site.<br><a href="https://www.wpquickdraw.com/documentation/settings/#compatibilitycheck" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'content'  => '<div class="button button-primary">Perform Check</div>',
                'full_width' => false
            ),

            array(
                'id'       => 'regenerate_images',
                'type'     => 'raw',
                'title'    => __('Regenerate Images', 'wpqd'),
                'subtitle' => __('Regenerate image sets using current plugin settings.<br><a href="https://www.wpquickdraw.com/documentation/settings/#regenerateimages" target="_blank">Learn More</a>', 'wpqd'),
                'content'  => '<div class="button button-primary">Regenerate Images</div>',
                'full_width' => false
            ),

            array(
                'id'       => 'reset_image_queue',
                'type'     => 'raw',
                'title'    => __('Reset Image Process Queue', 'wpqd'),
                'subtitle' => __('Clear the image set process queue (this will cancel any imports currently in progress).<br><a href="https://www.wpquickdraw.com/documentation/settings/#resetqueue" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'content'  => '<div class="button button-primary">Reset Queue</div>',
                'full_width' => false
            ),
            
            array(
                'id'       => 'image_status_page',
                'type'     => 'raw',
                'title'    => __('View Image Status', 'wpqd'),
                'subtitle' => __('See a detailed breakdown of images & if they have been processed by WP QuickDraw.<br><a href="https://www.wpquickdraw.com/documentation/settings/#viewimagestatus" class="wpqd_documentation" target="_blank">Learn More</a>', 'wpqd'),
                'content'  => sprintf(
                    '<a href="%s"><div class="button button-primary">View Image Status Page</div></a>',
                    get_site_url( null, '/wp-admin/tools.php?page=wpqd_image_status_page' )
                ),
                'full_width' => false
            ),

        ),
    ) );
}
add_action( 'wpqd/redux/init', 'wpqd_settings_init' );

/**
 * Custom validation callback for "Scroll Sensitivity"
 * 
 * @param   array   $field
 * @param   array   $value
 * @param   array   $existing_value
 * @return  array
 */
function wpqd_validate_scroll_sensitivity( $field, $value, $existing_value ) {
    $return = array(
        'value' => $existing_value
    );

    if  ( ! is_numeric($value) || ! is_float($value + 0) ) {
        $field['msg'] = __('You must enter a decimal value.', 'wpqd');
        $return['error'] = $field;
        return $return;
    }

    if ( $value > 1 || $value < 0 ) {
        $field['msg'] = __('You must enter a value between 0 and 1.', 'wpqd');
        $return['error'] = $field;
        return $return;
    }

    $return['value'] = $value;
    return $return;
}

/**
 * Apply bold class to current "Image Enhancement Behavior" field label
 *
 * @param   array   $field
 * @return  array
 */
function wpqd_enhancement_behavior_init( $field ) {
    if ( !isset($field['desc']) || strpos($field['desc'], 'behavior-label-') === false ) {
        return;
    }

    $options = get_option(WPQD_OPTIONS);
    $current_value = isset( $field['default'] ) ? $field['default'] : false;

    if ( isset($options[$field['id']]) ) {
        $current_value = $options[$field['id']];
    }

    if ( !$current_value ) {
        return;
    }

    $html_id = 'id="behavior-label-' . $current_value . '"';

    $field['desc'] = str_replace(
        $html_id,
        $html_id . ' class="strong"',
        $field['desc']
    );

    return $field;
}
add_action( 'redux/options/' . $wpqd_opt_name . '/field/enhancement_behavior', 'wpqd_enhancement_behavior_init' );

/**
 * Save settings as JSON-encoded string in {uploads_dir}/wpqd/settings.js
 *
 * @param   array   $this_options
 * @return  void
 * @throws  Exception
 */
function wpqd_save_config_file($this_options) {
    WP_Filesystem();
    global $wp_filesystem;

    $uploads = wp_upload_dir();
    $path = trailingslashit($uploads['basedir'] . WPQD_UPLOADS_FOLDER);
    $file = $path . 'settings.js';

    try {
        if ( ! $wp_filesystem->is_writable($uploads['basedir']) ) {
            throw new Exception( sprintf('Your uploads directory must be writable: %1$s', $uploads['basedir']) );
        }

        if ( ! $wp_filesystem->is_dir($path) ) {
            if ( ! $wp_filesystem->mkdir($path) ) {
                throw new Exception( sprintf('Unable to create directory: %1$s', $path) );
            }
        }

        if ( ! $wp_filesystem->put_contents( $file, wpqd_format_options($this_options) ) ) {
            throw new Exception( sprintf('Unable to save settings to file: %1$s', $file) );
        }
    } catch ( Exception $e ) {
        Wpqd\Logger::log_exception( $e );
        $messages = array( $e->getMessage() );
        Wpqd\Logger::log(
            sprintf( 'WordPress is using this class to interact with the filesystem: %1$s', get_class($wp_filesystem) )
        );
        set_transient( Wpqd\PluginState::TRANSIENT_KEY_FILESYSTEM, $messages );
    }
}
add_action ('redux/options/' . $wpqd_opt_name . '/saved', 'wpqd_save_config_file');

/**
 * Format settings for save to {uploads_dir}/wpqd/settings.js
 *
 * @param   array   $options
 * @return  string
 */
function wpqd_format_options( $options ) {
    global $wpqd_opt_name;
    $formatted_options = array();
    $include_sections = array( 'dashboard', 'behavior', 'advanced-config' );
    $skip_fields = array( 'enhancement_behavior', 'advanced_config' );
    $general_fields = array( 'enabled', 'file_types' );
    $sections = Redux::constructSections($wpqd_opt_name);

    foreach ( $sections as $section ) {
        if ( ! isset($section['id']) || ! in_array( $section['id'], $include_sections ) ) {
            continue;
        }

        if ( ! isset($section['fields']) || empty($section['fields']) ) {
            continue;
        }

        // Put dashboard & advanced configuration options in "Behavior" section
        $section_id = ( in_array( $section['id'], array( 'dashboard', 'advanced-config' ) ) ) ? 
            'behavior' : 
            $section['id'];

        foreach ( $section['fields'] as $field ) {
            if ( ! isset($field['id']) || ! isset($options[$field['id']]) ) {
                continue;
            }

            if ( in_array( $field['id'], $skip_fields ) ) {
                continue;
            }

            $current_section_id = $section_id;
            if ( in_array( $field['id'], $general_fields ) ) {
                $current_section_id = 'general';
            }

            $formatted_options[$current_section_id][$field['id']] = $options[$field['id']];
        }
    }

    $formatted_options['behavior']['ratios'] = array('1_8', '1_4', '1_2', '1_1');
    $formatted_options = apply_filters( 'wpqd_format_options', $formatted_options, $sections, $options );
    
    return 'window.hifipix_settings = ' . json_encode( $formatted_options, JSON_PRETTY_PRINT );
}

/**
 * Add "pro disabled" class to advertisement wrapper
 * 
 * @return  void
 */
function wpqd_advertisement_class() {
    echo 'pro-disabled';
}
add_action( 'wpqd/redux/advertisement', 'wpqd_advertisement_class' );

/**
 * Add link to settings page from Plugins list
 *
 * @param   array   $links
 * @return  array
 */
function wpqd_plugin_settings_link( $links ) {
    $links[] = '<a href="admin.php?page=wpqd_options_page">' . __( 'Settings', 'wpqd' ) . '</a>';
  	return $links;
}
add_filter( 'plugin_action_links_' . WPQD_BASENAME, 'wpqd_plugin_settings_link' );

/**
 * Custom CSS stylesheets & JS scripts for admin settings page
 *
 * @return  void
 */
function wpqd_admin_enqueue_scripts() {
    // Google fonts
    wp_enqueue_style( 'wpqd-fonts-raleway', '//fonts.googleapis.com/css?family=Raleway:300,400,500,600,700,800', array(), WPQD_VERSION );
    
    // Zebra Dialog - @see https://github.com/stefangabos/Zebra_Dialog
    wp_enqueue_style( 'wpqd-zebra-dialog-styles', WPQD_URL . 'assets/css/zebra_dialog/zebra_dialog.min.css', array(), WPQD_VERSION );
    wp_enqueue_script('wpqd-zebra-dialog-script', WPQD_URL . 'assets/js/zebra_dialog.min.js', array( 'jquery' ), WPQD_VERSION);

    // Custom styles & scripts
    wp_enqueue_style( 'wpqd-admin-styles', WPQD_URL . 'assets/css/admin.css', array( 'wpqd-fonts-raleway', 'wpqd-zebra-dialog-styles' ), WPQD_VERSION );
    wp_enqueue_script('wpqd-admin-script', WPQD_URL . 'assets/js/admin.js', array( 'jquery', 'redux-nouislider-js', 'redux-js', 'redux-field-slider-js' ), WPQD_VERSION);
    wp_enqueue_script('wpqd-admin-ml-script', WPQD_URL . 'assets/js/media-library.js', array( 'jquery' ), WPQD_VERSION);
    wp_enqueue_script('wpqd-admin-plugin-state', WPQD_URL . 'assets/js/plugin-state.js', array( 'jquery', 'wpqd-zebra-dialog-script' ), WPQD_VERSION);
}
add_action( 'admin_enqueue_scripts', 'wpqd_admin_enqueue_scripts' );

/**
 * Run custom hooks after all plugins are loaded
 * 
 * @return  void
 */
function wpqd_redux_init() {
    global $wpqd_opt_name;
    do_action( 'wpqd/redux/init', $wpqd_opt_name );
}
add_action( 'plugins_loaded', 'wpqd_redux_init' );