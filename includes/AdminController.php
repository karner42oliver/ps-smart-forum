<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class AdminController {
	private ?array $submitted_values = null;

	public function __construct(
		private ForumRepository $repository,
		private ForumService $service,
		private Settings $settings,
		private Template $templates
	) {}

	public function process_request(): void {
		$page = request_value($_GET, 'page');
		if (!in_array($page, array('psforum', 'psource_forum_settings'), true)) {
			return;
		}
		$action = request_value($_GET, 'action');
		if ($page === 'psource_forum_settings' && $action === 'update_settings') {
			$this->settings->save();
			redirect_to(add_query_arg('page', 'psource_forum_settings', $this->settings_url()));
		}
		if ($page !== 'psforum' || !in_array($action, array('new_forum_process', 'edit_forum_process', 'delete_forum_process'), true)) {
			return;
		}
		$forum_id = absint(request_value($_POST, 'fid'));
		if ($action === 'delete_forum_process') {
			validate_admin_forum_request($action);
			if (isset($_POST['Cancel'])) {
				redirect_to(admin_url('admin.php?page=psforum'));
			}
			$this->service->delete_forum($forum_id);
		} else {
			$this->submitted_values = $this->service->save_forum($action, $forum_id);
			if ($this->submitted_values['forum_name'] === '') {
				return;
			}
		}
		redirect_to(admin_url('admin.php?page=psforum&updated=true'));
	}

	private function settings_url(): string {
		return is_network_admin() ? network_admin_url('settings.php') : admin_url('options-general.php');
	}

	public function render_settings(): string {
		if (!current_user_can(is_multisite() ? 'manage_network_options' : 'manage_options')) {
			wp_die(esc_html__('Zugriff verweigert.', 'psforum'), '', array('response' => 403));
		}
		if (request_value($_GET, 'action') === 'update_settings') {
			validate_settings_request();
		}
		return $this->templates->render('admin/settings', array(
			'values' => $this->settings->values(), 'pro_available' => function_exists('is_pro_site'),
			'url' => add_query_arg(array('page' => 'psource_forum_settings', 'action' => 'update_settings'), $this->settings_url()),
			'nonce' => nonce_field('psforum_update_settings'),
		));
	}

	public function render(): string {
		require_moderator();
		$action = request_value($_GET, 'action');
		$forum_id = absint(request_value($_GET, 'fid'));
		switch ($action) {
			case 'new_forum':
				$this->service->require_available_forum();
				return $this->forum_form(0, forum_form_values());
			case 'edit_forum':
				require_forum($forum_id);
				return $this->forum_form($forum_id, $this->repository->forum($forum_id));
			case 'new_forum_process':
			case 'edit_forum_process':
				$values = $this->submitted_values ?? validate_admin_forum_request($action);
				return $this->forum_form(
					$action === 'edit_forum_process' ? absint(request_value($_POST, 'fid')) : 0,
					$values, $values['forum_name'] === ''
				);
			case 'delete_forum':
				require_forum($forum_id);
				return $this->templates->render('admin/delete-forum', array(
					'forum_id' => $forum_id,
					'url' => admin_url('admin.php?page=psforum&action=delete_forum_process'),
					'nonce' => nonce_field('psforum_delete_forum_process'),
				));
			case 'delete_forum_process':
				validate_admin_forum_request($action);
				break;
		}
		return $this->forum_list();
	}

	private function forum_form(int $forum_id, array $values, bool $error = false): string {
		$action = $forum_id ? 'edit_forum_process' : 'new_forum_process';
		return $this->templates->render('admin/forum-form', array(
			'forum_id' => $forum_id, 'values' => $values, 'error' => $error,
			'url' => add_query_arg(array('page' => 'psforum', 'action' => $action), admin_url('admin.php')),
			'nonce' => nonce_field('psforum_' . $action),
		));
	}

	private function forum_list(): string {
		global $psts, $blog_id;
		$forums = $this->repository->forums();
		foreach ($forums as &$forum) {
			$forum['edit_url'] = add_query_arg(array('page' => 'psforum', 'action' => 'edit_forum', 'fid' => (int) $forum['forum_ID']), admin_url('admin.php'));
			$forum['delete_url'] = add_query_arg(array('page' => 'psforum', 'action' => 'delete_forum', 'fid' => (int) $forum['forum_ID']), admin_url('admin.php'));
		}
		unset($forum);
		$upgrades = (bool) $this->settings->values()['forums_enable_upgrades'];
		$advertise = count($forums) >= $this->settings->forum_limit() && $upgrades
			&& function_exists('upgrades_active_feature')
			&& upgrades_active_feature('68daf8bdc8755fe8f4859024b3054fb8') !== 'active';
		$checkout_url = '';
		if ($upgrades && !$forums && function_exists('is_pro_site') && !is_pro_site() && function_exists('psts_hide_ads') && !psts_hide_ads()) {
			$checkout_url = $psts->checkout_url($blog_id);
		}
		return $this->templates->render('admin/forums', array(
			'forums' => $forums, 'can_create' => count($forums) < $this->settings->forum_limit(),
			'new_url' => admin_url('admin.php?page=psforum&action=new_forum'),
			'advertisement' => $advertise ? $this->advertisement() : '',
			'empty_upgrade' => $upgrades && !$forums, 'checkout_url' => $checkout_url,
		));
	}

	public function advertisement(): string {
		return $this->templates->render('admin/upgrade', array('limit' => $this->settings->forum_limit()));
	}
}
