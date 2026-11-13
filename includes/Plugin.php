<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class Plugin {
	private static ?self $instance = null;
	private ForumRepository $repository;
	private Settings $settings;
	private ForumService $service;
	private ForumView $view;
	private FrontController $front;
	private AdminController $admin;

	private function __construct(\wpdb $db) {
		$this->repository = new ForumRepository($db);
		$this->settings = new Settings();
		$templates = new Template();
		$this->service = new ForumService($this->repository, $this->settings);
		$this->view = new ForumView($this->repository, $this->settings, $templates);
		$this->front = new FrontController($this->repository, $this->service, $this->view, $this->settings);
		$this->admin = new AdminController($this->repository, $this->service, $this->settings, $templates);
		$this->sync_legacy_settings();
	}

	public static function instance(): self {
		if (self::$instance === null) {
			global $wpdb;
			self::$instance = new self($wpdb);
		}
		return self::$instance;
	}

	public function register(): void {
		add_action('admin_init', __NAMESPACE__ . '\\install_schema');
		add_action('admin_init', array($this->admin, 'process_request'));
		register_activation_hook(PLUGIN_FILE, __NAMESPACE__ . '\\install_schema');
		add_action('init', array($this, 'initialize'));
		add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
		add_action('template_redirect', array($this->front, 'process_request'));
		add_action('admin_print_styles-toplevel_page_psforum', __NAMESPACE__ . '\\forums_admin_styles');
		add_action('admin_print_scripts-toplevel_page_psforum', __NAMESPACE__ . '\\forums_admin_scripts');
		add_action('admin_menu', array($this, 'register_forum_page'));
		add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', array($this, 'register_settings_page'));
		add_filter('wpabar_menuitems', __NAMESPACE__ . '\\forums_admin_bar');
		add_shortcode('forum', array($this->front, 'shortcode'));
	}

	public function frontend_assets(): void {
		wp_register_style('psforum', plugins_url('assets/css/forums.css', PLUGIN_FILE), array(), VERSION);
		wp_register_script('psforum', plugins_url('assets/js/forums.js', PLUGIN_FILE), array(), VERSION, true);
		global $post;
		if ($post && has_shortcode($post->post_content, 'forum')) {
			wp_enqueue_style('psforum');
			wp_enqueue_script('psforum');
		}
	}

	private function sync_legacy_settings(): void {
		foreach ($this->settings->values() as $key => $value) {
			$GLOBALS[$key] = $value;
		}
		$GLOBALS['forums_max_forums'] = $this->settings->forum_limit();
		$GLOBALS['forums_current_version'] = VERSION;
	}

	public function initialize(): void {
		$this->settings->initialize_upgrades();
		$this->sync_legacy_settings();
		$domain_path = dirname(plugin_basename(PLUGIN_FILE)) . '/languages';
		if (defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename(PLUGIN_FILE))) {
			load_muplugin_textdomain('psforum', $domain_path);
		} else {
			load_plugin_textdomain('psforum', false, $domain_path);
		}
		if (is_admin()) {
			wp_register_script('forums_admin_js', plugins_url('assets/js/forums-admin.js', PLUGIN_FILE), array('wp-color-picker'), VERSION, true);
			wp_register_style('forums_admin_css', plugins_url('assets/css/wp_admin.css', PLUGIN_FILE), array('wp-color-picker'), VERSION);
		}
	}

	public function register_forum_page(): void {
		$demo = $this->settings->values()['forums_enable_upgrades'] && FORUM_DEMO_FOR_NON_SUPPORTER && function_exists('is_pro_site') && !is_pro_site();
		add_menu_page(
			__('Foren', 'psforum'), __('Foren', 'psforum'), 'manage_options', 'psforum',
			__NAMESPACE__ . ($demo ? '\\forums_non_supporter_output' : '\\forums_manage_output'), 'dashicons-format-status'
		);
	}

	public function register_settings_page(): void {
		add_submenu_page(
			is_network_admin() ? 'settings.php' : 'options-general.php',
			__('Forum Einstellungen', 'psforum'), __('Foren', 'psforum'),
			is_network_admin() ? 'manage_network_options' : 'manage_options',
			'psource_forum_settings', __NAMESPACE__ . '\\forums_manage_options_output', 20
		);
	}

	public function repository(): ForumRepository {
		return $this->repository;
	}

	public function service(): ForumService {
		return $this->service;
	}

	public function view(): ForumView {
		return $this->view;
	}

	public function front(): FrontController {
		return $this->front;
	}

	public function admin(): AdminController {
		return $this->admin;
	}

	public function settings(): Settings {
		return $this->settings;
	}
}
