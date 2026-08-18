<?php
/**
 * Comments template.
 *
 * @package Odd_Note
 */

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			echo esc_html(
				sprintf(
					_n( '댓글 %s개', '댓글 %s개', get_comments_number(), 'odd-note' ),
					number_format_i18n( get_comments_number() )
				)
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size'=> 48,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	if ( ! comments_open() && get_comments_number() ) {
		echo '<p class="no-comments">' . esc_html__( '댓글이 닫혀 있습니다.', 'odd-note' ) . '</p>';
	}

	comment_form();
	?>
</section>
