<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<ul class="forum-topic-meta">
	<li><?php esc_html_e('Letzte Aktualisierung: ', 'psforum'); ?><time><?php echo esc_html($data['date']); ?></time></li>
	<li><?php esc_html_e('Letzte Antwort von: ', 'psforum'); ?><?php echo esc_html($data['author']); ?></li>
</ul>
