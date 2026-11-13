<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class Settings {
	private ?int $forum_limit = null;

	public function values(): array {
		$values = array();
		foreach (array('forums_topics_per_page' => 20, 'forums_posts_per_page' => 20, 'forums_max_forums' => 1, 'forums_upgrades_forums' => 10) as $key => $default) {
			$value = is_multisite() ? get_site_option($key) : get_option($key);
			$values[$key] = max(1, (int) ($value ?: $default));
		}
		$values['forums_enable_upgrades'] = (int) (bool) (is_multisite() ? get_site_option('forums_enable_upgrades') : get_option('forums_enable_upgrades'));
		$policy = is_multisite() ? get_site_option('forums_title_edit_policy', '30_minutes') : get_option('forums_title_edit_policy', '30_minutes');
		$values['forums_title_edit_policy'] = in_array($policy, array('moderators', '30_minutes', 'authors'), true) ? $policy : '30_minutes';
		return $values;
	}

	public function topics_per_page(): int {
		return $this->values()['forums_topics_per_page'];
	}

	public function posts_per_page(): int {
		return $this->values()['forums_posts_per_page'];
	}

	public function forum_limit(): int {
		return $this->forum_limit ?? $this->values()['forums_max_forums'];
	}

	public function initialize_upgrades(): void {
		$this->forum_limit = null;
		$values = $this->values();
		if (!$values['forums_enable_upgrades']) {
			return;
		}
		if (function_exists('upgrades_register_feature')) {
			upgrades_register_feature('68daf8bdc8755fe8f4859024b3054fb8', __('Foren', 'psforum'), __('Zusätzliche Foren', 'psforum'));
			if (upgrades_active_feature('68daf8bdc8755fe8f4859024b3054fb8') === 'active') {
				$this->forum_limit = $values['forums_upgrades_forums'];
			}
		} elseif (function_exists('is_pro_site') && is_pro_site()) {
			$this->forum_limit = $values['forums_upgrades_forums'];
		}
	}

	public function save(): void {
		validate_settings_request();
		$keys = array('forums_topics_per_page', 'forums_posts_per_page', 'forums_max_forums');
		if (function_exists('is_pro_site')) {
			$keys[] = 'forums_upgrades_forums';
			$keys[] = 'forums_enable_upgrades';
		}
		foreach ($keys as $key) {
			save_setting($key, (int) request_value($_POST, $key));
		}
		save_setting('forums_title_edit_policy', request_value($_POST, 'forums_title_edit_policy', '30_minutes'));
	}
}
