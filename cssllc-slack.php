<?php
/**
Plugin Name: CSS LLC Slack Integration
Description: Integration plugin for WordPress projects to Slack
     Author: Caleb Stauffer Style, LLC
 Author URI: http://develop.calebstauffer.com
    Version: 0.0.2
**/

register_deactivation_hook(__FILE__,array('cssllc_slack','on_deactivation'));

new cssllc_slack;
class cssllc_slack {

	public static $wrap_title = '*';
	public static $wrap_body = '```';

	public static $current_hook = '';
	public static $current_args = array();

	public static $children = array();

	function __construct() {

		$actions = apply_filters('cssllc_slack_actions',array(
			'activate_blog' => false,
			'activated_plugin' => 2,
			'after_db_upgrade' => false,
			'after_mu_upgrade' => false,
			'after_switch_theme' => false,
			'archive_blog' => false,
			'automatic_updates_complete' => false,
			'deactivate_blog' => false,
			'deactivated_plugin' => 2,
			'generate_rewrite_rules' => false,
			'granted_super_admin' => false,
			'make_delete_blog' => false,
			'make_undelete_blog' => false,
			'make_ham_blog' => false,
			'make_spam_blog' => false,
			'make_ham_user' => false,
			'make_spam_user' => false,
			'mature_blog' => false,
			'revoked_super_admin' => false,
			'set_site_transient_update_themes' => false,
			'set_site_transient_update_plugins' => false,
			'update_blog_public' => false,
			'upgrader_process_complete' => false,
			'unmature_blog' => false,
			'wp_install' => false,
			'wp_upgrade' => false,
			'wpmu_activate_blog' => 2,
			'wpmu_new_blog' => 2,
			'_core_updated_successfully' => false,
			'woocommerce_settings_save_general' => false,
			'woocommerce_settings_save_products' => false,
			'woocommerce_settings_save_tax' => false,
			'woocommerce_settings_save_checkout' => false,
			'woocommerce_settings_save_shipping' => false,
			'woocommerce_settings_save_accounts' => false,
			'woocommerce_settings_save_emails' => false,
			'woocommerce_settings_save_integrations' => false,
			'woocommerce_settings_save_webhooks' => false,
		));

		$filters = apply_filters('cssllc_slack_filters',array(
			'woocommerce_add_error' => false,
		));

		foreach (array_merge($actions,$filters) as $hook => $array)
			if (is_array($array) && array_key_exists('parents',$array) && count($array['parents'])) {
				add_filter('cssllc_slack_record_' . $hook,'__return_true');
				foreach ($array['parents'] as $parent) {
					add_filter('cssllc_slack_record_' . $parent,'__return_true');
					cssllc_slack::$children[$hook][] = $parent;
				}
				cssllc_slack::$children[$hook] = array_unique(cssllc_slack::$children[$hook]);
			}

		foreach ($actions as $action => $args) {
			$num_args = is_array($args) ? $args['num_args'] : $args;
			add_action($action,array(__CLASS__,'action'),9999999999999,($num_args ?: 1));
		}

		foreach ($filters as $filter => $args) {
			$num_args = is_array($args) ? $args['num_args'] : $args;
			add_filter($filter,array(__CLASS__,'filter'),9999999999999,($num_args ?: 1));
		}

		cssllc_slack::filters();

		add_action((is_admin() ? 'admin_' : '') . 'init',array(__CLASS__,'push_record'));

	}

	public static function action() {
		$action = current_filter();
		$args = func_get_args();

		$args = apply_filters('cssllc_slack_args_' . $action,
			apply_filters('cssllc_slack_args',
				$args
			,$action,$args)
		,$action);

		if (true === apply_filters('cssllc_slack_cancel' . $action,
			apply_filters('cssllc_slack_cancel',
				false
			,$action,$args)
		,$args))
			return;

		cssllc_slack::$current_hook = $action;
		cssllc_slack::$current_args = $args;

		if (true === apply_filters('cssllc_slack_record_' . $action,
			apply_filters('cssllc_slack_record',
				false
			,$action,$args)
		,$args)) {
			cssllc_slack::record($action,$args);
			return;
		}

		$payload = array('text' => cssllc_slack::text());

		if (false !== $payload['text'])
			cssllc_slack::push_post($payload);
	}

	public static function filter() {
		$filter = current_filter();
		$args = func_get_args();

		$args = apply_filters('cssllc_slack_args_' . $filter,
			apply_filters('cssllc_slack_args',
				$args
			,$filter,$args)
		,$filter);

		if (true === apply_filters('cssllc_slack_cancel' . $filter,
			apply_filters('cssllc_slack_cancel',
				false
			,$filter,$args)
		,$args))
			return;

		cssllc_slack::$current_hook = $filter;
		cssllc_slack::$current_args = $args;

		if (true === apply_filters('cssllc_slack_record_' . $filter,
			apply_filters('cssllc_slack_record',
				false
			,$filter,$args)
		,$args)) {
			cssllc_slack::record($filter,$args);
			return;
		}

		$payload = array('text' => cssllc_slack::text());

		if (false !== $payload['text'])
			cssllc_slack::push_post($payload);
	}

	public static function record() {
		$record = $orig = get_site_transient('cssllc_slack_record');

		$new = apply_filters('cssllc_slack_record_' . cssllc_slack::$current_hook . '_body',
			apply_filters('cssllc_slack_record_body',
				cssllc_slack::$current_args
			,cssllc_slack::$current_hook)
		,cssllc_slack::$current_args);

		if (false !== $new)
			$record[cssllc_slack::$current_hook][] = $new;

		if ($record !== $orig)
			set_site_transient('cssllc_slack_record',$record,60*10);
	}

	public static function text($title = false,$site = false,$body = false) {
		if (false === $title)
			$title = apply_filters('cssllc_slack_post_title',cssllc_slack::$current_hook,cssllc_slack::$current_args);
		if (false === $site)
			$site = apply_filters('cssllc_slack_post_site',get_bloginfo('url'),cssllc_slack::$current_hook,cssllc_slack::$current_args);
		if (false === $body)
			$body = apply_filters('cssllc_slack_post_body','',cssllc_slack::$current_hook,cssllc_slack::$current_args);

		return apply_filters('cssllc_slack_post_' . cssllc_slack::$current_hook,
			apply_filters('cssllc_slack_post',
				$title . $site . $body
			,cssllc_slack::$current_hook,cssllc_slack::$current_args)
		,cssllc_slack::$current_args);
	}

	public static function filters() {
		add_filter('cssllc_slack_cancel',array(__CLASS__,'filter_cancel'),10,3);
		add_filter('cssllc_slack_record',array(__CLASS__,'filter_record'),10,3);
		add_filter('cssllc_slack_post_title',array(__CLASS__,'filter_title'),10,2);
		add_filter('cssllc_slack_post_site',array(__CLASS__,'filter_generate_site_link'),1);
		add_filter('cssllc_slack_post_body',array(__CLASS__,'filter_body'),10,3);
	}

		public static function filter_cancel($bool,$hook,$args) {
			switch ($hook) {
				case 'updated_postmeta':
					if ('_edit_lock' == $args[2]) return true;
					break;

				case 'set_site_transient_update_themes':
				case 'set_site_transient_update_plugins':
					if (!isset($args->response) || !is_array($args->response) || !count($args->response))
						return true;

					$working_action = str_replace('set_site_transient_','',$hook);

					if (get_site_transient('cssllc_slack_' . $working_action) === $args->response)
						return true;

					set_site_transient('cssllc_slack_' . $working_action,$args->response,60*60*24*7);

					break;

				case 'upgrader_process_complete':
					if ('Core_Upgrader' == get_class($args[0]))
						return true;

				case 'woocommerce_add_error':
					if (
						false === strpos($_SERVER['REQUEST_URI'],'cart') &&
						false === strpos($_SERVER['REQUEST_URI'],'checkout')
					)
						return true;

					$exclude_messages = apply_filters('cssllc_slack_wc_exclude_messages',array(
						'field_required' => 'is a required field',
							  'card_num' => 'card number is invalid',
							 'card_expr' => 'card expiration date',
							 'card_code' => 'card security code is invalid',
						   'card_verify' => 'credit card verification number',
							'card_valid' => 'enter a valid credit card number',
								'coupon' => 'coupon',
						 'no_user_email' => 'user could not be found with this email',
						 'email_invalid' => 'not a valid email address',
						  'user_invalid' => 'invalid username',
							 'valid_zip' => 'enter a valid postcode',
						   'valid_phone' => 'not a valid phone number',
							  'password' => 'lost your password',
					));

					foreach ($exclude_messages as $message)
						if (false !== stripos(strip_tags($args[0]),$message))
							return true;
			}

			return $bool;
		}

		public static function filter_record($bool,$action,$args) {
			switch ($action) {
				case 'activated_plugin':
				case 'deactivated_plugin':
					return true;
			}
		}

		public static function filter_title($action,$args) {
			$wrap = apply_filters('cssllc_slack_wrap_title_' . cssllc_slack::$current_hook,
				apply_filters('cssllc_slack_wrap_title',
					cssllc_slack::$wrap_title
				,$action)
			);

			switch ($action) {
				case 'activated_plugin':
				case 'deactivated_plugin':
					return $wrap . (isset($args[1]) && true === $args[1] ? 'Network ' : '') . ucfirst(str_replace('_',' ',$action)) . (is_array($args) && 1 < count($args) ? 's' : '') . $wrap;

				case 'after_db_upgrade':
					return $wrap . 'Database upgraded' . $wrap;

				case 'generate_rewrite_rules':
					return $wrap . 'Rewrite rules generated' . $wrap;

				case 'set_site_transient_update_themes':
				case 'set_site_transient_update_plugins':
					$working_action = str_replace('set_site_transient_','',$action);

					if ('update_plugins' == $working_action) {

						$plugins = array();
						foreach ($args->response as $obj) {
							if (!is_object($obj) || !count($obj) || !isset($obj->plugin) || '' == $obj->plugin) continue;
							$plugin = get_plugin_data(ABSPATH . '/wp-content/plugins/' . $obj->plugin);
							if ('' == $plugin['Name']) continue;
							$plugins[] = $plugin['Name'];
						}
						if (0 == count($plugins)) return false;

						return $wrap . 'Plugin ' . _n('update','updates',count($plugins)) . '(' . count($plugins) . ')' . $wrap;

					} else if ('update_themes' == $args[1]) {

						$themes = array();
						foreach ($args->response as $obj) {
							$theme = wp_get_theme(ABSPATH . '/wp-content/themes/' . $obj->theme);
							if ('' == $theme->get('Name')) continue;
							$themes[] = $theme->get('Name');
						}
						if (0 == count($themes)) return false;

						return $wrap . 'Theme ' . _n('update','updates',count($themes)) . '(' . count($themes) . ')' . $wrap;

					}

				case 'upgrader_process_complete':
					if (is_object($args[0])) {
						if ('Plugin_Upgrader' == get_class($args[0]))
							return $wrap . 'Plugin ' . _n('update','updates',count($args)) . ' complete' . $wrap;
					} else
						return $wrap . 'Update complete' . $wrap;

				case 'woocommerce_settings_save_general':
				case 'woocommerce_settings_save_products':
				case 'woocommerce_settings_save_tax':
				case 'woocommerce_settings_save_checkout':
				case 'woocommerce_settings_save_shipping':
				case 'woocommerce_settings_save_accounts':
				case 'woocommerce_settings_save_emails':
				case 'woocommerce_settings_save_integrations':
				case 'woocommerce_settings_save_webhooks':
					return $wrap . 'WooCommerce settings saved' . $wrap;

				case 'woocommerce_add_error':
					return $wrap . 'WooCommerce error' . $wrap;

				case '_core_updated_successfully':
					echo '<p>Sending notification...</p>';
					return $wrap . 'Core updated to v' . $args[0] . $wrap;

				default:
					return $wrap . $action . $wrap;
			}
		}

		public static function filter_generate_site_link($siteurl) {
			$domain = str_replace('https://','',
				str_replace('http://','',
					str_replace('www.','',
						stripslashes($siteurl)
					)
				)
			);
			return ' on <' . $siteurl . '|' . $domain . '>';
		}

		public static function filter_body($text,$action,$args) {
			$wrap = apply_filters('cssllc_slack_wrap_body_' . cssllc_slack::$current_hook,
				apply_filters('cssllc_slack_wrap_body',
					cssllc_slack::$wrap_body
				,cssllc_slack::$current_hook)
			,cssllc_slack::$current_args);

			switch ($action) {
				case 'activated_plugin':
				case 'deactivated_plugin':
					if (!is_array($args[0]) && count($args[0]))
						$plugins = array($args);
					else
						$plugins = $args;

					$return = array();
					foreach ($plugins as $plugin) {
						$data = get_plugin_data(ABSPATH . '/wp-content/plugins/' . $plugin[0]);
						$return[] = $data['Name'];
					}
					return ': ' . (1 < count($plugins) ? "\n •" : '') . implode("\n• ",$return);

				/*case 'upgrader_process_complete':
					if (is_object($args[0])) {
						if ('Plugin_Upgrader' == get_class($args[0])) {
							$plugins = array();
							foreach ($args as $plugin)
								$plugins[] = $plugin->skin->plugin_info['Name'];
							return ":\n• " . implode("\n• ",$plugins);
						}
						return ': ' . get_class($args[0]);
					}*/

				case 'woocommerce_settings_save_general':
				case 'woocommerce_settings_save_products':
				case 'woocommerce_settings_save_tax':
				case 'woocommerce_settings_save_checkout':
				case 'woocommerce_settings_save_shipping':
				case 'woocommerce_settings_save_accounts':
				case 'woocommerce_settings_save_emails':
				case 'woocommerce_settings_save_integrations':
				case 'woocommerce_settings_save_webhooks':
					return ': ' . ucfirst(str_replace('woocommerce_settings_save_','',self::$current_hook));

				case 'woocommerce_add_error':
					return ': ' . strip_tags($args[0]);

				case '_core_updated_successfully':
				case 'after_db_upgrade':
				case 'generate_rewrite_rules':
					return '';
			}

			if ('' === $text && (is_array($args) || is_object($args)))
				return ": \n" . $wrap . print_r($args,true) . $wrap;

			return $text;
		}

	public static function push_record() {
		if (defined('DOING_AJAX') && DOING_AJAX) return;

		$record = get_site_transient('cssllc_slack_record');
		if (!is_array($record) || !count($record)) return false;

		foreach ($record as $action => $args)
			if (array_key_exists($action,cssllc_slack::$children))
				foreach (cssllc_slack::$children[$action] as $parent)
					if (array_key_exists($parent,$record))
						unset($record[$action]);

		if (!count($record)) return false;

		foreach ($record as $action => $args) {
			self::$current_hook = $action;
			self::$current_args = $args;
			$payload = array('text' => cssllc_slack::text());
			if (false !== $payload['text'])
				cssllc_slack::push_post($payload);
		}

		delete_site_transient('cssllc_slack_record');
	}

	public static function push_post($payload) {
		$api_url = apply_filters('cssllc_slack_apiurl','https://hooks.slack.com/services/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');
		$payload['username'] = 'wordpress-notifier';

		$channel = apply_filters('cssllc_slack_channel',false);
		if (false !== $channel) $payload['channel'] = $channel;

		if (file_exists(get_template_directory() . '/slack.png'))
			$payload['icon_url'] = get_template_directory_uri() . '/slack.png';

		$response = wp_remote_post(
			$api_url,
			array('body' => array(
				'payload' => json_encode(
					apply_filters('cssllc_slack_payload',
						$payload,
					cssllc_slack::$current_hook,cssllc_slack::$current_args)
				)
			))
		);

		if ((is_wp_error($response) || '500' == $response['response']['code']) && array_key_exists('channel',$payload)) {
			unset($payload['channel']);
			wp_remote_post(
				$api_url,
				array('body' => array(
					'payload' => json_encode(
						apply_filters('cssllc_slack_payload',
							$payload,
						cssllc_slack::$current_hook,cssllc_slack::$current_args)
					)
				))
			);
		}
	}

	public static function on_deactivation() {
		if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

		$user = wp_get_current_user();

		$site = apply_filters('cssllc_slack_post_site',get_bloginfo('url'),'deactivating_cssllc_slack_plugin');
		self::push_post(array('text' => '*CSSLLC Slack plugin deactivated*' . $site . ' by ' . $user->user_login));
	}

}
