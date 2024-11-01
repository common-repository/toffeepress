=== ToffeePress ===
Contributors: danrwalker,jamesdurham
Donate link: https://toffeepress.twistphp.com/about-us
Tags: toffeepress, toffee, compress, images, media, png, jpg, optimise, compression
Requires at least: 5.0
Tested up to: 5.3.2
Requires PHP: 7.0
Stable tag: 0.10.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Compress your images to a high quality allowing your pages to load faster, use less bandwidth, less storage and score better on general SEO tests.

== Description ==

Compress all the images in your media library effortlessly, we provide near lossless compression to ensure that your images stay crisp and sharp.

Once compressed with our service your images will:

* be smaller in file size
* load quicker reducing your overall page load time
* use less of your customers bandwidth/data allowance
* help to improve your overall SEO score

A realtime intuitive interface keeps you informed of how many images you have compressed, how long you have to go and how much data you have saved.

As this service is free to use it requires an API Key that you can obtain free from the plugin settings page or our website. This is to ensure that all users get a consistent, fast and reliable service each and every time. We provide 500 free credits every month to all registered accounts.

[Register for a free API key](https://toffeepress.twistphp.com/register "Register for a free API key")

== Installation ==

1. Install via WordPress plugin screen, or Upload `toffeepress` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin from Admin > Plugins menu.
3. Once activated you should check ToffeePress > Settings
4. Enter your API Key, if you dont have an API Key you can register one for free at [toffeepress.twistphp.com](https://toffeepress.twistphp.com/register "Register for a free API key")

We require the use of an API key to connect to our remote image compression server to ensure that

== Frequently Asked Questions ==

= Is it FREE to get an API key? =

Yes, our API keys are FREE, you can register directly from the plugin settings page or register via our website [toffeepress.twistphp.com](https://toffeepress.twistphp.com/register "Register for a free API key") to get started

= Why do I need an API key? =

We require you to use an API key to access our remote compression servers, this is to ensure that every user gets a fast reliable service by enforcing a fair usage policy.

= Are there different levels of compression? =

Yes, we offer 3 different levels of image compression that are configurable from within the plugin settings page:

* **High Quality** - Small savings, better image quality
* **Medium Quality** - Balance between compression and quality
* **Low Quality** - Higher image compression (although called Low Quality the image is still fairly crisp, just provides better savings)

= Can I backup and restore my original images? =

Yes, we have the ability to backup your original full sized images by checking the "Keep Originals" option on the settings page. There is an option to restore the original images as well the other sizes which are regenerated from the backed up full-sized originals.

= What are ToffeePress credits? =

Credits are redeemed one per image that you compress, we offer 500 free credits per month to all registered accounts. You can purchase additional credits from our website should you need to compress a large volume of images.

== Screenshots ==

1. Realtime stats to let you know your progress, when the process will be finished and how much data you have saved

== Changelog ==

= 0.10.0 =
* Updated the wp_original backup folder to follow the structure of uploads /year/month/file
* Added the ability to restore images by size from the wp_original backup folder
* Added a warning message to the compressor page with original backup is disabled
* Added more in-depth error messages around the backup and restore process
* Updated the FAQs in the readme.txt file
* Fixed some form label links, number formats, typos and incorrect element id's

= 0.9.1 =
* Updated styling and layout of the pages
* Fixed bug in thumb regeneration, catch and process WP error response
* Fixed PHP notice when calling filesize() on non existent file

= 0.9.0 =
* Added the ability to register for an API key from within the plugin
* Added 3 new selectable compression levels
* Added the ability to backup the original (wp_original) image before compressing
* Fixed PHP warnings in multiple locations
* Updated styling of the plugin pages
* Updated readme.txt file

= 0.8.1 =
* Updated readme.txt file
* Added plugin logo

= 0.8 =
* Initial Release

== Upgrade Notice ==

= 0.10.0 =
Added the ability to restore images from back-up as well as other fixes

= 0.9.0 =
Added 3 new compression levels and the ability to backup original images before compression

== Arbitrary section ==

Our website and remote compression servers are kindly hosted by the open source PHP framework [TwistPHP.com](https://twistphp.com/ "The framework with a TWIST").
