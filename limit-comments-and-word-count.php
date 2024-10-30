<?php

/**
 * @package          LPWC
 * @wordpress-plugin
 *
 * Plugin Name:      Limit Comments and Word Count
 * Plugin URI:       http://wordpress.org/plugins/limit-comments-and-word-count
 * Description:      Limit the number of comments and word length each user can add to a Wordpress blog post, definable by user role and length of time. No Administrator, Editor, Author, or Contributor limits unless profile is created.
 * Version:          1.2.1
 * Author:           Artios Media
 * Author URI:       http://www.artiosmedia.com
 * Developer:        Repon Hossain
 * Copyright:        © 2018-2024 Artios Media (email: contact@artiosmedia.com).
 * License:          GNU General Public License v3.0
 * License URI:      http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:      limit-comments-and-word-count
 * Domain Path:      /languages
 * Tested up to:     6.6.1
 * PHP tested up to: 8.3.11
 */

define('LPWC_VERSION', '1.2.0');
define('LPWC_DB_VERSION', '1.2.0');
define('LPWC_URL', plugins_url('/', __FILE__));
define('LPWC_PATH', plugin_dir_path(__FILE__));

if (!class_exists('lpwc')) {
	define('LPWC_TRIAL_PERIOD', 3600 * 24 * 15);

	class lpwc {
		protected $_MU;
		protected $comments_restricted = true;
		public $current_message;

		//constructor
		public function __construct() {
			if (function_exists('is_multisite') && is_multisite()) {
				$this->_MU = true;
			} else {
				$this->_MU = false;
			}

			//hook actions and filters
			$this->hooks();
			//add shortcodes
			$this->addShortCodes();
		}

		/**
		 * addShortCodes registers plugin shortcodes
		 * @since 3.0
		 */
		public function addShortCodes() {
			add_shortcode('IN_LIMIT', array($this, 'limits_shortcode_handler'));
			add_shortcode('in_limit', array($this, 'limits_shortcode_handler'));
		}

		/**
		 * Hooks a central location for all action and filter hooks
		 * @since 3.0
		 * @return void
		 */
		public function hooks() {
			add_action('admin_init', array($this, 'check_trial'));

			//run on activation of plugin
			register_activation_hook(__FILE__, array($this, 'lpwc_activate'));
			add_action('admin_init', array($this, 'run_on_upgrade'));

			//Add plugin description link
			add_filter('plugin_row_meta', array($this, 'add_description_link'), 10, 2);
			//hook limits check function to admin head.
			//add menu hook
			add_action('admin_head', array($this, 'lpwc_limit_post_count'));
			add_action('admin_head', array($this, 'remove_add_new'));
			add_action('admin_menu', array($this, 'lpwc_menu'));
			//create options
			add_action('admin_init', array($this, 'wpsnfl_init'));
			add_action('admin_init', array($this, 'add_notification'));
			add_action('admin_print_scripts', array($this, 'lpwc_print_settings_js'));
			add_action('admin_print_styles', array($this, 'print_settings_styles'));

			add_action('wp_enqueue_scripts', array($this, 'add_comment_script'));
			//limit xml-rpc
			add_filter('wp_insert_post_empty_content', array($this, 'limit_xml_rpc'));
			add_filter('comment_form_field_comment', array($this, 'add_comment_restrictions'), 10, 1);
			add_filter('preprocess_comment', array($this, 'preprocess_comment'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_link'));
			add_filter('plugin_row_meta', array($this, 'add_details_link'), 10, 4);
			add_filter('comment_form_submit_button', array($this, 'restrict_comment_button'), 10, 2);
			add_action('init', array($this, 'add_translations'));
			//Disable Flood Protection Notice
			add_filter('comment_flood_filter', array($this, 'disable_comment_flood_protection'));

			//Allow duplicate comments
			add_filter('preprocess_comment', array($this, 'enable_duplicate_comments_preprocess_comment'));
			add_action('comment_post', array($this, 'enable_duplicate_comments_comment_post'));

			add_action('wp_ajax_lpwc_cancel_notification', array($this, 'cancel_notification'));
			add_action('wp_ajax_nopriv_lpwc_cancel_notification', array($this, 'cancel_notification'));

			add_action('wp_ajax_lpwc_close_feature_notification', array($this, 'close_feature_notification'));

			// Display Feature Notification
			add_action('admin_notices', array($this, 'display_feature_message'));

			// metabox for limit comment
			add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 10);
			// Save metabox settings
			add_action('save_post', array($this, 'save_post'), 10);

			add_filter('comments_open', array($this, 'hide_comment_form'));
		}

		/**
		 * Run on activation of the plugin
		 * @global object $wpdb
		 * @param type $network_wide
		 * @since 1.1.0
		 * TODO: Saving of options in different function
		 */
		public function lpwc_activate($network_wide) {
			global $wpdb;
			$this->run_on_activation();
			if (function_exists('is_multisite') && is_multisite()) {
				// check if it is a network activation - if so, run the activation function for each blog id
				if ($network_wide) {
					// Get all blog ids
					$blogids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
					foreach ($blogids as $blog_id) {
						switch_to_blog($blog_id);
						//$this->run_for_site();
						if (!get_option('lpwc_show_comment_rules')) {
							update_option("lpwc_show_comment_rules", "active");
						}
						if (!get_option('lpwc_comment_rules')) {
							update_option("lpwc_comment_rules", $this->get_default_comment_rules());
						}
						restore_current_blog();
					}
					return;
				}
			}

			// for non-network sites only
			//$this->run_for_site();
			if (!get_option('lpwc_show_comment_rules')) {
				update_option("lpwc_show_comment_rules", "active");
			}
			if (!get_option('lpwc_comment_rules')) {
				update_option("lpwc_comment_rules", $this->get_default_comment_rules());
			}
		}

		private function run_on_activation() {
			$plugin_options = get_site_option('lpwc_info');
			if (false === $plugin_options) {
				$lpwc_info = array(
					'version' => LPWC_VERSION,
					'db_version' => LPWC_DB_VERSION
				);
				update_site_option('lpwc_info', $lpwc_info);
			} elseif (LPWC_VERSION != $plugin_options['version']) {
				$this->run_on_upgrade();
			}
		}

		public function run_on_upgrade() {
			$plugin_options = get_site_option('lpwc_info');

			if ($plugin_options['version'] == "1.0.7" || (!$plugin_options)) {
				$this->upgrade_database_110();
			}

			// Update the version
			$lpwc_info = array(
				'version' => LPWC_VERSION,
				'db_version' => LPWC_DB_VERSION
			);
			update_site_option('lpwc_info', $lpwc_info);
		}

		private function upgrade_database_110() {
			global $wpdb;
			// look through each of the blogs and upgrade the DB
			if (function_exists('is_multisite') && is_multisite()) {
				$blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
				foreach ($blog_ids as $blog_id) {
					switch_to_blog($blog_id);
					$this->upgrade_helper_110();
					restore_current_blog();
				}
			} else {
				$this->upgrade_helper_110();
			}
		}

		private function upgrade_helper_110() {
			// Add setting for mandatory comment
			if (!get_option('lpwc_show_comment_rules')) {
				update_option('lpwc_show_comment_rules', "active");
			}

			if (!get_option('lpwc_comment_rules')) {
				update_option("lpwc_comment_rules", $this->get_default_comment_rules());
			}

			if (!get_option('lpwc_display_feature_notification')) {
				update_option('lpwc_display_feature_notification', "show");
			}
		}

		public function add_description_link($links, $file) {
			if (plugin_basename(__FILE__) == $file) {
				$row_meta = array(
					'donation' => '<a href="' . esc_url('https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=E7LS2JGFPLTH2') . '" target="_blank">' . esc_html__('Donation for Homeless', 'limit-comments-and-word-count') . '</a>'
				);

				return array_merge($links, $row_meta);
			}

			return (array) $links;
		}

		/**
		 * limit_xml_rpc limit xml-rpc user
		 * @since 2.5
		 *
		 * @param  boolean $maybe
		 * @param  array $postarr
		 *
		 * @return true to limit false to allow
		 */
		public function limit_xml_rpc($maybe, $postarr = array()) {
			//exit early if not xmlrpc request
			if (!defined('XMLRPC_REQUEST') || XMLRPC_REQUEST != true) {
				return $maybe;
			}

			if (isset($postarr['post_post_type']) && isset($postarr['post_author']) && $this->limitUser($postarr['post_author'], $postarr['post_type'])) {
				return apply_filters('lpwc_xml_rpc_limit', true);
			}

			return $maybe;
		}

		/**
		 * limitUser this is the money function which checks to limit a user by post count
		 * @since 2.5
		 *
		 * @param  int $user_id
		 * @param  strin $type post type
		 * @param  boolean $use_m use shortcode message flag
		 *
		 * @return true to limit false to allow
		 */
		public function limitUser($user_id = null, $type = null, $use_m = true) {
			//exit early if no settings
			$options = $this->lpwc_getOptions('lpwc');
			if (!isset($options['rules'])) {
				return false;
			}

			if ($user_id == null) {
				$current_user = wp_get_current_user();
				$user_id      = $current_user->ID;
				if ($user_id <= 0) {
					return true;
				}
			}

			if ($type == null) {
				global $typenow;
				$type = isset($typenow) ? $typenow : 'post';
			}

			if ($this->_MU) {
				if (current_user_can('manage_network')) {
					return false;
				}
			} elseif (current_user_can('manage_options')) {
				return false;
			}

			global $wpdb;

			$user = wp_get_current_user();
			if ($user && !$user->has_cap('administrator')) {
				$rule = $this->get_user_rule($user);
				if ($rule) {
					$last_limit_update = $this->get_last_update_time($user_id, $this->get_time_span($rule['time_span']));
					update_user_meta($user_id, 'lpwc_last_update', $last_limit_update);
					global $wpdb;

					if ($rule['post_type'] == $type || $rule['post_type'] == "any") {
						$count = $this->count_comments_for_user($wpdb, $user_id, $rule, $last_limit_update);
						if ($count > $rule['limit']) {
							if ($use_m) {
								$this->current_message = $rule['message'];
							}

							return true;
						}
					}
				}
			}

			return false;
		}

		private function get_last_update_time($user_id, $time_span = false) {
			$last_limit_update = get_user_meta($user_id, 'lpwc_last_update');
			if (!$last_limit_update) {
				$last_limit_update = new DateTime();
			} else {
				$last_limit_update = new DateTime($last_limit_update[0]);
			}
			$next_update = new DateTime($last_limit_update->format('Y-m-d'));
			if (is_numeric($time_span)) {
				$interval = new DateInterval('PT' . $time_span . 'S');
				$next_update->add($interval);
			}
			$current_time = $this->get_current_time();
			if ($next_update->getTimestamp() > $last_limit_update->getTimestamp() && $next_update->getTimestamp() <= $current_time->getTimestamp()) {
				$last_limit_update = $this->get_current_time();
			}

			return $last_limit_update->format('Y-m-d');
		}

		private function get_current_time() {
			$now = new DateTime();
			//$interval = new DateInterval('P' . 1 . 'D');
			//$now->add($interval);
			return $now;
		}

		private function count_comments_for_user($wpdb, $user_id, $rule, $after_date) {
			$time_span = $this->get_time_span($rule['time_span']);
			$ptype     = ($rule['post_type'] == 'any') ? "IN ('" . implode("', '", get_post_types('', 'names')) . "')" : " = '" . $rule['post_type'] . "'";
			$time      = (isset($rule['time_span']) && $rule['time_span'] != "FOREVER") ? " AND TIMESTAMPDIFF(SECOND, '$after_date', comment_date) >= 0" : "";
			$pstatus   = ($rule['status'] == 'any') ? "IN ('publish', 'pending', 'draft', 'future', 'private', 'trash')" : " = '" . $rule['status'] . "'";
			$count     = $this->get_comments_count_at_interval($wpdb, $ptype, $pstatus, $user_id, $time);

			return apply_filters('lpwc_Count_filter', $count, $rule, $user_id);
		}

		private function get_time_span($time_span_value) {

			switch ($time_span_value) {
				case "day":
					$time_span = DAY_IN_SECONDS;
					break;
				case "week":
					$time_span = WEEK_IN_SECONDS;
					break;
				case "month":
					$time_span = MONTH_IN_SECONDS;
					break;
				case "year":
					$time_span = YEAR_IN_SECONDS;
					break;
				default:
					$time_span = HOUR_IN_SECONDS;
			}

			return $time_span;
		}

		private function get_comments_count_at_interval($wpdb, $posttype, $status, $user_id, $time) {
			$comments_table = $wpdb->comments;
			$posts_table    = $wpdb->posts;
			$query          = "SELECT COUNT($comments_table.comment_ID) FROM $comments_table LEFT JOIN $posts_table ON $comments_table.comment_post_ID = $posts_table.ID" . " WHERE $comments_table.comment_approved NOT IN ('trash') AND post_status $status AND user_id = $user_id" . " AND post_type $posttype $time";

			return $wpdb->get_var($query);
		}

		/**
		 * limits_shortcode_handler
		 * @since 2.4
		 *
		 * @param  array $atts
		 * @param  string $content
		 *
		 * @return string
		 */
		public function limits_shortcode_handler($atts, $content = null) {
			extract(shortcode_atts(array(
				'message' => __('You are not allowed to create any more', 'limit-comments-and-word-count'),
				'm'       => __('Please login to post', 'limit-comments-and-word-count'),
				'use_m'   => 'true',
				'type'    => 'post'
			), $atts));
			if (!is_user_logged_in()) {
				return apply_filters('lpwc_shortcode_not_logged_in', $m);
			}

			$current_user = wp_get_current_user();
			//get_currentuserinfo();
			if ($this->_MU) {
				if (current_user_can('manage_network')) {
					return apply_filters('lpwc_shortcode_network_admin', do_shortcode($content));
				}
			} elseif (current_user_can('manage_options')) {
				return apply_filters('lpwc_shortcode_admin', do_shortcode($content));
			}

			if ($this->limitUser($current_user->ID, $type)) {
				if ($use_m == 'true') {
					return apply_filters('lpwc_shortcode_limited', $this->current_message);
				} else {
					return apply_filters('lpwc_shortcode_limited', $message);
				}
			}

			//all good return the content
			return apply_filters('lpwc_shortcode_ok', do_shortcode($content));
		}

		public function remove_add_new() {
			global $pagenow, $typenow;
			if (is_admin() && $pagenow === 'post-new.php') {
				//get_currentuserinfo();
				$current_user = wp_get_current_user();
				if ($this->limitUser($current_user->ID, $typenow)) {
					$this->lpwc_not_allowed_remove_links();
				}
			}
		}

		public function lpwc_not_allowed_remove_links() {
			add_action('admin_footer', array($this, 'hide_links'));
		}

		//remove links
		public function hide_links() {
			global $typenow;
			if ('post' == $typenow) {
				$href = 'post-new.php';
			} else {
				$href = 'post-new.php?post_type=' . $typenow;
			}
?>
			<script>
				jQuery(document).ready(function() {
					jQuery('.add-new-h2').remove();
					jQuery('[href$="<?php echo $href; ?>"]').remove();
				});
			</script>
		<?php
		}

		//limit post type count per user 
		public function lpwc_limit_post_count() {
			global $pagenow, $typenow;

			if (is_admin() && in_array($pagenow, array('post-new.php', 'press-this.php'))) {
				$options = $this->lpwc_getOptions();
				if (!isset($options['rules'])) {
					return;
				}

				$current_user = wp_get_current_user();
				if ($this->_MU) {
					if (current_user_can('manage_network')) {
						return;
					}
				} elseif (current_user_can('manage_options')) {
					return;
				}

				if ($this->limitUser($current_user->ID, $typenow)) {
					$this->lpwc_not_allowed($this->current_message);
					exit;
				}

				do_action('post_creation_limits_custom_checks', $typenow, $current_user->ID);
			}
		}

		//add menu function
		public function lpwc_menu() {
			if ($this->_MU) { // Add a new submenu under Settings:
				$hook = add_options_page(__('Comment-Word Limit', 'limit-comments-and-word-count'), __('Comment-Word Limit', 'limit-comments-and-word-count'), 'manage_network', 'lpwc_settings_page', array(
					$this,
					'lpwc_settings_page'
				));
			} else {
				$hook = add_options_page(__('Comment-Word Limit', 'limit-comments-and-word-count'), __('Comment-Word Limit', 'limit-comments-and-word-count'), 'manage_options', 'lpwc_settings_page', array(
					$this,
					'lpwc_settings_page'
				));
			}
			add_action('load-' . $hook, 'add_thickbox');
		}

		//register options api
		public function wpsnfl_init() {
			register_setting('lpwc_Options', 'lpwc');
			register_setting(
				'lpwc_Options',
				'lpwc_disable_flood_protection',
				array($this, 'validate_disable_flood_protection')
			);
			register_setting(
				'lpwc_Options',
				'lpwc_allow_duplicate_comments',
				array($this, 'validate_allow_duplicate_comments')
			);
			register_setting(
				'lpwc_Options',
				'lpwc_show_comment_rules',
				array($this, 'validate_show_comment_rules')
			);

			register_setting(
				'lpwc_Options',
				'lpwc_global_max_comments'
			);


			register_setting(
				'lpwc_Options',
				'lpwc_comment_rules',
				array($this, 'validate_comment_rules_content')
			);

			$this->lpwc_getOptions();
		}

		//plugin settings and defaults
		public function lpwc_getOptions() {
			$getOptions = get_option('lpwc');
			if (empty($getOptions)) {
				if ($this->_MU) {
					$getOptions = get_site_option('lpwc');
				}
			}

			if (is_main_site()) {
				update_site_option('lpwc', $getOptions);
			}

			return $getOptions;
		}

		/**
		 * Validate disable flood protection settings
		 * @param string $is_enable
		 * @return string
		 * @since 1.0.7
		 */
		public function validate_disable_flood_protection($is_enable) {
			return sanitize_text_field($is_enable);
		}

		/**
		 * Validate allow duplicate comments settings
		 * @param string $is_allowed
		 * @return string
		 * @since 1.0.7
		 */
		public function validate_allow_duplicate_comments($is_allowed) {
			return sanitize_text_field($is_allowed);
		}

		/**
		 * Validate show comment rules
		 * @param string $is_active
		 * @return string
		 * @since 1.1.0
		 */
		public function validate_show_comment_rules($is_active) {
			return sanitize_text_field($is_active);
		}

		/**
		 * Validate comment rules settings
		 * @param string $content
		 * @return string
		 * @since 1.1.0
		 */
		public function validate_comment_rules_content($content) {
			return wp_kses_post($content);
		}

		public function lpwc_print_settings_js() {
			$rules = get_option('rules');
			if (!$rules) {
				$counter = 0;
			} else {
				$counter = count(array_keys($rules));
			}

			$edit_icon  = plugin_dir_url(__FILE__) . 'images/edit-icon-30x30.png';
			$trash_icon = plugin_dir_url(__FILE__) . 'images/trash-icon-30x30.png';
			$data       = array(
				'counter'              => $counter,
				'forever'              => __('FOREVER', 'limit-comments-and-word-count'),
				'every'                => __("Every", 'limit-comments-and-word-count'),
				'per_day'              => __('Per Day', 'limit-comments-and-word-count'),
				'per_week'             => __('Per Week', 'limit-comments-and-word-count'),
				'per_month'            => __('Per Month', 'limit-comments-and-word-count'),
				'per_year'            => __('Per Year', 'limit-comments-and-word-count'),
				'hours'                => __('hours', 'limit-comments-and-word-count'),
				'editor'               => __('Editor', 'limit-comments-and-word-count'),
				'any'                  => __('any', 'limit-comments-and-word-count'),
				'new_rule_caption'     => __('Add New Limit', 'limit-comments-and-word-count'),
				'day'                  => __('day', 'limit-comments-and-word-count'),
				'edit'                 => __('Edit', 'limit-comments-and-word-count'),
				'remove'               => __('Remove', 'limit-comments-and-word-count'),
				'update_table_caption' => __('Update Table', 'limit-comments-and-word-count'),
				'edit_icon'            => $edit_icon,
				'trash_icon'           => $trash_icon
			);

			wp_register_script('lpwc-settings', plugin_dir_url(__FILE__) . 'js/settings.js', array('thickbox'), LPWC_VERSION);
			wp_localize_script('lpwc-settings', 'data', $data);
			wp_enqueue_script('lpwc-settings');
		}

		public function add_comment_script() {
			$user = wp_get_current_user();
			if ($user) {
				$rule = $this->get_user_rule($user);
				if ($rule) {
					wp_enqueue_style('lpwc-comments-css', plugin_dir_url(__FILE__) . 'css/styles.css');
					wp_enqueue_style('wp-jquery-ui-dialog');
					$data = array(
						'comment_words_limit_message' => @$rule['comment_word_limit_ms'],
						'ajax_url' => admin_url('admin-ajax.php')
					);
					wp_register_script('lpwc-comments', plugin_dir_url(__FILE__) . 'js/comments.js', array('jquery-ui-dialog'));
					wp_localize_script('lpwc-comments', 'lpwc', $data);
					wp_enqueue_script('lpwc-comments');
					wp_enqueue_script('jquery-simplemodal', LPWC_URL . 'js/modal/jquery.simplemodal.js', '', '1.4.6', true);
					wp_enqueue_style('lpwc-modal-css', LPWC_URL . 'css/modal/simple-modal.css', false, LPWC_VERSION, 'all');
				}
			}
		}

		public function print_settings_styles() {
			if (@$_GET['page'] === 'lpwc_settings_page') {
				wp_enqueue_style('lpwc-styles', plugin_dir_url(__FILE__) . 'css/settings.css');
			}
		}

		//settings page
		public function lpwc_settings_page() {
			global $wp_roles;
			if ($this->_MU) {
				if (!current_user_can('manage_network')) {
					wp_die(__('You do not have sufficient permissions to access this page.', 'limit-comments-and-word-count'));
				}
			} else {
				if (!current_user_can('manage_options')) {
					wp_die(__('You do not have sufficient permissions to access this page.', 'limit-comments-and-word-count'));
				}
			}
		?>
			<div class="wrap">
				<div class="clear"></div>
				<div id="plugin-wrapper">
					<div id="icon-options-general" class="icon32"></div>
					<h2><?php _e('Limit Comments and Word Count', 'limit-comments-and-word-count'); ?></h2>
					<br /><br />
					<div class="postbox limit-form-wrapper">
						<div class="inside">
							<form action="limit" id="limit_form" method="post" name="limiter">
								<h3><?php _e('Add a New Rule Limit For User Role', 'limit-comments-and-word-count'); ?></h3>
								<div class="table-wrapper">
									<table class="wp-list-table" style="flex-grow: 1">
										<thead>
											<tr>
												<th></th>
												<th></th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="label">
													<label for="role"><?php _e('Select A User Role', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<select name="role" id="ur" class="full-width">
														<?php
														global $wp_roles;
														if (!isset($wp_roles)) {
															$wp_roles = new WP_Roles();
														}
														$roles = $wp_roles->role_names;
														unset($roles['administrator']);
														foreach ($roles as $role => $name) { ?>
															<option value="<?php echo $role; ?>"><?php echo $name; ?></option>
														<?php
														}
														?>
														<option value="USER_ID"><?php _e('USER ID', 'limit-comments-and-word-count'); ?></option>
													</select>
												</td>
											</tr>
											<tr class="user_i" style="display: none;">
												<td class="label">
													<label for="role"><?php _e('User ID', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<input name="ro2" type="text" id="ro2" class="full-width" />
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="ptype"><?php _e('Select A Post Type', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<select name="ptype" id="pt" class="full-width">
														<option value="any">Any</option>
														<?php
														$post_types = get_post_types('', 'names');
														foreach ($post_types as $post_type) {
														?>
															<option value="<?php echo $post_type; ?>"><?php echo $post_type; ?></option>
														<?php
														}
														?>
													</select>
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="limit"><?php _e('Comment Limit', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<input name="limit" type="number" id="lim" class="full-width" />
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="Status"><?php _e('Post Status', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<select name="Status" id="st" class="full-width">
														<option value="any"><?php _e('any', 'limit-comments-and-word-count'); ?></option>
														<option value="publish"><?php _e('publish', 'limit-comments-and-word-count'); ?></option>
														<option value="pending"><?php _e('pending', 'limit-comments-and-word-count'); ?></option>
														<option value="draft"><?php _e('draft', 'limit-comments-and-word-count'); ?></option>
														<option value="auto-draft"><?php _e('auto-draft', 'limit-comments-and-word-count'); ?></option>
														<option value="future"><?php _e('future', 'limit-comments-and-word-count'); ?></option>
														<option value="private"><?php _e('private', 'limit-comments-and-word-count'); ?></option>
														<option value="inherit"><?php _e('inherit', 'limit-comments-and-word-count'); ?></option>
														<option value="trash"><?php _e('trash', 'limit-comments-and-word-count'); ?></option>
													</select><br />
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="time_span"><?php _e('Comment Frequency', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<select name="time_span" id="tis" class="full-width">
														<option value="day"><?php _e('Per Day', 'limit-comments-and-word-count'); ?></option>
														<option value="week"><?php _e('Per Week', 'limit-comments-and-word-count'); ?></option>
														<option value="month"><?php _e('Per Month', 'limit-comments-and-word-count'); ?></option>
														<option value="year"><?php _e('Per Year', 'limit-comments-and-word-count'); ?></option>
													</select>
												</td>
											</tr>
										</tbody>
									</table>
									<table class="wp-list-table" style="flex-grow: 7">
										<thead>
											<tr>
												<th></th>
												<th></th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="label">
													<label for="comment_limit_max"><?php _e('Word Limit', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<input name="comment_limit_max" type="number" id="comment_limit_max" min="0" /><span class="hint"><?php _e('(Most words allowed per comment)', 'limit-comments-and-word-count'); ?></span>
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="message"><?php _e('Comment Limit Alert', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<input type="text" name="message" id="ms" class="full-width" rows="2" />
												</td>
											</tr>
											<tr>
												<td class="label">
													<label for="comment_word_limit_ms"><?php _e('Word Limit Alert', 'limit-comments-and-word-count'); ?></label>
												</td>
												<td>
													<input type="text" name="comment_word_limit_ms" id="comment_word_limit_ms" rows="2" class="full-width" />
												</td>
											</tr>
											<tr><input type="hidden" id="rule_count" value="" /></tr>
											<tr>
												<td></td>
												<td><input type='submit' value='<?php _e('Add New Limit', 'limit-comments-and-word-count'); ?>' class='add-new-h2 new_rule' /></td>
											</tr>
										</tbody>
									</table>
								</div>
							</form>
						</div>
					</div>
					<form method="post" action="options.php" id="rules_form">
						<?php settings_fields('lpwc_Options'); ?>
						<?php //delete_site_option( 'lpwc' );
						?>
						<?php $options = $this->lpwc_getOptions('lpwc'); //get_option display: none
						$is_disable_flood_protection = get_option('lpwc_disable_flood_protection');
						$is_duplicate_comments_allowed = get_option('lpwc_allow_duplicate_comments');
						$show_commnet_rules = get_option('lpwc_show_comment_rules');
						$comments_rules = get_option('lpwc_comment_rules');
						$global_max_comments = get_option('lpwc_global_max_comments');
						?>
						<div id="list_limits">
							<table id="limit_rules" class="widefat">
								<thead>
									<tr>
										<th><?php _e('Role', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Type', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Limit', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Status', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Frequency', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Words', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Comment Alert', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Word Alert', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Actions', 'limit-comments-and-word-count'); ?></th>
									</tr>
								</thead>
								<tfoot>
									<tr>
										<th><?php _e('Role', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Type', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Limit', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Status', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Frequency', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Words', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Comment Alert', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Word Alert', 'limit-comments-and-word-count'); ?></th>
										<th><?php _e('Actions', 'limit-comments-and-word-count'); ?></th>
									</tr>
								</tfoot>
								<tbody>
									<?php
									$c          = 0;
									$edit_icon  = plugin_dir_url(__FILE__) . 'images/edit-icon-30x30.png';
									$trash_icon = plugin_dir_url(__FILE__) . 'images/trash-icon-30x30.png';
									if (isset($options['rules'])) {
										foreach ($options['rules'] as $k => $v) {
											$row_id = 'row_num_' . $c;
											echo '<tr data-row-id="' . $row_id . '">';
											echo '<td>' . $this->get_role_name($v['role']) . '<input type="hidden" name="lpwc[rules][' . $c . '][role]" value="' . strtolower($v['role']) . '"></td>';
											echo '<td>' . $v['post_type'] . '<input type="hidden" name="lpwc[rules][' . $c . '][post_type]" value="' . $v['post_type'] . '"></td>';
											echo '<td>' . $v['limit'] . '<input type="hidden" name="lpwc[rules][' . $c . '][limit]" value="' . $v['limit'] . '"></td>';
											echo '<td>' . $v['status'] . '<input type="hidden" name="lpwc[rules][' . $c . '][status]" value="' . $v['status'] . '"></td>';
											echo '<td>' . $this->get_frequency_label($v['time_span']) . '<input type="hidden" name="lpwc[rules][' . $c . '][time_span]" value="' . $v['time_span'] . '"></td>';
											echo '<td>' . $v['comment_limit_max'] . '<input type="hidden" name="lpwc[rules][' . $c . '][comment_limit_max]" value="' . $v['comment_limit_max'] . '"></td>';
											echo '<td id="message"><pre><code>' . $this->wrap_text(htmlentities($v['message']), 2) . '</code></pre><input type="hidden" name="lpwc[rules][' . $c . '][message]" value="' . @$v['message'] . '"/></td>';
											echo '<td id="comment_word_limit_ms"><pre><code>' . $this->wrap_text(htmlentities(@$v['comment_word_limit_ms']), 2) . '</code></pre><input type="hidden" name="lpwc[rules][' . $c . '][comment_word_limit_ms]" value="' . @$v['comment_word_limit_ms'] . '"/></td>';
											echo '<td><span class="edit_rule flat-button" data-row-id="' . $row_id . '"><img src="' . $edit_icon . '" alt="edit" title="Edit"/> </span> 
                                            <span class="remove_rule flat-button" data-row-id="' . $row_id . '"><img src="' . $trash_icon . '" alt="remove" title="Remove"/></span></td>';
											echo '</tr>';
											$c++;
										}
									}
									?>
								</tbody>
							</table>
						</div>
						<div class="select-info">
							<label class="settings-title">
								<input type="checkbox" name="lpwc_disable_flood_protection" value="active" <?php echo ($is_disable_flood_protection == 'active') ? 'checked' : ''; ?> />
								<?php echo __("Disable flood protection notice", "limit-comments-and-word-count"); ?>
							</label>
							<span class="lpwc-tooltip">
								<span class="dashicons dashicons-editor-help"></span>
								<span class="tooltiptext tooltip-right-msg"><?php echo __("Allow user to continuously add comments.", "limit-comments-and-word-count"); ?></span>
							</span>

							<label class="settings-title" style="margin-left:30px;">
								<input type="checkbox" name="lpwc_allow_duplicate_comments" value="active" <?php echo ($is_duplicate_comments_allowed == 'active') ? 'checked' : ''; ?> />
								<?php echo __("Allow duplicate comments", "limit-comments-and-word-count"); ?>
							</label>
							<span class="lpwc-tooltip">
								<span class="dashicons dashicons-editor-help"></span>
								<span class="tooltiptext tooltip-right-msg"><?php echo __("Allow user to add duplicate comments.", "limit-comments-and-word-count"); ?></span>
							</span>
						</div>

						<div class="select-info">
							<label class="settings-title">
								<input type="number" name="lpwc_global_max_comments" value="<?php echo $global_max_comments ?>" style="width: 45px; text-align:center" min="0" max="999" />
								<?php _e("Global maximum number of comments allowed per post.", "limit-comments-and-word-count"); ?>
							</label>
							<span class="lpwc-tooltip">
								<span class="dashicons dashicons-editor-help"></span>
								<span class="tooltiptext tooltip-right-msg"><?php echo __("Three digit maximum. No entry defaults to unlimited comments.", "limit-comments-and-word-count"); ?></span>
							</span>
						</div>

						<div class="select-info">
							<h2><?php _e('Text for Comment Rules Tooltip Box', 'limit-comments-and-word-count'); ?></h2>
							<label class="settings-title" style="margin-left:10px;">
								<input type="checkbox" name="lpwc_show_comment_rules" value="active" <?php echo ($show_commnet_rules == 'active') ? 'checked' : ''; ?> />
								<?php echo __("Activate comment rules feature", "limit-comments-and-word-count"); ?>
							</label>
							<span class="lpwc-tooltip">
								<span class="dashicons dashicons-editor-help"></span>
								<span class="tooltiptext tooltip-right-msg"><?php echo __("Turn off or on the comments note feature on the user’s side.", "limit-comments-and-word-count"); ?></span>
							</span>

							<?php
							$content = ($comments_rules && $comments_rules !== "") ? stripslashes($comments_rules) : $this->get_default_comment_rules();
							$editor_id = "commentrules";
							$args      = array(
								'textarea_name' => 'lpwc_comment_rules',
								'textarea_rows' => 10,
								'media_buttons' => TRUE,
								'editor_height' =>  200
							);
							wp_editor($content, $editor_id, $args);
							?>
						</div>

						<div style="margin-top: 20px;">
							<input type="submit" value="<?php _e('Save Changes', 'limit-comments-and-word-count'); ?>" class="button-primary" />
						</div>
					</form>
				</div>
			</div>
		<?php
		}

		/**
		 * Get role friendly name by slug
		 * 
		 * @param string $slug The role slug
		 * 
		 * @return null|string Role name if found or null
		 */
		private function get_role_name($slug) {
			$wp_roles = wp_roles();
			$slug = strtolower($slug);

			return $wp_roles->roles[$slug] ? $wp_roles->roles[$slug]["name"] : null;
		}

		private function wrap_text($text, $linesCount) {
			$len = strlen($text);
			if ($len > 10) {
				$pieceLen = (int) ($len / $linesCount);
				$result   = '';
				$start    = 0;
				for ($i = 0; $i < $linesCount - 1; $i++) {
					$stop    = ($i + 1) * $pieceLen;
					$stop    = mb_strrpos(mb_substr($text, $start, $stop), " ");
					$subtext = mb_substr($text, $start, $stop) . " " . PHP_EOL;
					if ($i > 0) {
						$subtext = " " . $subtext;
					}
					$result .= $subtext;
					$start  = ++$stop;
				}
				$subtext = mb_substr($text, $start);
				if ($i == 1) {
					$subtext = " " . $subtext;
				}
				$result .= $subtext;

				return $result;
			}

			return $text;
		}

		public function get_frequency_label($val) {
			$result = '';
			switch ($val) {
				case 'day':
					$result = __('Per Day', 'limit-comments-and-word-count');
					break;
				case 'week':
					$result = __('Per Week', 'limit-comments-and-word-count');
					break;
				case 'month':
					$result = __('Per Month', 'limit-comments-and-word-count');
					break;
				case 'year':
					$result = __('Per Year', 'limit-comments-and-word-count');
			}

			return $result;
		}

		// display error massage
		public function lpwc_not_allowed($m = null) {
			do_action('post_creation_limits_before_limited_message');
			if ($m == null) {
				$options = $this->lpwc_getOptions();
				$m       = $options['m'];
			}
		?>
			<style>
				html {
					background: #f9f9f9;
				}

				#error-page {
					margin-top: 50px;
				}

				#error-page p {
					font-size: 14px;
					line-height: 1.5;
					margin: 25px 0 20px;
				}

				#error-page code {
					font-family: Consolas, Monaco, monospace;
				}

				body {
					background: #fff;
					color: #333;
					font-family: sans-serif;
					margin: 2em auto;
					padding: 1em 2em;
					-webkit-border-radius: 3px;
					border-radius: 3px;
					border: 1px solid #dfdfdf;
					max-width: 700px;
					height: auto;
				}
			</style>
			<div id="error-page">
				<div id="message" class="<?php echo apply_filters('post_creation_limits_limited_message_class', 'error'); ?>" style="padding: 10px;"><?php echo apply_filters('lpwc_limited_message_Filter', $m); ?></div>
			</div>
		<?php
			do_action('post_creation_limits_after_limited_message');
		}


		/************************
		 * helpers
		 ************************/
		//get user role
		public function lpwc_get_current_user_role() {
			global $wp_roles;
			$current_user = wp_get_current_user();
			$roles        = $current_user->roles;
			$role         = array_shift($roles);

			return isset($wp_roles->role_names[$role]) ? translate_user_role($wp_roles->role_names[$role]) : false;
		}

		//get sub array where key exists
		public function get_sub_array($Arr, $key, $val) {
			$new_arr = array();
			foreach ((array) $Arr as $k => $v) {
				if (isset($v[$key]) && $v[$key] == $val) {
					$new_arr[] = $v;
				}
			}
			if (count($new_arr) > 0) {
				return $new_arr;
			}

			return false;
		}

		//sub value array sort
		public function subval_sort($a, $subkey) {
			foreach ((array) $a as $k => $v) {
				$b[$k] = strtolower($v[$subkey]);
			}
			asort($b);
			foreach ((array) $b as $key => $val) {
				$c[] = $a[$key];
			}

			return $c;
		}

		//Add rules to comment textarea
		public function add_comment_restrictions($field) {
			global $post;
			$user = wp_get_current_user();

			$meta_value = is_object($post) && isset($post->ID) ? (int)get_post_meta($post->ID, '_exclude_comments_limit', true) : 0;

			if ($meta_value > 0) {
				$this->comments_restricted = false;
			} elseif ($user->ID && !$user->has_cap('administrator')) {
				// Had to specify user ID to make sure its not a guest
				$total_rule = null;
				$rule       = $this->get_user_rule($user);

				global $wpdb;
				if ($rule) {
					$last_limit_update = $this->get_last_update_time($user->ID, $this->get_time_span($rule['time_span']));
					update_user_meta($user->ID, 'lpwc_last_update', $last_limit_update);
					$time_span     = $this->get_time_span($rule['time_span']);
					$ptype         = ($rule['post_type'] == 'any') ? sprintf("IN ('%s')", implode("', '", get_post_types('', 'names'))) : " = '" . $rule['post_type'] . "'";
					$time          = (isset($rule['time_span']) && $rule['time_span'] != "FOREVER") ? " AND TIMESTAMPDIFF(SECOND, '$last_limit_update', comment_date) >= 0" : "";
					$pstatus       = ($rule['status'] == 'any') ? "IN ('publish', 'pending', 'draft', 'future', 'private', 'trash')" : " = '" . $rule['status'] . "'";
					$comment_count = $this->get_comments_count_at_interval($wpdb, $ptype, $pstatus, $user->ID, $time);
					if ($comment_count < $rule['limit']) {
						$total_rule                = $rule;
						$this->comments_restricted = false;
					} else {
						$this->comments_restricted = true;
					}
				} else {
					$this->comments_restricted = false;
				}
				if ($this->comments_restricted) {
					if ($rule) {
						$field = $this->restrict_comment($field, $rule['message']);
					}
				} elseif ($rule) {
					$last_limit_update     = $this->get_last_update_time($user->ID, $this->get_time_span($total_rule['time_span']));
					$posted_messages_total = $this->count_comments_for_user($wpdb, $user->ID, $total_rule, $last_limit_update);
					$limit                 = $total_rule['limit'] - $posted_messages_total;
					$field                 = $this->apply_restrictions_to_comment_editor($field, $total_rule['comment_limit_max'], $limit, $total_rule['comment_word_limit_ms']);
				}
			} else {
				$this->comments_restricted = false;
			}


			return $field;
		}

		/**
		 * Get a rule that applies to a a user
		 * 
		 * @param WP_User $user 
		 * 
		 * @return null|array Returns a rule applied to a current user
		 */
		private function get_user_rule($user) {
			$rules = $this->get_user_rules($user);

			if (!$rules)
				return;

			$this->sort_rules($rules);

			$rule = $rules[0];

			// if (is_single()) {
			// 	global $post;
			// 	$comment_limit = trim(get_post_meta(get_the_ID(), 'maximum_comments_allow', true));

			// 	if (strlen($comment_limit) > 0) {
			// 		$rule['limit'] = absint($comment_limit);
			// 		$rule['message'] = absint($comment_limit);
			// 	}
			// }

			return $rule;
		}

		/**
		 * Sort rules based on comment limits
		 * 
		 * @param array &$rules array of rules
		 */
		private function sort_rules(&$rules) {
			usort($rules, function ($a, $b) {
				return $a['limit'] < $b['limit'] && $a['comment_limit_max'] < $b['comment_limit_max'] ? 1 : -1;
			});
		}

		/**
		 * Gets all rules applicable to current user
		 * 
		 * @param WP_User $user 
		 * 
		 * @return null|array
		 */
		private function get_user_rules($user) {
			$options = $this->lpwc_getOptions();


			if ($options && isset($options['rules'])) {
				$rules = [];
				$rules = array_merge($rules, $this->filter_rules_by_user_id($options['rules'], $user->ID));
				$rules = array_merge($rules, $this->filter_rules_by_role($options['rules'], $user));

				return $rules;
			}

			return null;
		}

		private function filter_rules_by_user_id($rules, $user_id) {
			return array_filter($rules, function ($rule) use ($user_id) {
				return isset($rule['role']) && $rule['role'] == $user_id;
			});
		}

		/**
		 * Fetch rules that applies to a user if in a role
		 * 
		 * @param array $rules array of rules to filter
		 * @param WP_User|int $user User ID or WP_User object
		 * 
		 * @return array Array of rules applicable to user
		 */
		private function filter_rules_by_role($rules, $user) {
			return array_filter($rules, function ($rule) use ($user) {
				return isset($rule['role']) && user_can($user, strtolower($rule['role']));
			});
		}

		private function sort_rules_by_comment_limit(array &$rules) {
			usort($rules, function ($a, $b) {
				return $a['limit'] > $b['limit'] ? 1 : -1;
			});
		}

		private function apply_restrictions_to_comment_editor($field, $max_len, $comment_limit, $word_limit_exceeded_message) {
			$start            = strpos($field, 'maxlength');
			$max_length_field = 'data-max="' . $max_len . '" ';
			if ($start) {
				$pos   = strpos($field, '"', $start) + 1;
				$end   = strpos($field, '"', $pos) + 1;
				$field = mb_substr($field, 0, $start) . mb_substr($field, $end);
			}
			$start = strpos($field, '<textarea');

			//Check if show comment rules option is active
			$display_rules = "";
			if (get_option("lpwc_show_comment_rules") == "active") {
				$nonce = wp_create_nonce('lpwc-comment-rules-nonce');
				$display_rules = '<a class="lpwc-comment-rules" data-nonce="' . $nonce . '">' . __('Comment Rules ', 'limit-comments-and-word-count') . '</a>';
			}

			return mb_substr($field, 0, $start) . '<div class="limit">' . $display_rules . __('Characters', 'limit-comments-and-word-count') . ': <span class="comment-current" id="chars_count">0</span>' .
			__('Words', 'limit-comments-and-word-count') . ': <span class="comment-current" id="words_count">0</span> ' . __('Word Limit', 'limit-comments-and-word-count') . ': <span class="comment-max">' . sprintf("%03d", $max_len) . '</span>
                                                <span class="comment-limit">' . __('Comments Remaining', 'limit-comments-and-word-count') . ': ' . $comment_limit . '</span>
                                                </div><textarea ' . $max_length_field . mb_substr($field, $start + strlen('<textarea') + 1) . $this->get_alert($word_limit_exceeded_message, false);
		}

		private function get_alert($message, $display_message) {
			$style = '';
			if (!$display_message) {
				$style = ' style="display:none;"';
			}

			return '<div class="alert-message"' . $style . '><p>' . $message . '</p></div>';
		}

		private function restrict_comment($field, $message) {
			$start  = strpos($field, '<textarea');
			$start1 = strpos($field, '</textarea>') + strlen('</textarea>');

			return mb_substr($field, 0, $start) . $this->get_alert($message, true) . mb_substr($field, $start1);
		}

		public function preprocess_comment($comment) {
			$user = wp_get_current_user();
			if ($user && !$user->has_cap('administrator')) {
				$rule = $this->get_user_rule($user);
				if ($rule) {
					$comment_len = $this->count_words($comment['comment_content']);
					if ($rule) {
						$lim_max = intval($rule['comment_limit_max']);

						if ($comment_len > $lim_max) {
							$this->lpwc_not_allowed($rule['comment_max_message']);
							exit;
						}
					}
				}
			}

			return $comment;
		}

		private function count_words($text) {
			if (strlen($text) > 0) {
				return count(mb_split(" ", $text));
			}

			return 0;
		}

		public function restrict_comment_button($button, $args) {
			if ($this->comments_restricted) {
				return '';
			}

			return $button;
		}

		public function add_plugin_link($links) {
			$settings_link = '<a href="options-general.php?page=lpwc_settings_page">' . __('Settings') . '</a>';
			$links         = array_merge([$settings_link], $links);

			return $links;
		}

		public function add_details_link($links, $plugin_file, $plugin_data) {
			if (isset($plugin_data['PluginURI']) && false !== strpos($plugin_data['PluginURI'], 'http://wordpress.org/extend/plugins/')) {
				$slug = basename($plugin_data['PluginURI']);
				unset($links[2]);
				$links[] = sprintf('<a href="%s" class="thickbox" title="%s">%s</a>', self_admin_url('plugin-install.php?tab=plugin-information&amp;plugin=' . $slug . '&amp;TB_iframe=true&amp;width=772&amp;height=563'), esc_attr(sprintf(__('More information about %s', 'limit-comments-and-word-count'), $plugin_data['Name'])), __('View Details', 'limit-comments-and-word-count'));
			}

			return $links;
		}

		public function add_translations() {
			load_plugin_textdomain('limit-comments-and-word-count', false, basename(dirname(__FILE__)) . '/languages');
		}

		/**
		 * Disable Comment flood notice
		 * @return boolean
		 * @since 1.0.7
		 */
		public function disable_comment_flood_protection() {
			$is_disable = get_option('lpwc_disable_flood_protection');
			if ($is_disable == "active") {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Adds random content to the duplicate comments
		 * @param array $comment_data
		 * @return array
		 * @since 1.0.7
		 */
		public function enable_duplicate_comments_preprocess_comment($comment_data) {
			$is_duplicate_allowed = get_option('lpwc_allow_duplicate_comments');
			if ($is_duplicate_allowed == "active") {
				//add some random content to comment to keep dupe checker from finding it
				$random = md5(time());
				$comment_data['comment_content'] .= "disabledupes{" . $random . "}disabledupes";
			}

			return $comment_data;
		}

		/**
		 * Remove random content from the duplicate comments to avoid duplicate comment notice
		 * @global object $wpdb
		 * @param int $comment_id
		 * @since 1.0.7
		 */
		public function enable_duplicate_comments_comment_post($comment_id) {
			global $wpdb;
			$is_duplicate_allowed = get_option('lpwc_allow_duplicate_comments');
			if ($is_duplicate_allowed == "active") {
				//remove the random content
				$comment_content = $wpdb->get_var("SELECT comment_content FROM $wpdb->comments WHERE comment_ID = '$comment_id' LIMIT 1");
				$comment_content = preg_replace("/disabledupes{.*}disabledupes/", "", $comment_content);
				$wpdb->query("UPDATE $wpdb->comments SET comment_content = '" . $wpdb->escape($comment_content) . "' WHERE comment_ID = '$comment_id' LIMIT 1");
			}
		}

		public function check_trial() {
			$prev_version        = get_option('lpwc_version');
			$current_plugin_data = get_plugin_data(__FILE__);
			$show_alert          = get_option('lpwc_show_notification', -1);


			if (!$prev_version && $show_alert === -1) {
				wp_schedule_single_event(time() + LPWC_TRIAL_PERIOD, 'lpwc_add_notification');
				update_option('lpwc_version', $current_plugin_data['Version']);
			}
		}

		public function add_notification() {
			if (get_option('lpwc_show_notification') && is_admin()) {
				add_action('admin_notices', array($this, 'show_admin_message'));
			}
		}

		public function add_admin_notification_notice() {
			update_option('lpwc_show_notification', true);
		}

		public function show_admin_message() {
		?>
			<div class="notice notice-error is-dismissible" id="lpwc-trial-message">
				<p><?= __('You have been using \'Limit Comments and Word Count\' for 15 days. A positive feedback is the only payment request for this effort. Please <a href="https://wordpress.org/plugins/limit-comments-and-word-count/" id="review-link" target="_blank">Click Here</a> to do so.', 'limit-comments-and-word-count'); ?></p>
			</div>
			<?php }

		public function cancel_notification() {
			update_option('lpwc_show_notification', false);
		}

		/**
		 * Display Feature Message
		 * @since 1.1.0
		 */
		public function display_feature_message() {
			$plugin_options = get_site_option('lpwc_info');
			$display_feature = get_option('lpwc_display_feature_notification');

			if ($plugin_options['version'] == "1.1.0"  && $display_feature === "show") {
			?>
				<div class="notice notice-success is-dismissible" id="lpwc-feature-message">
					<p><?php echo __('<strong>Limit Comments and Word Count</strong> plugin includes a new feature that pops up comment rules so that users have an explanation of a blogs rule limits, editable from the settings. Please visit the plugins description <a href="https://wordpress.org/plugins/limit-comments-and-word-count/" target="_blank" >HERE</a> for more information along with revised screen captures.', 'limit-comments-and-word-count'); ?></p>
				</div>
<?php
			}
		}

		/**
		 * Close Feature Message notification
		 * @since 1.1.0
		 */
		public function close_feature_notification() {
			update_option('lpwc_display_feature_notification', "hide");
		}

		public function get_default_comment_rules() {
			$default_body = '<ul>';
			$default_body .= '<li style="font-weight: 400;">' . __('Please show respect to the opinions of others no matter how seemingly far-fetched.', 'limit-comments-and-word-count') . '</li>';
			$default_body .= '<li style="font-weight: 400;">' . __('Abusive, foul language, and/or divisive comments may be deleted without notice.', 'limit-comments-and-word-count') . '</li>';
			$default_body .= '<li style="font-weight: 400;">' . __('Each blog member is allowed limited comments, as displayed above the comment box.', 'limit-comments-and-word-count') . '</li>';
			$default_body .= '<li style="font-weight: 400;">' . __('Comments must be limited to the number of words displayed above the comment box.', 'limit-comments-and-word-count') . '</li>';
			$default_body .= '<li style="font-weight: 400;">' . __('Please limit one comment after any comment posted per post.', 'limit-comments-and-word-count') . '</li>';
			$default_body .= '</ul>';
			return $default_body;
		}

		/**
		 * Includes comments rule modal
		 * @since 1.1.0
		 */
		public function add_comment_rules() {
			$user = wp_get_current_user();
			if ($user) {
				$rule = $this->get_user_rule($user);
				if ($rule) {
					include(LPWC_PATH . "includes/comment-rules-modal.php");
				}
			}
		}

		/**
		 * Register a new box for rules exclusion
		 *
		 * @param string $post_type
		 *
		 * @return void
		 * @author Olatechpro
		 */
		public function add_meta_boxes($post_type) {

			add_meta_box(
				'limit-comments-and-word-count-settings',
				__('Limit Comments and Word Count Settings', 'limit-comments-and-word-count'),
				array($this, 'metabox'),
				$post_type,
				'side',
				'low'
			);
		}

		/**
		 * Build HTML of form
		 *
		 * @param object $post
		 *
		 * @return void
		 * @author Olatechpro
		 */
		public function metabox($post) {
			if (!isset($post->post_type)) {
				return;
			}
			$meta_value = get_post_meta($post->ID, '_exclude_comments_limit', true);
			echo '<p>' . "\n";
			echo '<label><input type="checkbox" name="exclude_comments_limit" value="true" ' . checked($meta_value, true, false) . ' /> ' . __('Turn off Comments and Word Count for this post ?', 'limit-comments-and-word-count') . '</label><br />' . "\n";
			echo '</p>' . "\n";
			echo '<input type="hidden" name="_meta_exclude_comments_limit" value="true" />';

			// echo '<p>';
			// echo '<label for="maximum_comments_allow">' . __('Maximum comments allowed', 'limit-comments-and-word-count') . '</label>';
			// printf('<input style="width: 60px; margin-left:5px;padding-right:0" id="maximum_comments_allow" name="maximum_comments_allow" type="number" value="%s" />', $post->maximum_comments_allow);
			// echo '</p>';
		}

		/**
		 * Save this settings in post meta, delete if no exclude, clean DB :)
		 *
		 * @param integer $object_id
		 *
		 * @return void
		 * @author Olatechpro
		 */
		public function save_post($object_id = 0) {
			if (isset($_POST['_meta_exclude_comments_limit']) && 'true' === $_POST['_meta_exclude_comments_limit']) {
				if (isset($_POST['exclude_comments_limit'])) {
					update_post_meta($object_id, '_exclude_comments_limit', true);
				} else {
					delete_post_meta($object_id, '_exclude_comments_limit');
				}
			}

			// if (isset($_POST['maximum_comments_allow'])) {
			// 	update_post_meta($object_id, 'maximum_comments_allow', $_POST['maximum_comments_allow']);
			// }
		}

		/**
		 * Hide comment form after getting maximum amount of comment
		 * @since 1.1.8
		 * @author Repon
		 */

		public function hide_comment_form($comments_open) {
			if (!is_single()) {
				return $comments_open;
			}

			$global_max_comments = get_option('lpwc_global_max_comments');
			if (empty($global_max_comments)) {
				return $comments_open;
			}

			$total_comments = get_comments_number(get_the_id());
			if ($total_comments >= $global_max_comments) {
				return false;
			}

			return $comments_open;
		}
	} //end class
} //end if class exists

global $lpwc;
$lpwc = new lpwc();

add_action('lpwc_add_notification', array($lpwc, 'add_admin_notification_notice'));
add_action('wp_footer', array($lpwc, 'add_comment_rules'));
