<?php
/*
* Plugin Name: bbPress Notify (No-Spam)
* Description: Sends email notifications upon topic/reply creation, as long as it's not flagged as spam.
* Version: 1.5.4
* Author: Vinny Alves, Andreas Baumgartner, Paul Schroeder
* License:       GNU General Public License, v2 (or newer)
* License URI:  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* Copyright (C) 2012-2013 www.usestrict.net, released under the GNU General Public License.
*/

/* Search for translations */
load_plugin_textdomain('bbpress_notify',false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');

class bbPress_Notify_noSpam {
	
	protected $settings_section = 'bbpress_notify_options';
	protected $bbpress_topic_post_type;
	protected $bbpress_reply_post_type;
	
	public static $instance;

	function __construct()
	{
		/* Register hooks, filters and actions */
		if (is_admin())
		{
			// Add settings to the Dashboard
			add_action('admin_init', array(&$this,'admin_settings'));
			// Add Settings link to the plugin page
			add_filter( 'plugin_action_links', array(&$this,'plugin_action_links'), 10, 2 );
			
			// On plugin activation, check whether bbPress is active
			register_activation_hook(__FILE__, array(&$this,'on_activation'));
				
			// Deactivate original bbPress Notify if found
			add_action('admin_init', array(&$this,'deactivate_old'));
			
			// Notification meta boxes if needed
			add_action('add_meta_boxes', array(&$this,'add_notification_meta_box'), 10);
			
			add_action('save_post', array(&$this, 'notify_on_save'), 10, 2);
		}
		
		// New topics and replies can be generated from admin and non-admin interfaces
		
		// Set the bbpress post_types
		add_action('plugins_loaded', array(&$this,'set_post_types'));
		
		// Triggers the notifications on new topics
		if ( get_option('bbpress_notify_newtopic_background') )
		{
			add_action('bbpress_notify_bg_topic', array(&$this, 'notify_new_topic'), 10, 4);
			add_action('bbp_new_topic', array(&$this,'bg_notify_new_topic'), 100);
		}
		else
		{
			add_action('bbp_new_topic', array(&$this,'notify_new_topic'), 100);
		} 
		
		// Triggers the notifications on new replies
		if ( get_option('bbpress_notify_newreply_background') )
		{
			add_action('bbpress_notify_bg_reply', array(&$this, 'notify_new_reply'), 10, 4);
			add_action('bbp_new_reply', array(&$this,'bg_notify_new_reply'), 100, 4);
		}
		else
		{
			add_action('bbp_new_reply', array(&$this,'notify_new_reply'), 100);
		}
		
		// Munge bbpress_notify_newtopic_recipients if forum is hidden
		add_filter('bbpress_notify_recipients_hidden_forum', array(&$this, 'munge_newtopic_recipients'), 10, 2);
	}
	
	
	/**
	 * @since 1.5
	 * @desc Make it a singleton
	 * @return bbPress_Notify_noSpam
	 */
	public static function bootstrap()
	{
		// Make sure bbPress is still installed and avoid race conditions
		if (! class_exists('bbPress') )
		{
			if ( 'plugins_loaded' !== current_filter() )
			{
				add_action('plugins_loaded', array('bbPress_Notify_NoSpam', 'bootstrap'), 100000);
			}
			else
			{
				add_action('admin_notices', array('bbPress_Notify_NoSpam', 'missing_bbpress_notice'));
			}
			
			return false;
		}
		
		
		// bbPress is here, so let's load ourselves 
		if (! isset(self::$instance))
			self::$instance = new self();
		
		return self::$instance;
	}

	function set_post_types()
	{
		$this->bbpress_topic_post_type = bbp_get_topic_post_type();
		$this->bbpress_reply_post_type = bbp_get_reply_post_type();
	}
	
	function bg_notify_new_reply($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0)
	{
		wp_schedule_single_event(time() + 10, 'bbpress_notify_bg_reply', array($topic_id, $forum_id, $anonymous_data, $topic_author));
	}
	
	
	function bg_notify_new_topic($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0)
	{
		wp_schedule_single_event(time() + 10, 'bbpress_notify_bg_topic', array($topic_id, $forum_id, $anonymous_data, $topic_author));
	}
	
	
	function deactivate_old()
	{
		$old_plugin = 'bbpress-notify/bbpress-notify.php';
		if (is_plugin_active($old_plugin))
		{
			deactivate_plugins($old_plugin);
		}
	
	}
	
	/* Checks whether bbPress is active because we need it. If bbPress isn't active, we are going to disable ourself */
	function on_activation()
	{
		if(!class_exists('bbPress'))
		{
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die( __('Sorry, you need to activate bbPress first.', 'bbpress_notify'));
		}
	
		// Default settings
		if (!get_option('bbpress_notify_newtopic_background'))
		{
			update_option('bbpress_notify_newtopic_background', 0);
		}
		if (!get_option('bbpress_notify_newreply_background'))
		{
			update_option('bbpress_notify_newreply_background', 0);
		}
		if (!get_option('bbpress_notify_newtopic_recipients'))
		{
			update_option('bbpress_notify_newtopic_recipients', array('administrator'));
		}
		if (!get_option('bbpress_notify_newreply_recipients'))
		{
			update_option('bbpress_notify_newreply_recipients', array('administrator'));
		}
		if (!get_option('bbpress_notify_newtopic_email_subject'))
		{
			update_option('bbpress_notify_newtopic_email_subject', __('[[blogname]] New topic: [topic-title]'));
		}
		if (!get_option('bbpress_notify_newtopic_email_body'))
		{
			update_option('bbpress_notify_newtopic_email_body', __("Hello!\nA new topic has been posted by [topic-author].\nTopic title: [topic-title]\nTopic url: [topic-url]\n\nExcerpt:\n[topic-excerpt]"));
		}
		if (!get_option('bbpress_notify_newreply_email_subject'))
		{
			update_option('bbpress_notify_newreply_email_subject', __('[[blogname]] [reply-title]'));
		}
		if (!get_option('bbpress_notify_newreply_email_body'))
		{
			update_option('bbpress_notify_newreply_email_body', __("Hello!\nA new reply has been posted by [reply-author].\nTopic title: [reply-title]\nTopic url: [reply-url]\n\nExcerpt:\n[reply-excerpt]"));
		}
		if (!get_option("bbpress_notify_default_{$this->bbpress_topic_post_type}_notification"))
		{
			update_option("bbpress_notify_default_{$this->bbpress_topic_post_type}_notification", 0);
		}
		if (!get_option("bbpress_notify_default_{$this->bbpress_reply_post_type}_notification"))
		{
			update_option("bbpress_notify_default_{$this->bbpress_reply_post_type}_notification", 0);
		}
	}
	
	/**
	 * @since 1.0
	 */
	function notify_new_topic($topic_id = 0, $forum_id = 0)
	{
		global $wpdb;

		if (get_post_status($topic_id) == 'spam') 
			return -1;
		
		if (0 === $forum_id)
			$forum_id = bbp_get_topic_forum_id($topic_id);
		
		if (true === apply_filters('bbpnns_skip_topic_notification', false, $forum_id, $topic_id))
			return -3;
		
		$opt_recipients = apply_filters('bbpress_notify_recipients_hidden_forum', get_option('bbpress_notify_newtopic_recipients'), $forum_id);
		
		$recipients = array();
		foreach ((array)$opt_recipients as $opt_recipient)
		{
			if (! $opt_recipient) continue;
				
			$users = get_users(array('role' => $opt_recipient));
			foreach ((array)$users as $user)
			{
				$user = get_object_vars($user);
				$recipients[$user['ID']] = $user['ID']; // make sure unique recepients
			}
		}
		
		if ( empty($recipients) ) 
			return -2;
		
		list($email_subject, $email_body) = $this->_build_email('topic', $topic_id);
	
		return $this->send_notification($recipients, $email_subject, $email_body);
	}
	
	
	/**
	 * @since 1.5
	 * @desc Forces admin-only recipients if forum is hidden
	 * @param array $recipients
	 * @param number $topic_id
	 * @return array
	 */
	public function munge_newtopic_recipients($recipients=array(), $forum_id = 0)
	{
		if (true === (bool) bbp_is_forum_hidden($forum_id) &&
		    true === (bool) get_option('bbpress_notify_hidden_forum_topic_override', true))
		{
			$recipients = 'administrator';
		}
	
		return $recipients;
	}
	
	
	/**
	 * @since 1.0
	 */
	function notify_new_reply($reply_id = 0, $topic_id = 0, $forum_id = 0)
	{
		global $wpdb;
	
		if (get_post_status($reply_id) == 'spam') 
			return -1;
		
		if (true === apply_filters('bbpnns_skip_reply_notification', false, $forum_id, $topic_id, $reply_id))
			return -3;
	
		$opt_recipients = apply_filters('bbpress_notify_recipients_hidden_forum', get_option('bbpress_notify_newreply_recipients'), $forum_id);
		
		$recipients = array();
		foreach ((array)$opt_recipients as $opt_recipient)
		{
			if (! $opt_recipient) continue;
				
			$users = get_users(array('role' => $opt_recipient));
			foreach ((array)$users as $user)
			{
				$user = get_object_vars($user);
				$recipients[$user['ID']] = $user['ID']; // make sure unique recepients
			}
		}
	
		if ( empty($recipients) ) 
			return -2;
	
		list($email_subject, $email_body) = $this->_build_email('reply', $reply_id);
	
		return $this->send_notification($recipients, $email_subject, $email_body);
	}
	
	
	/**
	 * @since 1.4
	 */
	private function _build_email($type, $post_id)
	{
		$email_subject = get_option("bbpress_notify_new{$type}_email_subject");
		$email_body    = get_option("bbpress_notify_new{$type}_email_body");
		
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$excerpt_size = apply_filters('bpnns_excerpt_size', 100);
		
		// Replace shortcodes
		if ('topic' === $type)
		{
			$topic_content = bbp_get_topic_content($post_id);
			$topic_title   = html_entity_decode(strip_tags(bbp_get_topic_title($post_id)), ENT_NOQUOTES, 'UTF-8');
			$topic_excerpt = html_entity_decode(strip_tags(bbp_get_topic_excerpt($post_id, $excerpt_size)), ENT_NOQUOTES, 'UTF-8');
			$topic_author  = bbp_get_topic_author($post_id);
			$topic_url     = bbp_get_topic_permalink($post_id);
		}
		elseif ('reply' === $type)
		{
			$topic_content = bbp_get_reply_content($post_id);
			$topic_title   = html_entity_decode(strip_tags(bbp_get_reply_title($post_id)), ENT_NOQUOTES, 'UTF-8');
			$topic_excerpt = html_entity_decode(strip_tags(bbp_get_reply_excerpt($post_id, $excerpt_size)), ENT_NOQUOTES, 'UTF-8');
			$topic_author  = bbp_get_reply_author($post_id);
			$topic_url     = bbp_get_reply_permalink($post_id);
		}
		else 
		{
			wp_die('Invalid type!');
		}
		
		$topic_content = preg_replace('/<br\s*\/?>/is', "\n", $topic_content);
		$topic_content = preg_replace('/(?:<\/p>\s*<p>)/ism', "\n\n", $topic_content);
		$topic_content = html_entity_decode(strip_tags($topic_content), ENT_NOQUOTES, 'UTF-8');
		
		$topic_reply = bbp_get_reply_url($post_id);
		
		$email_subject = str_replace('[blogname]', $blogname, $email_subject);
		$email_subject = str_replace("[$type-title]", $topic_title, $email_subject);
		$email_subject = str_replace("[$type-content]", $topic_content, $email_subject);
		$email_subject = str_replace("[$type-excerpt]", $topic_excerpt, $email_subject);
		$email_subject = str_replace("[$type-author]", $topic_author, $email_subject);
		$email_subject = str_replace("[$type-url]", $topic_url, $email_subject);
		$email_subject = str_replace("[$type-replyurl]", $topic_reply, $email_subject);
		
		$email_body = str_replace('[blogname]', $blogname, $email_body);
		$email_body = str_replace("[$type-title]", $topic_title, $email_body);
		$email_body = str_replace("[$type-content]", $topic_content, $email_body);
		$email_body = str_replace("[$type-excerpt]", $topic_excerpt, $email_body);
		$email_body = str_replace("[$type-author]", $topic_author, $email_body);
		$email_body = str_replace("[$type-url]", $topic_url, $email_body);
		$email_body = str_replace("[$type-replyurl]", $topic_reply, $email_body);
		
		return array($email_subject, $email_body);
	}
	
	/**
	 * @since 1.0
	 */
	function send_notification($recipients, $subject, $body)
	{
		$headers = sprintf("From: %s <%s>\r\n", get_option('blogname'), get_bloginfo('admin_email'));
		$headers = apply_filters('bbpnns_extra_headers', $headers, $recipients, $subject, $body);
		
		
		foreach ( (array) $recipients as $recipient_id)
		{
			$user_info = get_userdata($recipient_id);
			
			$email = ($recipient_id == -1) ? get_bloginfo('admin_email') : (string) $user_info->user_email ; 

			if (false === apply_filters('bbpnns_dry_run', false))
			{
				if ( ! wp_mail($email, $subject, $body, $headers) )
				{
					error_log('wp_mail failed: ' . print_r(error_get_last(),1));
					return false;
				}
			}
		}
		
		if (true === apply_filters('bbpnns_dry_run', false))
			return array($recipients, $body);
		
		return true;
	}
	
	/**
	 * @since 1.0
	 */
	/* Add the settings to the bbPress page in the Dashboard */
	function admin_settings() {
		// Add section to bbPress options
		add_settings_section($this->settings_section, __('E-mail Notifications', 'bbpress_notify'), array(&$this,'_settings_intro_text'), 'bbpress');
	
		// Add background option
		add_settings_field('bbpress_notify_newtopic_background', __('Background Topic Notifications', 'bbpress_notify'), array(&$this,'_topic_background_inputfield'), 'bbpress', 'bbpress_notify_options');
		
		// Add default notification option
		add_settings_field("bbpress_notify_default_{$this->bbpress_topic_post_type}_notification", __('Admin UI Topic Notifications', 'bbpress_notify'), array(&$this,'_admin_ui_topic_inputfield'), 'bbpress', 'bbpress_notify_options');
		
		// Add form fields for all settings
		add_settings_field('bbpress_notify_newtopic_recipients', __('Notifications about new topics are sent to', 'bbpress_notify'), array(&$this,'_topic_recipients_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_hidden_forum_topic_override', __('Force Admin-only emails if Forum is hidden (topics)', 'bbpress_notify'), array(&$this,'_hidden_forum_topic_override'), 'bbpress', 'bbpress_notify_options');
		
		add_settings_field('bbpress_notify_newtopic_email_subject', __('E-mail subject', 'bbpress_notify'), array(&$this,'_email_newtopic_subject_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newtopic_email_body', __('E-mail body (template tags: [blogname], [topic-title], [topic-content], [topic-excerpt], [topic-author], [topic-url], [topic-replyurl])', 'bbpress_notify'), array(&$this,'_email_newtopic_body_inputfield'), 'bbpress', 'bbpress_notify_options');
		
		add_settings_field('bbpress_notify_newreply_background', __('Background Reply Notifications', 'bbpress_notify'), array(&$this,'_reply_background_inputfield'), 'bbpress', 'bbpress_notify_options');
		
		// Add default notification option
		add_settings_field("bbpress_notify_default_{$this->bbpress_reply_post_type}_notification", __('Admin UI Reply Notifications', 'bbpress_notify'), array(&$this,'_admin_ui_reply_inputfield'), 'bbpress', 'bbpress_notify_options');
		
		add_settings_field('bbpress_notify_newreply_recipients', __('Notifications about replies are sent to', 'bbpress_notify'), array(&$this,'_reply_recipients_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_hidden_forum_reply_override', __('Force Admin-only emails if Forum is hidden (replies)', 'bbpress_notify'), array(&$this,'_hidden_forum_reply_override'), 'bbpress', 'bbpress_notify_options');
		
		
		add_settings_field('bbpress_notify_newreply_email_subject', __('E-mail subject', 'bbpress_notify'), array(&$this,'_email_newreply_subject_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newreply_email_body', __('E-mail body (template tags: [blogname], [reply-title], [reply-content], [reply-excerpt], [reply-author], [reply-url], [reply-replyurl])', 'bbpress_notify'), array(&$this,'_email_newreply_body_inputfield'), 'bbpress', 'bbpress_notify_options');
	
		// Register the settings as part of the bbPress settings
		register_setting('bbpress', 'bbpress_notify_newtopic_recipients');
		register_setting('bbpress', 'bbpress_notify_hidden_forum_topic_override');
		register_setting('bbpress', 'bbpress_notify_hidden_forum_reply_override');
		register_setting('bbpress', 'bbpress_notify_newtopic_email_subject');
		register_setting('bbpress', 'bbpress_notify_newtopic_email_body');
		register_setting('bbpress', 'bbpress_notify_newtopic_background');
	
		register_setting('bbpress', 'bbpress_notify_newreply_recipients');
		register_setting('bbpress', 'bbpress_notify_newreply_email_subject');
		register_setting('bbpress', 'bbpress_notify_newreply_email_body');
		register_setting('bbpress', 'bbpress_notify_newreply_background');
		
		register_setting('bbpress', "bbpress_notify_default_{$this->bbpress_topic_post_type}_notification");
		register_setting('bbpress', "bbpress_notify_default_{$this->bbpress_reply_post_type}_notification");
	}
	
	/**
	 * @since 1.0
	 */
	function _settings_intro_text($args)
	{
		printf('<span id="%s">%s</span>', $args['id'], __('Configure e-mail notifications when new topics and/or replies are posted.', 'bbpress_notify'));
	}
	
	/**
	 * @since 1.3
	 */
	function _topic_background_inputfield()
	{
		$saved_option = get_option('bbpress_notify_newtopic_background');
		$html_checked = ( $saved_option ) ? 'checked="checked"' : '';
		$description = __('Send emails in the background the next time the site is visited', 'bbpress_notify');
		printf('<input type="checkbox" %s name="bbpress_notify_newtopic_background" value="1"/> %s<br>', $html_checked, $description);
	}
	
	/**
	 * @since 1.3
	 */
	function _reply_background_inputfield()
	{
		$saved_option = get_option('bbpress_notify_newreply_background');
		$html_checked = ( $saved_option ) ? 'checked="checked"' : '';
		$description = __('Send emails in the background the next time the site is visited', 'bbpress_notify');
		printf('<input type="checkbox" %s name="bbpress_notify_newreply_background" value="1"/> %s<br>', $html_checked, $description);
	}
	
	/**
	 * @since 1.0
	 */
	/* Show a <select> combobox with recipient options for new topic notifications */
	function _topic_recipients_inputfield()
	{
		global $wp_roles;
		
		$options = $wp_roles->get_names();
		$saved_option = get_option('bbpress_notify_newtopic_recipients');
		foreach ($options as $value => $description)
		{
			$html_checked = '';
			if (in_array($value, (array)$saved_option)) { $html_checked = 'checked="checked"'; }
			printf('<input type="checkbox" %s name="bbpress_notify_newtopic_recipients[]" value="%s"/> %s<br>', $html_checked, $value, $description);
		}
	}
	
	/**
	 * @since 1.5
	 */
	function _hidden_forum_topic_override()
	{
		$saved_option = get_option('bbpress_notify_hidden_forum_topic_override');
		
		$checked = true === (bool) $saved_option ? 'checked="checked"' : '';
		printf('<input type="checkbox" %s name="bbpress_notify_hidden_forum_topic_override" value="1"/><br>', $checked);
	}
	
	/**
	 * @since 1.0
	 */
	/* Show a <select> combobox with recipient options for new reply notifications */
	function _reply_recipients_inputfield()
	{
		global $wp_roles;

		$options = $wp_roles->get_names();
		$saved_option = get_option('bbpress_notify_newreply_recipients');
		foreach ($options as $value => $description)
		{
			$html_checked = '';
			if (in_array($value, (array)$saved_option)) { $html_checked = 'checked="checked"'; }
			printf('<input type="checkbox" %s name="bbpress_notify_newreply_recipients[]" value="%s"/> %s<br>', $html_checked, $value, $description);
		}
	}
	
	
	/**
	 * @since 1.5
	 */
	function _hidden_forum_reply_override()
	{
		$saved_option = get_option('bbpress_notify_hidden_forum_reply_override');
	
		$checked = true === (bool) $saved_option ? 'checked="checked"' : '';
		printf('<input type="checkbox" %s name="bbpress_notify_hidden_forum_reply_override" value="1"/><br>', $checked);
	}
	
	
	/**
	 * @since 1.0
	 */
	/* Show a <input> field for new topic e-mail subject */
	function _email_newtopic_subject_inputfield()
	{
		printf('<input type="text" id="bbpress_notify_newtopic_email_subject" name="bbpress_notify_newtopic_email_subject" value="%s" />', get_option('bbpress_notify_newtopic_email_subject'));
	}
	
	/**
	 * @since 1.0
	 */
	/* Show a <textarea> input for new topic e-mail body */
	function _email_newtopic_body_inputfield()
	{
		printf('<textarea id="bbpress_notify_newtopic_email_body" name="bbpress_notify_newtopic_email_body" cols="50" rows="5">%s</textarea>', get_option('bbpress_notify_newtopic_email_body'));
		printf('<p>%s: [blogname], [topic-title], [topic-content], [topic-excerpt], [topic-url], [topic-replyurl], [topic-author]</p>', __('Shortcodes', 'bbpress_notify'));
	}
	
	/**
	 * @since 1.0
	 */
	/* Show a <input> field for new reply e-mail subject */
	function _email_newreply_subject_inputfield()
	{
		printf('<input type="text" id="bbpress_notify_newreply_email_subject" name="bbpress_notify_newreply_email_subject" value="%s" />', get_option('bbpress_notify_newreply_email_subject'));
	}
	
	/**
	 * @since 1.0
	 */
	/* Show a <textarea> input for new reply e-mail body */
	function _email_newreply_body_inputfield()
	{
		printf('<textarea id="bbpress_notify_newreply_email_body" name="bbpress_notify_newreply_email_body" cols="50" rows="5">%s</textarea>', get_option('bbpress_notify_newreply_email_body'));
		printf('<p>%s: [blogname], [reply-title], [reply-content], [reply-excerpt], [reply-url], [reply-replyurl], [reply-author]</p>', __('Shortcodes', 'bbpress_notify'));
	}
	
	/**
	 * @since 1.4
	 */
	function plugin_action_links( $links, $file ) 
	{
		if ( $file === plugin_basename( dirname(__FILE__).'/bbpress-notify-nospam.php' ) )
			$links[] = '<a href="' . admin_url( 'admin.php?page=bbpress#' . $this->settings_section ) . '">'.__( 'Settings' ).'</a>';
		
	
		return $links;
	}
	
	/**
	 * @since 1.4
	 */
	function add_notification_meta_box()
	{
		add_meta_box( 'send_notification', __( 'Notifications', 'bbpress_notify' ),
			array(&$this,'notification_meta_box_content'),  bbp_get_topic_post_type(), 'side','high' );
		
		add_meta_box( 'send_notification', __( 'Notifications', 'bbpress_notify' ),
			array(&$this,'notification_meta_box_content'),  bbp_get_reply_post_type(), 'side','high' );
	} 
	
	/**
	 * @since 1.4
	 */
	function notification_meta_box_content( $post )
	{
		$type = ($post->post_type === $this->bbpress_topic_post_type) ? 'topic' : 'reply';
		
		$default = get_option("bbpress_notify_default_{$type}_notification");
		$checked = checked($default, true, false);
		
		wp_create_nonce("bbpress_send_{$type}_notification_nonce");
		
		wp_nonce_field("bbpress_send_{$type}_notification_nonce", "bbpress_send_{$type}_notification_nonce");
		printf('<input type="checkbox" name="bbpress_notify_send_notification" %s> %s',$checked, __('Send notification.', 'bbpress_notify'));
	} 
	
	/**
	 * @since 1.4
	 */
	function _admin_ui_topic_inputfield()
	{
		$default = get_option("bbpress_notify_default_{$this->bbpress_topic_post_type}_notification");
		$checked = checked($default, true, false);

		printf('<input type="checkbox" value="1" name="%s" %s> ', "bbpress_notify_default_{$this->bbpress_topic_post_type}_notification", $checked);
		_e('Send notifications when creating Topics in the Admin UI (<span class="description">Can be overridden in the New/Update Topic screen</span>).');
	}
	
	/**
	 * @since 1.4
	 */
	function _admin_ui_reply_inputfield()
	{
		$default = get_option("bbpress_notify_default_{$this->bbpress_reply_post_type}_notification");
		$checked = checked($default, true, false);

		printf('<input type="checkbox" value="1" name="%s" %s> ', "bbpress_notify_default_{$this->bbpress_reply_post_type}_notification", $checked);
		_e('Send notifications when creating Replies in the Admin UI (<span class="description">Can be overridden in the New/Update Reply screen</span>).');
	}
	
	
	function notify_on_save($post_id, $post)
	{
		if (empty($_POST)) return;
		
		if ($this->bbpress_topic_post_type !== $post->post_type && $this->bbpress_reply_post_type !== $post->post_type) return;
		
		if (! current_user_can('edit_post', $post_id)) return;
		
		if (wp_is_post_revision( $post_id )) return;
		
		if (! isset($_POST['bbpress_notify_send_notification']) || ! $_POST['bbpress_notify_send_notification']) return;

		$type = ($post->post_type === $this->bbpress_topic_post_type) ? 'topic' : 'reply';
		if (! isset($_POST["bbpress_send_{$type}_notification_nonce"]) ||
			! check_admin_referer( "bbpress_send_{$type}_notification_nonce", "bbpress_send_{$type}_notification_nonce" ) )
		{
			return;
		}
		
		
		// Still here, so we can notify
		if ($post->post_type === $this->bbpress_topic_post_type)
		{
			$this->notify_new_topic($post_id);
		}
		else 
		{
			$this->notify_new_reply($post_id);
		}
			
	}
	
	/**
	 * @since 1.5.4
	 */
	public static function missing_bbpress_notice()
	{
		?>
		<div class="error">
			<p>
				<?php _e('<strong>bbPress Notify (No-Spam)</strong> could not find an active bbPress plugin. It will not load until bbPress is installed and active.'); ?>
			</p>
		</div>
		<?php 
	}
}


/* Kick off the class */
bbPress_Notify_NoSpam::bootstrap();


/* End of file bbpress-notify-nospam.php */
/* Location: bbpress-notify-nospam/bbpress-notify-nospam.php */