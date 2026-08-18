<?php
/**
 * Not found template.
 *
 * @package Odd_Note
 */

get_header();
?>

<main id="main-content" class="not-found section-shell" tabindex="-1">
	<p class="not-found__code reveal" aria-hidden="true">404</p>
	<div class="not-found__copy reveal">
		<p class="section-kicker"><?php esc_html_e( 'LOST, BUT CURIOUS', 'odd-note' ); ?></p>
		<h1><?php esc_html_e( '이 페이지는 다른 생각으로 이동했습니다.', 'odd-note' ); ?></h1>
		<p><?php esc_html_e( '검색하거나 첫 화면에서 새로운 이야기를 골라보세요.', 'odd-note' ); ?></p>
		<?php get_search_form(); ?>
		<a class="text-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" data-cursor="HOME"><?php esc_html_e( '첫 화면으로 돌아가기', 'odd-note' ); ?> →</a>
	</div>
</main>

<?php
get_footer();
