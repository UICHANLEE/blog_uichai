<?php
/**
 * Search form.
 *
 * @package Odd_Note
 */
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text"><?php esc_html_e( '검색어', 'odd-note' ); ?></span>
		<input type="search" class="search-field" placeholder="<?php esc_attr_e( '무엇이 궁금한가요?', 'odd-note' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	</label>
	<button type="submit" class="search-submit" data-cursor="FIND"><?php esc_html_e( '검색', 'odd-note' ); ?> ↗</button>
</form>
