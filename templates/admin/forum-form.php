<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<div class="wrap">
	<h2><?php echo $data['forum_id'] ? esc_html__('Forum bearbeiten', 'psforum') : esc_html__('Neues Forum', 'psforum'); ?></h2>
	<?php if ($data['error']) : ?><p class="error_text" role="alert"><?php esc_html_e('Bitte fülle alle ERFORDERLICHEN Felder aus und speichere dann erneut!', 'psforum'); ?></p><?php endif; ?>
	<form name="form1" method="post" action="<?php echo esc_url($data['url']); ?>">
		<?php echo $data['nonce']; ?>
		<?php if ($data['forum_id']) : ?><input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" /><?php endif; ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="forum_name"><?php esc_html_e('Foren-Name', 'psforum'); ?></label></th>
				<td><input type="text" name="forum_name" id="forum_name" style="width:95%" value="<?php echo esc_attr($data['values']['forum_name']); ?>" /><br /><?php esc_html_e('Erforderlich', 'psforum'); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="forum_description"><?php esc_html_e('Beschreibung', 'psforum'); ?></label></th>
				<td><textarea name="forum_description" id="forum_description" style="width:95%" rows="5"><?php echo esc_textarea((string) $data['values']['forum_description']); ?></textarea><br /><?php esc_html_e('Optional', 'psforum'); ?></td>
			</tr>
			<?php foreach (array(
				'forum_color_one' => __('Hauptfarbe', 'psforum'),
				'forum_color_two' => __('Sekundäre Farbe', 'psforum'),
				'forum_color_header' => __('Header Farbe', 'psforum'),
				'forum_color_border' => __('Rahmen Farbe', 'psforum'),
			) as $key => $label) : ?>
				<tr valign="top">
					<th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
					<td><input type="text" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" class="forum_color" maxlength="7" value="<?php echo esc_attr((string) $data['values'][$key]); ?>" /><br /><?php esc_html_e('Optional - Beispiel: #000000 ODER #FFFFFF', 'psforum'); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr valign="top">
				<th scope="row"><label for="forum_border_size"><?php esc_html_e('Rahmengröße', 'psforum'); ?></label></th>
				<td><select name="forum_border_size" id="forum_border_size">
					<?php for ($size = 0; $size <= 5; $size++) : ?>
						<option value="<?php echo $size; ?>" <?php selected((int) $data['values']['forum_border_size'], $size); ?>><?php echo $size; ?>px</option>
					<?php endfor; ?>
				</select></td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="Submit" value="<?php echo $data['forum_id'] ? esc_attr__('Änderungen speichern', 'psforum') : esc_attr__('Speichern', 'psforum'); ?>" /></p>
	</form>
</div>
