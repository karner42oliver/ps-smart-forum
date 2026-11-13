<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<h3><?php esc_html_e('Suchergebnisse', 'psforum'); ?></h3>
<?php echo $data['search_form']; ?>
<?php if ($data['results']) : ?>
	<ol class="psforum-results">
		<?php foreach ($data['results'] as $result) : ?>
			<li>
				<h4><a href="<?php echo esc_url($result['url']); ?>"><?php echo esc_html($result['topic_title']); ?></a></h4>
				<div class="psforum-post-content"><?php echo $result['html']; ?></div>
			</li>
		<?php endforeach; ?>
	</ol>
<?php else : ?>
	<p class="psforum-empty"><?php esc_html_e('Keine Treffer...', 'psforum'); ?></p>
<?php endif; ?>
