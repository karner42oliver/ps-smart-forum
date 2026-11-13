<?php

namespace PSource\QuickForum\Tests;

use function PSource\QuickForum\check_database_result;
use function PSource\QuickForum\forum_appearance;
use function PSource\QuickForum\forum_form_values;
use function PSource\QuickForum\forum_shortcode;
use function PSource\QuickForum\forums_display_post_content;
use function PSource\QuickForum\forums_output;
use function PSource\QuickForum\forums_output_edit_post_process;
use function PSource\QuickForum\forums_output_edit_post;
use function PSource\QuickForum\forums_output_new_post_separate;
use function PSource\QuickForum\forums_output_new_post_process;
use function PSource\QuickForum\forums_output_delete_post_process;
use function PSource\QuickForum\forums_output_delete_topic_process;
use function PSource\QuickForum\forums_manage_options_output;
use function PSource\QuickForum\forums_manage_output;
use function PSource\QuickForum\forums_topic_process;
use function PSource\QuickForum\install_schema;
use function PSource\QuickForum\require_front_action;
use function PSource\QuickForum\validate_front_request;

if (!defined('ABSPATH') || PHP_SAPI !== 'cli') {
	exit;
}

global $wpdb, $forums_current_version, $forums_topics_per_page, $forums_posts_per_page,
	$forums_max_forums, $forums_upgrades_forums, $forums_enable_upgrades;

require_once dirname(__DIR__) . '/forums.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';

final class RequestRejected extends \RuntimeException {}

add_filter('wp_die_handler', static function () {
	return static function ($message, $title = '', $args = array()) {
		throw new RequestRejected((string) $message, (int) ($args['response'] ?? 500));
	};
}, PHP_INT_MAX);

set_error_handler(static function ($severity, $message, $file, $line) {
	if (str_starts_with($file, dirname(__DIR__) . '/')) {
		throw new \ErrorException($message, 0, $severity, $file, $line);
	}
	return false;
});

function expect(bool $condition, string $message): void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function rejected(callable $callback, int $status): void {
	try {
		$callback();
	} catch (RequestRejected $error) {
		expect($error->getCode() === $status, 'Unexpected HTTP status: ' . $error->getCode());
		return;
	}
	throw new \RuntimeException('Request was not rejected.');
}

function post_request(string $action, int $forum_id, array $fields = array()): void {
	$_GET = array('action' => $action);
	$_POST = array_merge(array(
		'fid' => (string) $forum_id,
		'_psforum_nonce' => wp_create_nonce('psforum_' . $action . '_' . $forum_id),
		'_psforum_request' => bin2hex(random_bytes(16)),
	), $fields);
	$_SERVER['REQUEST_METHOD'] = 'POST';
}

$original_user = get_current_user_id();
$original_get = $_GET;
$original_post = $_POST;
$original_method = $_SERVER['REQUEST_METHOD'] ?? '';
$users = array();
$forums = array();
$marker = 'psforum_test_' . bin2hex(random_bytes(6));
$passed = 0;
$run = static function (string $name, callable $test) use (&$passed): void {
	$test();
	$passed++;
	echo "PASS: {$name}\n";
};

try {
	install_schema();
	foreach (array('author', 'other', 'outsider', 'moderator') as $role) {
		$id = wp_insert_user(array(
			'user_login' => $marker . '_' . $role,
			'user_pass' => wp_generate_password(32),
			'role' => $role === 'outsider' ? '' : ($role === 'moderator' ? 'administrator' : 'subscriber'),
		));
		expect(!is_wp_error($id), 'Could not create fixture user.');
		$users[$role] = (int) $id;
		if ($role === 'outsider' && is_multisite()) {
			$result = remove_user_from_blog($id, get_current_blog_id());
			expect(!is_wp_error($result) && !is_user_member_of_blog($id), 'Could not remove fixture membership.');
		}
	}
	foreach (array(get_current_blog_id(), get_current_blog_id() + 1000000) as $blog_id) {
		check_database_result($wpdb->insert($wpdb->base_prefix . 'forums', array(
			'forum_blog_ID' => $blog_id, 'forum_name' => $marker,
			'forum_color_one' => '#ffffff', 'forum_border_size' => '0',
		)));
		$forums[] = (int) $wpdb->insert_id;
	}
	[$forum_id, $foreign_forum] = $forums;
	wp_set_current_user($users['author']);
	post_request('new_topic_process', $forum_id, array(
		'topic_title' => '<img src=x onerror=alert(1)> Test',
		'post_content' => '<p>Allowed <strong>text</strong></p><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a>',
		'uid' => (string) $users['other'],
	));
	$topic_id = forums_topic_process($forum_id);
	$post_id = (int) $wpdb->get_var($wpdb->prepare("SELECT post_ID FROM {$wpdb->base_prefix}forums_posts WHERE post_topic_ID = %d", $topic_id));

	$run('Topic authors come from the session, not the submitted uid; HTML is sanitized', static function () use ($wpdb, $topic_id, $post_id, $users): void {
		$topic = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}forums_topics WHERE topic_ID = %d", $topic_id));
		$post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}forums_posts WHERE post_ID = %d", $post_id));
		expect((int) $topic->topic_author === $users['author'] && (int) $post->post_author === $users['author'], 'Spoofed author accepted.');
		expect(!str_contains($topic->topic_title, '<'), 'Topic title contains HTML.');
		expect(!str_contains($post->post_content, 'onerror') && !str_contains($post->post_content, 'javascript:'), 'Unsafe post was stored.');
		expect(str_contains($post->post_content, '<strong>text</strong>'), 'Allowed markup was removed.');
	});

	$run('Anonymous topic creation and direct post deletion are denied without changing data', static function () use ($wpdb, $forum_id, $topic_id, $post_id): void {
		wp_set_current_user(0);
		post_request('new_topic_process', $forum_id, array('topic_title' => 'Denied', 'post_content' => 'Denied'));
		rejected(static fn() => forums_topic_process($forum_id), 403);
		post_request('delete_post_process', $forum_id, array('tid' => (string) $topic_id, 'pid' => (string) $post_id));
		rejected(static fn() => forums_output_delete_post_process($forum_id, $post_id, $topic_id), 403);
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_posts WHERE post_ID = %d", $post_id)) === 1, 'Denied deletion changed data.');
	});

	$run('Missing nonces and GET mutations are rejected', static function () use ($users, $forum_id): void {
		wp_set_current_user($users['author']);
		post_request('new_topic_process', $forum_id);
		unset($_POST['_psforum_nonce']);
		rejected(static fn() => require_front_action('new_topic_process', $forum_id), 403);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		rejected(static fn() => require_front_action('new_topic_process', $forum_id), 405);
	});

	$run('Subscribers cannot delete posts or moderate topics even with a valid nonce', static function () use ($forum_id, $topic_id, $post_id): void {
		post_request('delete_post_process', $forum_id);
		rejected(static fn() => forums_output_delete_post_process($forum_id, $post_id, $topic_id), 403);
		post_request('close_topic', $forum_id);
		rejected(static fn() => require_front_action('close_topic', $forum_id, $topic_id), 403);
	});

	$run('Cross-forum and cross-site replies are rejected', static function () use ($wpdb, $forum_id, $foreign_forum, $topic_id): void {
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => 'Denied'));
		rejected(static fn() => forums_output_new_post_process($foreign_forum, $topic_id), 404);
		check_database_result($wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_forum_ID' => $foreign_forum), array('topic_ID' => $topic_id)));
		try {
			rejected(static fn() => forums_output_new_post_process($forum_id, $topic_id), 404);
		} finally {
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_forum_ID' => $forum_id), array('topic_ID' => $topic_id));
		}
	});

	if (is_multisite()) {
		$run('Network users without membership in this site cannot post', static function () use ($users, $forum_id): void {
			wp_set_current_user($users['outsider']);
			post_request('new_topic_process', $forum_id);
			rejected(static fn() => require_front_action('new_topic_process', $forum_id), 403);
		});
	}

	$run('Authors can edit their own recent posts, but not another author or an expired post', static function () use ($wpdb, $users, $forum_id, $topic_id, $post_id): void {
		wp_set_current_user($users['other']);
		post_request('edit_post_process', $forum_id, array('post_content' => 'Denied'));
		rejected(static fn() => forums_output_edit_post_process($forum_id, $post_id, $topic_id), 403);
		wp_set_current_user($users['author']);
		post_request('edit_post_process', $forum_id, array('post_content' => '<p>Edited safely</p>'));
		forums_output_edit_post_process($forum_id, $post_id, $topic_id);
		expect($wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->base_prefix}forums_posts WHERE post_ID = %d", $post_id)) === '<p>Edited safely</p>', 'Valid edit did not persist.');
		$wpdb->update($wpdb->base_prefix . 'forums_posts', array('post_stamp' => time() - 1801), array('post_ID' => $post_id));
		rejected(static fn() => forums_output_edit_post_process($forum_id, $post_id, $topic_id), 403);
	});

	$run('Valid replies persist, while closed topics reject replies', static function () use ($wpdb, $forum_id, $topic_id): void {
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => 'Reply'));
		forums_output_new_post_process($forum_id, $topic_id);
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT topic_posts FROM {$wpdb->base_prefix}forums_topics WHERE topic_ID = %d", $topic_id)) === 2, 'Reply or counter did not persist.');
		$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_closed' => 1), array('topic_ID' => $topic_id));
		rejected(static fn() => forums_output_new_post_process($forum_id, $topic_id), 403);
		$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_closed' => 0), array('topic_ID' => $topic_id));
	});

	$run('Existing stored HTML and malicious color values are safe when rendered', static function () use ($wpdb, $forum_id): void {
		$html = forums_display_post_content('<img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><script>alert(1)</script><strong>safe</strong>');
		expect(!str_contains($html, 'onerror') && !str_contains($html, 'javascript:') && !str_contains($html, '<script'), 'Unsafe legacy HTML rendered.');
		expect(str_contains($html, '<strong>safe</strong>'), 'Safe legacy markup lost.');
		$wpdb->update($wpdb->base_prefix . 'forums', array('forum_color_one' => '" onmouseover="alert(1)', 'forum_border_size' => '999'), array('forum_ID' => $forum_id));
		$appearance = forum_appearance($forum_id);
		expect($appearance[0] === '' && $appearance[4] === 5, 'Unsafe styles accepted.');
		$_POST = array('forum_color_one' => 'not-a-color');
		rejected(static fn() => forum_form_values(), 400);
	});

	$run('Malformed request arrays are rejected and missing shortcode IDs produce a visible error', static function () use ($forum_id): void {
		$_GET = array('action' => array('new_topic_process'));
		$_POST = array();
		rejected(static fn() => validate_front_request($forum_id), 400);
		$_GET = array();
		expect(str_contains(forum_shortcode(array()), 'role="alert"'), 'Invalid shortcode is silent.');
	});

	$run('Admin writes require nonces, valid settings and local forum ownership', static function () use ($users, $foreign_forum): void {
		wp_set_current_user($users['moderator']);
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array();
		ob_start();
		try {
			$_GET = array('action' => 'new_forum_process');
			rejected(static fn() => forums_manage_output(), 403);
			$_GET = array('action' => 'update_settings');
			rejected(static fn() => forums_manage_options_output(), 403);
			wp_get_current_user()->add_cap('manage_network_options');
			$_POST = array('_psforum_nonce' => wp_create_nonce('psforum_update_settings'), 'forums_topics_per_page' => '0');
			rejected(static fn() => forums_manage_options_output(), 400);
			$_GET = array('action' => 'delete_forum_process');
			$_POST = array('_psforum_nonce' => wp_create_nonce('psforum_delete_forum_process'), 'fid' => (string) $foreign_forum);
			rejected(static fn() => forums_manage_output(), 404);
		} finally {
			ob_end_clean();
		}
	});

	$run('Edit forms and validation errors render without undefined variables or missing optional inputs', static function () use ($forum_id, $topic_id, $post_id): void {
		$_GET = array();
		$_POST = array('post_content' => '');
		expect(str_contains(forums_output_edit_post($post_id, $forum_id, $topic_id, 0), '_psforum_nonce'), 'Edit form nonce missing.');
		expect(str_contains(forums_output_edit_post($post_id, $forum_id, $topic_id, 1), 'ausfüllen'), 'Edit validation error missing.');
		expect(str_contains(forums_output_new_post_separate($forum_id, $topic_id, 1), '_psforum_nonce'), 'Reply validation form nonce missing.');
		$_GET = array('action' => 'edit_forum_process');
		$_POST = array('fid' => (string) $forum_id, 'forum_name' => '', '_psforum_nonce' => wp_create_nonce('psforum_edit_forum_process'));
		ob_start();
		try {
			forums_manage_output();
			expect(str_contains(ob_get_contents(), 'ERFORDERLICHEN'), 'Admin validation error missing.');
		} finally {
			ob_end_clean();
		}
	});

	$run('Schema upgrades preserve existing topics and add the expected query index', static function () use ($wpdb, $topic_id): void {
		update_site_option('forums_schema_version', '0');
		install_schema();
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_topics WHERE topic_ID = %d", $topic_id)) === 1, 'Schema upgrade lost existing data.');
		$indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->base_prefix}forums_posts", ARRAY_A);
		expect(in_array('topic_forum', array_column($indexes, 'Key_name'), true), 'Expected index missing.');
		install_schema();
	});

	$run('Existing shortcode syntax and topic rendering remain functional without PHP warnings', static function () use ($forum_id, $topic_id): void {
		$_GET = array();
		$_POST = array();
		expect(str_contains(forum_shortcode(array('id' => (string) $forum_id)), 'THEMEN'), 'Named shortcode failed.');
		expect(str_contains(forum_shortcode(array(':' . $forum_id)), 'THEMEN'), 'Legacy positional shortcode failed.');
		$_GET = array('topic' => (string) $topic_id, 'forum_page' => '-1');
		expect(str_contains(forums_output('[forum:' . $forum_id . ']'), 'Edited safely'), 'Topic rendering failed.');
	});

	$run('Rendering submitted frontend and admin forms never writes data', static function () use ($wpdb, $users, $forum_id, $topic_id): void {
		wp_set_current_user($users['moderator']);
		$before = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_posts WHERE post_forum_ID = %d", $forum_id));
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => 'Must not be written by a view'));
		expect(str_contains(forums_output('[forum:' . $forum_id . ']'), 'Must not be written by a view'), 'Reply form did not preserve submitted content.');
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_posts WHERE post_forum_ID = %d", $forum_id)) === $before, 'Frontend rendering inserted a post.');
		$name = $wpdb->get_var($wpdb->prepare("SELECT forum_name FROM {$wpdb->base_prefix}forums WHERE forum_ID = %d", $forum_id));
		$_GET = array('action' => 'edit_forum_process');
		$_POST = array('fid' => (string) $forum_id, 'forum_name' => 'Must not be saved by a view', '_psforum_nonce' => wp_create_nonce('psforum_edit_forum_process'));
		ob_start();
		try {
			forums_manage_output();
			expect(str_contains(ob_get_contents(), 'Must not be saved by a view'), 'Admin form did not preserve submitted values.');
		} finally {
			ob_end_clean();
		}
		expect($wpdb->get_var($wpdb->prepare("SELECT forum_name FROM {$wpdb->base_prefix}forums WHERE forum_ID = %d", $forum_id)) === $name, 'Admin rendering updated a forum.');
	});

	$run('Controllers keep validation errors for templates without emitting output or changing data', static function () use ($wpdb, $forum_id): void {
		$before = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_topics WHERE topic_forum_ID = %d", $forum_id));
		post_request('new_topic_process', $forum_id, array('topic_title' => 'A "quoted" title', 'post_content' => ''));
		ob_start();
		try {
			\PSource\QuickForum\Plugin::instance()->front()->process($forum_id);
			expect(ob_get_contents() === '', 'Controller emitted HTML before rendering.');
		} finally {
			ob_end_clean();
		}
		$html = forum_shortcode(array('id' => (string) $forum_id));
		expect(str_contains($html, 'ausfüllen') && str_contains($html, 'A &quot;quoted&quot; title'), 'Validation error or escaped form values missing.');
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_topics WHERE topic_forum_ID = %d", $forum_id)) === $before, 'Invalid controller submission created a topic.');
		$_GET = array('page' => 'psforum', 'action' => 'edit_forum_process');
		$_POST = array('fid' => (string) $forum_id, 'forum_name' => '', '_psforum_nonce' => wp_create_nonce('psforum_edit_forum_process'));
		$admin = new \PSource\QuickForum\AdminController(
			\PSource\QuickForum\Plugin::instance()->repository(), \PSource\QuickForum\Plugin::instance()->service(),
			new \PSource\QuickForum\Settings(), new \PSource\QuickForum\Template()
		);
		ob_start();
		try {
			$admin->process_request();
			expect(ob_get_contents() === '', 'Admin controller emitted HTML.');
		} finally {
			ob_end_clean();
		}
		expect(str_contains($admin->render(), 'ERFORDERLICHEN'), 'Admin controller lost its validation error.');
		$_GET = array('page' => 'another-plugin', 'action' => array('unrelated'));
		$admin->process_request();
	});

	$run('Repository reads never expose foreign-site or mismatched topic and post relationships', static function () use ($wpdb, $forum_id, $foreign_forum, $topic_id, $post_id): void {
		$repository = \PSource\QuickForum\Plugin::instance()->repository();
		expect($repository->forum($foreign_forum) === null, 'Foreign forum was exposed.');
		$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_forum_ID' => $foreign_forum), array('topic_ID' => $topic_id));
		try {
			expect($repository->topic($topic_id) === null && $repository->post($post_id) === null, 'Foreign topic or mismatched post was exposed.');
			expect($repository->post_count($topic_id) === 0 && $repository->search($forum_id, 'Edited safely') === array(), 'Counts or search exposed mismatched records.');
		} finally {
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_forum_ID' => $forum_id), array('topic_ID' => $topic_id));
		}
	});

	$run('Paginated templates render the correct persisted posts and clamp invalid page numbers', static function () use ($forum_id, $topic_id): void {
		$key = 'forums_posts_per_page';
		$old = is_multisite() ? get_site_option($key) : get_option($key);
		\PSource\QuickForum\save_setting($key, 1);
		try {
			$view = \PSource\QuickForum\Plugin::instance()->view();
			$first = $view->posts($forum_id, $topic_id, -1);
			$last = $view->posts($forum_id, $topic_id, 999);
			expect(str_contains($first, 'Edited safely') && !str_contains($first, '>Reply<'), 'First page did not clamp correctly.');
			expect(str_contains($last, 'Reply') && !str_contains($last, 'Edited safely'), 'Last page rendered the wrong posts.');
			$pagination = $view->pagination(2, 1, 999, $topic_id);
			expect($pagination['current'] === 2 && $pagination['offset'] === 1 && $pagination['next'] === '', 'Pagination bounds are incorrect.');
			expect(str_contains($pagination['html'], 'forum_page=1'), 'Previous-page link missing.');
			$last = \PSource\QuickForum\Plugin::instance()->repository()->posts($forum_id, $topic_id, 1, 1)[0];
			$topics = $view->topics($forum_id);
			expect(str_contains($topics, 'forum_page=2') && str_contains($topics, '#post-' . (int) $last['post_ID']), 'Last-reply link does not reach its actual page and anchor.');
		} finally {
			if ($old === false) {
				is_multisite() ? delete_site_option($key) : delete_option($key);
			} else {
				is_multisite() ? update_site_option($key, $old) : update_option($key, $old);
			}
		}
	});

	$run('Repeated submission tokens create exactly one topic or reply, and reject changed payloads', static function () use ($wpdb, $users, $forum_id, $topic_id): void {
		wp_set_current_user($users['moderator']);
		$service = \PSource\QuickForum\Plugin::instance()->service();
		$repository = \PSource\QuickForum\Plugin::instance()->repository();
		post_request('new_topic_process', $forum_id, array('topic_title' => 'Idempotent topic', 'post_content' => 'One initial post'));
		$id = $service->create_topic($forum_id, 'Idempotent topic', 'One initial post');
		try {
			expect($service->create_topic($forum_id, 'Idempotent topic', 'One initial post') === $id, 'Repeated topic request returned another topic.');
			expect($repository->post_count($id, $forum_id) === 1, 'Repeated request created duplicate initial posts.');
			rejected(static fn() => $service->create_topic($forum_id, 'Changed title', 'One initial post'), 409);
		} finally {
			post_request('delete_topic_process', $forum_id);
			$service->delete_topic($forum_id, $id);
		}
		$before = $repository->post_count($topic_id, $forum_id);
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => 'Idempotent reply'));
		$id = $service->reply($forum_id, $topic_id, 'Idempotent reply');
		try {
			expect($service->reply($forum_id, $topic_id, 'Idempotent reply') === $id, 'Repeated reply request returned another post.');
			expect($repository->post_count($topic_id, $forum_id) === $before + 1, 'Duplicate reply persisted.');
			rejected(static fn() => $service->reply($forum_id, $topic_id, 'Changed reply'), 409);
		} finally {
			post_request('delete_post_process', $forum_id);
			$service->delete_post($forum_id, $topic_id, $id);
		}
		expect($repository->post_count($topic_id, $forum_id) === $before, 'Idempotency fixture cleanup changed counters.');
	});

	$run('Failed multi-table writes roll back posts, counters and submission keys, then allow a clean retry', static function () use ($wpdb, $users, $forum_id, $topic_id): void {
		wp_set_current_user($users['moderator']);
		$service = \PSource\QuickForum\Plugin::instance()->service();
		$repository = \PSource\QuickForum\Plugin::instance()->repository();
		$before = $repository->post_count($topic_id, $forum_id);
		$topic_before = $repository->topic($topic_id);
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => 'Atomic retry'));
		$break_counter = static function (string $query) use ($wpdb): string {
			return str_starts_with($query, 'UPDATE `' . $wpdb->base_prefix . 'forums_topics`')
				? 'INVALID QUICK FORUM TEST QUERY' : $query;
		};
		$suppressed = $wpdb->suppress_errors(true);
		add_filter('query', $break_counter);
		try {
			rejected(static fn() => $service->reply($forum_id, $topic_id, 'Atomic retry'), 500);
		} finally {
			remove_filter('query', $break_counter);
			$wpdb->suppress_errors($suppressed);
		}
		expect($repository->post_count($topic_id, $forum_id) === $before, 'Failed write left an inserted post.');
		expect($repository->topic($topic_id) === $topic_before, 'Failed write changed topic metadata.');
		$id = $service->reply($forum_id, $topic_id, 'Atomic retry');
		expect($repository->post_count($topic_id, $forum_id) === $before + 1, 'Rolled-back submission key prevented retry.');
		post_request('delete_post_process', $forum_id);
		$service->delete_post($forum_id, $topic_id, $id);
	});

	$run('Title editing follows the saved operator policy and enforces author, time, nonce and moderator boundaries', static function () use ($wpdb, $users, $forum_id, $topic_id): void {
		$key = 'forums_title_edit_policy';
		$old = is_multisite() ? get_site_option($key) : get_option($key);
		$repository = \PSource\QuickForum\Plugin::instance()->repository();
		$service = \PSource\QuickForum\Plugin::instance()->service();
		$original = $repository->topic($topic_id);
		try {
			\PSource\QuickForum\save_setting($key, '30_minutes');
			wp_set_current_user($users['author']);
			post_request('edit_topic_process', $forum_id);
			$service->rename_topic($forum_id, $topic_id, '<b>Renamed safely</b>');
			expect($repository->topic($topic_id)['topic_title'] === 'Renamed safely', 'Title edit did not sanitize and persist.');
			wp_set_current_user($users['other']);
			post_request('edit_topic_process', $forum_id);
			rejected(static fn() => $service->rename_topic($forum_id, $topic_id, 'Denied'), 403);
			wp_set_current_user($users['author']);
			post_request('edit_topic_process', $forum_id);
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_stamp' => time() - 1801), array('topic_ID' => $topic_id));
			rejected(static fn() => $service->rename_topic($forum_id, $topic_id, 'Expired'), 403);
			\PSource\QuickForum\save_setting($key, 'authors');
			$service->rename_topic($forum_id, $topic_id, 'Unlimited author');
			\PSource\QuickForum\save_setting($key, 'moderators');
			rejected(static fn() => $service->rename_topic($forum_id, $topic_id, 'Denied'), 403);
			wp_set_current_user($users['moderator']);
			post_request('edit_topic_process', $forum_id);
			$service->rename_topic($forum_id, $topic_id, 'Moderator edit');
			unset($_POST['_psforum_nonce']);
			rejected(static fn() => $service->rename_topic($forum_id, $topic_id, 'Missing nonce'), 403);
		} finally {
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_title' => $original['topic_title'], 'topic_stamp' => $original['topic_stamp']), array('topic_ID' => $topic_id));
			if ($old === false) {
				is_multisite() ? delete_site_option($key) : delete_option($key);
			} else {
				is_multisite() ? update_site_option($key, $old) : update_option($key, $old);
			}
		}
	});

	$run('Pinned topics sort first, unpin correctly and remain moderator-only', static function () use ($wpdb, $users, $forum_id, $topic_id): void {
		$repository = \PSource\QuickForum\Plugin::instance()->repository();
		$service = \PSource\QuickForum\Plugin::instance()->service();
		wp_set_current_user($users['author']);
		post_request('pin_topic', $forum_id);
		rejected(static fn() => $service->set_sticky($forum_id, $topic_id, true), 403);
		wp_set_current_user($users['moderator']);
		post_request('new_topic_process', $forum_id);
		$other = $service->create_topic($forum_id, 'Newer unpinned topic', 'Pin test');
		$stamp = $repository->topic($topic_id)['topic_last_updated_stamp'];
		try {
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_last_updated_stamp' => 1), array('topic_ID' => $topic_id));
			post_request('pin_topic', $forum_id);
			$_GET['topic'] = (string) $topic_id;
			validate_front_request($forum_id);
			$service->set_sticky($forum_id, $topic_id, true);
			expect((int) $repository->topics($forum_id, 0, 2)[0]['topic_ID'] === $topic_id, 'Pinned topic did not sort first.');
			post_request('unpin_topic', $forum_id);
			$service->set_sticky($forum_id, $topic_id, false);
			expect((int) $repository->topics($forum_id, 0, 2)[0]['topic_ID'] === $other, 'Unpinned topic still sorts first.');
		} finally {
			$wpdb->update($wpdb->base_prefix . 'forums_topics', array('topic_sticky' => 0, 'topic_last_updated_stamp' => $stamp), array('topic_ID' => $topic_id));
			post_request('delete_topic_process', $forum_id);
			$service->delete_topic($forum_id, $other);
		}
	});

	$run('Multiple embedded forums isolate topic views, search, validation and pagination without duplicate field IDs', static function () use ($wpdb, $forum_id, $topic_id, &$forums): void {
		check_database_result($wpdb->insert($wpdb->base_prefix . 'forums', array('forum_blog_ID' => get_current_blog_id(), 'forum_name' => 'Isolated fixture')));
		$second = (int) $wpdb->insert_id;
		$forums[] = $second;
		$_GET = array('fid' => (string) $forum_id, 'topic' => (string) $topic_id, 'forum_page' => '2');
		$_POST = array();
		$html = forum_shortcode(array('id' => (string) $second));
		expect(str_contains($html, 'THEMEN') && !str_contains($html, 'Edited safely') && !str_contains($html, 'role="alert"'), 'Topic request leaked into another forum.');
		$_GET = array('fid' => (string) $forum_id, 'search' => 'Edited safely');
		expect(!str_contains(forum_shortcode(array('id' => (string) $second)), 'Edited safely'), 'Search leaked into another forum.');
		post_request('new_post_process', $forum_id, array('tid' => (string) $topic_id, 'post_content' => ''));
		\PSource\QuickForum\Plugin::instance()->front()->process($forum_id);
		expect(!str_contains(forum_shortcode(array('id' => (string) $second)), 'role="alert"'), 'Validation error leaked into another forum.');
		$view = \PSource\QuickForum\Plugin::instance()->view();
		$html = $view->post_form('new', $forum_id) . $view->post_form('new', $second);
		preg_match_all('/\\bid="([^"]+)"/', $html, $ids);
		expect(count($ids[1]) === count(array_unique($ids[1])), 'Embedded forms have duplicate IDs.');
		expect(!str_contains($html, '<table') && !str_contains($html, '<center'), 'Layout tables remain in post forms.');
	});

	$run('Authorized moderation deletes only the selected data and updates counters', static function () use ($wpdb, $forum_id, $topic_id, $post_id): void {
		post_request('delete_post_process', $forum_id);
		forums_output_delete_post_process($forum_id, $post_id, $topic_id);
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT topic_posts FROM {$wpdb->base_prefix}forums_topics WHERE topic_ID = %d", $topic_id)) === 1, 'Post counter not updated.');
		post_request('delete_topic_process', $forum_id);
		forums_output_delete_topic_process($forum_id, $topic_id);
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->base_prefix}forums_posts WHERE post_topic_ID = %d", $topic_id)) === 0, 'Topic posts remain.');
		expect((int) $wpdb->get_var($wpdb->prepare("SELECT forum_topics FROM {$wpdb->base_prefix}forums WHERE forum_ID = %d", $forum_id)) === 0, 'Forum counter not updated.');
	});

	echo "{$passed} integration tests passed on PHP " . PHP_VERSION . ".\n";
} finally {
	foreach ($forums as $id) {
		$wpdb->delete($wpdb->base_prefix . 'forums_posts', array('post_forum_ID' => $id));
		$wpdb->delete($wpdb->base_prefix . 'forums_topics', array('topic_forum_ID' => $id));
		$wpdb->delete($wpdb->base_prefix . 'forums', array('forum_ID' => $id));
	}
	foreach ($users as $id) {
		if (is_multisite()) {
			wpmu_delete_user($id);
		} else {
			wp_delete_user($id);
		}
	}
	wp_set_current_user($original_user);
	$_GET = $original_get;
	$_POST = $original_post;
	$_SERVER['REQUEST_METHOD'] = $original_method;
	restore_error_handler();
}
