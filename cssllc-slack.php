<?php
/**
Plugin Name: CSS LLC Slack Integration
Description: Integration plugin for WordPress projects to Slack
     Author: Caleb Stauffer Style, LLC
 Author URI: http://develop.calebstauffer.com
    Version: 0.0.1
**/

new cssllc_slack_integration;
class cssllc_slack_integration {

	private static $site_url = '';
	private static $api_url = 'https://hooks.slack.com/services/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';
	private static $current_action = '';

	function __construct() {
		self::$site_url = get_bloginfo('url');

		add_filter('woocommerce_add_error',array(__CLASS__,'woocommerce_error_notice'),9999999999999);

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
			'setted_site_transient' => 2,
			'update_blog_public' => false,
			'upgrader_process_complete' => false,
			'unmature_blog' => false,
			'wp_install' => false,
			'wp_upgrade' => false,
			'wpmu_activate_blog' => 2,
			'wpmu_new_blog' => 2,
			'_core_updated_successfully' => false,
		));

		foreach (array(
			'general',
			'products',
			'tax',
			'checkout',
			'shipping',
			'accounts',
			'emails',
			'integrations',
			'webhooks'
		) as $wc_action)
			$actions['woocommerce_settings_save_' . $wc_action] = false;

		foreach ($actions as $action => $num_args)
			add_action($action,function() {
				$action = self::$current_action = current_filter();
				$args = func_get_args();
				$domain = str_replace('https://','',str_replace('http://','',str_replace('www.','',self::$site_url)));

				$payload = array();
				$payload['username'] = 'wordpress-notifier';
				$channel = apply_filters('cssllc_slack_channel',false);
				if (false !== $channel) $payload['channel'] = $channel;

				if (method_exists(__CLASS__,str_replace('-','_',$action) . '_text')) {
					$method = str_replace('-','_',$action) . '_text';
					$payload['text'] = self::$method($args,$domain);
					if (false === $payload['text']) return false;
				} else if (false !== strpos($action,'woocommerce_settings_save_') && method_exists(__CLASS__,'woocommerce_settings_save_text'))
					$payload['text'] = self::woocommerce_settings_save_text($args,$domain);

				if (!array_key_exists('text',$payload) || '' === $payload['text']) {
					foreach ($args as $k => $v)
						if (is_bool($v)) $args[$k] = $v ? 'true' : 'false';
						else if (empty($v)) $args[$k] = 'EMPTY';
					$payload['text'] = '*' . $action . '* on <' . self::$site_url . '|' . $domain . '>: ' . (1 == count($args) ? (is_object($args[0]) || is_array($args[0]) ? print_r($args[0],true) : $args[0]) : print_r($args,true));
				}

				if (file_exists(get_template_directory() . '/slack.png'))
					$payload['icon_url'] = get_template_directory_uri() . '/slack.png';

				$response = wp_remote_post(self::$api_url,array('body' => array('payload' => json_encode($payload))));

				if ((is_wp_error($response) || '500' == $response['response']['code']) && array_key_exists('channel',$payload)) {
					unset($payload['channel']);
					wp_remote_post(self::$api_url,array(
						'body' => array('payload' => json_encode($payload)),
					));
				}
			},9999999999999,($num_args ?: 1));
	}

	private static function plugin_text($args,$domain) {
		$plugin_data = get_plugin_data(ABSPATH . '/wp-content/plugins/' . $args[0]);
		return ' \'' . $plugin_data['Name'] . '\' on <' . self::$site_url . '|' . $domain . '>';
	}

		private static function activated_plugin_text($args,$domain) {
			return '*' . (true === $args[1] ? 'Network a' : 'A') . 'ctivated plugin*' . self::plugin_text($args,$domain);
		}

		private static function deactivated_plugin_text($args,$domain) {
			return '*' . (true === $args[1] ? 'Network d' : 'D') . 'eactivated plugin*' . self::plugin_text($args,$domain);
		}

	private static function _core_updated_successfully_text($args,$domain) {
		echo '<p>Sending notification...</p>';
		return '*Core updated* on <' . self::$site_url . '|' . $domain . '> to v' . $args[0];
	}

	private static function upgrader_process_complete_text($args,$domain) {
		if (is_object($args[0])) {
			if ('Core_Upgrader' == get_class($args[0])) return false;
			else if ('Plugin_Upgrader' == get_class($args[0])) {
				$return = array();
				foreach ($args as $plugin)
					$return[] = $plugin->skin->plugin_info['Name'];
				return '*Plugin update' . (1 == count($return) ? '' : 's') . ' complete* on <' . self::$site_url . '|' . $domain . '>:' . "\n" . implode(', ',$return);
			}
			return '*Update complete* on <' . self::$site_url . '|' . $domain . '>: ' . get_class($args[0]);
		} else if (!is_object($args[0])) return '*Update complete* on <' . self::$site_url . '|' . $domain . '>: ' . print_r($args,true);
	}

	private static function setted_site_transient_text($args,$domain) {
		if (
			!in_array($args[0],array('update_themes','update_plugins')) ||
			!isset($args[1]->response) || !is_array($args[1]->response) || !count($args[1]->response)
		) return false;
		if (get_site_transient('cssllc_slack_' . $args[0]) == $args[1]->response) return false;

		set_site_transient('cssllc_slack_' . $args[0],$args[1]->response,60*60*12);

		if ('update_plugins' == $args[0]) {
			$plugins = array();
			foreach ($args[1]->response as $obj) {
				if (!is_object($obj) || !count($obj) || !isset($obj->plugin) || '' == $obj->plugin) continue;
				$plugin = get_plugin_data(ABSPATH . '/wp-content/plugins/' . $obj->plugin);
				if ('' == $plugin['Name']) continue;
				$plugins[] = $plugin['Name'];
			}
			if (0 == count($plugins)) {
				delete_site_transient('cssllc_slack_' . $args[0]);
				return false;
			}
			return '*Plugin updates (' . count($plugins) . ') available* on <' . self::$site_url . '|' . $domain . '>' . (count($plugins) ? ":\n- " . implode("\n- ",$plugins) : '');
		} else if ('update_themes' == $args[1]) {
			$themes = array();
			foreach ($args[1]->response as $obj) {
				$theme = wp_get_theme(ABSPATH . '/wp-content/themes/' . $obj->theme);
				if ('' == $theme->get('Name')) continue;
				$themes[] = $theme->get('Name');
			}
			if (0 == count($themes)) {
				delete_site_transient('cssllc_slack_' . $args[0]);
				return false;
			}
			return '*Theme updates (' . count($themes) . ') available* on <' . self::$site_url . '|' . $domain . '>: ' . "\n- " . implode("\n- ",$themes);
		}
	}

	private static function generate_rewrite_rules_text($args,$domain) {
		return '*Rewrite rules generated* on <' . self::$site_url . '|' . $domain . '>';
	}

	private static function woocommerce_settings_save_text($args,$domain) {
		return '*WooCommerce settings saved* on <' . self::$site_url . '|' . $domain . '>: ' . ucfirst(str_replace('woocommerce_settings_save_','',self::$current_action));
	}

	public static function woocommerce_error_notice($message) {
		if (
			false === strpos($_SERVER['REQUEST_URI'],'cart') &&
			false === strpos($_SERVER['REQUEST_URI'],'checkout') &&
			apply_filters('cssllc_slack_override_page_send_wc_error',true,$message)
		)
			return $message;

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
			  'item_amounts' => 'totals of the cart item amounts',
			     'valid_zip' => 'enter a valid postcode',
			   'valid_phone' => 'not a valid phone number',
		));
		foreach ($exclude_messages as $msg)
			if (false !== stripos(strip_tags($message),$msg))
				return $message;

		$domain = str_replace('https://','',str_replace('http://','',str_replace('www.','',self::$site_url)));
		$payload = array();
		$payload['text'] = '*WooCommerce error* on <' . self::$site_url . '|' . $domain . '>:' . "\n\"" . html_entity_decode(strip_tags($message)) . '"';
		$payload['username'] = 'wordpress-notifier';
		$channel = apply_filters('cssllc_slack_channel',false);
		if (false !== $channel) $payload['channel'] = $channel;
		if (file_exists(get_template_directory() . '/slack.png'))
			$payload['icon_url'] = get_template_directory_uri() . '/slack.png';

		$response = wp_remote_post(self::$api_url,array('body' => array('payload' => json_encode($payload))));

		if ((is_wp_error($response) || '500' == $response['response']['code']) && array_key_exists('channel',$payload)) {
			unset($payload['channel']);
			wp_remote_post(self::$api_url,array(
				'body' => array('payload' => json_encode($payload)),
			));
		}

		return $message;
	}

}

//add_action('init','cssllc_init');
function cssllc_init() {
	echo '<pre>' . print_r(get_site_transient('update_plugins'),true) . '</pre>';
}

//add_filter('wp_get_update_data','cssllc_wp_get_update_data',10,2);
function cssllc_wp_get_update_data($data,$titles) {
	echo print_r($data,true) . ' | ' . print_r($titles,true) . '<br />';
	return $data;
}
