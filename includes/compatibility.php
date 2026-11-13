<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

// Keep the namespaced API introduced in 1.1 available to existing integrations.
function forums_upgrades_advertise() {
	echo Plugin::instance()->admin()->advertisement();
}

function forums_plug_init() {
	Plugin::instance()->initialize();
}

function forums_admin_styles() {
	wp_enqueue_style('forums_admin_css');
}

function forums_admin_scripts() {
	wp_enqueue_script('forums_admin_js');
}

function forums_make_current() {
	install_schema();
}

function forums_blog_install() {
	install_schema();
}

function forums_global_install() {
	install_schema();
}

function forums_plug_pages() {
	Plugin::instance()->register_forum_page();
}

function forums_options_plug_pages() {
	Plugin::instance()->register_settings_page();
}

function forums_non_supporter_output() {
	global $blog_id, $psts;
	redirect_to($psts->checkout_url($blog_id));
}

function forums_admin_bar($menu) {
	unset($menu['admin.php?page=psforum']);
	return $menu;
}

function forums_delete_forum($tmp_fid) {
	Plugin::instance()->service()->delete_forum((int) $tmp_fid);
}

function forums_output($content) {
	return Plugin::instance()->front()->content($content);
}

function forums_get_navigation($post_id, $topic_id, $topic_closed) {
	return Plugin::instance()->view()->navigation((int) $topic_id);
}

function forums_get_metadata($last_updated, $last_author) {
	return Plugin::instance()->view()->metadata((int) $last_updated, (int) $last_author);
}

function forum_shortcode($atts) {
	return Plugin::instance()->front()->shortcode($atts);
}

function forums_output_search_results($tmp_fid, $tmp_query) {
	$request_forum = request_value($_POST, 'fid', request_value($_GET, 'fid'));
	if ($request_forum !== '' && (int) $request_forum !== (int) $tmp_fid) {
		return Plugin::instance()->view()->index((int) $tmp_fid, max(1, (int) request_value($_GET, 'forum_page', '1')));
	}
	return Plugin::instance()->view()->search((int) $tmp_fid, (string) $tmp_query);
}

function forums_output_search_form($tmp_fid) {
	$query = (int) request_value($_POST, 'fid', request_value($_GET, 'fid')) === (int) $tmp_fid
		? request_value($_POST, 'search', request_value($_GET, 'search')) : '';
	return Plugin::instance()->view()->search_form((int) $tmp_fid, $query);
}

function forums_output_new_post_process($tmp_fid, $tmp_tid) {
	Plugin::instance()->service()->reply((int) $tmp_fid, (int) $tmp_tid, request_value($_POST, 'post_content'));
}

function forums_output_new_post_separate($tmp_fid, $tmp_tid, $tmp_errors, $tmp_error_msg = '') {
	return Plugin::instance()->view()->post_form(
		'reply', (int) $tmp_fid, (int) $tmp_tid, 0, 1, Plugin::instance()->front()->form_values(),
		$tmp_errors ? ($tmp_error_msg ?: __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum')) : ''
	);
}

function forums_output_new_post($tmp_fid, $tmp_tid, $tmp_errors, $tmp_error_msg = '') {
	return Plugin::instance()->view()->post_form(
		'reply', (int) $tmp_fid, (int) $tmp_tid, 0, 1, Plugin::instance()->front()->form_values(),
		$tmp_errors ? ($tmp_error_msg ?: __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum')) : '', true
	);
}

function forums_output_edit_post_process($tmp_fid, $tmp_pid, $tmp_tid) {
	Plugin::instance()->service()->edit_post((int) $tmp_fid, (int) $tmp_tid, (int) $tmp_pid, request_value($_POST, 'post_content'));
}

function forums_output_edit_post($tmp_pid, $tmp_fid, $tmp_tid, $tmp_errors, $tmp_error_msg = '', $tmp_page = '') {
	return Plugin::instance()->view()->post_form(
		'edit', (int) $tmp_fid, (int) $tmp_tid, (int) $tmp_pid, max(1, (int) $tmp_page),
		Plugin::instance()->front()->form_values(),
		$tmp_errors ? ($tmp_error_msg ?: __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum')) : ''
	);
}

function forums_output_delete_post_process($tmp_fid, $tmp_pid, $tmp_tid) {
	Plugin::instance()->service()->delete_post((int) $tmp_fid, (int) $tmp_tid, (int) $tmp_pid);
	return 0;
}

function forums_output_delete_post($tmp_pid, $tmp_tid, $tmp_page) {
	$topic = Plugin::instance()->repository()->topic((int) $tmp_tid);
	if (!$topic) {
		wp_die(esc_html__('Das Thema konnte nicht geladen werden.', 'psforum'), '', array('response' => 404));
	}
	return Plugin::instance()->view()->delete_confirmation((int) $topic['topic_forum_ID'], (int) $tmp_tid, (int) $tmp_pid, max(1, (int) $tmp_page));
}

function forums_output_delete_topic_process($tmp_fid, $tmp_tid) {
	Plugin::instance()->service()->delete_topic((int) $tmp_fid, (int) $tmp_tid);
	return 0;
}

function forums_output_delete_topic($tmp_fid, $tmp_tid) {
	return Plugin::instance()->view()->delete_confirmation((int) $tmp_fid, (int) $tmp_tid);
}

function forums_output_topic_nav($tmp_tid) {
	$plugin = Plugin::instance();
	return $plugin->view()->pagination(
		$plugin->repository()->post_count((int) $tmp_tid), $plugin->settings()->posts_per_page(),
		(int) request_value($_GET, 'forum_page', '1'), (int) $tmp_tid
	)['html'];
}

function forums_output_forum_nav($tmp_fid) {
	$plugin = Plugin::instance();
	return $plugin->view()->pagination(
		$plugin->repository()->topic_count((int) $tmp_fid), $plugin->settings()->topics_per_page(),
		(int) request_value($_GET, 'forum_page', '1')
	)['html'];
}

function forums_output_view_topic($tmp_tid, $tmp_fid) {
	return Plugin::instance()->view()->posts((int) $tmp_fid, (int) $tmp_tid, (int) request_value($_GET, 'forum_page', '1'));
}

function forums_topic_process($tmp_fid) {
	return Plugin::instance()->service()->create_topic((int) $tmp_fid, request_value($_POST, 'topic_title'), request_value($_POST, 'post_content'));
}

function forums_output_new_topic($tmp_fid, $tmp_errors, $tmp_error_msg = '') {
	$request_forum = request_value($_POST, 'fid', request_value($_GET, 'fid'));
	if ($request_forum !== '' && (int) $request_forum !== (int) $tmp_fid) {
		return Plugin::instance()->view()->index((int) $tmp_fid, (int) request_value($_GET, 'forum_page', '1'));
	}
	return Plugin::instance()->view()->post_form(
		'new', (int) $tmp_fid, 0, 0, 1, Plugin::instance()->front()->form_values(),
		$tmp_errors ? ($tmp_error_msg ?: __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum')) : ''
	);
}

function forums_output_forum($tmp_fid) {
	return Plugin::instance()->view()->forum((int) $tmp_fid, (int) request_value($_GET, 'forum_page', '1'));
}

function forums_output_topics($tmp_fid) {
	return Plugin::instance()->view()->topics((int) $tmp_fid, (int) request_value($_GET, 'forum_page', '1'));
}

function forums_topic_count_posts($tmp_tid) {
	Plugin::instance()->repository()->recount_topic((int) $tmp_tid);
}

function forums_forum_count_posts($tmp_fid) {
	Plugin::instance()->repository()->recount_forum((int) $tmp_fid);
}

function forums_forum_count_topics($tmp_fid) {
	Plugin::instance()->repository()->recount_forum((int) $tmp_fid);
}

function forums_manage_options_output() {
	echo Plugin::instance()->admin()->render_settings();
}

function forums_manage_output() {
	echo Plugin::instance()->admin()->render();
}

function forum_get_details($topic_id) {
	$topic = Plugin::instance()->repository()->topic((int) $topic_id);
	if (!$topic) {
		wp_die(esc_html__('Das Thema konnte nicht geladen werden.', 'psforum'), '', array('response' => 404));
	}
	return array(
		'topic_title' => esc_html($topic['topic_title']),
		'topic_last_updated_stamp' => (int) $topic['topic_last_updated_stamp'],
		'topic_last_author' => (int) $topic['topic_last_author'],
		'topic_closed' => (int) $topic['topic_closed'],
	);
}
