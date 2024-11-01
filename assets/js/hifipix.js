// hifipix.js

function initializeHifiPix() {
  jQuery('.wpqd').each( function() {

      // Skip images that are not visible on the page
      if ( jQuery(this).is(':hidden') ) {
        jQuery(this)
          .removeClass( 'wpqd' )
          .removeAttr( 'wpqd-id' )
          .removeAttr( 'wpqd-imageset' )
          .removeAttr( 'wpqd-ar' )
          .removeAttr( 'wpqd-breakpoints' )
          .attr( 'src', jQuery(this).attr('orig_src') );
        return true;
      }

    jQuery(this).hifiLoad();
   }
  );
}

jQuery.widget( 'wpqd.hifiLoad', {
  image: null,
  loadImage: null,
  enabledImageExtensions: [],
  normalImageExtensions: [],
  events: [],

  _create: function () {
    var that = this;

    
    // List of image types that are loaded without
    // the ProgressivePNG codec
    jQuery.each(window.hifipix_settings.general.file_types, function(type, enabled) {
      if ( ! enabled ) {
        return true;
      }

      that.enabledImageExtensions.push( type );

      // TODO: Uncomment this when progressive PNG functionality works
      // if ( type === 'png' ) {
      //   return true;
      // }

      that.normalImageExtensions.push( type );

      // Also include "jpeg" as a valid extension type
      if ( type === 'jpg' ) {
        that.normalImageExtensions.push( 'jpeg' );
        that.enabledImageExtensions.push( 'jpeg' );
      }
    })

    var jqueryElement = this.element;

    var imagesetAttr = jqueryElement.attr('wpqd-imageset');
    if (imagesetAttr !== undefined) {
      // Note: hifi-imageset JSON uses singlequotes for ability
      // to be contained within html attributes. Replace with
      // double quotes for parsing.
      imagesetAttr  = imagesetAttr.replace(/'/g, "\"");

      var hifiImageset = JSON.parse(imagesetAttr);
      var filename = hifiImageset.wpqd_root_filename + '.' + hifiImageset.wpqd_file_extension;

      hifiCacheManager.init();

      // Build image object with metadata about image
      // including jQuery element for that image
      // to be used by loading functions
      this.image = {
        id: jqueryElement.attr('wpqd-id'),
        jqueryElement: jqueryElement,
        filename: filename,
        type: hifiImageset.wpqd_file_extension,
        imageset: hifiImageset,
        aspectRatio: jqueryElement.attr('wpqd-ar'),
        origin: this.getImageOrigin( jqueryElement.attr('orig_src') ),
        breakpoints: JSON.parse( jqueryElement.attr('wpqd-breakpoints') ).sort( function(a, b){return a - b} )
      }

      hifiLoadManager.initProperties( this.image.id, this.image.breakpoints );

      // Skip disabled image extension types
      if ( ! this.enabledImageExtensions.includes(this.image.type) ) {
        return;
      }

      if ( this.image.jqueryElement[0].style.height === "" ) {
        if (this.image.jqueryElement.attr('height') === undefined || this.image.jqueryElement.attr('height') === 0) {
          // Use image "width" attribute to set initial image size
          this.image.jqueryElement.height( this.image.jqueryElement.attr('width') * (1/this.image.aspectRatio) );
        } else {
          this.image.jqueryElement.height( this.image.jqueryElement.width() * (1/this.image.aspectRatio) );
        }
      }

      
      // No-Op image function for debugging
      this.loadImage = function() {
        console.log('loading nothing');
      }

            // free
      this.loadImage = this.loadNormalImage;
      
      // Set various load event handlers
      if ( window.hifipix_settings.behavior.deferred_load == '1' ) {
        if ( window.hifipix_settings.behavior.trigger == 'hover' ) {
          this.setHoverEventHandler();
        } else {
          this.setScrollEventHandler();
        }
      }
      
      this.setDocumentReadyEventHandler();

      if ( window.hifipix_settings.behavior.resize == '1' ) {
        this.events.push('resize');
      }

      
      if ( this.events.length > 0 ) {
        this.setSizingEventHandlers( this.image.jqueryElement.innerWidth() );
      }
    }
  },

  getImageOrigin: function ( src ) {
    if ( typeof src === 'undefined' ) {
      return window.location.origin;
    }

    if ( src.match(/^(https?:)?\/\//i) === null ) {
      var srcUrl = new URL( src, window.location.origin );
    } else {
      var srcUrl = new URL( src );
    }    
    return srcUrl.origin;
  },

  // Checks if image is already loaded or if it's inside our threshold
  shouldLoadImage: function ( size, scrollY ) {
    // Image is already loaded
    if ( hifiLoadManager.isLoaded(this.image.id, size) ) {
      return false;
    }

    // "Deferred Load" setting is disabled
    if ( ! window.hifipix_settings.behavior.deferred_load || window.hifipix_settings.behavior.deferred_load === '0' ) {
      return true;
    }

    // Image is outside visible threshold
    if ( ! this.isImageVisible(scrollY) ) {
      return false;
    }

    hifiLoadManager.setImageToLoad( this.image.id );
    return true;
  },

  // Checks if image is within "visible" threshold per scroll_sensitivity
  // setting in hifipix.settings.js
  isImageVisible: function ( scrollY ) {
    var elTop               = this.image.jqueryElement.offset().top,
        windowThreshold     = window.innerHeight * (1 + parseFloat(window.hifipix_settings.behavior.scroll_sensitivity)),
        offset              = windowThreshold + scrollY;

    if (offset > elTop) {
      return true;
    }

    return false;
  },

  // Wrapper function for loadImage that checks whether the image
  // should be loaded and sets status in "hifiLoadManager"
  loadImageWrapper: function ( scrollY, size ) {
    if (typeof size === 'undefined') {
      size = this.getImageBreakpoint();
    }

    if ( ! this.shouldLoadImage(size, scrollY) ) {
      return false;
    }

    this.loadImage(size);
    hifiLoadManager.setIsLoaded(this.image.id, size, true);

    this.image.jqueryElement.css('height', '');
    return true;
  },

  // Retrieves the correct image breakpoint according to the parent element's width
  getImageBreakpoint: function () {
    var zoom        = ( typeof window.visualViewport !== 'undefined' ) ? window.visualViewport.scale : 1,
        parentWidth = this.image.jqueryElement.innerWidth() * window.devicePixelRatio * zoom,
        output      = this.image.breakpoints[this.image.breakpoints.length - 1];

    jQuery.each(this.image.breakpoints, function(i, breakpoint) {
        if (parentWidth <= breakpoint) {
          output = breakpoint;
          return false;
        }
      });

    return output;
  },

  setDocumentReadyEventHandler: function () {
    var that = this;

    function onReady() {
      that.loadImageWrapper(window.scrollY);
    }
    jQuery(document).ready(onReady);
  },

  // Load images when cursor hovers over them
  setHoverEventHandler: function () {
    var that = this;

    function onHover() {
        that.loadImageWrapper(window.scrollY);
    }

    this.image.jqueryElement.on( 'mouseover', onHover );
  },

  // Load images while scrolling through page
  setScrollEventHandler: function () {
    var that            = this,
        lastScrollY     = 0,
        ticking         = false;

     // Keeps track of the last scroll value
    function onScroll() {
        lastScrollY = window.scrollY;
        requestTick();
    }

    // Prevents multiple calls to rAF (otherwise it's a major performance issue)
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(update);
            ticking = true;
        }
    }

    // rAF callback
    function update() {
        that.loadImageWrapper(lastScrollY);
        ticking = false;
    }

    jQuery(window).on('scroll', onScroll);
  },

  setSizingEventHandlers: function ( initialWidth ) {
    var that              = this,
        previousWidth     = initialWidth,
        currentBreakpoint = this.getImageBreakpoint(),
        currentZoom       = ( typeof window.visualViewport !== 'undefined' ) ? window.visualViewport.scale : 1;

    function onResize() {
      var currentWidth = that.image.jqueryElement.innerWidth() * currentZoom * window.devicePixelRatio;

      if ( needsResize( currentWidth ) ) {
          that.loadImageWrapper(window.scrollY, currentBreakpoint);
      }
      that.responsiveResize();
    }

    function needsResize( currentWidth ) {
      var output = false;

      // If the previous width was larger than the current width, no  
      // need to resize (we've already loaded a larger image)
      if (previousWidth >= currentWidth || currentZoom < 1) {
        return output;
      }

      // Iterate through breakpoints defined in hifipix.settings.js
      jQuery.each(that.image.breakpoints,
        function(i, breakpoint) {
          if (currentWidth <= breakpoint) {
            // If the breakpoint hasn't changed, no need to resize
            if (breakpoint === currentBreakpoint) {
              return false;
            }
            
            currentBreakpoint = breakpoint;
            output = true;
            return false;
          }
        }
      );
      previousWidth = currentWidth;
      return output;
    }

    
    if ( this.events.includes('resize') ) {
      jQuery(window).on('resize', onResize);
    }

      },

   // Loading Functions

  // Progressively load images in an imageset
  // by recursively calling self and incrementing
  // array index of imageset
  loadNormalImage: function ( breakpoint, indexToLoad ) {
    var that = this;
    if (indexToLoad === undefined) {
      indexToLoad = 0;
    }

    if (breakpoint === undefined || breakpoint === 'default') {
      breakpoint = '1200';
    }

    var is = this.image.imageset;
    if (is !== undefined) {
      if (indexToLoad < window.hifipix_settings.behavior.ratios.length) {
        var newImagePath = "/wp-content/uploads/wpqd/" + is.wpqd_uid + '/' + is.wpqd_root_filename + '_' + breakpoint + '_' + window.hifipix_settings.behavior.ratios[indexToLoad] + '.' + is.wpqd_file_extension;
        var largestImagePath = "/wp-content/uploads/wpqd/" + is.wpqd_uid + '/' + is.wpqd_root_filename + '_' + breakpoint + '_' + window.hifipix_settings.behavior.ratios[window.hifipix_settings.behavior.ratios.length - 1] + '.' + is.wpqd_file_extension;
        // are we loading smaller images, between the breakpoints?

      
        // Set height based on hf-ar
        if (this.image.jqueryElement.width() > 0) {
          this.responsiveResize();
        }

        // Check if the largest image of the set we are about to load
        // is cached in localStorage via hifiCacheManager
        // If image is cached, skip progressive loading process and go 
        // straight to the src of largest image
        if (hifiCacheManager.isCached(largestImagePath)) {
                  this.image.jqueryElement[0].src = this.image.origin + largestImagePath;
        } else {
          jQuery.get(newImagePath).then(function() {
            that.image.jqueryElement[0].src = that.image.origin + newImagePath;

            jQuery(that.image.jqueryElement[0]).load(function(){
              // Put src in cache
              hifiCacheManager.add(newImagePath);

              indexToLoad = indexToLoad + 1;

              that.loadNormalImage(breakpoint, indexToLoad);
            });
          });
        }
      }
    }
  },

  
  responsiveResize: function () {
    var that = this;
    this.image.jqueryElement
      .height( this.image.jqueryElement.width() * (1/this.image.aspectRatio) )
      .promise()
      .done( function () {
        that.checkImageHeight();
    });
  },

  // Check if CSS height matches actual image height
  checkImageHeight: function() {
    var that = this;

    if ( parseInt(that.image.jqueryElement[0].getBoundingClientRect().height) != parseInt(this.image.jqueryElement[0].style.height.slice(0, -2)) ) {
      setTimeout( function() {
        that.checkImageHeight()
      }, 100);
    } else {
      hifiLoadManager.unsetImageToLoad( this.image.id );
    }
  },

  }); // end hifiLoad widget

var hifiLoadManager = {
  imagesToLoad: [],
  _imagesToLoad: [],
  secondPass: false,
  loadedImages: {},

  initProperties: function (id, breakpoints) {
    var scope = this;

    this.loadedImages[id] = {
      'loaded': {}
    };

    jQuery.each(breakpoints, function(i, breakpoint) {
        scope.loadedImages[id].loaded[breakpoint] = false;
    });
  },

  setIsLoaded: function (id, size, isLoaded) {
    this.loadedImages[id].loaded[size] = isLoaded;
    return this;
  },

  isLoaded: function (id, size) {
    return this.loadedImages[id].loaded[size];
  },

  setImageToLoad: function (id) {
    this.imagesToLoad.push( id );
    this._imagesToLoad.push( id );
    return this.imagesToLoad;
  },

  unsetImageToLoad: function (id) {
    var index = this.imagesToLoad.indexOf(id);
    if (index !== -1) {
      this.imagesToLoad.splice(index, 1);
    }

    if ( this.imagesToLoad.length === 0 ) {
      this.initSecondLoad();
    }
    return this.imagesToLoad;
  },

  initSecondLoad: function () {
    var that = this;

    if ( this.secondPass === true ) {
      return;
    }

    if ( this._imagesToLoad.length === 0 ) {
      return;
    }

    jQuery('.wpqd').each( function() {
      var id = jQuery(this).attr( 'wpqd-id' );

      if ( ! that._imagesToLoad.includes( id ) ) {
        jQuery(this).hifiLoad( 'destroy' ).hifiLoad();
      }
    });

    this.secondPass = true;
  }
};

var hifiCacheManager = {
  ls: [],

  init: function() {
    if(localStorage.hfp_cf && localStorage.hfp_cf !== undefined && localStorage.hfp_cf !== "") {
      this.ls = JSON.parse(localStorage.hfp_cf);
    } else {
      localStorage.hfp_cf = JSON.stringify(this.ls);
    }
  },

  add: function(src) {
    this.ls.push(src);
    this.save();
  },

  save: function() {
    localStorage.hfp_cf = JSON.stringify(this.ls);
  },

  isCached: function(src) {
    return localStorage.hfp_cf.includes(src);
  }
}
