<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class FrontController {
	private array $errors = array();

	public function __construct(
		private ForumRepository $repository,
		private ForumService $service,
		private ForumView $view,
		private Settings $settings
	) {}

	public function process_request(): void {
		global $post;
		$action = request_value($_GET, 'action');
		if (!is_singular() || !$post || (!$this->is_write($action) && $action !== 'preview_post')) {
			return;
		}
		preg_match_all('/' . get_shortcode_regex(array('forum')) . '/s', $post->post_content, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			if ($match[1] === '[' && $match[6] === ']') {
				continue;
			}
			$atts = shortcode_parse_atts($match[3]);
			$id = is_array($atts) ? ($atts['id'] ?? $atts[0] ?? '') : '';
			if (is_string($id) && preg_match('/^:?[1-9][0-9]*$/', $id)) {
				$forum_id = absint(ltrim($id, ':'));
				if (request_value($_POST, 'fid') === '' || absint(request_value($_POST, 'fid')) === $forum_id) {
					if ($action === 'preview_post') {
						require_forum($forum_id);
						require_member();
						require_nonce('psforum_preview_post_' . $forum_id);
						nocache_headers();
						wp_send_json_success(array('html' => forums_display_post_content(request_value($_POST, 'post_content'))));
					}
					$this->process($forum_id);
					return;
				}
			}
			if ($matches || request_value($_POST, 'fid', request_value($_GET, 'fid')) !== '') {
				wp_die(esc_html__('Das angeforderte Forum ist auf dieser Seite nicht eingebettet.', 'psforum'), '', array('response' => 404));
			}
		}
	}

	private function is_write(string $action): bool {
		return in_array($action, array('new_topic_process', 'new_post_process', 'edit_post_process', 'delete_post_process', 'delete_topic_process', 'open_topic', 'close_topic', 'pin_topic', 'unpin_topic', 'edit_topic_process'), true);
	}

	public function process(int $forum_id): void {
		validate_front_request($forum_id);
		$action = request_value($_GET, 'action');
		if (!$this->is_write($action)) {
			return;
		}
		$topic_id = in_array($action, array('open_topic', 'close_topic', 'pin_topic', 'unpin_topic'), true)
			? absint(request_value($_GET, 'topic')) : absint(request_value($_POST, 'tid'));
		$post_id = absint(request_value($_POST, 'pid'));
		$page = max(1, (int) request_value($_POST, 'forum_page', '1'));
		if (isset($_POST['Cancel'])) {
			redirect_to(forum_url($forum_id, $topic_id ? array('topic' => $topic_id, 'forum_page' => $page) : array()));
		}
		if ($action === 'edit_topic_process') {
			$title = sanitize_text_field(request_value($_POST, 'topic_title'));
			if ($title === '') {
				$this->errors[$forum_id] = __('Ein Thementitel ist erforderlich.', 'psforum');
				return;
			}
			$this->service->rename_topic($forum_id, $topic_id, $title);
			redirect_to(forum_url($forum_id, array('topic' => $topic_id)));
		}
		if (in_array($action, array('new_topic_process', 'new_post_process', 'edit_post_process'), true)) {
			$content = request_value($_POST, 'post_content');
			$title = request_value($_POST, 'topic_title');
			if (trim(forums_save_post_content($content)) === '' || ($action === 'new_topic_process' && sanitize_text_field($title) === '')) {
				$this->errors[$forum_id] = __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum');
				return;
			}
			if ($action === 'new_topic_process') {
				$topic_id = $this->service->create_topic($forum_id, $title, $content);
				redirect_to(forum_url($forum_id, array('topic' => $topic_id)));
			}
			if ($action === 'new_post_process') {
				$this->service->reply($forum_id, $topic_id, $content);
				$page = max(1, (int) ceil($this->repository->post_count($topic_id, $forum_id) / $this->settings->posts_per_page()));
				redirect_to(forum_url($forum_id, array('topic' => $topic_id, 'forum_page' => $page)));
			}
			$this->service->edit_post($forum_id, $topic_id, $post_id, $content);
			redirect_to(forum_url($forum_id, array('topic' => $topic_id, 'forum_page' => $page), 'post-' . $post_id));
		}
		switch ($action) {
			case 'delete_post_process':
				$this->service->delete_post($forum_id, $topic_id, $post_id);
				break;
			case 'delete_topic_process':
				$this->service->delete_topic($forum_id, $topic_id);
				redirect_to(forum_url($forum_id));
				break;
			case 'open_topic':
			case 'close_topic':
				$this->service->set_closed($forum_id, $topic_id, $action === 'close_topic');
				break;
			case 'pin_topic':
			case 'unpin_topic':
				$this->service->set_sticky($forum_id, $topic_id, $action === 'pin_topic');
				break;
		}
		redirect_to(forum_url($forum_id, array('topic' => $topic_id)));
	}

	public function shortcode($atts): string {
		$atts = is_array($atts) ? $atts : array();
		$id = $atts['id'] ?? $atts[0] ?? '';
		if (!is_string($id) || !preg_match('/^:?[1-9][0-9]*$/', $id)) {
			return $this->view->message(__('Bitte gib eine gültige Forum-ID an.', 'psforum'));
		}
		return $this->embedded(absint(ltrim($id, ':')));
	}

	public function content(string $content): string {
		if (!preg_match('/\\[forum:([0-9]{1,20})\\]/', $content, $matches)) {
			return $content;
		}
		$forum_id = (int) $matches[1];
		return str_replace('[forum:' . $forum_id . ']', '', $content) . $this->embedded($forum_id);
	}

	private function embedded(int $forum_id): string {
		wp_enqueue_style('psforum');
		wp_enqueue_script('psforum');
		$forum = $this->repository->forum($forum_id);
		$appearance = $forum ? $this->view->appearance($forum_id) : array('', '', '', '', 0);
		$style = '--psforum-border-width:' . $appearance[4] . 'px;';
		foreach (array('row', 'alternate', 'header', 'border') as $index => $key) {
			if ($appearance[$index] !== '') {
				$style .= '--psforum-' . $key . ':' . $appearance[$index] . ';';
			}
		}
		$label = $forum ? sprintf(__('Forum: %s', 'psforum'), $forum['forum_name']) : __('Forum', 'psforum');
		return '<section class="psforum" id="psforum-' . $forum_id . '" aria-label="' . esc_attr($label) . '" style="' . esc_attr($style) . '">' . $this->render($forum_id) . '</section>';
	}

	public function render(int $forum_id): string {
		if (!$this->repository->forum($forum_id)) {
			return $this->view->message(__('Ungültiger Forum-Code', 'psforum'));
		}
		$request_forum = request_value($_POST, 'fid', request_value($_GET, 'fid'));
		if ($request_forum === '') {
			$requested_topic = absint(request_value($_GET, 'topic', request_value($_GET, 'tid')));
			$topic = $requested_topic ? $this->repository->topic($requested_topic) : null;
			if ($topic) {
				$request_forum = (string) $topic['topic_forum_ID'];
			}
		}
		if ($request_forum !== '' && (int) $request_forum !== $forum_id) {
			return $this->view->index($forum_id);
		}
		validate_front_request($forum_id);
		$action = request_value($_GET, 'action');
		$page = max(1, (int) request_value($_GET, 'forum_page', '1'));
		$request_forum = request_value($_POST, 'fid', request_value($_GET, 'fid'));
		$matches_forum = $request_forum === '' || (int) $request_forum === $forum_id;
		if ($action === 'edit_topic' || $action === 'edit_topic_process') {
			$topic_id = $action === 'edit_topic' ? absint(request_value($_GET, 'tid')) : absint(request_value($_POST, 'tid'));
			return $this->view->title_form($forum_id, $topic_id, $action === 'edit_topic_process' ? request_value($_POST, 'topic_title') : null, $this->errors[$forum_id] ?? '');
		}
		if (in_array($action, array('new_topic_process', 'new_post_process', 'edit_post_process'), true)) {
			$mode = array('new_topic_process' => 'new', 'new_post_process' => 'reply', 'edit_post_process' => 'edit')[$action];
			$error = $this->errors[$forum_id] ?? __('Du musst alle erforderlichen Felder ausfüllen...', 'psforum');
			return $this->view->post_form(
				$mode, $forum_id, absint(request_value($_POST, 'tid')), absint(request_value($_POST, 'pid')),
				max(1, (int) request_value($_POST, 'forum_page', '1')), $this->form_values(), $error
			);
		}
		if ($action === 'new_topic' && $matches_forum) {
			return $this->view->post_form('new', $forum_id, 0, 0, $page, $this->form_values());
		}
		$topic_id = absint(request_value($_GET, 'topic'));
		if ($topic_id) {
			if (!$this->repository->topic($topic_id, $forum_id)) {
				return $this->view->message(__('Ungültiges Thema.', 'psforum'));
			}
			return $this->view->topic($forum_id, $topic_id, $page, urldecode(request_value($_GET, 'msg')));
		}
		$topic_id = absint(request_value($_GET, 'tid'));
		$post_id = absint(request_value($_GET, 'pid'));
		if ($action === 'edit_post') {
			return $this->view->post_form('edit', $forum_id, $topic_id, $post_id, $page);
		}
		if ($action === 'delete_post' || $action === 'delete_topic') {
			return $this->view->delete_confirmation($forum_id, $topic_id, $action === 'delete_post' ? $post_id : 0, $page);
		}
		$query = $matches_forum ? request_value($_POST, 'search', request_value($_GET, 'search')) : '';
		return $query !== '' ? $this->view->search($forum_id, $query) : $this->view->index($forum_id, $page);
	}

	public function form_values(): array {
		return array('post_content' => request_value($_POST, 'post_content'), 'topic_title' => request_value($_POST, 'topic_title'));
	}
}
