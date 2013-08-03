=== bbPress Notify (No Spam) ===
Contributors: useStrict
Author URI: http://www.usestrict.net/
Plugin URI: http://usestrict.net/2013/02/bbpress-notify-nospam/
Tags: bbpress, email notification, no spam
Requires at least: 3.1
Tested up to: 3.5.1
Text Domain: bbpress_notify
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.2.1
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=VLQU2MMXKB6S2

== Description ==
This is a modification of the original bbPress-Notify plugin, after several failed attempts to contact the author requesting that he add the no-spam code to it. I don't like spam. Do you?

This plugin integrates into bbPress and sends a notification via e-mail when new topics or replies are posted. It is fully configurable in the bbPress settings.

Settings include:
* Notification recipients for new topics, 
* Notification recipients for new replies, 
* Notification e-mail's subject and body for both new topics and replies


== Installation ==

1. Upload the entire plugin folder via FTP to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==
= Did you write this plugin? =
No, I simply added a spam filter.

= Why did you do this? =
Because the original author never answered the WP support forums or any emails, for that matter.

= Do you plan on improving the plugin? =
Not really. I just want to stop receiving spam from my bbPress install. However, if you want an improvement badly enough, contact me through vinny [at] usestrict [dot] net.


== Screenshots ==
1. The settings page


== Changelog ==
= 1.2.1 =
* Added back old plugin deactivation
* Bug fix for topic author not displaying when anonymous by Rick Tuttle

= 1.2 =
* Improved role handling by Paul Schroeder.

= 1.1.2 =
* Fixed edge case where user doesn't select any checkbox in recipients list.
* Array casting in foreach blocks. 

= 1.1.1 =
* Fixed load_plugin_textdomain call.

= 1.1 =
* Fixed methods called as functions.

= 1.0 =
* No-spam version created. 

= 0.2.1 =
* Added template tags "[topic-replyurl]" and "[reply-replyurl]"

= 0.2 =
* Improved selection of e-mail recipients; now it is possible to select multiple user roles

= 0.1 =
* First alpha version
