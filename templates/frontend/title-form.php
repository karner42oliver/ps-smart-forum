<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<h3><?php esc_html_e('Thementitel bearbeiten', 'psforum'); ?></h3>
<?php if ($data['error'] !== '') : ?><p class="psforum-notice" role="alert"><?php echo esc_html($data['error']); ?></p><?php endif; ?>
<form method="post" action="<?php echo esc_url($data['url']); ?>">
	<?php echo $data['nonce']; ?>
	<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
	<input type="hidden" name="tid" value="<?php echo $data['topic_id']; ?>" />
	<p class="psforum-field">
		<label for="<?php echo esc_attr($data['field_id']); ?>"><?php esc_html_e('Titel:', 'psforum'); ?> *</label>
		<input type="text" name="topic_title" id="<?php echo esc_attr($data['field_id']); ?>" value="<?php echo esc_attr($data['title']); ?>" required />
	</p>
	<div class="psforum-actions">
		<button type="submit"><?php esc_html_e('Speichern', 'psforum'); ?></button>
		<button type="submit" name="Cancel" formnovalidate><?php esc_html_e('Abbrechen', 'psforum'); ?></button>
	</div>
</form>
