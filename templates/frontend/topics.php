<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<?php if ($data['topics']) : ?>
	<ul class="psforum-topics">
		<?php foreach ($data['topics'] as $topic) : ?>
			<li class="psforum-topic-card">
				<div>
					<?php if ((int) $topic['topic_sticky']) : ?><span class="psforum-badge"><?php esc_html_e('Angeheftet', 'psforum'); ?></span><?php endif; ?>
					<?php if ((int) $topic['topic_closed']) : ?><span class="psforum-badge"><?php esc_html_e('Geschlossen', 'psforum'); ?></span><?php endif; ?>
					<h4><a href="<?php echo esc_url($topic['url']); ?>"><?php echo esc_html($topic['topic_title']); ?></a></h4>
				</div>
				<div class="psforum-topic-summary">
					<span><?php echo esc_html(sprintf(__('%s Beiträge', 'psforum'), (int) $topic['topic_posts'])); ?></span>
					<a href="<?php echo esc_url($topic['last_url']); ?>"><?php esc_html_e('Letzte Antwort', 'psforum'); ?>: <?php echo esc_html($topic['author']); ?></a>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
<?php else : ?>
	<p class="psforum-empty"><?php esc_html_e('Keine Themen zum Anzeigen...', 'psforum'); ?></p>
<?php endif; ?>
