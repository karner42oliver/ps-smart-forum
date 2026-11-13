<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<?php if (!$data['logged_in']) : ?>
	<h3><?php echo esc_html($data['title']); ?></h3>
	<p><?php echo wp_kses_post($data['login_message']); ?></p>
<?php elseif (!$data['closed']) : ?>
	<h3><?php echo esc_html($data['title']); ?></h3>
	<?php if ($data['error'] !== '') : ?><p class="psforum-notice" role="alert"><?php echo esc_html($data['error']); ?></p><?php endif; ?>
	<form class="psforum-post-form" <?php if ($data['mode'] === 'reply') : ?>id="forum-topic-reply-form-<?php echo $data['forum_id']; ?>"<?php endif; ?> name="<?php echo $data['mode'] === 'edit' ? 'edit_post' : 'new_topic'; ?>" method="post" action="<?php echo esc_url($data['url']); ?>" data-preview-url="<?php echo esc_url($data['preview_url']); ?>" data-preview-nonce="<?php echo esc_attr($data['preview_nonce']); ?>" data-preview-error="<?php esc_attr_e('Die Vorschau konnte nicht geladen werden. Bitte versuche es erneut.', 'psforum'); ?>">
		<?php echo $data['nonce']; ?>
		<input type="hidden" name="_psforum_request" value="<?php echo esc_attr($data['request_key']); ?>" />
		<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
		<?php if ($data['topic_id']) : ?><input type="hidden" name="tid" value="<?php echo $data['topic_id']; ?>" /><?php endif; ?>
		<?php if ($data['post_id']) : ?><input type="hidden" name="pid" value="<?php echo $data['post_id']; ?>" /><?php endif; ?>
		<?php if ($data['mode'] === 'edit') : ?><input type="hidden" name="forum_page" value="<?php echo $data['page']; ?>" /><?php endif; ?>
		<?php if ($data['mode'] === 'new') : ?>
			<p class="psforum-field">
				<label for="<?php echo esc_attr($data['field_id']); ?>-title"><?php esc_html_e('Titel:', 'psforum'); ?> *</label>
				<input type="text" name="topic_title" id="<?php echo esc_attr($data['field_id']); ?>-title" value="<?php echo esc_attr($data['topic_title']); ?>" required />
			</p>
		<?php endif; ?>
		<p class="psforum-field">
			<label for="<?php echo esc_attr($data['field_id']); ?>-content"><?php esc_html_e('Beitrag:', 'psforum'); ?> *</label>
			<textarea name="post_content" id="<?php echo esc_attr($data['field_id']); ?>-content" rows="7" required aria-describedby="<?php echo esc_attr($data['field_id']); ?>-help"><?php echo esc_textarea($data['content']); ?></textarea>
			<small id="<?php echo esc_attr($data['field_id']); ?>-help"><?php esc_html_e('Erforderlich. Erlaubt sind Absätze, Links, Bilder, Listen und einfache HTML-Formatierung. Die Vorschau zeigt das gefilterte Ergebnis.', 'psforum'); ?></small>
		</p>
		<div class="psforum-actions">
			<button type="submit" name="Submit"><?php echo $data['mode'] === 'edit' ? esc_html__('Aktualisieren', 'psforum') : esc_html__('Beitrag senden', 'psforum'); ?></button>
			<button type="button" class="psforum-preview-button" aria-controls="<?php echo esc_attr($data['field_id']); ?>-preview" aria-expanded="false" hidden><?php esc_html_e('Vorschau', 'psforum'); ?></button>
			<?php if ($data['mode'] === 'edit') : ?><button type="submit" name="Cancel" formnovalidate><?php esc_html_e('Abbrechen', 'psforum'); ?></button><?php endif; ?>
		</div>
		<div id="<?php echo esc_attr($data['field_id']); ?>-preview" class="psforum-preview" aria-live="polite" hidden></div>
	</form>
<?php endif; ?>
