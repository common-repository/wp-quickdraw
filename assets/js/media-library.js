jQuery(document).ready(function($){

	// Advanced options toggle
	$('body').on('click', '.wpqd_ml_advanced_toggle', function(){
		var wpqd_context = $(this).closest('.compat-attachment-fields');
		if ($(this).hasClass('wpqd_ml_advanced_toggle_closed')) {
			$(this).removeClass('wpqd_ml_advanced_toggle_closed');
			$(this).html('Hide Advanced');

			// Un-hide the advanced fields
			$( '.compat-field-wpqd_aspectratio, .compat-field-wpqd_generated, .compat-field-wpqd_root_filename, .compat-field-wpqd_file_extension', 
				wpqd_context
			).show();

		} else {
			$(this).addClass('wpqd_ml_advanced_toggle_closed');
			$(this).html('Show Advanced');

			// Hide The advanced fields
			$( '.compat-field-wpqd_aspectratio, .compat-field-wpqd_generated, .compat-field-wpqd_root_filename, .compat-field-wpqd_file_extension', 
				wpqd_context
			).hide();

		}
	});

});
