=== Limit Comments and Word Count ===

Contributors: Artiosmedia, steveneray, repon.wp
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=E7LS2JGFPLTH2
Tags: comment limits, comments per user, user comment limit, word limits, comment word limit
Requires at least: 4.6
Tested up to: 6.6.1
Version: 1.2.1
Stable tag: 1.2.1
Requires PHP: 7.4.33
License: GPLv3 or later license and included
URI: http://www.gnu.org/licenses/gpl-3.0.html

This plugin will limit the number of comments and word length each user can add to a Wordpress blog post, definable by user role and length of time.

== Description ==

This plugin adds above the comment box an active letter counter, word counter, and comment counter, that provides a user constant visual access to their input activity. Any comment that exceeds the word limit will result in a red change it count as the user tries to type more words. If the users tries to post anyway, a warning dialogue will appear below the comment box that will state the limit and the comment will not post. Once the user edits the words that exceeds the limit, the comment can be posted. User types are defined when the rule is created, isolating what user type is restricted by a given rule. Multiple rule types can be created if different rules are required.

The plugin never places a restriction or limits on the Wordpress default user types Administrator, Editor, Author and Contributor. If the blog administrator would create a rule for any of the three roles seperately (Editor, Author and Contributor), then that rule would supersede the default setting. Other users types that may be seen in the user's type dropdown are not default to WordPress and would require a rule created if limits are required.

This plugin also combines the ability to limit the number of comments allowed by the blog administrator. The defined limit is contained to each day, week, month, or per year. Once the limit is reached by definition of the time span selected, an alert message displays the reason for not allowing further comments.

The plugin settings allows you to disable the flood protection notice provided default in Wordpress, where a user is normally blocked from quick successive posts resulting in a bleak 404 error. Along with this, a user is by default blocked from pasting the same commment, letter for letter, under any post. This can too can be disabled.

The latest addition includes the ability to activate a Comment Rules pop-up modal within the post. It can be deactivated in the settings but is active by default. It appears top left above the post's comment box. You may create the rules to read any way you wish in settings, but keep the text rules short as the sample text or they will wrap and malform the windows appearance.

The plugin as a whole is very simple and uses nearly no system resources and is compatible with all tested blog add-ons loaded to the initial staging site. This includes membership platforms like Magic Members, MemberPress, Memberships Pro, Restrict Content Pro, LearnDash, S2Member and WooCommerce Memberships. Additionally, the plugin works in combination with any other Wordpress module that requires a user to register before commenting. To clarify, for the plugin to work, a user must be a registered and active for the plugin to track activity. Logically, no plugin can monitor or limit unregistered anonymous comments. Guests cannot be controlled by the plugin limits, only registered users.

As of <strong>version 1.1.3</strong>, you now can from the metabox in each post, select the post not to be subject to the limit rules saved in the plugins settings. This has been a repeat feature request.

As of <strong>version 1.1.8</strong>, an administrator can optionally enter a global value in the settings panel, to limit the total number of comments allowed on all posts. If the field is left blank, the default value of WordPress is maintained.

Notes: This plugin will not work with wpDiscuz where it uses its own hook and templates. If the limit is set to two comments in 24 hours for example, but one was deleted by the moderator, the subscribers comments will now show another comment remains. Previously, any comments in the trash within the limited time was counted against the subscriber.

The plugin’s language support includes: English, Spanish, German, French and Russian.

== Installation ==

1. Upload the plugin files to the '/wp-content/plugins/plugin-name' directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings -> Limit Comments and Word Count setup page to configure the plugin
4. Configure your settings with two different error messages and save settings.

== Using in Multisite Installation ==

1. Extract the zip file contents in the wp-content/mu-plugins/ directory of your WordPress installation. (This is not created by default. You must create it in the wp-content folder.) The 'mu' does not stand for multi-user like it did for WPMU, it stands for 'must-use' as any code placed in that folder will run without needing to be activated.
2. Access the Plugins settings panel named 'Limit Comments and Word Count' under options.
3. Configure your settings with two different error messages and save settings.

== Technical Details for Release 1.2.1 ==

Load time: 0.311 s; Memory usage: 3.56 MiB
PHP up to tested version: 8.3.11
MySQL up to tested version: 8.0.39
MariaDB up to tested version: 11.5.2
cURL up to tested version: 8.9.1, OpenSSL/3.3.1
PHP 7.4, 8.0, 8.1, 8.2, and 8.3 compliant.

== Frequently Asked Questions ==

= Is this plugin frequently updated to Wordpress compliance? =

Yes, attention is given on a staged installation with many other plugins via debug mode.

= Is the plugin as simple to use as it looks? =

Yes. No other plugin exists that addresses both comments and word limits so simply.

= Has there ever any compatibility issues? =

There were conflicts with PHP 7.2 that has since been resolved.

= Is the code in the plugin proven stable? =

Please click the following link to check the current stability of this plugin:
<a href="https://plugintests.com/plugins/limit-comments-and-word-count/latest" rel="nofollow ugc">https://plugintests.com/plugins/limit-comments-and-word-count/latest</a>

== Screenshots ==

1. Create or edit limit rule settings page with sample content
2. Add optional Comment Rules modal above comment box for users
3. Word count exceeded warning dialogue example
4. Comment count exceeded sample screen capture
5. Metabox allows rule exclusion on individual posts

== Upgrade Notice ==

None to report as of the release version

== Changelog ==

1.2.1 09/01/24
- Minor edits to language files
- Assure compliance with WordPress 6.6.1

1.2.0 04/06/24
- Make minor adjustments and edits
- Assure compliance with WordPress 6.5

1.1.9 12/27/23
- Fixed code to allow missing language results
- Test total compatibility with several themes
- Assure compliance with WordPress 6.4.2

1.1.8 08/28/23
- Added global comment limit for posts
- Assure compliance with WordPress 6.3.0

1.1.7 08/04/23
- New: Add German language (user request)
- Fixed user side static text not using PO files
- Assure compliance with WordPress 6.2.2

1.1.6 03/29/23
- Optimize for PHP 8.1 and WordPress 6.2
- Fixed: Issue in editing existing rules for comment
- Add Russian language
- Assure current stable PHP 8.1 and 8.2 use

1.1.5 06/23/22
- Add Comment Frequency option Per Year
- Update: All language files
- Assure current stable PHP 8.1.6 use

1.1.4 05/23/22
- Text edits along with translations
- Assure compliance with WordPress 6.0

1.1.3 01/25/22
- New: Added metabox to post screen to exclude post from limit Comments rule
- Fixed: Comment button missing for administrator
- Fixed: Some known themes compatibility issues
- Fixed: Rules not been deleted after reloading page
- Fixed: Stats still show up on comment page when no rules is added
- Fixed: Some PHP/WordPress warning error log on when viewing posts when no rules are added
- Fixed: Issue with translation folder
- Update: All language files
- Updates for Wordpress 5.9
- Assure current stable PHP 8.1.1 use

1.1.2 04/29/21
- Modify readme.txt with description updates
- Update: Make compatible with Wordpress 5.7.2.

1.1.1 09/24/20
- Modify readme.txt with description updates
- Update: Make compatible with Wordpress 5.5.1.

1.1.0 06/19/20
- New: Add modal to provide optional blog comments rules.
- New: Add Spanish and French languages.
- Update: User comments in trash do not count against limit.
- Update: CSS for modal and plugin warning dialogues.
- Update: Make compatible with Wordpress 5.4.2.

1.0.7 01/07/20
- New: Add settings to disable flood protection notice.
- New: Add settings to allow user to add duplicate comments.
- Update: Make compatible with Wordpress 5.3.2.

1.0.6 11/21/19
- Fixed: PHP line 854 error and custom roles.
- Update: Make compatible with Wordpress 5.3.
- Update: Make compatible with MemberPress 1.7.2.
- Bug fix on selected user role when editing
- Highlight word limit count when reached and exhausted 

1.0.5 12/16/18
- Update: Make compatible with Wordpress 5.0.1.

1.0.4 08/04/18
- Fixed: Rating reminder would not disable properly.
- Update: Make compatible with Wordpress 4.9.8.

1.0.3 07/25/18
- Fixed: Style error conflicting with blog formatting.

1.0.2 07/20/18
- Fixed: Comments limit not updating after full cycle.

1.0.1 07/19/18
- New: Administrator reminder to rate after 15 days
- Improvement: Enlarge alert message fields width
- Update: Emphasize plugin works with registered users only
- Fixed: Users waiting 24 hrs after each comment

1.0.0 07/03/18
- Initial release