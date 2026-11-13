<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class ForumView {
	private int $field_sequence = 0;
	public function __construct(
		private ForumRepository $repository,
		private Settings $settings,
		private Template $templates
	) {}

	public function message(string $message): string {
		return $this->templates->render('message', array('message' => $message));
	}

	public function appearance(int $forum_id): array {
		$forum = $this->repository->forum($forum_id);
		if (!$forum) {
			wp_die(esc_html__('Das Forum konnte nicht geladen werden.', 'psforum'), '', array('response' => 404));
		}
		return array(
			sanitize_hex_color((string) $forum['forum_color_one']) ?: '',
			sanitize_hex_color((string) $forum['forum_color_two']) ?: '',
			sanitize_hex_color((string) $forum['forum_color_header']) ?: '',
			sanitize_hex_color((string) $forum['forum_color_border']) ?: '',
			max(0, min(5, (int) $forum['forum_border_size'])),
		);
	}

	public function pagination(int $count, int $per_page, int $requested, int $topic_id = 0, int $forum_id = 0): array {
		$total = max(1, (int) ceil($count / $per_page));
		$current = max(1, min($total, $requested));
		$args = $topic_id ? array('topic' => $topic_id) : array();
		if (!$forum_id && $topic_id) {
			$topic = $this->repository->topic($topic_id);
			$forum_id = $topic ? (int) $topic['topic_forum_ID'] : 0;
		}
		if ($forum_id) {
			$args['fid'] = $forum_id;
		}
		$data = array(
			'count' => $count, 'current' => $current, 'total' => $total,
			'offset' => ($current - 1) * $per_page,
			'low' => $count ? ($current - 1) * $per_page + 1 : 0,
			'high' => min($count, $current * $per_page),
			'previous' => $current > 1 ? add_query_arg(array_merge($args, array('forum_page' => $current - 1)), get_permalink()) : '',
			'next' => $current < $total ? add_query_arg(array_merge($args, array('forum_page' => $current + 1)), get_permalink()) : '',
		);
		$data['html'] = $count || $topic_id ? $this->templates->render('frontend/pagination', $data) : '';
		return $data;
	}

	public function index(int $forum_id, int $page = 1, string $query = ''): string {
		require_forum($forum_id);
		$pagination = $this->pagination($this->repository->topic_count($forum_id), $this->settings->topics_per_page(), $page, 0, $forum_id);
		return $this->templates->render('frontend/index', array(
			'search' => $this->search_form($forum_id, $query),
			'pagination' => $pagination['html'],
			'forum' => $this->forum($forum_id, $page),
		));
	}

	public function forum(int $forum_id, int $page = 1): string {
		require_forum($forum_id);
		return $this->templates->render('frontend/forum', array(
			'can_post' => is_user_logged_in(),
			'new_topic_url' => forum_url($forum_id, array('action' => 'new_topic')),
			'topics' => $this->topics($forum_id, $page),
		));
	}

	public function topics(int $forum_id, int $page = 1): string {
		require_forum($forum_id);
		$pagination = $this->pagination($this->repository->topic_count($forum_id), $this->settings->topics_per_page(), $page, 0, $forum_id);
		$topics = $this->repository->topics($forum_id, $pagination['offset'], $this->settings->topics_per_page());
		foreach ($topics as &$topic) {
			$topic['url'] = forum_url($forum_id, array('topic' => (int) $topic['topic_ID']));
			$topic['last_url'] = forum_url($forum_id, array(
				'topic' => (int) $topic['topic_ID'],
				'forum_page' => max(1, (int) ceil((int) $topic['topic_posts'] / $this->settings->posts_per_page())),
			), $topic['last_post_ID'] ? 'post-' . (int) $topic['last_post_ID'] : '');
			$topic['author'] = forums_author_display_name($topic['topic_last_author']);
		}
		unset($topic);
		return $this->templates->render('frontend/topics', array(
			'topics' => $topics,
			'can_post' => is_user_logged_in(),
			'new_topic_url' => forum_url($forum_id, array('action' => 'new_topic')),
		));
	}

	public function topic(int $forum_id, int $topic_id, int $page = 1, string $message = ''): string {
		require_forum($forum_id, $topic_id);
		$topic = $this->repository->topic($topic_id, $forum_id);
		$pagination = $this->pagination($this->repository->post_count($topic_id, $forum_id), $this->settings->posts_per_page(), $page, $topic_id);
		return $this->templates->render('frontend/topic', array(
			'topic' => $topic, 'message' => $message, 'pagination' => $pagination['html'],
			'metadata' => $this->metadata((int) $topic['topic_last_updated_stamp'], (int) $topic['topic_last_author']),
			'navigation' => $this->navigation($topic_id),
			'posts' => $this->posts($forum_id, $topic_id, $page),
			'reply' => $this->post_form('reply', $forum_id, $topic_id, 0, $page, array(), '', true),
		));
	}

	public function metadata(int $stamp, int $author): string {
		return $this->templates->render('frontend/metadata', array(
			'date' => date(get_option('date_format', __('D, F jS Y g:i A', 'psforum')), $stamp),
			'author' => forums_author_display_name($author),
		));
	}

	public function navigation(int $topic_id): string {
		$topic = $this->repository->topic($topic_id);
		if (!$topic) {
			wp_die(esc_html__('Das Thema konnte nicht geladen werden.', 'psforum'), '', array('response' => 404));
		}
		$action = (int) $topic['topic_closed'] ? 'open_topic' : 'close_topic';
		return $this->templates->render('frontend/navigation', array(
			'index_url' => forum_url((int) $topic['topic_forum_ID']), 'moderator' => current_user_can('manage_options'),
			'delete_url' => forum_url((int) $topic['topic_forum_ID'], array('action' => 'delete_topic', 'tid' => $topic_id)),
			'action_url' => forum_url((int) $topic['topic_forum_ID'], array('action' => $action, 'topic' => $topic_id)),
			'nonce' => nonce_field('psforum_' . $action . '_' . (int) $topic['topic_forum_ID']),
			'forum_id' => (int) $topic['topic_forum_ID'], 'closed' => (bool) $topic['topic_closed'],
			'pin_url' => forum_url((int) $topic['topic_forum_ID'], array('action' => $topic['topic_sticky'] ? 'unpin_topic' : 'pin_topic', 'topic' => $topic_id)),
			'pin_nonce' => nonce_field('psforum_' . ($topic['topic_sticky'] ? 'unpin_topic' : 'pin_topic') . '_' . (int) $topic['topic_forum_ID']),
			'sticky' => (bool) $topic['topic_sticky'],
			'edit_url' => can_edit_topic($topic) ? forum_url((int) $topic['topic_forum_ID'], array('action' => 'edit_topic', 'tid' => $topic_id)) : '',
		));
	}

	public function posts(int $forum_id, int $topic_id, int $page = 1): string {
		require_forum($forum_id, $topic_id);
		$count = $this->repository->post_count($topic_id, $forum_id);
		$pagination = $this->pagination($count, $this->settings->posts_per_page(), $page, $topic_id);
		$posts = $this->repository->posts($forum_id, $topic_id, $pagination['offset'], $this->settings->posts_per_page());
		$moderator = current_user_can('manage_options');
		foreach ($posts as &$post) {
			$args = array('forum_page' => $pagination['current'], 'tid' => $topic_id, 'pid' => (int) $post['post_ID']);
			$post['author'] = forums_author_display_name($post['post_author']);
			$post['avatar'] = get_avatar($post['post_author'], 48, get_option('avatar_default'));
			$post['html'] = forums_display_post_content($post['post_content']);
			$post['date'] = date(get_option('date_format', __('D, F jS Y g:i A', 'psforum')), (int) $post['post_stamp']);
			$post['url'] = forum_url($forum_id, array('topic' => $topic_id, 'forum_page' => $pagination['current']), 'post-' . (int) $post['post_ID']);
			$can_edit = $moderator || (is_user_logged_in() && (int) $post['post_author'] === get_current_user_id() && time() - (int) $post['post_stamp'] < 1800);
			$post['edit_url'] = $can_edit ? forum_url($forum_id, array_merge($args, array('action' => 'edit_post'))) : '';
			$post['delete_url'] = $moderator && $count > 1 ? forum_url($forum_id, array_merge($args, array('action' => 'delete_post'))) : '';
		}
		unset($post);
		return $this->templates->render('frontend/posts', array(
			'posts' => $posts, 'topic_id' => $topic_id,
		));
	}

	public function search_form(int $forum_id, string $query = ''): string {
		$fields = array();
		wp_parse_str((string) wp_parse_url(get_permalink(), PHP_URL_QUERY), $fields);
		return $this->templates->render('frontend/search-form', array(
			'visible' => $this->repository->topic_count($forum_id) > 0,
			'forum_id' => $forum_id, 'query' => $query,
			'url' => forum_url($forum_id),
			'query_fields' => $fields,
		));
	}

	public function search(int $forum_id, string $query): string {
		require_forum($forum_id);
		$results = $this->repository->search($forum_id, $query);
		foreach ($results as &$result) {
			$result['url'] = $this->post_url($forum_id, (int) $result['post_topic_ID'], (int) $result['post_ID']);
			$result['html'] = forums_display_post_content($result['post_content']);
		}
		unset($result);
		return $this->templates->render('frontend/search', array(
			'results' => $results, 'search_form' => $this->search_form($forum_id, $query),
		));
	}

	public function post_form(
		string $mode, int $forum_id, int $topic_id = 0, int $post_id = 0, int $page = 1,
		array $values = array(), string $error = '', bool $inline = false
	): string {
		require_forum($forum_id, $topic_id, $post_id);
		if ($mode === 'edit') {
			require_post_editor($post_id);
			$post = $this->repository->post($post_id, $topic_id, $forum_id);
			if ($error === '') {
				$values['post_content'] = (string) $post['post_content'];
			}
		}
		$topic = $topic_id ? $this->repository->topic($topic_id, $forum_id) : null;
		$action = array('new' => 'new_topic_process', 'reply' => 'new_post_process', 'edit' => 'edit_post_process')[$mode];
		$title = array('new' => __('Neues Thema', 'psforum'), 'reply' => __('Neuer Beitrag', 'psforum'), 'edit' => __('Beitrag bearbeiten', 'psforum'))[$mode];
		return $this->templates->render('frontend/post-form', array(
			'mode' => $mode, 'title' => $title, 'error' => $error,
			'logged_in' => is_user_logged_in(), 'closed' => $mode === 'reply' && (bool) $topic['topic_closed'],
			'inline' => $inline, 'forum_id' => $forum_id, 'topic_id' => $topic_id,
			'post_id' => $post_id, 'page' => max(1, $page),
			'content' => $values['post_content'] ?? '', 'topic_title' => $values['topic_title'] ?? '',
			'url' => forum_url($forum_id, array('action' => $action)),
			'nonce' => nonce_field('psforum_' . $action . '_' . $forum_id),
			'request_key' => bin2hex(random_bytes(16)),
			'preview_url' => forum_url($forum_id, array('action' => 'preview_post')),
			'preview_nonce' => wp_create_nonce('psforum_preview_post_' . $forum_id),
			'field_id' => 'psforum-field-' . $forum_id . '-' . ++$this->field_sequence,
			'login_message' => sprintf(
				__('Du musst ein registrierter und angemeldeter Benutzer dieses Blogs sein, um in diesem Forum posten zu können. Bitte <a href="%s">anmelden</a> oder %s.', 'psforum'),
				esc_url(wp_login_url()), wp_register('', '', false)
			),
		));
	}

	public function delete_confirmation(int $forum_id, int $topic_id, int $post_id = 0, int $page = 1): string {
		require_forum($forum_id, $topic_id, $post_id);
		require_moderator();
		$action = $post_id ? 'delete_post_process' : 'delete_topic_process';
		return $this->templates->render('frontend/delete', array(
			'forum_id' => $forum_id, 'topic_id' => $topic_id, 'post_id' => $post_id, 'page' => $page,
			'url' => forum_url($forum_id, array('action' => $action)),
			'nonce' => nonce_field('psforum_' . $action . '_' . $forum_id),
		));
	}

	public function title_form(int $forum_id, int $topic_id, ?string $title = null, string $error = ''): string {
		require_topic_editor($forum_id, $topic_id);
		$topic = $this->repository->topic($topic_id, $forum_id);
		return $this->templates->render('frontend/title-form', array(
			'forum_id' => $forum_id, 'topic_id' => $topic_id, 'title' => $title ?? $topic['topic_title'],
			'error' => $error, 'url' => forum_url($forum_id, array('action' => 'edit_topic_process')),
			'nonce' => nonce_field('psforum_edit_topic_process_' . $forum_id),
			'field_id' => 'psforum-title-' . $forum_id . '-' . ++$this->field_sequence,
		));
	}

	public function post_url(int $forum_id, int $topic_id, int $post_id): string {
		$position = $this->repository->post_position($forum_id, $topic_id, $post_id);
		$page = max(1, (int) ceil($position / $this->settings->posts_per_page()));
		return forum_url($forum_id, array('topic' => $topic_id, 'forum_page' => $page), 'post-' . $post_id);
	}

}
