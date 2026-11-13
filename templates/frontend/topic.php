<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<header class="psforum-heading">
	<h3><?php echo esc_html($data['topic']['topic_title']); ?></h3>
	<?php if ((int) $data['topic']['topic_closed']) : ?><span class="psforum-badge"><?php esc_html_e('Geschlossen', 'psforum'); ?></span><?php endif; ?>
	<?php if ((int) $data['topic']['topic_sticky']) : ?><span class="psforum-badge"><?php esc_html_e('Angeheftet', 'psforum'); ?></span><?php endif; ?>
</header>
<?php if ($data['message'] !== '') : ?><p role="status"><?php echo esc_html($data['message']); ?></p><?php endif; ?>
<?php echo $data['metadata']; ?>
<?php echo $data['navigation']; ?>
<?php echo $data['pagination']; ?>
<?php echo $data['posts']; ?>
<?php echo $data['pagination']; ?>
<?php echo $data['reply']; ?>
