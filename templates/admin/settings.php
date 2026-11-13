<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<div class="wrap">
	<h2><?php esc_html_e('Forum Einstellungen', 'psforum'); ?></h2>
	<form name="form1" method="post" action="<?php echo esc_url($data['url']); ?>">
		<?php echo $data['nonce']; ?>
		<table class="form-table">
			<?php foreach (array(
				'forums_topics_per_page' => __('Themen pro Seite', 'psforum'),
				'forums_posts_per_page' => __('Beiträge pro Seite', 'psforum'),
				'forums_max_forums' => __('Maximale Anzahl von Foren', 'psforum'),
				'forums_upgrades_forums' => __('Maximale Anzahl von Foren für Pro Blogs', 'psforum'),
				'forums_enable_upgrades' => __('Upgrades zulassen', 'psforum'),
			) as $key => $label) : ?>
				<?php if (!$data['pro_available'] && in_array($key, array('forums_upgrades_forums', 'forums_enable_upgrades'), true)) { continue; } ?>
				<tr valign="top">
					<th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
					<td><input type="text" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" size="3" value="<?php echo (int) $data['values'][$key]; ?>" /></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="forums_title_edit_policy"><?php esc_html_e('Thementitel bearbeiten', 'psforum'); ?></label></th>
				<td><select name="forums_title_edit_policy" id="forums_title_edit_policy">
					<?php foreach (array('moderators' => __('Nur Moderatoren', 'psforum'), '30_minutes' => __('Autoren 30 Minuten, Moderatoren jederzeit', 'psforum'), 'authors' => __('Autoren und Moderatoren jederzeit', 'psforum')) as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($data['values']['forums_title_edit_policy'], $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="Submit" value="<?php esc_attr_e('Speichern', 'psforum'); ?>" /></p>
	</form>
</div>
