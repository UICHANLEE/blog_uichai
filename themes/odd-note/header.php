<?php
/**
 * Site header.
 *
 * @package Odd_Note
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! has_site_icon() ) : ?>
		<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22%3E%3Crect width=%2264%22 height=%2264%22 rx=%2216%22 fill=%22%230b0b0e%22/%3E%3Ccircle cx=%2232%22 cy=%2232%22 r=%2218%22 fill=%22%23d7ff3f%22/%3E%3Ccircle cx=%2232%22 cy=%2232%22 r=%228%22 fill=%22%230b0b0e%22/%3E%3C/svg%3E">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-mood="acid">
<?php wp_body_open(); ?>

<a class="skip-link" href="#main-content"><?php esc_html_e( '본문으로 바로가기', 'odd-note' ); ?></a>

<div class="reading-progress" aria-hidden="true">
	<span data-reading-progress></span>
</div>

<div class="pointer-halo" data-pointer-halo aria-hidden="true" hidden>
	<span data-pointer-label></span>
</div>

<div class="page-atmosphere" aria-hidden="true"></div>

<header class="site-header" data-site-header>
	<div class="site-header__inner">
		<a class="site-brand magnetic" href="<?php echo esc_url( home_url( '/' ) ); ?>" data-cursor="HOME">
			<span class="site-brand__mark" aria-hidden="true">◉</span>
			<span class="site-brand__name"><?php bloginfo( 'name' ); ?></span>
		</a>

		<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-primary-navigation" data-menu-toggle hidden>
			<span class="menu-toggle__label" lang="en"><?php esc_html_e( 'Menu', 'odd-note' ); ?></span>
		</button>

		<nav id="site-primary-navigation" class="primary-nav" aria-label="<?php esc_attr_e( '주요 메뉴', 'odd-note' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_id'        => 'primary-menu',
					'menu_class'     => 'site-menu',
					'fallback_cb'    => 'odd_note_primary_menu_fallback',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<div class="experience-controls" role="group" aria-label="<?php esc_attr_e( '화면 효과 설정', 'odd-note' ); ?>" aria-hidden="true" data-experience-controls inert>
			<button class="control-button" type="button" data-mood-toggle data-cursor="COLOR" aria-label="<?php esc_attr_e( '색상 분위기 바꾸기', 'odd-note' ); ?>">
				<span class="control-button__dot" aria-hidden="true"></span>
				<span data-mood-label lang="en">ACID</span>
			</button>
			<button class="control-button" type="button" data-motion-toggle data-cursor="MOTION" aria-label="<?php esc_attr_e( '화면 효과', 'odd-note' ); ?>" aria-describedby="odd-note-motion-status" aria-pressed="true">
				<span class="control-button__pulse" aria-hidden="true"></span>
				<span data-motion-label lang="en">FX ON</span>
			</button>
			<span id="odd-note-motion-status" class="screen-reader-text" data-motion-status></span>
		</div>
	</div>
</header>
