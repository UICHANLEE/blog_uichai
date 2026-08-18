<?php
/**
 * Odd Note theme functions.
 *
 * @package Odd_Note
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configure theme capabilities.
 */
function odd_note_setup() {
	load_theme_textdomain( 'odd-note', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'odd-note' ),
			'footer'  => __( 'Footer navigation', 'odd-note' ),
		)
	);
}
add_action( 'after_setup_theme', 'odd_note_setup' );

/**
 * Return a cache-safe asset version.
 *
 * @param string $relative_path Path relative to the theme.
 * @return string
 */
function odd_note_asset_version( $relative_path ) {
	$absolute_path = get_theme_file_path( $relative_path );

	if ( is_readable( $absolute_path ) ) {
		return (string) filemtime( $absolute_path );
	}

	return (string) wp_get_theme()->get( 'Version' );
}

/**
 * Load the theme assets.
 */
function odd_note_enqueue_assets() {
	wp_enqueue_style(
		'odd-note-site',
		get_theme_file_uri( 'assets/css/site.css' ),
		array(),
		odd_note_asset_version( 'assets/css/site.css' )
	);

	wp_enqueue_script(
		'odd-note-interactions',
		get_theme_file_uri( 'assets/js/site.js' ),
		array(),
		odd_note_asset_version( 'assets/js/site.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'odd_note_enqueue_assets' );

/**
 * Add a stable body hook for theme-specific styling.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
function odd_note_body_classes( $classes ) {
	$classes[] = 'odd-note';
	return $classes;
}
add_filter( 'body_class', 'odd_note_body_classes' );

/**
 * Keep cards concise.
 *
 * @return int
 */
function odd_note_excerpt_length() {
	return 26;
}
add_filter( 'excerpt_length', 'odd_note_excerpt_length', 999 );

/**
 * Replace the default excerpt suffix.
 *
 * @return string
 */
function odd_note_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'odd_note_excerpt_more' );

/**
 * Estimate a readable duration for Korean and Latin content.
 *
 * @param int|null $post_id Post ID.
 * @return int
 */
function odd_note_reading_time( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$content = wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) );

	$latin_count  = preg_match_all( "/[A-Za-z0-9]+(?:['’\x{2019}-][A-Za-z0-9]+)*/u", $content );
	$korean_count = preg_match_all( '/[\x{AC00}-\x{D7A3}]/u', $content );
	$minutes      = ( (int) $latin_count / 220 ) + ( (int) $korean_count / 500 );

	return max( 1, (int) ceil( $minutes ) );
}

/**
 * Return the preferred category name for the current post.
 *
 * @param int|null $post_id Post ID.
 * @return string
 */
function odd_note_primary_category( $post_id = null ) {
	$post_id    = $post_id ? (int) $post_id : get_the_ID();
	$categories = get_the_category( $post_id );

	if ( empty( $categories ) ) {
		return __( 'Note', 'odd-note' );
	}

	$preferred_id = (int) get_post_meta( $post_id, '_odd_note_primary_category_id', true );
	if ( $preferred_id ) {
		foreach ( $categories as $category ) {
			if ( $preferred_id === (int) $category->term_id ) {
				return $category->name;
			}
		}
	}

	return $categories[0]->name;
}

/**
 * Return the configured posts page, or a dependable all-posts fallback.
 *
 * @return string
 */
function odd_note_posts_url() {
	$posts_page_id = (int) get_option( 'page_for_posts' );

	if ( $posts_page_id ) {
		return (string) get_permalink( $posts_page_id );
	}

	return home_url( '/?s=' );
}

/**
 * Return a category archive URL with a stable fallback.
 *
 * @param string $slug Category slug.
 * @return string
 */
function odd_note_category_url( $slug ) {
	$category = get_category_by_slug( $slug );

	if ( $category ) {
		$url = get_category_link( $category );
		if ( ! is_wp_error( $url ) ) {
			return (string) $url;
		}
	}

	return odd_note_posts_url();
}

/**
 * Return the editorial about page without exposing the administrator login slug.
 *
 * @return string
 */
function odd_note_about_url() {
	$about = get_page_by_path( 'about', OBJECT, 'page' );

	if ( $about ) {
		return (string) get_permalink( $about );
	}

	return home_url( '/' );
}

/**
 * A useful menu before the user creates one.
 */
function odd_note_primary_menu_fallback() {
	echo '<ul class="site-menu">';
	echo '<li><a href="' . esc_url( odd_note_posts_url() ) . '">' . esc_html__( '전체 글', 'odd-note' ) . '</a></li>';

	foreach ( array( 'it-news', 'ai-paper-analysis', 'business-knowledge' ) as $slug ) {
		$category = get_category_by_slug( $slug );
		if ( $category ) {
			echo '<li><a href="' . esc_url( get_category_link( $category ) ) . '">' . esc_html( $category->name ) . '</a></li>';
		}
	}

	echo '<li><a href="' . esc_url( odd_note_about_url() ) . '">' . esc_html__( '소개', 'odd-note' ) . '</a></li>';
	echo '</ul>';
}

/**
 * Set the browser chrome color without an extra plugin.
 */
function odd_note_theme_color_meta() {
	echo '<meta name="theme-color" content="#0d0d12">' . "\n";
}
add_action( 'wp_head', 'odd_note_theme_color_meta' );

/**
 * Add a lightweight social preview until a dedicated SEO plugin takes over.
 */
function odd_note_social_meta() {
	if ( is_admin() ) {
		return;
	}

	$title        = wp_get_document_title();
	$description  = (string) get_bloginfo( 'description' );
	$url          = '';
	$type         = 'website';
	$add_canonical = false;

	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_singular() ) {
		$post_id      = get_queried_object_id();
		$url          = (string) get_permalink( $post_id );
		$type         = is_singular( 'post' ) ? 'article' : 'website';
		$post_excerpt = get_the_excerpt( $post_id );
		if ( $post_excerpt ) {
			$description = wp_strip_all_tags( $post_excerpt );
		}
	} elseif ( is_home() ) {
		$url           = odd_note_posts_url();
		$description   = 'Odd Note의 IT 최신 뉴스, AI 논문 분석, 사업 지식과 개발 실전 기록을 한곳에서 확인합니다.';
		$add_canonical = true;
	} elseif ( is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$term_link = get_term_link( $term );
			if ( ! is_wp_error( $term_link ) ) {
				$url = (string) $term_link;
			}
			$term_description = term_description( $term );
			if ( $term_description ) {
				$description = wp_strip_all_tags( $term_description );
			}
		}
		$add_canonical = true;
	} elseif ( is_author() ) {
		$author_id     = (int) get_queried_object_id();
		$url           = get_author_posts_url( $author_id );
		$author_bio    = (string) get_the_author_meta( 'description', $author_id );
		$description   = $author_bio ? $author_bio : $description;
		$add_canonical = true;
	} elseif ( is_archive() ) {
		$url                 = (string) get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ), false );
		$archive_description = get_the_archive_description();
		if ( $archive_description ) {
			$description = wp_strip_all_tags( $archive_description );
		}
		$add_canonical = true;
	} elseif ( is_search() ) {
		$url           = home_url( '/?s=' . rawurlencode( get_search_query() ) );
		$description   = sprintf( 'Odd Note에서 “%s” 검색 결과를 확인합니다.', get_search_query() );
		$add_canonical = true;
	}

	$image = get_theme_file_uri( 'assets/images/og-tech-business.png' );

	echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	echo '<meta property="og:locale" content="ko_KR">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	if ( $url ) {
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	}
	echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	echo '<meta property="og:image:width" content="1200">' . "\n";
	echo '<meta property="og:image:height" content="630">' . "\n";
	echo '<meta property="og:image:alt" content="Odd Note — IT 최신 뉴스, AI 논문 분석, 사업 지식">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( $add_canonical && $url ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'odd_note_social_meta', 5 );
