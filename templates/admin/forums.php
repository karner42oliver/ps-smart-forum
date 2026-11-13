<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<div class="wrap">
	<h2>
		<?php esc_html_e('Foren verwalten', 'psforum'); ?>
		<?php if ($data['can_create']) : ?>(<a href="<?php echo esc_url($data['new_url']); ?>"><?php esc_html_e('Neues Forum', 'psforum'); ?></a>)<?php endif; ?>
	</h2>
	<?php echo $data['advertisement']; ?>
	<?php if ($data['empty_upgrade']) : ?>
		<?php if ($data['checkout_url'] !== '') : ?>
			<div id="message" class="error"><p><a href="<?php echo esc_url($data['checkout_url']); ?>"><?php esc_html_e('Aktualisiere Deinen Blog jetzt, um Foren hinzuzufügen, den Speicherplatz zu vergrößern, Videos einzubetten und mehr.', 'psforum'); ?></a></p></div>
		<?php else : ?>
			<p><a href="<?php echo esc_url($data['new_url']); ?>"><?php esc_html_e('Klicke hier um ein neues Forum hinzuzufügen.', 'psforum'); ?></a></p>
		<?php endif; ?>
	<?php else : ?>
		<table cellpadding="3" cellspacing="3" width="100%" class="widefat">
			<thead><tr>
				<th scope="col">ID</th><th scope="col"><?php esc_html_e('Name', 'psforum'); ?></th>
				<th scope="col"><?php esc_html_e('Themen', 'psforum'); ?></th><th scope="col"><?php esc_html_e('Beiträge', 'psforum'); ?></th>
				<th scope="col">Shortcode</th><th scope="col"><?php esc_html_e('Aktionen', 'psforum'); ?></th><th scope="col"></th>
			</tr></thead>
			<tbody id="the-list">
				<?php foreach ($data['forums'] as $index => $forum) : ?>
					<tr class="<?php echo $index % 2 === 0 ? 'alternate' : ''; ?>">
						<td valign="top"><strong><?php echo (int) $forum['forum_ID']; ?></strong></td>
						<td valign="top"><?php echo esc_html(stripslashes($forum['forum_name'])); ?></td>
						<td valign="top"><?php echo (int) $forum['forum_topics']; ?></td>
						<td valign="top"><?php echo (int) $forum['forum_posts']; ?></td>
						<td valign="top"><?php echo esc_html('[forum id="' . (int) $forum['forum_ID'] . '"]'); ?></td>
						<td valign="top"><a href="<?php echo esc_url($forum['edit_url']); ?>" rel="permalink" class="edit"><?php esc_html_e('Bearbeiten', 'psforum'); ?></a></td>
						<td valign="top"><a href="<?php echo esc_url($forum['delete_url']); ?>" rel="permalink" class="delete"><?php esc_html_e('Entfernen', 'psforum'); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
