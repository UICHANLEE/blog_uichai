<?php
/**
 * Single post template.
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
				<div class="post-meta reveal">
					<span><?php echo esc_html( odd_note_primary_category() ); ?></span>
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></time>
					<span><?php echo esc_html( sprintf( __( '%d min read', 'odd-note' ), odd_note_reading_time() ) ); ?></span>
				</div>
				<h1 class="article-title reveal"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="article-deck reveal"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</header>

			<div class="article-visual section-shell reveal">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'full', array( 'class' => 'article-image', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
				<?php else : ?>
					<div class="generative-cover generative-cover--article" style="--cover-seed: <?php echo esc_attr( (string) ( get_the_ID() % 9 ) ); ?>;">
						<span class="generative-cover__word"><?php echo esc_html( odd_note_primary_category() ); ?></span>
						<span class="generative-cover__issue">NOTE <?php echo esc_html( str_pad( (string) get_the_ID(), 3, '0', STR_PAD_LEFT ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="article-layout section-shell">
				<aside class="article-aside reveal">
					<p><?php esc_html_e( 'Written by', 'odd-note' ); ?></p>
					<strong><?php the_author(); ?></strong>
					<a href="<?php echo esc_url( odd_note_about_url() ); ?>" data-cursor="MORE"><?php esc_html_e( '편집 원칙 보기', 'odd-note' ); ?> ↗</a>
				</aside>

				<div class="article-content reveal">
					<?php the_content(); ?>
					<?php
					wp_link_pages(
						array(
							'before' => '<nav class="page-links">' . esc_html__( '페이지:', 'odd-note' ),
							'after'  => '</nav>',
						)
					);
					?>
				</div>
			</div>

			<footer class="article-footer section-shell reveal">
				<div class="article-tags"><?php the_tags( '', ' ', '' ); ?></div>
				<nav class="post-navigation" aria-label="<?php esc_attr_e( '다른 글', 'odd-note' ); ?>">
					<div><?php previous_post_link( '%link', '<span>' . esc_html__( '이전 글', 'odd-note' ) . '</span>%title' ); ?></div>
					<div><?php next_post_link( '%link', '<span>' . esc_html__( '다음 글', 'odd-note' ) . '</span>%title' ); ?></div>
				</nav>
			</footer>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<div class="comments-shell section-shell">
					<?php comments_template(); ?>
				</div>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
