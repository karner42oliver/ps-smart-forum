<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class DatabaseFailure extends \RuntimeException {}

final class ForumRepository {
	private bool $writing = false;

	public function __construct(private \wpdb $db) {}

	private function write_result(int|bool $result): void {
		if ($result === false && $this->writing) {
			throw new DatabaseFailure('Quick Forum database write failed.');
		}
		check_database_result($result);
	}

	public function supports_transactions(): bool {
		$tables = $this->rows($this->db->prepare(
			"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s, %s)",
			$this->table(''), $this->table('_topics'), $this->table('_posts')
		));
		return count($tables) === 3 && count(array_filter($tables, static fn(array $table): bool => strtoupper((string) $table['ENGINE']) === 'INNODB')) === 3;
	}

	public function atomic(int $forum_id, callable $operation): mixed {
		if ($this->writing) {
			throw new \LogicException('Nested Quick Forum writes are not supported.');
		}
		$transactional = $this->supports_transactions();
		if (!$transactional) {
			wp_die(esc_html__('Die Forumtabellen müssen InnoDB verwenden. Bitte kontaktiere die Website-Administration.', 'psforum'), '', array('response' => 503));
		}
		$lock = 'psforum_' . hash('sha256', $this->db->dbname . ':' . $this->table('') . ':' . get_current_blog_id() . ':' . $forum_id);
		$lock = substr($lock, 0, 64);
		$acquired = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, 10)', $lock));
		$this->check_read();
		if ((string) $acquired !== '1') {
			wp_die(esc_html__('Das Forum wird gerade aktualisiert. Bitte versuche es erneut.', 'psforum'), '', array('response' => 409));
		}
		$committed = false;
		$started = false;
		$this->writing = true;
		try {
			try {
				if ($transactional) {
					$this->write_result($this->db->query('START TRANSACTION'));
					$started = true;
				}
				$result = $operation();
				if ($started) {
					$this->write_result($this->db->query('COMMIT'));
				}
				$committed = true;
				return $result;
			} finally {
				$this->writing = false;
				if ($started && !$committed && $this->db->query('ROLLBACK') === false) {
					error_log('Quick Forum: database rollback failed.');
				}
				$released = $this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $lock));
				if ((string) $released !== '1') {
					error_log('Quick Forum: database lock release failed.');
				}
			}
		} catch (DatabaseFailure $error) {
			wp_die(esc_html__('Die Änderung konnte nicht vollständig gespeichert werden. Bitte lade die Seite neu und versuche es erneut.', 'psforum'), '', array('response' => 500));
		}
	}

	private function table(string $name): string {
		return $this->db->base_prefix . 'forums' . $name;
	}

	private function check_read(): void {
		if ($this->db->last_error !== '') {
			if ($this->writing) {
				throw new DatabaseFailure('Quick Forum database read failed.');
			}
			wp_die(esc_html__('Die Forum-Datenbank konnte nicht gelesen werden.', 'psforum'), '', array('response' => 500));
		}
	}

	private function rows(string $query): array {
		$rows = $this->db->get_results($query, ARRAY_A);
		$this->check_read();
		if (!is_array($rows)) {
			wp_die(esc_html__('Die Forum-Datenbank konnte nicht gelesen werden.', 'psforum'), '', array('response' => 500));
		}
		return $rows;
	}

	private function row(string $query): ?array {
		$row = $this->db->get_row($query, ARRAY_A);
		$this->check_read();
		return $row;
	}

	private function count(string $query): int {
		$count = $this->db->get_var($query);
		$this->check_read();
		return (int) $count;
	}

	private function insert(string $table, array $values): int {
		$this->write_result($this->db->insert($this->table($table), $values));
		return (int) $this->db->insert_id;
	}

	private function update(string $table, array $values, array $where): void {
		$this->write_result($this->db->update($this->table($table), $values, $where));
	}

	private function delete(string $table, array $where): void {
		$this->write_result($this->db->delete($this->table($table), $where));
	}

	public function forum(int $forum_id): ?array {
		return $this->row($this->db->prepare(
			"SELECT * FROM {$this->table('')} WHERE forum_ID = %d AND forum_blog_ID = %d",
			$forum_id, get_current_blog_id()
		));
	}

	public function forums(): array {
		return $this->rows($this->db->prepare(
			"SELECT * FROM {$this->table('')} WHERE forum_blog_ID = %d ORDER BY forum_ID DESC",
			get_current_blog_id()
		));
	}

	public function forum_count(): int {
		return $this->count($this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table('')} WHERE forum_blog_ID = %d", get_current_blog_id()
		));
	}

	public function topic(int $topic_id, int $forum_id = 0): ?array {
		return $this->row($this->db->prepare(
			"SELECT t.* FROM {$this->table('_topics')} t
			INNER JOIN {$this->table('')} f ON f.forum_ID = t.topic_forum_ID
			WHERE t.topic_ID = %d AND f.forum_blog_ID = %d AND (%d = 0 OR t.topic_forum_ID = %d)",
			$topic_id, get_current_blog_id(), $forum_id, $forum_id
		));
	}

	public function post(int $post_id, int $topic_id = 0, int $forum_id = 0): ?array {
		return $this->row($this->db->prepare(
			"SELECT p.* FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('_topics')} t ON t.topic_ID = p.post_topic_ID AND t.topic_forum_ID = p.post_forum_ID
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_ID = %d AND f.forum_blog_ID = %d
			AND (%d = 0 OR p.post_topic_ID = %d) AND (%d = 0 OR p.post_forum_ID = %d)",
			$post_id, get_current_blog_id(), $topic_id, $topic_id, $forum_id, $forum_id
		));
	}

	public function topic_count(int $forum_id): int {
		return $this->count($this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table('_topics')} t
			INNER JOIN {$this->table('')} f ON f.forum_ID = t.topic_forum_ID
			WHERE t.topic_forum_ID = %d AND f.forum_blog_ID = %d", $forum_id, get_current_blog_id()
		));
	}

	public function post_count(int $topic_id, int $forum_id = 0): int {
		return $this->count($this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('_topics')} t ON t.topic_ID = p.post_topic_ID AND t.topic_forum_ID = p.post_forum_ID
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE t.topic_ID = %d AND f.forum_blog_ID = %d AND (%d = 0 OR p.post_forum_ID = %d)",
			$topic_id, get_current_blog_id(), $forum_id, $forum_id
		));
	}

	public function topics(int $forum_id, int $offset, int $limit): array {
		return $this->rows($this->db->prepare(
			"SELECT t.*, (SELECT MAX(p.post_ID) FROM {$this->table('_posts')} p
			WHERE p.post_topic_ID = t.topic_ID AND p.post_forum_ID = t.topic_forum_ID) AS last_post_ID
			FROM {$this->table('_topics')} t
			INNER JOIN {$this->table('')} f ON f.forum_ID = t.topic_forum_ID
			WHERE t.topic_forum_ID = %d AND f.forum_blog_ID = %d
			ORDER BY t.topic_sticky DESC, t.topic_last_updated_stamp DESC, t.topic_ID DESC LIMIT %d, %d",
			$forum_id, get_current_blog_id(), $offset, $limit
		));
	}

	public function posts(int $forum_id, int $topic_id, int $offset, int $limit): array {
		return $this->rows($this->db->prepare(
			"SELECT p.* FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('_topics')} t ON t.topic_ID = p.post_topic_ID AND t.topic_forum_ID = p.post_forum_ID
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_forum_ID = %d AND p.post_topic_ID = %d AND f.forum_blog_ID = %d
			ORDER BY p.post_ID ASC LIMIT %d, %d",
			$forum_id, $topic_id, get_current_blog_id(), $offset, $limit
		));
	}

	public function search(int $forum_id, string $query): array {
		return $this->rows($this->db->prepare(
			"SELECT p.*, t.topic_title FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('_topics')} t ON t.topic_ID = p.post_topic_ID AND t.topic_forum_ID = p.post_forum_ID
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_forum_ID = %d AND f.forum_blog_ID = %d AND p.post_content LIKE %s
			ORDER BY p.post_ID ASC LIMIT 25",
			$forum_id, get_current_blog_id(), '%' . $this->db->esc_like($query) . '%'
		));
	}

	public function create_forum(array $values): int {
		return $this->insert('', array_merge($values, array('forum_blog_ID' => get_current_blog_id())));
	}

	public function update_forum(int $forum_id, array $values): void {
		$this->update('', $values, array('forum_ID' => $forum_id, 'forum_blog_ID' => get_current_blog_id()));
	}

	public function delete_forum(int $forum_id): void {
		$this->delete('_posts', array('post_forum_ID' => $forum_id));
		$this->delete('_topics', array('topic_forum_ID' => $forum_id));
		$this->delete('', array('forum_ID' => $forum_id, 'forum_blog_ID' => get_current_blog_id()));
	}

	public function create_topic(int $forum_id, string $title, string $content, int $author, int $stamp, ?string $request_key = null): int {
		$topic_id = $this->insert('_topics', array(
			'topic_forum_ID' => $forum_id, 'topic_title' => $title, 'topic_author' => $author,
			'topic_last_author' => $author, 'topic_stamp' => $stamp, 'topic_last_updated_stamp' => $stamp,
		));
		$result = $this->db->insert($this->table('_posts'), array(
			'post_forum_ID' => $forum_id, 'post_topic_ID' => $topic_id,
			'post_author' => $author, 'post_content' => $content, 'post_stamp' => $stamp,
			'post_request_key' => $request_key,
		));
		if ($result === false) {
			$this->delete('_topics', array('topic_ID' => $topic_id, 'topic_forum_ID' => $forum_id));
			$this->write_result($result);
		}
		$this->recount_topic($topic_id);
		$this->recount_forum($forum_id);
		return $topic_id;
	}

	public function create_post(int $forum_id, int $topic_id, string $content, int $author, int $stamp, ?string $request_key = null): int {
		$id = $this->insert('_posts', array(
			'post_forum_ID' => $forum_id, 'post_topic_ID' => $topic_id,
			'post_author' => $author, 'post_content' => $content, 'post_stamp' => $stamp,
			'post_request_key' => $request_key,
		));
		$this->recount_topic($topic_id);
		$this->recount_forum($forum_id);
		return $id;
	}

	public function update_post(int $forum_id, int $topic_id, int $post_id, string $content): void {
		$this->update('_posts', array('post_content' => $content), array(
			'post_ID' => $post_id, 'post_topic_ID' => $topic_id, 'post_forum_ID' => $forum_id,
		));
	}

	public function delete_post(int $forum_id, int $topic_id, int $post_id): void {
		$this->delete('_posts', array('post_ID' => $post_id, 'post_topic_ID' => $topic_id, 'post_forum_ID' => $forum_id));
		$this->recount_topic($topic_id);
		$this->recount_forum($forum_id);
	}

	public function delete_topic(int $forum_id, int $topic_id): void {
		$this->delete('_posts', array('post_topic_ID' => $topic_id, 'post_forum_ID' => $forum_id));
		$this->delete('_topics', array('topic_ID' => $topic_id, 'topic_forum_ID' => $forum_id));
		$this->recount_forum($forum_id);
	}

	public function set_closed(int $forum_id, int $topic_id, bool $closed): void {
		$this->update('_topics', array('topic_closed' => (int) $closed), array('topic_ID' => $topic_id, 'topic_forum_ID' => $forum_id));
	}

	public function submitted_post(string $key): ?array {
		return $this->row($this->db->prepare(
			"SELECT p.*, t.topic_title FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('_topics')} t ON t.topic_ID = p.post_topic_ID AND t.topic_forum_ID = p.post_forum_ID
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_request_key = %s AND f.forum_blog_ID = %d",
			$key, get_current_blog_id()
		));
	}

	public function set_sticky(int $forum_id, int $topic_id, bool $sticky): void {
		$this->update('_topics', array('topic_sticky' => (int) $sticky), array('topic_ID' => $topic_id, 'topic_forum_ID' => $forum_id));
	}

	public function rename_topic(int $forum_id, int $topic_id, string $title): void {
		$this->update('_topics', array('topic_title' => $title), array('topic_ID' => $topic_id, 'topic_forum_ID' => $forum_id));
	}

	public function post_position(int $forum_id, int $topic_id, int $post_id): int {
		return $this->count($this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_forum_ID = %d AND p.post_topic_ID = %d AND p.post_ID <= %d AND f.forum_blog_ID = %d",
			$forum_id, $topic_id, $post_id, get_current_blog_id()
		));
	}

	public function recount_topic(int $topic_id): void {
		$topic = $this->topic($topic_id);
		if (!$topic) {
			wp_die(esc_html__('Das Thema konnte nicht geladen werden.', 'psforum'), '', array('response' => 404));
		}
		$last = $this->row($this->db->prepare(
			"SELECT post_author, post_stamp FROM {$this->table('_posts')}
			WHERE post_topic_ID = %d AND post_forum_ID = %d ORDER BY post_ID DESC LIMIT 1",
			$topic_id, $topic['topic_forum_ID']
		));
		$values = array('topic_posts' => $this->post_count($topic_id));
		if ($last) {
			$values['topic_last_author'] = (int) $last['post_author'];
			$values['topic_last_updated_stamp'] = (int) $last['post_stamp'];
		}
		$this->update('_topics', $values, array('topic_ID' => $topic_id, 'topic_forum_ID' => (int) $topic['topic_forum_ID']));
	}

	public function recount_forum(int $forum_id): void {
		$posts = $this->count($this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table('_posts')} p
			INNER JOIN {$this->table('')} f ON f.forum_ID = p.post_forum_ID
			WHERE p.post_forum_ID = %d AND f.forum_blog_ID = %d", $forum_id, get_current_blog_id()
		));
		$this->update_forum($forum_id, array('forum_posts' => $posts, 'forum_topics' => $this->topic_count($forum_id)));
	}
}
