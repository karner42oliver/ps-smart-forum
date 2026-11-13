<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

function install_schema(): void {
	Schema::install();
}

function request_value(array $source, string $key, string $default = ''): string {
	if (!isset($source[$key])) {
		return $default;
	}
	if (!is_string($source[$key])) {
		wp_die(esc_html__('Ungültige Anfrage.', 'psforum'), '', array('response' => 400));
	}
	return wp_unslash($source[$key]);
}

function require_nonce(string $action): void {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		wp_die(esc_html__('Diese Aktion erfordert eine POST-Anfrage.', 'psforum'), '', array('response' => 405));
	}
	if (!wp_verify_nonce(request_value($_POST, '_psforum_nonce'), $action)) {
		wp_die(esc_html__('Die Sicherheitsprüfung ist fehlgeschlagen. Bitte lade die Seite neu.', 'psforum'), '', array('response' => 403));
	}
}

function validate_settings_request(): void {
	if (!current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options')) {
		wp_die(esc_html__('Zugriff verweigert.', 'psforum'), '', array('response' => 403));
	}
	require_nonce('psforum_update_settings');
	foreach (array('forums_topics_per_page', 'forums_posts_per_page', 'forums_max_forums', 'forums_upgrades_forums') as $key) {
		if ($key === 'forums_upgrades_forums' && !function_exists('is_pro_site')) {
			continue;
		}
		$value = request_value($_POST, $key);
		if (filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
			wp_die(esc_html__('Bitte gib positive ganze Zahlen ein.', 'psforum'), '', array('response' => 400));
		}
	}
	if (function_exists('is_pro_site') && !in_array(request_value($_POST, 'forums_enable_upgrades'), array('0', '1'), true)) {
		wp_die(esc_html__('Ungültige Upgrade-Einstellung.', 'psforum'), '', array('response' => 400));
	}
	if (!in_array(request_value($_POST, 'forums_title_edit_policy', '30_minutes'), array('moderators', '30_minutes', 'authors'), true)) {
		wp_die(esc_html__('Ungültige Forum-Einstellung.', 'psforum'), '', array('response' => 400));
	}
}

function save_setting(string $name, int|string $value): void {
	if (is_multisite()) {
		update_site_option($name, $value);
		$stored = get_site_option($name);
	} else {
		update_option($name, $value);
		$stored = get_option($name);
	}
	if ((string) $stored !== (string) $value) {
		wp_die(esc_html__('Die Einstellung konnte nicht gespeichert werden.', 'psforum'), '', array('response' => 500));
	}
}

function validate_admin_forum_request(string $action): array {
	require_moderator();
	require_nonce('psforum_' . $action);
	if ($action !== 'new_forum_process') {
		require_forum(absint(request_value($_POST, 'fid')));
	}
	return $action === 'delete_forum_process' ? array() : forum_form_values();
}

function process_admin_request(): void {
	Plugin::instance()->admin()->process_request();
}

function nonce_field(string $action): string {
	return str_replace(' id="_psforum_nonce"', '', wp_nonce_field($action, '_psforum_nonce', false, false));
}

function process_front_request(): void {
	Plugin::instance()->front()->process_request();
}

function forum_belongs_to_site(int $forum_id): bool {
	return Plugin::instance()->repository()->forum($forum_id) !== null;
}

function require_forum(int $forum_id, int $topic_id = 0, int $post_id = 0): void {
	$repository = Plugin::instance()->repository();
	if (!$forum_id || !forum_belongs_to_site($forum_id)) {
		wp_die(esc_html__('Ungültiges Forum.', 'psforum'), '', array('response' => 404));
	}
	if ($topic_id && !$repository->topic($topic_id, $forum_id)) {
		wp_die(esc_html__('Ungültiges Thema.', 'psforum'), '', array('response' => 404));
	}
	if ($post_id && (!$topic_id || !$repository->post($post_id, $topic_id, $forum_id))) {
		wp_die(esc_html__('Ungültiger Beitrag.', 'psforum'), '', array('response' => 404));
	}
}

function require_member(): void {
	if (!is_user_logged_in() || (is_multisite() && !is_user_member_of_blog() && !is_super_admin())) {
		wp_die(esc_html__('Du musst als Mitglied dieser Website angemeldet sein.', 'psforum'), '', array('response' => 403));
	}
}

function require_moderator(): void {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('Zugriff verweigert.', 'psforum'), '', array('response' => 403));
	}
}

function require_post_editor(int $post_id): void {
	require_member();
	if (current_user_can('manage_options')) {
		return;
	}
	$post = Plugin::instance()->repository()->post($post_id);
	if (!$post || (int) $post['post_author'] !== get_current_user_id() || time() - (int) $post['post_stamp'] >= 1800) {
		wp_die(esc_html__('Du darfst diesen Beitrag nicht bearbeiten.', 'psforum'), '', array('response' => 403));
	}
}

function can_edit_topic(array $topic): bool {
	if (!is_user_logged_in() || (is_multisite() && !is_user_member_of_blog() && !is_super_admin())) {
		return false;
	}
	if (current_user_can('manage_options')) {
		return true;
	}
	$policy = Plugin::instance()->settings()->values()['forums_title_edit_policy'];
	return (int) $topic['topic_author'] === get_current_user_id()
		&& ($policy === 'authors' || ($policy === '30_minutes' && time() - (int) $topic['topic_stamp'] < 1800));
}

function require_topic_editor(int $forum_id, int $topic_id): void {
	require_forum($forum_id, $topic_id);
	if (!can_edit_topic(Plugin::instance()->repository()->topic($topic_id, $forum_id))) {
		wp_die(esc_html__('Du darfst diesen Thementitel nicht bearbeiten.', 'psforum'), '', array('response' => 403));
	}
}

function submission_key(int $forum_id, int $topic_id, string $action): string {
	$token = request_value($_POST, '_psforum_request');
	if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
		wp_die(esc_html__('Das Formular ist unvollständig. Bitte lade es neu.', 'psforum'), '', array('response' => 400));
	}
	return hash('sha256', get_current_user_id() . ':' . $forum_id . ':' . $topic_id . ':' . $action . ':' . $token);
}

function forum_url(int $forum_id, array $args = array(), string $fragment = ''): string {
	return add_query_arg(array_merge(array('fid' => $forum_id), $args), get_permalink()) . '#' . ($fragment ?: 'psforum-' . $forum_id);
}

function require_front_action(string $action, int $forum_id, int $topic_id = 0, int $post_id = 0): void {
	$repository = Plugin::instance()->repository();
	if (($action !== 'new_topic_process' && $topic_id < 1)
		|| (in_array($action, array('edit_post_process', 'delete_post_process'), true) && $post_id < 1)) {
		wp_die(esc_html__('Unvollständige Anfrage.', 'psforum'), '', array('response' => 400));
	}
	require_forum($forum_id, $topic_id, $post_id);
	require_member();
	require_nonce('psforum_' . $action . '_' . $forum_id);
	if (in_array($action, array('delete_topic_process', 'delete_post_process', 'open_topic', 'close_topic', 'pin_topic', 'unpin_topic'), true)) {
		require_moderator();
	}
	if ($action === 'delete_post_process' && $repository->post_count($topic_id, $forum_id) < 2) {
		wp_die(esc_html__('Den letzten Beitrag kannst du nur zusammen mit dem Thema löschen.', 'psforum'), '', array('response' => 400));
	}
	if ($action === 'edit_post_process') {
		require_post_editor($post_id);
	}
	if ($action === 'edit_topic_process') {
		require_topic_editor($forum_id, $topic_id);
	}
	if ($action === 'new_post_process' && (int) $repository->topic($topic_id, $forum_id)['topic_closed'] === 1) {
		wp_die(esc_html__('Dieses Thema ist geschlossen.', 'psforum'), '', array('response' => 403));
	}
}

function validate_front_request(int $forum_id): void {
	$action = request_value($_GET, 'action');
	foreach (array('topic', 'tid', 'pid', 'fid', 'forum_page', 'search', 'msg') as $key) {
		request_value($_GET, $key);
	}
	foreach (array('tid', 'pid', 'fid', 'forum_page', 'topic_title', 'post_content', 'search', 'Cancel') as $key) {
		request_value($_POST, $key);
	}
	$writes = array('new_topic_process', 'new_post_process', 'edit_post_process', 'delete_post_process', 'delete_topic_process', 'open_topic', 'close_topic', 'pin_topic', 'unpin_topic', 'edit_topic_process');
	if (in_array($action, $writes, true)) {
		$topic_id = in_array($action, array('open_topic', 'close_topic', 'pin_topic', 'unpin_topic'), true)
			? absint(request_value($_GET, 'topic')) : absint(request_value($_POST, 'tid'));
		$post_id = absint(request_value($_POST, 'pid'));
		if (($action !== 'new_topic_process' && !$topic_id)
			|| (in_array($action, array('edit_post_process', 'delete_post_process'), true) && !$post_id)) {
			wp_die(esc_html__('Unvollständige Anfrage.', 'psforum'), '', array('response' => 400));
		}
		if ($action === 'edit_topic') {
			require_topic_editor($forum_id, absint(request_value($_GET, 'tid')));
		}
		require_front_action($action, $forum_id, $topic_id, $post_id);
		if (request_value($_POST, 'fid') !== '' && absint(request_value($_POST, 'fid')) !== $forum_id) {
			wp_die(esc_html__('Ungültiges Forum.', 'psforum'), '', array('response' => 400));
		}
		if ($action === 'new_topic_process' && !isset($_POST['Cancel'])) {
			if (!isset($_POST['topic_title'], $_POST['post_content'])) {
				wp_die(esc_html__('Titel und Beitrag sind erforderlich.', 'psforum'), '', array('response' => 400));
			}
		}
		if (in_array($action, array('new_post_process', 'edit_post_process'), true) && !isset($_POST['Cancel']) && !isset($_POST['post_content'])) {
			wp_die(esc_html__('Der Beitrag fehlt.', 'psforum'), '', array('response' => 400));
		}
	}
	if (in_array($action, array('edit_post', 'delete_post', 'delete_topic'), true)) {
		$topic_id = absint(request_value($_GET, 'tid'));
		$post_id = absint(request_value($_GET, 'pid'));
		if (!$topic_id || ($action !== 'delete_topic' && !$post_id)) {
			wp_die(esc_html__('Unvollständige Anfrage.', 'psforum'), '', array('response' => 400));
		}
		require_forum($forum_id, $topic_id, $post_id);
		if ($action === 'edit_post') {
			require_post_editor($post_id);
		} else {
			require_moderator();
		}
	}
}

function check_database_result(int|bool $result): void {
	if ($result === false) {
		wp_die(esc_html__('Die Änderung konnte nicht gespeichert werden. Bitte versuche es erneut.', 'psforum'), '', array('response' => 500));
	}
}

function redirect_to(string $url): void {
	$url = wp_validate_redirect($url, home_url('/'));
	if (!headers_sent()) {
		wp_safe_redirect($url);
	} else {
		echo '<script>location.href=' . wp_json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
		echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Weiter', 'psforum') . '</a></p>';
	}
	exit;
}

function forum_form_values(): array {
	$values = array(
		'forum_name' => sanitize_text_field(request_value($_POST, 'forum_name')),
		'forum_description' => sanitize_textarea_field(request_value($_POST, 'forum_description')),
	);
	foreach (array('forum_color_one', 'forum_color_two', 'forum_color_header', 'forum_color_border') as $key) {
		$raw = request_value($_POST, $key);
		$color = sanitize_hex_color($raw);
		if ($raw !== '' && !$color) {
			wp_die(esc_html__('Bitte gib eine gültige Hex-Farbe ein.', 'psforum'), '', array('response' => 400));
		}
		$values[$key] = $color ?: '';
	}
	$border = request_value($_POST, 'forum_border_size', '0');
	if (!in_array($border, array('0', '1', '2', '3', '4', '5'), true)) {
		wp_die(esc_html__('Ungültige Rahmenbreite.', 'psforum'), '', array('response' => 400));
	}
	$values['forum_border_size'] = (int) $border;
	return $values;
}

function forum_appearance(int $forum_id): array {
	return Plugin::instance()->view()->appearance($forum_id);
}

function forums_save_post_content($post_content) {
	return wp_kses($post_content, array(
		'p' => array(), 'br' => array(), 'ul' => array(), 'ol' => array(),
		'li' => array(), 'strong' => array(), 'em' => array(), 'blockquote' => array(),
		'code' => array(), 'pre' => array(),
		'a' => array('href' => true, 'title' => true),
		'img' => array('src' => true, 'alt' => true, 'width' => true, 'height' => true),
	), array('http', 'https', 'mailto'));
}

function forums_display_post_content($post_content) {
	return wpautop(make_clickable(forums_save_post_content((string) $post_content)));
}

function forums_author_display_name($author_ID) {
	$user = get_userdata($author_ID);
	return $user ? $user->display_name : __('Gelöschtes Benutzerkonto', 'psforum');
}

function forums_roundup($value, $dp) {
	return ceil($value * pow(10, $dp)) / pow(10, $dp);
}