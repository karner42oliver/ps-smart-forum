<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<div class="forum-topic" id="topic-<?php echo $data['topic_id']; ?>">
	<?php foreach ($data['posts'] as $post) : ?>
		<article class="forum-post" id="post-<?php echo (int) $post['post_ID']; ?>">
			<header class="forum-post-author">
				<?php echo $post['avatar']; ?>
				<strong><?php echo esc_html($post['author']); ?></strong>
			</header>
			<div class="psforum-post-main">
				<div class="psforum-post-content"><?php echo $post['html']; ?></div>
				<footer class="forum-post-meta">
					<span><?php esc_html_e('Veröffentlicht: ', 'psforum'); ?><time><?php echo esc_html($post['date']); ?></time></span>
					<a href="<?php echo esc_url($post['url']); ?>" aria-label="<?php esc_attr_e('Direktlink zu diesem Beitrag', 'psforum'); ?>">#<?php echo (int) $post['post_ID']; ?></a>
					<?php if ($post['edit_url'] !== '') : ?><a href="<?php echo esc_url($post['edit_url']); ?>"><?php esc_html_e('Bearbeiten', 'psforum'); ?></a><?php endif; ?>
					<?php if ($post['delete_url'] !== '') : ?><a href="<?php echo esc_url($post['delete_url']); ?>"><?php esc_html_e('Löschen', 'psforum'); ?></a><?php endif; ?>
				</footer>
			</div>
		</article>
	<?php endforeach; ?>
	<?php if (!$data['posts']) : ?><p class="psforum-empty"><?php esc_html_e('Keine Beiträge zum Anzeigen...', 'psforum'); ?></p><?php endif; ?>
</div>
