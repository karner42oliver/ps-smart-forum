<?php
/*
Plugin Name: Quick Forum
Plugin URI: https://psource.eimen.net/psource/quick-forum/
Description: Ermöglicht jedem Blog, eigene Foren zu haben - eingebettet in jede Seite oder jeden Beitrag.
Author: PSOURCE
Author URI: https://psource.eimen.net
Version: 1.0.0
Requires at least: 4.9
Requires PHP: 8.0
Text Domain: psforum
Domain Path: /languages
*/

/*
Copyright 2020-2026 PSOURCE (https://psource.eimen.net)
Author - DerN3rd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA.
*/

namespace PSource\QuickForum;

defined('ABSPATH') || exit;

const VERSION = '1.0.0';
const PLUGIN_FILE = __FILE__;

if (!defined('FORUM_DEMO_FOR_NON_SUPPORTER')) {
	define('FORUM_DEMO_FOR_NON_SUPPORTER', true);
}

require_once __DIR__ . '/forums-framework.php';
foreach (array('Schema', 'Settings', 'ForumRepository', 'ForumService', 'Template', 'ForumView', 'FrontController', 'AdminController', 'Plugin', 'compatibility') as $file) {
	require_once __DIR__ . '/includes/' . $file . '.php';
}

Plugin::instance()->register();
