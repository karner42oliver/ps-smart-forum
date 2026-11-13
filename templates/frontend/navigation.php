<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<nav class="forum-navigation psforum-actions" aria-label="<?php esc_attr_e('Themenaktionen', 'psforum'); ?>">
	<a href="<?php echo esc_url($data['index_url']); ?>"><?php esc_html_e('Zurück zum Index', 'psforum'); ?></a>
	<?php if ($data['edit_url'] !== '') : ?><a href="<?php echo esc_url($data['edit_url']); ?>"><?php esc_html_e('Thementitel bearbeiten', 'psforum'); ?></a><?php endif; ?>
	<?php if ($data['moderator']) : ?>
		<a href="<?php echo esc_url($data['delete_url']); ?>"><?php esc_html_e('Thema löschen', 'psforum'); ?></a>
		<form method="post" class="forum-topic-toggle" action="<?php echo esc_url($data['action_url']); ?>">
			<?php echo $data['nonce']; ?>
			<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
			<button type="submit"><?php echo $data['closed'] ? esc_html__('Thema öffnen', 'psforum') : esc_html__('Thema schließen', 'psforum'); ?></button>
		</form>
		<form method="post" class="forum-topic-pin" action="<?php echo esc_url($data['pin_url']); ?>">
			<?php echo $data['pin_nonce']; ?>
			<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
			<button type="submit"><?php echo $data['sticky'] ? esc_html__('Loslösen', 'psforum') : esc_html__('Anheften', 'psforum'); ?></button>
		</form>
	<?php endif; ?>
</nav>
