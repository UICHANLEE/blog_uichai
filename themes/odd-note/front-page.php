<?php
/**
 * Interactive front page.
 *
 * @package Odd_Note
 */

get_header();

$sticky_posts   = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
$featured_posts = array();

if ( $sticky_posts ) {
	$featured_posts = get_posts(
		array(
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'post__in'       => $sticky_posts,
			'orderby'        => 'post__in',
		)
	);
}

if ( ! $featured_posts ) {
	$featured_posts = get_posts(
		array(
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);
}
$featured_post  = ! empty( $featured_posts ) ? $featured_posts[0] : null;
$featured_id    = $featured_post ? (int) $featured_post->ID : 0;
$it_news_url          = odd_note_category_url( 'it-news' );
$ai_paper_url         = odd_note_category_url( 'ai-paper-analysis' );
$business_knowledge_url = odd_note_category_url( 'business-knowledge' );
?>

<main id="main-content" tabindex="-1">
	<section class="hero" data-pointer-stage>
		<div class="hero__glow" aria-hidden="true"></div>
		<div class="hero__grid" aria-hidden="true"></div>

		<div class="hero__meta reveal">
			<p lang="en"><span class="status-dot" aria-hidden="true"></span><?php esc_html_e( 'TECH · AI · BUSINESS JOURNAL', 'odd-note' ); ?></p>
			<p><span data-seoul-clock>SEOUL — --:--</span></p>
		</div>

		<div class="hero__copy">
			<p class="section-kicker reveal" lang="en"><?php esc_html_e( 'FROM SIGNAL TO DECISION', 'odd-note' ); ?></p>
			<h1 class="hero__title" aria-label="<?php esc_attr_e( '빠른 변화를 근거로 읽고 사업으로 연결합니다.', 'odd-note' ); ?>">
				<span class="hero__line"><?php esc_html_e( '빠른 변화를', 'odd-note' ); ?></span>
				<span class="hero__line hero__line--accent"><?php esc_html_e( '근거로 읽고', 'odd-note' ); ?></span>
				<span class="hero__line"><?php esc_html_e( '사업으로 연결합니다.', 'odd-note' ); ?></span>
			</h1>
		</div>

		<div class="hero__footer reveal">
			<p><?php esc_html_e( 'IT 최신 뉴스의 맥락, AI 논문의 핵심과 한계, 사업에 필요한 판단 기준을 짧고 정확하게 정리합니다.', 'odd-note' ); ?></p>
			<a class="magnetic-button magnetic" href="#stories" data-cursor="EXPLORE">
				<span><?php esc_html_e( '오늘의 브리핑', 'odd-note' ); ?></span>
				<span aria-hidden="true">↓</span>
			</a>
		</div>

		<div class="hero-orbit" aria-hidden="true">
			<div class="hero-orbit__ring"></div>
			<div class="hero-orbit__core">→</div>
			<span class="hero-orbit__note hero-orbit__note--one">SIGNAL</span>
			<span class="hero-orbit__note hero-orbit__note--two">EVIDENCE</span>
			<span class="hero-orbit__note hero-orbit__note--three">ACTION</span>
		</div>
	</section>

	<section class="signal-strip" aria-label="<?php esc_attr_e( '블로그 키워드', 'odd-note' ); ?>">
		<div class="signal-strip__track">
			<div class="signal-strip__group">
				<span lang="en">TECH NEWS, IN CONTEXT</span><i aria-hidden="true">✦</i>
				<span lang="en">AI PAPERS, EXPLAINED</span><i aria-hidden="true">✦</i>
				<span lang="en">BUSINESS, APPLIED</span><i aria-hidden="true">✦</i>
			</div>
			<div class="signal-strip__group" aria-hidden="true">
				<span>TECH NEWS, IN CONTEXT</span><i>✦</i>
				<span>AI PAPERS, EXPLAINED</span><i>✦</i>
				<span>BUSINESS, APPLIED</span><i>✦</i>
			</div>
		</div>
	</section>

	<section class="topic-deck section-shell">
		<div class="section-heading reveal">
			<p class="section-kicker" lang="en"><?php esc_html_e( 'THREE EDITORIAL DESKS', 'odd-note' ); ?></p>
			<h2><?php esc_html_e( '무엇을 깊게', 'odd-note' ); ?><br><?php esc_html_e( '읽나요?', 'odd-note' ); ?></h2>
		</div>

		<div class="topic-deck__grid">
			<a class="topic-card reveal" href="<?php echo esc_url( $it_news_url ); ?>" data-cursor="TECH">
				<span class="topic-card__number">01 · TECH SIGNAL</span>
				<span class="topic-card__icon" aria-hidden="true">↗</span>
				<h3><?php esc_html_e( 'IT 최신 뉴스', 'odd-note' ); ?></h3>
				<p><?php esc_html_e( '제품·플랫폼·정책의 변화를 원문 출처와 실제 영향까지 정리합니다.', 'odd-note' ); ?></p>
			</a>
			<a class="topic-card reveal" href="<?php echo esc_url( $ai_paper_url ); ?>" data-cursor="PAPER">
				<span class="topic-card__number">02 · AI RESEARCH</span>
				<span class="topic-card__icon" aria-hidden="true">↗</span>
				<h3><?php esc_html_e( 'AI 논문 분석', 'odd-note' ); ?></h3>
				<p><?php esc_html_e( '새 논문의 질문·방법·결과·한계를 풀어 읽고 실무 가능성을 구분합니다.', 'odd-note' ); ?></p>
			</a>
			<a class="topic-card reveal" href="<?php echo esc_url( $business_knowledge_url ); ?>" data-cursor="BUSINESS">
				<span class="topic-card__number">03 · BUSINESS</span>
				<span class="topic-card__icon" aria-hidden="true">↗</span>
				<h3><?php esc_html_e( '사업 지식', 'odd-note' ); ?></h3>
				<p><?php esc_html_e( '고객·시장·가격·수익모델·운영을 사례와 실행 질문으로 정리합니다.', 'odd-note' ); ?></p>
			</a>
		</div>
	</section>

	<section id="stories" class="featured-story section-shell">
		<div class="section-heading section-heading--row reveal">
			<div>
				<p class="section-kicker" lang="en"><?php esc_html_e( 'EDITOR’S PICK', 'odd-note' ); ?></p>
				<h2><?php esc_html_e( '오늘의 분석', 'odd-note' ); ?></h2>
			</div>
			<p class="section-index">01 — FEATURE</p>
		</div>

		<?php if ( $featured_post ) : ?>
			<?php
			$GLOBALS['post'] = $featured_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $featured_post );
			?>
			<article <?php post_class( 'feature-card reveal' ); ?>>
				<a class="feature-card__visual" href="<?php the_permalink(); ?>" data-cursor="READ" aria-label="<?php echo esc_attr( sprintf( __( '%s 읽기', 'odd-note' ), get_the_title() ) ); ?>">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'class' => 'feature-card__image', 'loading' => 'lazy' ) ); ?>
					<?php else : ?>
						<div class="generative-cover generative-cover--feature" style="--cover-seed: <?php echo esc_attr( (string) ( $featured_id % 9 ) ); ?>;">
							<span class="generative-cover__word"><?php echo esc_html( odd_note_primary_category( $featured_id ) ); ?></span>
							<span class="generative-cover__issue">NO. <?php echo esc_html( str_pad( (string) $featured_id, 3, '0', STR_PAD_LEFT ) ); ?></span>
						</div>
					<?php endif; ?>
				</a>
				<div class="feature-card__content">
					<div class="post-meta">
						<span><?php echo esc_html( odd_note_primary_category( $featured_id ) ); ?></span>
						<span><?php echo esc_html( sprintf( __( '%d min read', 'odd-note' ), odd_note_reading_time( $featured_id ) ) ); ?></span>
					</div>
					<h3><a href="<?php the_permalink(); ?>" data-cursor="READ"><?php the_title(); ?></a></h3>
					<div class="feature-card__excerpt"><?php the_excerpt(); ?></div>
					<a class="text-link" href="<?php the_permalink(); ?>" data-cursor="OPEN"><?php esc_html_e( '이야기 열기', 'odd-note' ); ?> <span aria-hidden="true">↗</span></a>
				</div>
			</article>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="empty-story reveal">
				<p><?php esc_html_e( '첫 번째 이야기를 준비하고 있습니다.', 'odd-note' ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<section class="latest-stories section-shell">
		<div class="section-heading section-heading--row reveal">
			<div>
				<p class="section-kicker" lang="en"><?php esc_html_e( 'LATEST INTELLIGENCE', 'odd-note' ); ?></p>
				<h2><?php esc_html_e( '새로 도착한 분석', 'odd-note' ); ?></h2>
			</div>
			<p class="section-index">02 — ARCHIVE</p>
		</div>

		<div class="story-grid">
			<?php
			$latest_query = new WP_Query(
				array(
					'posts_per_page'      => 6,
					'post__not_in'        => $featured_id ? array( $featured_id ) : array(),
					'ignore_sticky_posts' => true,
					'post_status'         => 'publish',
				)
			);
			?>
			<?php if ( $latest_query->have_posts() ) : ?>
				<?php while ( $latest_query->have_posts() ) : ?>
					<?php $latest_query->the_post(); ?>
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
							<h3><?php the_title(); ?></h3>
							<span class="story-card__arrow" aria-hidden="true">↗</span>
						</a>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<div class="story-card story-card--empty reveal">
					<p class="section-kicker" lang="en">NEXT NOTE</p>
					<h3><?php esc_html_e( '다음 이야기가 놓일 자리입니다.', 'odd-note' ); ?></h3>
					<p><?php esc_html_e( '첫 글을 발행하면 이곳부터 살아 움직이기 시작합니다.', 'odd-note' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="manifesto" aria-label="<?php esc_attr_e( '편집 원칙', 'odd-note' ); ?>">
		<div class="manifesto__line reveal">
			<span><?php esc_html_e( '빠르게 포착하고', 'odd-note' ); ?></span>
			<strong><?php esc_html_e( '깊게 검증하기.', 'odd-note' ); ?></strong>
		</div>
		<div class="manifesto__line manifesto__line--offset reveal">
			<span><?php esc_html_e( '기술을 읽고', 'odd-note' ); ?></span>
			<strong><?php esc_html_e( '사업으로 연결하기.', 'odd-note' ); ?></strong>
		</div>
	</section>
</main>

<?php
get_footer();
