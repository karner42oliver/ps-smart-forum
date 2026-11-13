<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<h3><?php echo $data['post_id'] ? esc_html__('Beitrag löschen', 'psforum') : esc_html__('Thema löschen', 'psforum'); ?></h3>
<p><?php echo $data['post_id'] ? esc_html__('Möchtest Du diesen Beitrag wirklich löschen?', 'psforum') : esc_html__('Möchtest Du dieses Thema wirklich löschen?', 'psforum'); ?></p>
<form name="delete_topic" method="post" action="<?php echo esc_url($data['url']); ?>">
	<?php echo $data['nonce']; ?>
	<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
	<input type="hidden" name="tid" value="<?php echo $data['topic_id']; ?>" />
	<?php if ($data['post_id']) : ?><input type="hidden" name="pid" value="<?php echo $data['post_id']; ?>" /><?php endif; ?>
	<input type="hidden" name="forum_page" value="<?php echo $data['page']; ?>" />
	<div class="psforum-actions">
		<button type="submit" name="Submit"><?php esc_html_e('Löschen', 'psforum'); ?></button>
		<button type="submit" name="Cancel"><?php esc_html_e('Abbrechen', 'psforum'); ?></button>
	</div>
</form>
