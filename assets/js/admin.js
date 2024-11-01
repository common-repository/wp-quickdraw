jQuery(document).ready(function($){
    $('body').on('click', '#redux_save-wpqd-settings', function(e) {
        e.preventDefault();
        $('#redux_save-footer').trigger('click');
    });

    // Bold labels for "Enhancement Behavior" slider options
    var toggleEnhancementLabels = function () {
        var value = $('#enhancement_behavior').val();
        var $els = $('.behavior-labels li');
        var $el = $('.behavior-labels #behavior-label-' + value);

        $els.removeClass('strong');
        $el.addClass('strong');
    };

    // Select options in "Advanced Configuration" per "Enhancement Behavior" field
    var updateEnhancementSettings = function (value) {
        // "Deferred Load" switch
        var $disableDeferred = $('.cb-disable[data-id="deferred_load"]');
        var $enableDeferred = $('.cb-enable[data-id="deferred_load"]');

        // "Trigger Type" select
        var $triggerTypeInput = $('#trigger-select');
        var $triggerTypeOpts = $triggerTypeInput.find('option');
        var $triggerTypeScroll = $triggerTypeInput.find('option[value="scroll"]');
        var $triggerTypeHover = $triggerTypeInput.find('option[value="hover"]');

        // "Initial State" select
        var $initialStateInput = $('#placeholder-select');
        var $initialStateOpts = $initialStateInput.find('option');
        var $initialStateLow = $initialStateInput.find('option[value="low"]');

        switch (value) {
            case '1':
                $enableDeferred.trigger('click', ['slider']);

                $triggerTypeOpts.attr('selected', false);
                $triggerTypeHover.attr('selected', 'selected');
                $triggerTypeInput.trigger('change', ['slider']);
                break;
            case '2':
                $enableDeferred.trigger('click', ['slider']);

                $triggerTypeOpts.attr('selected', false);
                $triggerTypeScroll.attr('selected', 'selected');
                $triggerTypeInput.trigger('change', ['slider']);
                break;
            case '3':
                $disableDeferred.trigger('click', ['slider']);
                break;
        }

        $initialStateOpts.attr('selected', false);
        $initialStateLow.attr('selected', 'selected');
        $initialStateInput.trigger('change', ['slider']);
    };

    $('body').on('change', '#enhancement_behavior', function () {
        toggleEnhancementLabels();
        updateEnhancementSettings($(this).val());
    });

    $('body').on('change', '#enabled', function () {
        var $lazyLoadWrapper = $( 'tr.wpqd-deferred-load.wpqd-setting' );

        switch ( $(this).val() ) {
            case '1':
                $lazyLoadWrapper.removeClass( 'processing-disabled' );
                break;
            case '0':
                $lazyLoadWrapper.addClass( 'processing-disabled' );
                break;
        }
    });
});
