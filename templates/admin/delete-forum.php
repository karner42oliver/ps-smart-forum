<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<div class="wrap">
	<h2><?php esc_html_e('Forum entfernen', 'psforum'); ?></h2>
	<p><?php esc_html_e('Möchtest Du dieses Forum wirklich entfernen? Alle Themen und Beiträge werden gelöscht.', 'psforum'); ?></p>
	<form name="step_one" method="post" action="<?php echo esc_url($data['url']); ?>">
		<?php echo $data['nonce']; ?>
		<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
		<p class="submit">
			<input type="submit" name="Submit" value="<?php esc_attr_e('Fortsetzen', 'psforum'); ?>" />
			<input type="submit" name="Cancel" value="<?php esc_attr_e('Abbrechen', 'psforum'); ?>" />
		</p>
	</form>
</div>
