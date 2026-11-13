<?php

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

final class Template {
	public function render(string $name, array $data = array()): string {
		$path = dirname(__DIR__) . '/templates/' . $name . '.php';
		if (!preg_match('/^[a-z0-9\/-]+$/', $name) || !is_file($path)) {
			throw new \LogicException('Unknown Quick Forum template: ' . $name);
		}
		$level = ob_get_level();
		ob_start();
		try {
			require $path;
			return (string) ob_get_clean();
		} finally {
			while (ob_get_level() > $level) {
				ob_end_clean();
			}
		}
	}
}
