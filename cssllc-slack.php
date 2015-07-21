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
		$actions = array(
			'activate_blog' => false,
			'activated_plugin' => 2,
			'after_db_upgrade' => false,
			'after_mu_upgrade' => false,
			'after_switch_theme' => false,
			'archive_blog' => false,
			'automatic_updates_complete' => false,
			'core_upgarde_preamble' => false,
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
		);
		foreach ($actions as $action => $num_args)
			add_action($action,function() {
				$action = self::$current_action = current_filter();
				$args = func_get_args();
				$domain = str_replace('https://','',str_replace('http://','',self::$site_url));

				$payload = array();
				$payload['username'] = 'wordpress-notifier';
				$channel = apply_filters('cssllc_slack_channel',false);
				if (false !== $channel) $payload['channel'] = $channel;

				if (method_exists(__CLASS__,str_replace('-','_',$action) . '_text')) {
					$method = str_replace('-','_',$action) . '_text';
					$payload['text'] = self::$method($args,$domain);
				}
				if (false === self::$method($args,$domains)) return false;
				if (!array_key_exists('text',$payload) || '' === $payload['text']) {
					foreach ($args as $k => $v)
						if (is_bool($v)) $args[$k] = $v ? 'true' : 'false';
						else if (empty($v)) $args[$k] = 'EMPTY';
					$payload['text'] = '*' . $action . '* on <' . self::$site_url . '|' . $domain . '>: ' . (1 == count($args) ? $args[0] : print_r($args,true));
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
			return '*Update complete* on <' . self::$site_url . '|' . $domain . '>: ' . get_class($args[0]);
		} else if (!is_object($args[0])) return '*Update complete* on <' . self::$site_url . '|' . $domain . '>: ' . print_r($args,true);
	}

	private static function setted_site_transient_text($args,$domain) {
		if (!in_array($args[0],array('update_themes','update_plugins')) || !is_array($args[1]->response) || !count($args[1]->response)) return false;
		if ('update_plugins' == $args[0]) {
			$plugins = array();
			foreach ($args[1]->response as $obj) {
				if (!count($obj) || '' == $obj->plugin) continue;
				$plugin = get_plugin_data(ABSPATH . '/wp-content/plugins/' . $obj->plugin);
				if ('' == $plugin['Name']) continue;
				$plugins[] = $plugin['Name'];
			}
			return '*Plugin updates available* on <' . self::$site_url . '|' . $domain . '>' . (count($plugins) ? ":\n- " . implode("\n- ",$plugins) : '');
		} else if ('update_themes' == $args[1]) {
			$themes = array();
			foreach ($args[1]->response as $obj) {
				$theme = wp_get_theme(ABSPATH . '/wp-content/themes/' . $obj->theme);
				$themes[] = $theme->get('Name');
			}
			return '*Theme updates available* on <' . self::$site_url . '|' . $domain . '>: ' . "\n- " . implode("\n- ",$themes);
		}
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
