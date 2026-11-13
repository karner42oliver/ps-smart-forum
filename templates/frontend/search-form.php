<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<?php if ($data['visible']) : ?>
	<form class="psforum-search" name="new_t" role="search" method="get" action="<?php echo esc_url($data['url']); ?>">
		<?php foreach ($data['query_fields'] as $key => $value) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" /><?php endforeach; ?>
		<input type="hidden" name="fid" value="<?php echo $data['forum_id']; ?>" />
		<label for="psforum-search-<?php echo $data['forum_id']; ?>"><?php esc_html_e('Forum durchsuchen', 'psforum'); ?></label>
		<div class="psforum-search-fields">
			<input type="search" name="search" id="psforum-search-<?php echo $data['forum_id']; ?>" value="<?php echo esc_attr($data['query']); ?>" />
			<button type="submit"><?php esc_html_e('Suche', 'psforum'); ?></button>
		</div>
	</form>
<?php endif; ?>
