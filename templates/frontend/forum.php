<?php
namespace PSource\QuickForum;
defined('ABSPATH') || exit;
?>
<header class="psforum-heading">
	<h3><?php esc_html_e('THEMEN', 'psforum'); ?></h3>
	<?php if ($data['can_post']) : ?><a class="psforum-button" href="<?php echo esc_url($data['new_topic_url']); ?>"><?php esc_html_e('Neues Thema', 'psforum'); ?></a><?php endif; ?>
</header>
<?php echo $data['topics']; ?>
