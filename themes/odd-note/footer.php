<?php
/**
 * Site footer.
 *
 * @package Odd_Note
 */
?>
<footer class="site-footer">
	<div class="site-footer__orb" aria-hidden="true"></div>
	<div class="site-footer__inner">
		<p class="section-kicker reveal" lang="en"><?php esc_html_e( 'KEEP THE CURIOSITY', 'odd-note' ); ?></p>
		<h2 class="site-footer__title reveal"><?php esc_html_e( '다음 질문은', 'odd-note' ); ?><br><em><?php esc_html_e( '당신의 것.', 'odd-note' ); ?></em></h2>

		<div class="site-footer__actions reveal">
			<a class="magnetic-button magnetic" href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>" data-cursor="RSS">
				<span><?php esc_html_e( 'RSS로 새 글 받기', 'odd-note' ); ?></span>
				<span aria-hidden="true">↗</span>
			</a>
			<?php get_search_form(); ?>
		</div>

		<div class="site-footer__base">
			<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer-nav" aria-label="<?php esc_attr_e( '푸터 메뉴', 'odd-note' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => 'footer-menu',
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>
			<p lang="en"><?php esc_html_e( 'Independent ideas, carefully edited.', 'odd-note' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
