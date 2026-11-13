<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class Schema {
	public static function install(): void {
		global $wpdb;
		if (get_site_option('forums_schema_version') === VERSION) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$prefix = $wpdb->base_prefix;
		$collate = $wpdb->get_charset_collate();
		$schemas = array(
			'forums' => "CREATE TABLE {$prefix}forums (
forum_ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
forum_blog_ID bigint(20) NOT NULL,
forum_name text NOT NULL,
forum_nicename text,
forum_description text,
forum_topics bigint(20) NOT NULL DEFAULT '0',
forum_posts bigint(20) NOT NULL DEFAULT '0',
forum_color_one varchar(255),
forum_color_two varchar(255),
forum_color_header varchar(255),
forum_color_border varchar(255),
forum_border_size varchar(255),
PRIMARY KEY  (forum_ID),
KEY forum_blog_ID (forum_blog_ID)
) ENGINE=InnoDB {$collate};",
			'forums_topics' => "CREATE TABLE {$prefix}forums_topics (
topic_ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
topic_forum_ID bigint(20) NOT NULL,
topic_title text NOT NULL,
topic_author bigint(20) NOT NULL,
topic_last_author bigint(20) NOT NULL,
topic_stamp bigint(30) NOT NULL,
topic_last_updated_stamp bigint(30) NOT NULL,
topic_closed tinyint(1) NOT NULL DEFAULT '0',
topic_sticky tinyint(1) NOT NULL DEFAULT '0',
topic_posts bigint(20) NOT NULL DEFAULT '0',
PRIMARY KEY  (topic_ID),
KEY forum_updated (topic_forum_ID,topic_last_updated_stamp)
) ENGINE=InnoDB {$collate};",
			'forums_posts' => "CREATE TABLE {$prefix}forums_posts (
post_ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
post_forum_ID bigint(20) NOT NULL,
post_topic_ID bigint(20) NOT NULL,
post_author bigint(20) NOT NULL,
post_content text,
post_stamp bigint(30) NOT NULL,
post_request_key varchar(64) DEFAULT NULL,
PRIMARY KEY  (post_ID),
UNIQUE KEY request_key (post_request_key),
KEY topic_forum (post_topic_ID,post_forum_ID),
KEY post_forum_ID (post_forum_ID)
) ENGINE=InnoDB {$collate};",
		);
		foreach ($schemas as $table => $schema) {
			dbDelta($schema);
			if ($wpdb->last_error !== '' || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix . $table))) !== $prefix . $table) {
				wp_die(esc_html__('Die Forum-Datenbank konnte nicht eingerichtet werden.', 'psforum'), '', array('response' => 500));
			}
		}
		$request_index = $wpdb->get_results("SHOW INDEX FROM {$prefix}forums_posts WHERE Key_name = 'request_key'", ARRAY_A);
		if ($wpdb->last_error !== '' || count($request_index) !== 1 || (int) $request_index[0]['Non_unique'] !== 0 || $request_index[0]['Column_name'] !== 'post_request_key') {
			wp_die(esc_html__('Der Schutz vor doppelten Beiträgen konnte nicht eingerichtet werden.', 'psforum'), '', array('response' => 500));
		}
		update_site_option('forums_schema_version', VERSION);
		update_site_option('forums_version', VERSION);
		update_site_option('forums_installed', 'yes');
	}
}
