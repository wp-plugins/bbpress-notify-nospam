<?php
/*
* Plugin Name: bbPress Notify (No-Spam)
* Description: Sends email notifications upon topic/reply creation, as long as it's not flagged as spam.
* Version: 1.2.2
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

	function __construct(){

		/* Register hooks, filters and actions */
		
		// Add settings to the Dashboard
		add_action('admin_init', array(&$this,'admin_settings'));
		
		// Triggers the notifications on new topics/replies
		add_action('bbp_new_topic', array(&$this,'notify_new_topic'), 100);
		add_action('bbp_new_reply', array(&$this,'notify_new_reply'), 100);
		
		// On plugin activation, check whether bbPress is active
		register_activation_hook(__FILE__, array(&$this,'on_activation'));
		
		// Deactivate original bbPress Notify if found
		add_action('admin_init', array(&$this,'deactivate_old'));
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
	}
	
	
	function notify_new_topic($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0)
	{
		global $wpdb;
		
		if (get_post_status($topic_id) == 'spam') return;
		
		$opt_recipients = get_option('bbpress_notify_newtopic_recipients');
		$recipients = array();
		foreach ((array)$opt_recipients as $opt_recipient)
		{
			if (! $opt_recipient) continue;
				
			$users = get_users(array('role' => $opt_recipient));
			foreach ((array)$users as $user)
			{
				$user = get_object_vars($user);
				$recipients[] = $user['ID'];
			}
		}
		
		if ( empty($recipients) ) return;
		
		$email_subject = get_option('bbpress_notify_newtopic_email_subject');
		$email_body = get_option('bbpress_notify_newtopic_email_body');
	
		// Replace shortcodes
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$topic_title = html_entity_decode(strip_tags(bbp_get_topic_title($topic_id)), ENT_NOQUOTES, 'UTF-8');
		$topic_content = html_entity_decode(strip_tags(bbp_get_topic_content($topic_id)), ENT_NOQUOTES, 'UTF-8');
		$topic_excerpt = html_entity_decode(strip_tags(bbp_get_topic_excerpt($topic_id, 100)), ENT_NOQUOTES, 'UTF-8');
		$topic_author = bbp_get_topic_author($topic_id);
		$topic_url = bbp_get_topic_permalink($topic_id);
		$topic_reply = bbp_get_reply_url($topic_id);
	
		$email_subject = str_replace('[blogname]', $blogname, $email_subject);
		$email_subject = str_replace('[topic-title]', $topic_title, $email_subject);
		$email_subject = str_replace('[topic-content]', $topic_content, $email_subject);
		$email_subject = str_replace('[topic-excerpt]', $topic_excerpt, $email_subject);
		$email_subject = str_replace('[topic-author]', $topic_author, $email_subject);
		$email_subject = str_replace('[topic-url]', $topic_url, $email_subject);
		$email_subject = str_replace('[topic-replyurl]', $topic_reply, $email_subject);
	
		$email_body = str_replace('[blogname]', $blogname, $email_body);
		$email_body = str_replace('[topic-title]', $topic_title, $email_body);
		$email_body = str_replace('[topic-content]', $topic_content, $email_body);
		$email_body = str_replace('[topic-excerpt]', $topic_excerpt, $email_body);
		$email_body = str_replace('[topic-author]', $topic_author, $email_body);
		$email_body = str_replace('[topic-url]', $topic_url, $email_body);
		$email_body = str_replace('[topic-replyurl]', $topic_reply, $email_body);
	
		$this->send_notification($recipients, $email_subject, $email_body);
	}
	
	
	function notify_new_reply($topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0)
	{
		global $wpdb;
		
		if (get_post_status($topic_id) == 'spam') return;
		
		$opt_recipients = get_option('bbpress_notify_newreply_recipients');
		$recipients = array();
		foreach ((array)$opt_recipients as $opt_recipient)
		{
			if (! $opt_recipient) continue;
			
			$users = get_users(array('role' => $opt_recipient));
			foreach ((array)$users as $user)
			{
				$user = get_object_vars($user);
				$recipients[] = $user['ID'];
			}
		}
		
		if ( empty($recipients) ) return;
	
		$email_subject = get_option('bbpress_notify_newreply_email_subject');
		$email_body = get_option('bbpress_notify_newreply_email_body');
	
		// Replace shortcodes
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$topic_title = html_entity_decode(strip_tags(bbp_get_topic_title($topic_id)), ENT_NOQUOTES, 'UTF-8');
		$topic_content = html_entity_decode(strip_tags(bbp_get_topic_content($topic_id)), ENT_NOQUOTES, 'UTF-8');
		$topic_excerpt = html_entity_decode(strip_tags(bbp_get_topic_excerpt($topic_id, 100)), ENT_NOQUOTES, 'UTF-8');
		$topic_author = bbp_get_topic_author($topic_id);
		$topic_url = bbp_get_topic_permalink($topic_id);
		$topic_reply = bbp_get_reply_url($topic_id);
	
		$email_subject = str_replace('[blogname]', $blogname, $email_subject);
		$email_subject = str_replace('[reply-title]', $topic_title, $email_subject);
		$email_subject = str_replace('[reply-content]', $topic_content, $email_subject);
		$email_subject = str_replace('[reply-excerpt]', $topic_excerpt, $email_subject);
		$email_subject = str_replace('[reply-author]', $topic_author, $email_subject);
		$email_subject = str_replace('[reply-url]', $topic_url, $email_subject);
		$email_subject = str_replace('[reply-replyurl]', $topic_reply, $email_subject);
	
		$email_body = str_replace('[blogname]', $blogname, $email_body);
		$email_body = str_replace('[reply-title]', $topic_title, $email_body);
		$email_body = str_replace('[reply-content]', $topic_content, $email_body);
		$email_body = str_replace('[reply-excerpt]', $topic_excerpt, $email_body);
		$email_body = str_replace('[reply-author]', $topic_author, $email_body);
		$email_body = str_replace('[reply-url]', $topic_url, $email_body);
		$email_body = str_replace('[reply-replyurl]', $topic_reply, $email_body);
	
		$this->send_notification($recipients, $email_subject, $email_body);
	}
	
	
	function send_notification($recipients, $subject, $body)
	{
		$headers = sprintf("From: %s <%s>\r\n", get_option('blogname'), get_bloginfo('admin_email'));
		foreach ((array)$recipients as $recipient_id)
		{
			$user_info = get_userdata($recipient_id);
			if ($recipient_id == -1) { $email = get_bloginfo('admin_email'); } else { $email = (string)$user_info->user_email; }
			@wp_mail($email, $subject, $body, $headers);
		}
	}
	
	
	/* Add the settings to the bbPress page in the Dashboard */
	function admin_settings() {
		// Add section to bbPress options
		add_settings_section('bbpress_notify_options', __('E-mail Notifications', 'bbpress_notify'), array(&$this,'_settings_intro_text'), 'bbpress');
	
		// Add form fields for all settings
		add_settings_field('bbpress_notify_newtopic_recipients', __('Notifications about new topics are sent to', 'bbpress_notify'), array(&$this,'_topic_recipients_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newtopic_email_subject', __('E-mail subject', 'bbpress_notify'), array(&$this,'_email_newtopic_subject_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newtopic_email_body', __('E-mail body (template tags: [blogname], [topic-title], [topic-content], [topic-excerpt], [topic-author], [topic-url], [topic-replyurl])', 'bbpress_notify'), array(&$this,'_email_newtopic_body_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newreply_recipients', __('Notifications about replies are sent to', 'bbpress_notify'), array(&$this,'_reply_recipients_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newreply_email_subject', __('E-mail subject', 'bbpress_notify'), array(&$this,'_email_newreply_subject_inputfield'), 'bbpress', 'bbpress_notify_options');
		add_settings_field('bbpress_notify_newreply_email_body', __('E-mail body (template tags: [blogname], [reply-title], [reply-content], [reply-excerpt], [reply-author], [reply-url], [reply-replyurl])', 'bbpress_notify'), array(&$this,'_email_newreply_body_inputfield'), 'bbpress', 'bbpress_notify_options');
	
		// Register the settings as part of the bbPress settings
		register_setting('bbpress', 'bbpress_notify_newtopic_recipients');
		register_setting('bbpress', 'bbpress_notify_newtopic_email_subject');
		register_setting('bbpress', 'bbpress_notify_newtopic_email_body');
	
		register_setting('bbpress', 'bbpress_notify_newreply_recipients');
		register_setting('bbpress', 'bbpress_notify_newreply_email_subject');
		register_setting('bbpress', 'bbpress_notify_newreply_email_body');
	
	}
	
	
	function _settings_intro_text()
	{
		_e('Configure e-mail notifications when new topics and/or replies are posted.', 'bbpress_notify');
	}
	
	
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
	
	
	/* Show a <input> field for new topic e-mail subject */
	function _email_newtopic_subject_inputfield()
	{
		printf('<input type="text" id="bbpress_notify_newtopic_email_subject" name="bbpress_notify_newtopic_email_subject" value="%s" />', get_option('bbpress_notify_newtopic_email_subject'));
	}
	
	
	/* Show a <textarea> input for new topic e-mail body */
	function _email_newtopic_body_inputfield()
	{
		printf('<textarea id="bbpress_notify_newtopic_email_body" name="bbpress_notify_newtopic_email_body" cols="50" rows="5">%s</textarea>', get_option('bbpress_notify_newtopic_email_body'));
		printf('<p>%s: [blogname], [topic-title], [topic-content], [topic-excerpt], [topic-url], [topic-replyurl], [topic-author]</p>', __('Shortcodes', 'bbpress_notify'));
	}
	
	/* Show a <input> field for new reply e-mail subject */
	function _email_newreply_subject_inputfield()
	{
		printf('<input type="text" id="bbpress_notify_newreply_email_subject" name="bbpress_notify_newreply_email_subject" value="%s" />', get_option('bbpress_notify_newreply_email_subject'));
	}
	
	
	/* Show a <textarea> input for new reply e-mail body */
	function _email_newreply_body_inputfield()
	{
		printf('<textarea id="bbpress_notify_newreply_email_body" name="bbpress_notify_newreply_email_body" cols="50" rows="5">%s</textarea>', get_option('bbpress_notify_newreply_email_body'));
		printf('<p>%s: [blogname], [reply-title], [reply-content], [reply-excerpt], [reply-url], [reply-replyurl], [reply-author]</p>', __('Shortcodes', 'bbpress_notify'));
	}
}



/* Kick off the class */
new bbPress_Notify_NoSpam();

/* End of file bbpress-notify-nospam.php */
/* Location: bbpress-notify-nospam/bbpress-notify-nospam.php */