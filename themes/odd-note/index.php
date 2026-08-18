<?php
/**
 * Main archive fallback.
 *
 * @package Odd_Note
 */

get_header();

if ( is_search() && '' !== get_search_query() ) {
	$page_kicker = __( 'SEARCH RESULTS', 'odd-note' );
	$page_title  = sprintf( __( '“%s”에 대한 기록', 'odd-note' ), get_search_query() );
} elseif ( is_archive() ) {
	$page_kicker = __( 'ARCHIVE', 'odd-note' );
	$page_title  = get_the_archive_title();
} else {
	$page_kicker = __( 'ALL STORIES', 'odd-note' );
	$page_title  = __( '모든 이야기', 'odd-note' );
}
?>

<main id="main-content" class="archive-page section-shell" tabindex="-1">
	<header class="archive-header reveal">
		<p class="section-kicker"><?php echo esc_html( $page_kicker ); ?></p>
		<h1><?php echo wp_kses_post( $page_title ); ?></h1>
		<?php if ( is_archive() && get_the_archive_description() ) : ?>
			<div class="archive-header__description"><?php the_archive_description(); ?></div>
		<?php endif; ?>
	</header>
	<div class="archive-search reveal">
		<?php get_search_form(); ?>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="story-grid story-grid--archive">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article <?php post_class( 'story-card reveal' ); ?> data-tilt-card>
					<a class="story-card__link" href="<?php the_permalink(); ?>" data-cursor="READ">
						<div class="story-card__visual">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large', array( 'class' => 'story-card__image', 'loading' => 'lazy' ) ); ?>
							<?php else : ?>
								<div class="generative-cover" style="--cover-seed: <?php echo esc_attr( (string) ( get_the_ID() % 9 ) ); ?>;">
									<span><?php echo esc_html( odd_note_primary_category() ); ?></span>
								</div>
							<?php endif; ?>
						</div>
						<div class="post-meta">
							<span><?php echo esc_html( odd_note_primary_category() ); ?></span>
							<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></time>
						</div>
						<h2><?php the_title(); ?></h2>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<span class="story-card__arrow" aria-hidden="true">↗</span>
					</a>
				</article>
			<?php endwhile; ?>
		</div>

		<div class="pagination-shell reveal">
			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => __( '← 이전', 'odd-note' ),
					'next_text' => __( '다음 →', 'odd-note' ),
				)
			);
			?>
		</div>
	<?php else : ?>
		<section class="empty-story reveal">
			<h2><?php esc_html_e( '이 주제의 첫 분석을 준비하고 있습니다.', 'odd-note' ); ?></h2>
			<p><?php esc_html_e( '새 글이 발행되기 전까지 전체 글에서 개발 실전 기록을 확인해 보세요.', 'odd-note' ); ?></p>
			<a class="magnetic-button magnetic" href="<?php echo esc_url( odd_note_posts_url() ); ?>" data-cursor="BROWSE"><span><?php esc_html_e( '전체 글 보기', 'odd-note' ); ?></span></a>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();
