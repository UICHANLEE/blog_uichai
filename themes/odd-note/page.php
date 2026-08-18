<?php
/**
 * Static page template.
 *
 * @package Odd_Note
 */

get_header();
?>

<main id="main-content" class="single-page" tabindex="-1">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article <?php post_class( 'single-article' ); ?>>
			<header class="article-hero section-shell">
				<p class="section-kicker reveal"><?php esc_html_e( 'PAGE', 'odd-note' ); ?></p>
				<h1 class="article-title reveal"><?php the_title(); ?></h1>
			</header>
			<div class="article-layout article-layout--page section-shell">
				<div class="article-content reveal">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</div>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
