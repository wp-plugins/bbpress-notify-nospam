<?php
/**
 * @group bbpnns
 */
require_once(ABSPATH . '/wp-content/plugins/bbpress/bbpress.php');
require_once(ABSPATH . '/wp-content/plugins/bbpress-notify-nospam/bbpress-notify-nospam.php');


class Tests_bbPress_notify_no_spam_notify_new extends WP_UnitTestCase 
{
	public $forum_id;
	public $topic_id;
	public $reply_id;
	
	
	public function setUp()
	{
		parent::setUp();
		
		// Create new forum
		$this->forum_id = bbp_insert_forum(
			array(
				'post_title'  => 'test-forum',
				'post_status' => 'publish'
			)
		);
		
		// Create new topic
		$this->topic_id = bbp_insert_topic(
			array(
				'post_parent' => $this->forum_id,
				'post_title'  => 'test-topic'
			),
			array(
				'forum_id' => $this->forum_id		
			)
		);
		
		// Create new reply
		$this->reply_id = bbp_insert_reply(
			array(
				'post_parent' => $this->topic_id,
				'post_title'  => 'test-reply'		
			),
			array(
				'forum_id' => $this->forum_id,
				'topic_id' => $this->topic_id		
			)
		);
		
		add_filter('bbpnns_dry_run', '__return_true');
		
		// Non-spam, non-empty recipents
		$recipients = array('administrator', 'subscriber');
		update_option('bbpress_notify_newtopic_recipients', $recipients);
		$subs_id = $this->factory->user->create( array( 'role' => 'subscriber' ));
		
		
	}
	
	public function tearDown()
	{
		parent::tearDown();
		
		remove_all_filters('bbpnns_dry_run');
		remove_all_filters('bbpnns_skip_reply_notification');
		remove_all_filters('bbpnns_skip_topic_notification');
	}
	
	
	public function test_topic_recipient_filter()
	{
		$bbpnns = bbPress_Notify_NoSpam::bootstrap();
		
		$this->assertTrue((bool) has_filter('bbpress_notify_recipients_hidden_forum', array(&$bbpnns, 'munge_newtopic_recipients')), 
				'bbpress_notify_recipients_hidden_forum filter exists');
		
		$expected = array('foo', 'bar');
		$recipients = apply_filters('bbpress_notify_recipients_hidden_forum', $expected, $this->forum_id);
		
		$this->assertEquals($expected, $recipients, 'Filter returns input array for non-hidden forum');

		//hide forum
		bbp_hide_forum($this->forum_id);
		
		$recipients = apply_filters('bbpress_notify_recipients_hidden_forum', $expected, $this->forum_id);
		$this->assertEquals('administrator', $recipients, 'Filter returns \'administrator\' for non-hidden forum');
		
	}
	
	
	public function test_notify_topic()
	{
		$bbpnns = bbPress_Notify_NoSpam::bootstrap();
		
		// Spam, returns -1
		bbp_spam_topic($this->topic_id);
		$status = $bbpnns->notify_new_topic($this->topic_id, $this->forum_id);
		$this->assertEquals(-1, $status, 'Spam topic returns -1');
		
		
		// Non-spam, empty recipients returns -2
		bbp_unspam_topic($this->topic_id);
		delete_option('bbpress_notify_newtopic_recipients');
		$status = $bbpnns->notify_new_topic($this->topic_id, $this->forum_id);
		$this->assertEquals(-2, $status, 'Empty Recipients -2');
		
		// Non-spam, non-empty recipents
		$recipients = array('administrator', 'subscriber');
		update_option('bbpress_notify_newtopic_recipients', $recipients);
		$status = $bbpnns->notify_new_topic($this->topic_id, $this->forum_id);
		$this->assertTrue(is_array($status), 'Good notify returns array in test mode');
		
		// Force skip
		add_filter('bbpnns_skip_topic_notification', '__return_true');
		$status = $bbpnns->notify_new_topic($this->topic_id, $this->forum_id);
		$this->assertEquals(-3, $status, 'Force skip -3');
		
	}
	
	
	public function test_notify_reply()
	{
		$bbpnns = bbPress_Notify_NoSpam::bootstrap();
		
		// Spam, returns -1
		bbp_spam_reply($this->reply_id);
		$status = $bbpnns->notify_new_reply($this->reply_id, $this->topic_id, $this->forum_id);
		$this->assertEquals(-1, $status, 'Spam reply returns -1');
		
		
		// Non-spam, empty recipients returns -2
		bbp_unspam_reply($this->reply_id);
		$status = $bbpnns->notify_new_reply($this->reply_id, $this->topic_id, $this->forum_id);
		$this->assertEquals(-2, $status, 'Empty Recipients -2');
		
		// Non-spam, non-empty recipents
		update_option('bbpress_notify_newreply_recipients', array('administrator', 'subscriber'));
		$status = $bbpnns->notify_new_reply($this->reply_id, $this->topic_id, $this->forum_id);
		$this->assertTrue(is_array($status), 'Good notify returns array in test mode');
		
		// Force skip
		add_filter('bbpnns_skip_reply_notification', '__return_true');
		$status = $bbpnns->notify_new_reply($this->topic_id);
		$this->assertEquals(-3, $status, 'Force skip -3');
	}
	
	
	public function test_send_notification()
	{
		$expected_recipients = array('administrator', 'subscriber');
		
		// Non-hidden forum
		update_option('bbpress_notify_newtopic_recipients', $recipients);
		
		$bbpnns = bbPress_Notify_NoSpam::bootstrap();
		$got_recipients = $bbpnns->send_notification($expected_recipients, 'test subject', 'test_body');
		$this->assertEquals($expected_recipients, $got_recipients, 'Test mode got expected recipients');
		
		// Hidden forum returns admins only
		bbp_hide_forum($this->forum_id);
		$recipients = apply_filters('bbpress_notify_recipients_hidden_forum', $expected_recipients, $this->forum_id);
		$got_recipients = $bbpnns->send_notification($recipients, 'test subject', 'test_body');
		$this->assertEquals('administrator', $got_recipients, 'Filtered send_notification returns administrator');
	}
		
}

/* End of 00-notify-new.php */
/* Location: bbpress-notify-no-spam/t/00-notify-new.php */