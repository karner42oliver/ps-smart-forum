<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<nav class="psforum-pagination" aria-label="<?php esc_attr_e('Forum-Seiten', 'psforum'); ?>">
	<?php if ($data['previous'] !== '') : ?><a rel="prev" href="<?php echo esc_url($data['previous']); ?>">&laquo; <?php esc_html_e('Bisherige', 'psforum'); ?></a><?php endif; ?>
	<span><?php echo esc_html(sprintf(__('Zeige %1s > %2s von %3s Beiträgen', 'psforum'), $data['low'], $data['high'], $data['count'])); ?></span>
	<?php if ($data['next'] !== '') : ?><a rel="next" href="<?php echo esc_url($data['next']); ?>"><?php esc_html_e('Weitere', 'psforum'); ?> &raquo;</a><?php endif; ?>
</nav>
