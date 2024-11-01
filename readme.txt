=== WP QuickDraw ===
Contributors: hifipix
Tags: responsive, progressive, png, ppng, image, rendering, images, media library, performance, lazy load, renderful, contentful, cache
Requires at least: 3.2
Tested up to: 5.3.2
Requires PHP: 5.5
Stable tag: 1.5.9
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
 
Highly accelerated page loading and rendering for image-rich websites. Compatible with caching and image optimization plugins for the highest SEO.

== Description ==

Highly accelerated page loading and rendering for image-rich websites. Compatible with caching and image optimization plugins for the highest SEO.

= Faster engagement. Faster results! =

**Visitor engagement is the number one priority for every website.** Don’t make your visitors wait for page elements to stop jumping around; for buttons to work; for high resolution images to load. With over 10X rendering speed enhancements, **WP QuickDraw is simply the fastest way for your website visitors to start seeing and engaging with your content**. Even your SEO ranking gets a boost so your visitors will find you faster when searching. One secret? Search engines don’t even “see” images during the initial page load because WP QuickDraw embeds initial image data right into the page. Fully compatible with popular CDN, image optimization and caching plugins including WP Super Cache, W3 Total Cache and WP Rocket, WP QuickDraw means no more need to load small, low quality images to increase your speed. Breathe easy. Sell more. **Sell dramatically.** It’s time to load large. **And it’s free!** More info? [Go here.](https://www.wpquickdraw.com/documentation/how-it-works/)

= Supercharge Your Image Rendering =
WP QuickDraw does everything in its power to speed up image delivery on your website.

* Automatic processing of new images
* Progressive image delivery for highly accelerated page loading, user interaction and max Google page rank and speed
* Compatible with popular caching, CDN, SEO and image optimization plugins including WP Super Cache, W3 Total Cache and WP Rocket
* Device-responsive image delivery for optimum quality during each user session
* Deferred (lazy) progressive loading of off-screen images until user scrolls to them
* **[Premium]** support for our [WP QuickDraw Pro](https://www.wpquickdraw.com/) users
* **[Premium]** the ability to use our patented [TrueZoom™, ClearView™, and pPNG™](https://www.wpquickdraw.com/) technologies to deliver amazing user experiences 

= Premium Features and Support =

The HifiPix team aims to provide regular support for the WP QuickDraw plugin on the WordPress.org forums. But please understand that we do prioritize our premium support. This one-on-one ticketing support is available to people who bought [WP QuickDraw Pro](https://www.wpquickdraw.com/).

Did you know that [WP QuickDraw Pro](https://www.wpquickdraw.com/) also has several extra features:

* TrueZoom™ on-demand maximum detail delivery when users zoom on their devices
* ClearView™ technology for the most naturally appearing progressive images
* pPNG™ conversion for 5x lossless visual acceleration of progressive PNG images
* Dedicated online support and automatic upgrades for one year

The [WP QuickDraw Pro](https://www.wpquickdraw.com/) plugin is well worth your investment!


== Changelog ==

= 1.5.9 =
* Release date: January 21, 2020
* Improved image processing retry logic.
* Fixed bug which caused arbitrary images to become disabled in WP QuickDraw.
* Fixed bug where image progress bar would appear in admin when there were no images to process.

= 1.5.8 =
* Release date: January 15, 2020
* Added checks to avoid initialization of WPQD processing for non-image attachments.
* Removed WPQD enabled option from Media Library for non-image attachments.
* Fixed logical error in number of generated vs. total WPQD-enabled images calculation.
* Cache remaining quota percentage in database for use in dashboard display to reduce load on API.
* Update quota percentage in dashboard dynamically while image processing is in progress.
* Responsive style fixes for Media Library Status graphs in admin dashboard.
* Disabling "WP QuickDraw Features" setting now disables other UI toggles in admin dashboard.

= 1.5.7 =
* Release date: January 13, 2020
* All images at least 768px wide will now be processed (regardless of height dimension).
* Minor bug fixes: addressed several PHP notices.
* Updated image progress bar logic to use countdown instead of # of total.
* Fixed image regeneration on plugin update trigger so that it will not run on a new install.
* Improved messaging added to Media Library "WPQD Enabled" option.
* Responsive style fixes and added message about flushing cache in admin dashboard.