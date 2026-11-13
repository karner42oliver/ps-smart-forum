<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class ForumService {
	public function __construct(private ForumRepository $repository, private Settings $settings) {}

	private function write(int $forum_id, callable $operation) {
		return $this->repository->atomic($forum_id, $operation);
	}

	private function replay(string $key, string $content, ?string $title = null): ?array {
		$existing = $this->repository->submitted_post($key);
		if ($existing && ($existing['post_content'] !== $content || ($title !== null && $existing['topic_title'] !== $title))) {
			wp_die(esc_html__('Dieses Formular wurde bereits mit anderem Inhalt verwendet. Bitte öffne ein neues Formular.', 'psforum'), '', array('response' => 409));
		}
		return $existing;
	}

	public function create_topic(int $forum_id, string $title, string $content): int {
		require_front_action('new_topic_process', $forum_id);
		$key = submission_key($forum_id, 0, 'new_topic_process');
		$title = sanitize_text_field($title);
		$content = forums_save_post_content($content);
		return $this->write($forum_id, function () use ($forum_id, $title, $content, $key): int {
			require_front_action('new_topic_process', $forum_id);
			$existing = $this->replay($key, $content, $title);
			return $existing ? (int) $existing['post_topic_ID']
				: $this->repository->create_topic($forum_id, $title, $content, get_current_user_id(), time(), $key);
		});
	}

	public function reply(int $forum_id, int $topic_id, string $content): int {
		require_front_action('new_post_process', $forum_id, $topic_id);
		$key = submission_key($forum_id, $topic_id, 'new_post_process');
		$content = forums_save_post_content($content);
		return $this->write($forum_id, function () use ($forum_id, $topic_id, $content, $key): int {
			require_front_action('new_post_process', $forum_id, $topic_id);
			$existing = $this->replay($key, $content);
			return $existing ? (int) $existing['post_ID']
				: $this->repository->create_post($forum_id, $topic_id, $content, get_current_user_id(), time(), $key);
		});
	}

	public function edit_post(int $forum_id, int $topic_id, int $post_id, string $content): void {
		require_front_action('edit_post_process', $forum_id, $topic_id, $post_id);
		$this->write($forum_id, function () use ($forum_id, $topic_id, $post_id, $content): void {
			require_front_action('edit_post_process', $forum_id, $topic_id, $post_id);
			$this->repository->update_post($forum_id, $topic_id, $post_id, forums_save_post_content($content));
		});
	}

	public function delete_post(int $forum_id, int $topic_id, int $post_id): void {
		require_front_action('delete_post_process', $forum_id, $topic_id, $post_id);
		$this->write($forum_id, function () use ($forum_id, $topic_id, $post_id): void {
			require_front_action('delete_post_process', $forum_id, $topic_id, $post_id);
			$this->repository->delete_post($forum_id, $topic_id, $post_id);
		});
	}

	public function delete_topic(int $forum_id, int $topic_id): void {
		require_front_action('delete_topic_process', $forum_id, $topic_id);
		$this->write($forum_id, function () use ($forum_id, $topic_id): void {
			require_front_action('delete_topic_process', $forum_id, $topic_id);
			$this->repository->delete_topic($forum_id, $topic_id);
		});
	}

	public function set_closed(int $forum_id, int $topic_id, bool $closed): void {
		$action = $closed ? 'close_topic' : 'open_topic';
		require_front_action($action, $forum_id, $topic_id);
		$this->write($forum_id, function () use ($action, $forum_id, $topic_id, $closed): void {
			require_front_action($action, $forum_id, $topic_id);
			$this->repository->set_closed($forum_id, $topic_id, $closed);
		});
	}

	public function set_sticky(int $forum_id, int $topic_id, bool $sticky): void {
		$action = $sticky ? 'pin_topic' : 'unpin_topic';
		require_front_action($action, $forum_id, $topic_id);
		$this->write($forum_id, function () use ($action, $forum_id, $topic_id, $sticky): void {
			require_front_action($action, $forum_id, $topic_id);
			$this->repository->set_sticky($forum_id, $topic_id, $sticky);
		});
	}

	public function rename_topic(int $forum_id, int $topic_id, string $title): void {
		require_front_action('edit_topic_process', $forum_id, $topic_id);
		$title = sanitize_text_field($title);
		if ($title === '') {
			wp_die(esc_html__('Ein Thementitel ist erforderlich.', 'psforum'), '', array('response' => 400));
		}
		$this->write($forum_id, function () use ($forum_id, $topic_id, $title): void {
			require_front_action('edit_topic_process', $forum_id, $topic_id);
			$this->repository->rename_topic($forum_id, $topic_id, $title);
		});
	}

	public function save_forum(string $action, int $forum_id): array {
		$values = validate_admin_forum_request($action);
		if ($values['forum_name'] !== '') {
			$this->write($action === 'new_forum_process' ? 0 : $forum_id, function () use ($action, $forum_id, $values): void {
				if ($action !== 'new_forum_process') {
					require_forum($forum_id);
				}
				if ($action === 'new_forum_process') {
					$this->require_available_forum();
					$this->repository->create_forum($values);
				} else {
					$this->repository->update_forum($forum_id, $values);
				}
			});
		} elseif ($action === 'new_forum_process') {
			$this->require_available_forum();
		}
		return $values;
	}

	public function delete_forum(int $forum_id): void {
		require_moderator();
		require_nonce('psforum_delete_forum_process');
		require_forum($forum_id);
		$this->write($forum_id, fn() => $this->repository->delete_forum($forum_id));
	}

	public function require_available_forum(): void {
		if ($this->repository->forum_count() >= $this->settings->forum_limit()) {
			wp_die(esc_html__('Die maximale Anzahl an Foren ist erreicht.', 'psforum'), '', array('response' => 403));
		}
	}
}
