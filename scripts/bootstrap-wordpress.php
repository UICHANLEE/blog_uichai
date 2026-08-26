<?php
/**
 * Install and seed the local Odd Note WordPress site.
 *
 * This file is mounted outside the web root and is intended for CLI use only.
 */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$bootstrap_site_url   = (string) getenv( 'ODD_NOTE_SITE_URL' );
$bootstrap_url_parts  = parse_url( $bootstrap_site_url );

if ( is_array( $bootstrap_url_parts ) && ! empty( $bootstrap_url_parts['host'] ) ) {
	$bootstrap_host = $bootstrap_url_parts['host'];
	if ( ! empty( $bootstrap_url_parts['port'] ) ) {
		$bootstrap_host .= ':' . (int) $bootstrap_url_parts['port'];
	}

	$_SERVER['HTTP_HOST']   = $bootstrap_host;
	$_SERVER['REQUEST_URI'] = '/';
	$_SERVER['SERVER_PORT'] = ! empty( $bootstrap_url_parts['port'] )
		? (string) (int) $bootstrap_url_parts['port']
		: ( ( $bootstrap_url_parts['scheme'] ?? 'http' ) === 'https' ? '443' : '80' );

	if ( ( $bootstrap_url_parts['scheme'] ?? 'http' ) === 'https' ) {
		$_SERVER['HTTPS'] = 'on';
	}
}

define( 'WP_INSTALLING', true );

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Stop with a concise error that never includes credentials.
 *
 * @param string $message Error message.
 * @param int    $code Exit code.
 * @return never
 */
function odd_note_bootstrap_fail( $message, $code = 1 ) {
	fwrite( STDERR, '오류: ' . $message . PHP_EOL );
	exit( $code );
}

/**
 * Read one required value from standard input.
 *
 * @param string $label Human-readable field name.
 * @return string
 */
function odd_note_bootstrap_input( $label ) {
	$value = fgets( STDIN );

	if ( false === $value ) {
		odd_note_bootstrap_fail( $label . ' 값을 받지 못했습니다.' );
	}

	return trim( $value );
}

/**
 * Insert or update a page/post owned by this bootstrap.
 *
 * @param array<string,mixed> $args Post fields.
 * @return int
 */
function odd_note_bootstrap_post( $args ) {
	$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );

	if ( $existing ) {
		$args['ID'] = (int) $existing->ID;
	}

	$post_id = wp_insert_post( wp_slash( $args ), true );

	if ( is_wp_error( $post_id ) ) {
		odd_note_bootstrap_fail( '콘텐츠 생성 실패: ' . $post_id->get_error_message() );
	}

	update_post_meta( $post_id, '_odd_note_bootstrap', '1' );

	return (int) $post_id;
}

/**
 * Return an existing category or create it.
 *
 * @param string $name Category name.
 * @param string $slug Category slug.
 * @param string $description Category description.
 * @return int
 */
function odd_note_bootstrap_category( $name, $slug, $description ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	if ( $term ) {
		$result = wp_update_term(
			(int) $term->term_id,
			'category',
			array(
				'name'        => $name,
				'description' => $description,
			)
		);
	} else {
		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'        => $slug,
				'description' => $description,
			)
		);
	}

	if ( is_wp_error( $result ) ) {
		odd_note_bootstrap_fail( '카테고리 생성 실패: ' . $result->get_error_message() );
	}

	return (int) $result['term_id'];
}

/**
 * Create one core editorial category without rewriting an existing taxonomy.
 *
 * @param string $name Category name.
 * @param string $slug Category slug.
 * @param string $description Category description.
 * @return int
 */
function odd_note_core_category( $name, $slug, $description ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	if ( $term ) {
		if ( $term->name !== $name ) {
			odd_note_bootstrap_fail( '새 핵심 카테고리와 같은 슬러그를 사용하는 기존 분류가 있습니다: ' . $slug );
		}

		return (int) $term->term_id;
	}

	$result = wp_insert_term(
		$name,
		'category',
		array(
			'slug'        => $slug,
			'description' => $description,
		)
	);

	if ( is_wp_error( $result ) ) {
		odd_note_bootstrap_fail( '핵심 카테고리 생성 실패: ' . $result->get_error_message() );
	}

	$term_id = (int) $result['term_id'];
	update_term_meta( $term_id, '_odd_note_bootstrap', '1' );
	update_term_meta( $term_id, '_odd_note_editorial_revision', '1.3.0' );

	return $term_id;
}

/**
 * Return the original About page body used before the editorial expansion.
 *
 * @return string
 */
function odd_note_legacy_about_content() {
	return <<<'HTML'
<!-- wp:paragraph -->
<p>Odd Note는 직접 써보고 직접 구축한 경험을 바탕으로 AI 도구, 맥 워크플로, 홈서버 운영을 기록하는 독립 웹 저널입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">무엇을 다루나요?</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>업무와 창작에 실제로 도움이 되는 AI 도구</li><li>Mac을 더 편하고 안전하게 사용하는 워크플로</li><li>WordPress, Docker, Cloudflare를 활용한 홈서버 운영</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">편집 원칙</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>직접 확인하지 않은 내용을 경험처럼 말하지 않습니다. 장점뿐 아니라 비용, 한계, 실패 가능성을 함께 기록합니다. 오래된 정보는 발견하는 대로 수정하고 중요한 수정은 글에 표시합니다.</p>
<!-- /wp:paragraph -->
HTML;
}

/**
 * Return the About page body for the technology and business journal.
 *
 * @return string
 */
function odd_note_about_content() {
	return <<<'HTML'
<!-- wp:paragraph -->
<p>Odd Note는 개발과 사업의 경계에서 중요한 변화를 골라 읽는 독립 정보 블로그입니다. IT 뉴스로 신호를 포착하고, AI 논문으로 근거와 한계를 확인하며, 사업 지식으로 다음 행동을 설계합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">무엇을 다루나요?</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>제품·플랫폼·정책의 변화가 개발과 사업에 미치는 영향</li><li>새 AI 논문의 연구 질문, 방법, 결과와 한계</li><li>고객, 시장, 가격, 수익모델과 운영에 필요한 사업 원리</li><li>직접 구축하고 운영하며 얻은 개발 실전 기록</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">편집 원칙</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>빠른 발행보다 정확한 맥락을 우선합니다. 뉴스에는 원문 링크와 확인 시각을, 논문에는 버전·방법·한계를, 사업 글에는 전제와 적용 범위를 밝힙니다. 사실과 해석을 구분하고 중요한 수정은 날짜와 함께 남깁니다. 광고와 제휴는 편집 내용과 분명히 구분합니다.</p>
<!-- /wp:paragraph -->
HTML;
}

/**
 * Return the editorial body for the AI-assisted blogging workflow article.
 *
 * @return string
 */
function odd_note_ai_blog_workflow_content() {
	return <<<'HTML'
<!-- wp:paragraph -->
<p>AI로 블로그 글을 쓰면 검색에서 불리할까요? 짧게 답하면 <strong>AI를 사용했다는 사실만으로 품질이 결정되지는 않습니다.</strong> 중요한 것은 누가 초안을 입력했느냐가 아니라, 글이 독자의 질문을 제대로 해결하고 독창적인 경험과 검증된 정보를 제공하느냐입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Google도 생성형 AI가 조사와 콘텐츠 구조화에 유용할 수 있다고 설명합니다. 반대로 검색 순위를 조작하려는 목적으로 가치 없는 페이지를 대량 생성하면 제작 방식과 관계없이 스팸 정책의 대상이 될 수 있습니다. 따라서 수익형 블로그에서 AI는 ‘자동 발행기’보다 <strong>생각을 정리하고 빠뜨린 부분을 찾는 편집 보조자</strong>로 쓰는 편이 안전합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. 독자의 질문을 한 문장으로 정한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>프롬프트보다 먼저 정할 것은 독자입니다. “AI 글쓰기”처럼 넓은 키워드만 주면 누구에게도 충분하지 않은 설명이 나오기 쉽습니다. 대신 “처음 수익형 블로그를 만드는 사람이 AI 초안을 어디까지 믿어도 되는가?”처럼 독자, 상황, 해결할 질문을 한 문장으로 좁힙니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Google의 <a href="https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=ko">SEO 기본 가이드</a>도 독자가 사용할 검색어의 차이를 예상하되 모든 표현을 억지로 반복할 필요는 없다고 안내합니다. 제목과 첫 문단에 핵심 질문이 자연스럽게 드러나면 충분합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. AI에게 묻기 전에 내 재료를 모은다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>좋은 초안의 차이는 모델보다 입력 재료에서 벌어집니다. 직접 사용한 제품, 실제 설정값, 실패한 방법, 걸린 시간, 확인한 화면, 독자가 주의해야 할 조건을 먼저 메모합니다. 경험하지 않은 사례는 빈칸으로 남겨야 합니다. AI가 그럴듯한 숫자나 체험을 만들어 채우게 해서는 안 됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>예를 들어 이 블로그의 <a href="/m1-pro-wordpress-home-server/">M1 Pro WordPress 홈서버 구축기</a>라면 ‘맥북은 빠르다’는 일반론보다 잠자기, 재부팅, Docker 자동 시작, 백업처럼 직접 운영하며 확인한 조건이 글의 중심이 됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. AI로 빠진 질문과 목차 후보를 찾는다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>수집한 재료를 AI에 넣고 곧바로 완성 글을 요구하지 않습니다. 먼저 “초보 독자가 이 내용을 읽고도 다시 검색할 질문은 무엇인가?”, “내 경험만으로 답할 수 없는 부분은 무엇인가?”를 묻습니다. 그 답을 이용해 목차를 만들고, 확인할 수 없는 항목은 삭제하거나 공식 자료 조사 목록으로 돌립니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. 섹션별로 초안을 만들고 경험을 다시 넣는다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>긴 글 전체를 한 번에 생성하면 같은 결론이 반복되고 문체가 평평해지기 쉽습니다. 소제목 하나씩 초안을 만든 다음, 일반적인 설명을 실제 선택과 결과로 바꿉니다. “보안이 중요하다” 대신 무엇을 외부에 열지 않았는지, “백업이 필요하다” 대신 어떤 파일을 같은 시점에 보관했는지를 적는 식입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. 가격·기능·날짜·인용은 원문에서 검증한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI 답변은 출발점이지 출처가 아닙니다. 가격, 제품 기능, 법률, 플랫폼 정책, 버전 번호처럼 바뀔 수 있는 정보는 공식 문서에서 다시 확인하고 링크를 남깁니다. 원문을 찾지 못한 숫자는 빼고, 추정이라면 추정 조건을 함께 적습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="https://developers.google.com/search/docs/fundamentals/using-gen-ai-content?hl=ko">Google의 생성형 AI 콘텐츠 가이드</a> 역시 자동 생성된 제목, 설명, 구조화 데이터와 이미지 대체 텍스트까지 정확성·품질·관련성을 확인하라고 권합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. 상투적인 문장과 검색용 장식을 걷어낸다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>“빠르게 변화하는 디지털 시대”, “결론적으로 매우 중요합니다”처럼 없어도 뜻이 같은 문장은 지웁니다. 정해진 글자 수를 채우기 위한 반복, 키워드의 부자연스러운 재사용, 질문만 조금 바꾼 여러 페이지도 만들지 않습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Google은 독창적인 가치가 거의 없는 페이지를 검색 순위 조작 목적으로 대량 제작하는 행위를 <a href="https://developers.google.com/search/docs/essentials/spam-policies?hl=ko#scaled-content">대규모 콘텐츠 악용</a>으로 설명합니다. 2026년 공개된 검색 AI 기능 가이드에서도 검색어의 모든 변형마다 별도 글을 만드는 식의 ‘AEO·GEO 꼼수’보다 기존 SEO 기본과 만족스러운 콘텐츠에 집중하라고 안내합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. 발행 후 실제 질문에 맞춰 업데이트한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>발행은 끝이 아니라 첫 검수입니다. 독자가 오래 머문 구간, 문의로 들어온 질문, 실제 검색어를 확인하고 빠진 설명을 보강합니다. 날짜만 최신으로 바꾸지 말고 내용이 달라졌을 때 수정일과 변경점을 남깁니다. 오래된 가격이나 기능은 확인 즉시 고칩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">바로 써먹는 초안 요청문</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>아래 요청문은 글을 대신 완성하는 주문이 아니라, 검토할 초안을 얻기 위한 시작점입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>독자: 처음 수익형 블로그를 운영하는 사람
해결할 질문: AI 초안을 어디까지 믿고 어떻게 검수해야 하는가
내가 직접 확인한 경험: [여기에 입력]
공식 출처: [여기에 링크]

위 재료만 사용해 목차와 섹션별 초안을 작성해줘.
경험이나 숫자를 만들어내지 말고, 확인이 필요한 문장에는 [검증 필요]를 표시해줘.
독자가 글을 읽은 뒤 바로 실행할 수 있는 순서로 구성하고 반복 문장은 제거해줘.</code></pre>
<!-- /wp:code -->

<!-- wp:heading -->
<h2 class="wp-block-heading">발행 전 10문항 체크리스트</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><li>이 글이 해결하는 질문을 한 문장으로 말할 수 있는가?</li><li>직접 경험한 내용과 조사한 내용을 구분했는가?</li><li>가격·기능·정책을 공식 원문에서 확인했는가?</li><li>출처를 열었을 때 실제로 해당 주장을 뒷받침하는가?</li><li>AI가 만든 숫자나 사례가 남아 있지 않은가?</li><li>제목이 내용을 과장하거나 결과를 보장하지 않는가?</li><li>같은 말을 표현만 바꿔 반복하지 않는가?</li><li>독자가 다시 검색해야 할 핵심 질문이 남지 않았는가?</li><li>작성자와 사이트의 소개·문의 경로가 보이는가?</li><li>나중에 무엇을 다시 확인해야 하는지 기록했는가?</li></ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">결론: AI는 속도를 높이고, 신뢰는 사람이 설계한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI 사용을 숨기거나 반대로 모든 문장을 자동화하는 것이 답은 아닙니다. 검색 의도와 실제 경험은 운영자가 정하고, AI는 질문 확장·구조화·초안·누락 검사에 사용합니다. 마지막 사실 확인과 발행 책임은 사이트에 남습니다. 이 원칙을 지키면 AI는 글을 대량 생산하는 기계가 아니라 한 편을 더 단단하게 만드는 도구가 됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>작성 과정:</strong> 이 글은 AI를 이용해 목차와 초안을 구성했으며, 주장은 Google Search Central의 <a href="https://developers.google.com/search/docs/fundamentals/creating-helpful-content?hl=ko">사람 중심 콘텐츠 가이드</a>, <a href="https://developers.google.com/search/docs/fundamentals/using-gen-ai-content?hl=ko">생성형 AI 콘텐츠 가이드</a>, <a href="https://developers.google.com/search/docs/fundamentals/ai-optimization-guide?hl=ko">검색 AI 기능 최적화 가이드</a>와 대조해 검토했습니다. 정책은 바뀔 수 있으므로 실제 적용 전 최신 원문을 다시 확인해야 합니다.</p>
<!-- /wp:paragraph -->
HTML;
}

/**
 * Publish the AI-assisted blogging workflow article without creating duplicates.
 *
 * @param int $owner_id Author user ID.
 * @param int $ai_tools_id Optional AI category ID.
 * @return int
 */
function odd_note_publish_ai_blog_workflow( $owner_id, $ai_tools_id = 0 ) {
	if ( ! $ai_tools_id ) {
		$ai_tools_id = odd_note_bootstrap_category(
			'AI 도구',
			'ai-tools',
			'업무와 창작에 실제로 도움이 되는 AI 도구를 직접 확인하고 기록합니다.'
		);
	}

	$post_id = odd_note_bootstrap_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_name'      => 'ai-blog-writing-workflow',
			'post_title'     => '수익형 블로그에 AI를 쓰는 7단계: 검색 의도부터 팩트체크까지',
			'post_excerpt'   => 'AI 사용 자체보다 중요한 것은 검색 의도, 실제 경험, 사실 검증입니다. 대량 복붙을 피하고 한 편의 신뢰도를 높이는 발행 과정을 정리했습니다.',
			'post_content'   => odd_note_ai_blog_workflow_content(),
			'post_author'    => $owner_id,
			'post_category'  => array( $ai_tools_id ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);

	wp_set_post_tags( $post_id, array( 'AI 글쓰기', '수익형 블로그', 'SEO', '팩트체크' ), false );

	return $post_id;
}

/**
 * Return the contact page body without publishing a placeholder address.
 *
 * @param string $admin_email Configured WordPress administrator email.
 * @return string
 */
function odd_note_contact_content( $admin_email ) {
	if ( is_email( $admin_email ) && ! str_ends_with( strtolower( $admin_email ), '.invalid' ) ) {
		$safe_email = antispambot( $admin_email );

		return sprintf(
			'<!-- wp:paragraph --><p>오류 제보, 내용 수정 요청, 협업과 제휴 문의는 아래 이메일로 보내주세요.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a href="mailto:%1$s">%2$s</a></p><!-- /wp:paragraph --><!-- wp:paragraph --><p>문의 내용과 회신 주소는 답변에 필요한 범위에서만 사용합니다.</p><!-- /wp:paragraph -->',
			esc_attr( $admin_email ),
			esc_html( $safe_email )
		);
	}

	return '<!-- wp:paragraph --><p><strong>연락 채널을 준비 중입니다.</strong> 실제로 수신할 수 있는 운영 이메일을 연결하면 이 페이지에 표시합니다.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>오류 제보, 내용 수정 요청, 광고·제휴 문의를 받을 수 있도록 공개 전에 이 페이지를 갱신합니다.</p><!-- /wp:paragraph -->';
}

/**
 * Apply a public editor identity that does not reveal the login name.
 *
 * @param int    $owner_id Administrator user ID.
 * @param string $admin_email Configured administrator email.
 * @return void
 */
function odd_note_apply_editor_identity( $owner_id, $admin_email ) {
	$result = wp_update_user(
		array(
			'ID'            => $owner_id,
			'user_nicename' => 'odd-note-editor',
			'display_name'  => 'Odd Note Editor',
			'nickname'      => 'Odd Note Editor',
			'description'   => 'IT 최신 뉴스, AI 논문 분석, 사업 지식을 개발과 운영 관점에서 검증하고 기록합니다.',
			'locale'        => 'ko_KR',
		)
	);

	if ( is_wp_error( $result ) ) {
		odd_note_bootstrap_fail( '공개 작성자 정보를 설정하지 못했습니다.' );
	}

	update_option( 'WPLANG', 'ko_KR' );

	$contact = get_page_by_path( 'contact', OBJECT, 'page' );
	if ( $contact ) {
		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $contact->ID,
					'post_content' => odd_note_contact_content( $admin_email ),
				)
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			odd_note_bootstrap_fail( '문의 페이지를 안전한 공개 상태로 갱신하지 못했습니다.' );
		}
	}
}

/**
 * Return the editorial body for the Mac image optimization workflow article.
 *
 * @return string
 */
function odd_note_mac_image_workflow_content() {
	return <<<'HTML'
<!-- wp:paragraph -->
<p>WordPress는 이미지를 올릴 때 여러 크기를 만들고 브라우저가 화면에 맞는 파일을 고르도록 도와줍니다. 그렇다고 카메라 원본이나 4K 스크린샷을 그대로 올려도 된다는 뜻은 아닙니다. 업로드 전에 실제 표시 크기로 줄이고 콘텐츠에 맞는 포맷을 고르면 유료 최적화 플러그인 없이도 전송량을 크게 줄일 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>이 글은 M1 Pro Mac과 WordPress 7.0.2 환경에서 확인한 결과를 바탕으로, <strong>원본 보관 → 리사이즈 → 포맷 변환 → WordPress 확인</strong>을 반복 가능한 작업으로 정리합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. 원본과 업로드본을 먼저 분리한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>이미지 폴더를 <code>original</code>, <code>convert</code>, <code>upload-ready</code> 세 단계로 나눕니다. 원본은 건드리지 않고 복사본만 변환합니다. 그래야 압축 품질이 나쁘거나 잘못 잘랐을 때 다시 시작할 수 있고, WordPress 미디어 파일을 원본 보관소처럼 사용하는 실수도 피할 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. WordPress가 해주는 일과 못 해주는 일을 구분한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WordPress는 업로드한 이미지에서 여러 중간 크기를 만들고, 생성한 HTML에 <code>srcset</code>과 <code>sizes</code>를 추가합니다. 브라우저는 이 정보를 보고 화면 폭과 해상도에 적합한 파일을 선택할 수 있습니다. 자세한 동작은 <a href="https://developer.wordpress.org/apis/responsive-images/">WordPress 반응형 이미지 공식 문서</a>에서 확인할 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>WordPress는 기본적으로 PNG를 제외한 이미지의 한 변이 2,560px를 넘으면 축소본을 만들어 가장 큰 사용 이미지로 삼고 원본도 보관합니다. 다만 테마의 실제 표시 크기, 콘텐츠에 맞는 포맷, 시각 품질까지 판단하지는 않습니다. 본문에서 최대 1,200px로 표시할 사진이라면 6,000px 원본을 업로드 전에 적정 크기로 줄이는 편이 저장 공간과 백업 시간까지 아낍니다. 자세한 기본 동작은 <a href="https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/">WordPress 대형 이미지 공식 안내</a>에서 확인할 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. 이미지 종류에 따라 포맷을 고른다</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li><strong>사진:</strong> JPEG, WebP, AVIF를 같은 화면 크기로 비교합니다.</li><li><strong>작은 글자가 있는 UI 스크린샷:</strong> PNG와 WebP를 100% 확대해 글자 번짐을 확인합니다.</li><li><strong>투명 배경 이미지:</strong> 알파 채널이 필요한지 확인하고 WebP 또는 PNG를 비교합니다.</li><li><strong>로고와 단순 도형:</strong> 출처가 안전한 벡터 원본이 있다면 SVG가 적합할 수 있지만, WordPress 업로드 정책과 보안 설정을 먼저 확인합니다.</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>‘가장 최신 포맷’이 매번 가장 작은 것은 아닙니다. 같은 품질 숫자도 포맷 사이에서 같은 시각 품질을 뜻하지 않으므로 파일 크기와 실제 화면을 함께 봐야 합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. 한두 장은 Finder 빠른 동작으로 처리한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Finder에서 이미지를 선택하고 <strong>빠른 동작 → 이미지 변환</strong>을 사용하면 별도 앱 없이 크기와 포맷을 바꿀 수 있습니다. Apple의 <a href="https://support.apple.com/guide/mac-help/perform-quick-actions-in-the-finder-on-mac-mchl97ff9142/mac">Finder 빠른 동작 안내</a>는 HEIC 사진을 JPEG나 PNG로 바꾸는 예를 제공합니다. 현재 Mac의 지원 포맷은 파일 종류와 확장 기능에 따라 달라질 수 있으므로 WebP가 기본으로 된다고 단정하지 않습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. 반복 변환은 cwebp 명령으로 고정한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WebP 변환을 반복한다면 Google의 <code>cwebp</code> 도구를 사용해 규칙을 명령으로 남길 수 있습니다. <a href="https://formulae.brew.sh/formula/webp">Homebrew의 WebP formula</a>로 설치한 뒤, 가로 사진은 너비를 1,600px로, 세로 사진은 높이를 1,600px로 지정할 수 있습니다. 품질은 82로 설정하고 메타데이터는 복사하지 않는 예시입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>brew install webp

# 가로 사진: 너비 1,600px, 높이는 비율에 맞춰 계산
cwebp -preset photo -q 82 -resize 1600 0 -metadata none input.jpg -o output.webp

# 세로 사진: 높이 1,600px, 너비는 비율에 맞춰 계산
cwebp -preset photo -q 82 -resize 0 1600 -metadata none input.jpg -o output.webp</code></pre>
<!-- /wp:code -->

<!-- wp:paragraph -->
<p><code>-q</code>는 0부터 100 사이의 손실 압축 품질, <code>-resize</code>는 크기 변경, <code>-metadata none</code>은 메타데이터 제거 옵션입니다. <code>-resize 1600 0</code>은 긴 변을 자동으로 찾는 명령이 아니라 너비를 지정하는 명령입니다. 따라서 먼저 이미지 방향과 크기를 확인하고, 이미 1,600px보다 작은 파일은 확대하지 않도록 변환 대상에서 제외합니다. 투명 이미지나 글자가 많은 화면에는 같은 값을 그대로 적용하지 말고 <a href="https://developers.google.com/speed/webp/docs/cwebp">cwebp 공식 옵션</a>의 <code>-lossless</code>, <code>-preset text</code> 등을 별도로 시험합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. 실제 Odd Note 이미지로 비교해 봤다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>2026년 8월 18일, 이 사이트의 1,200×630 공유 카드 PNG를 WordPress의 Imagick 편집기로 변환했습니다. 원본은 992,289바이트였고 JPEG·WebP·AVIF는 모두 품질 82로 저장했습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>포맷</th><th>파일 크기</th></tr></thead><tbody><tr><td>PNG 원본</td><td>992,289 B</td></tr><tr><td>JPEG</td><td>76,432 B</td></tr><tr><td>WebP</td><td>51,928 B</td></tr><tr><td>AVIF</td><td>56,071 B</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>이 한 장에서는 WebP가 AVIF보다 작았습니다. 다만 같은 품질 숫자가 동일한 시각 품질을 보장하지 않으므로 이 표만으로 포맷의 우열을 정할 수는 없습니다. 실제 글에 쓸 사진과 글자가 있는 스크린샷을 각각 변환해 모바일 화면과 100% 확대 상태에서 확인해야 합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. AVIF는 서버 지원부터 확인한다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WordPress는 6.5부터 AVIF 업로드를 지원하지만 서버의 GD 또는 Imagick 라이브러리가 AVIF를 처리할 수 있어야 합니다. 관리자 화면의 <strong>도구 → 사이트 건강 → 정보 → 미디어 처리</strong>에서 지원 포맷을 확인합니다. 이 Odd Note 서버는 점검 당시 Imagick을 사용했고 WebP와 AVIF를 모두 지원했습니다. 환경별 차이는 <a href="https://make.wordpress.org/core/2024/02/23/wordpress-6-5-adds-avif-support/">WordPress AVIF 공식 안내</a>를 참고합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. 업로드 후 네 가지를 다시 본다</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><li>미디어 정보에 기대한 픽셀 크기와 포맷이 표시되는가?</li><li>본문 HTML에 적절한 <code>srcset</code>과 <code>sizes</code>가 생성됐는가?</li><li>첫 화면에서 가장 큰 대표 이미지를 불필요하게 지연 로딩하지 않았는가?</li><li>모바일 네트워크에서 실제 전송 파일이 원본보다 작은가?</li></ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>최적화 플러그인을 추가했다면 같은 이미지를 두 번 압축하지 않는지도 확인합니다. 파일 크기 감소는 로딩 경험을 개선할 수 있지만 검색 순위나 PageSpeed 점수 상승을 보장하지는 않습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">발행 전 이미지 체크리스트</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>원본과 업로드본을 다른 폴더에 보관했다.</li><li>실제 표시 크기보다 지나치게 큰 파일을 줄였다.</li><li>사진과 스크린샷을 서로 다른 기준으로 비교했다.</li><li>파일명과 대체 텍스트가 이미지 내용을 설명한다.</li><li>WebP·AVIF 서버 지원을 사이트 건강에서 확인했다.</li><li>모바일과 100% 확대 화면에서 품질을 확인했다.</li><li>변환 전후 크기와 작업 규칙을 기록했다.</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><strong>결론:</strong> 이미지 최적화는 포맷 하나를 고르는 일이 아니라, 실제 표시 크기·콘텐츠별 품질·WordPress 반응형 이미지·측정을 하나의 반복 가능한 Mac 워크플로로 묶는 일입니다.</p>
<!-- /wp:paragraph -->
HTML;
}

/**
 * Publish the Mac image optimization workflow article without duplicates.
 *
 * @param int $owner_id Author user ID.
 * @param int $mac_workflow_id Optional Mac workflow category ID.
 * @return int
 */
function odd_note_publish_mac_image_workflow( $owner_id, $mac_workflow_id = 0 ) {
	if ( ! $mac_workflow_id ) {
		$mac_workflow_id = odd_note_bootstrap_category(
			'맥 워크플로',
			'mac-workflow',
			'Mac으로 일하고 만들고 운영하는 과정을 더 단단하게 다듬는 방법입니다.'
		);
	}

	$post_id = odd_note_bootstrap_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_name'      => 'mac-wordpress-image-optimization-workflow',
			'post_title'     => '맥에서 WordPress 이미지 용량 줄이기: 리사이즈·WebP 변환 워크플로',
			'post_excerpt'   => '원본 보관부터 리사이즈, WebP·AVIF 비교, WordPress 업로드 후 확인까지 유료 플러그인 없이 반복해서 사용할 수 있는 이미지 최적화 과정을 정리했습니다.',
			'post_content'   => odd_note_mac_image_workflow_content(),
			'post_author'    => $owner_id,
			'post_category'  => array( $mac_workflow_id ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);

	wp_set_post_tags( $post_id, array( 'macOS', 'WordPress 이미지', 'WebP', '성능 최적화' ), false );

	return $post_id;
}

/**
 * Rebuild a menu owned by this bootstrap.
 *
 * @param string                      $name Menu name.
 * @param array<int,array<string,mixed>> $items Menu items.
 * @return int
 */
function odd_note_bootstrap_menu( $name, $items ) {
	$menu = wp_get_nav_menu_object( $name );

	if ( $menu ) {
		$menu_id = (int) $menu->term_id;
	} else {
		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			odd_note_bootstrap_fail( '메뉴 생성 실패: ' . $menu_id->get_error_message() );
		}
	}

	$existing_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
	foreach ( (array) $existing_items as $existing_item ) {
		wp_delete_post( (int) $existing_item->ID, true );
	}

	foreach ( $items as $item ) {
		$item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $item['title'],
				'menu-item-object-id' => $item['object_id'],
				'menu-item-object'    => $item['object'],
				'menu-item-type'      => $item['type'],
				'menu-item-status'    => 'publish',
			)
		);

		if ( is_wp_error( $item_id ) ) {
			odd_note_bootstrap_fail( '메뉴 항목 생성 실패: ' . $item_id->get_error_message() );
		}
	}

	return $menu_id;
}

/**
 * Check that a menu still matches the bootstrap-owned item sequence.
 *
 * @param int                           $menu_id Menu term ID.
 * @param array<int,array<string,mixed>> $expected_items Expected items.
 * @return bool
 */
function odd_note_menu_matches( $menu_id, $expected_items ) {
	$items = wp_get_nav_menu_items( $menu_id );
	if ( count( (array) $items ) !== count( $expected_items ) ) {
		return false;
	}

	foreach ( array_values( (array) $items ) as $index => $item ) {
		$expected = $expected_items[ $index ];
		if (
			$item->title !== $expected['title'] ||
			(int) $item->object_id !== (int) $expected['object_id'] ||
			$item->object !== $expected['object'] ||
			$item->type !== $expected['type']
		) {
			return false;
		}
	}

	return true;
}

/**
 * Add and activate the three core editorial desks without rewriting legacy posts.
 *
 * @param int $owner_id Author user ID.
 * @return void
 */
function odd_note_apply_editorial_focus( $owner_id ) {
	$it_news_id = odd_note_core_category(
		'IT 최신 뉴스',
		'it-news',
		'개발자와 만드는 사람이 알아야 할 제품·플랫폼·정책 변화를 원문 출처와 실제 영향까지 정리합니다.'
	);
	$ai_paper_id = odd_note_core_category(
		'AI 논문 분석',
		'ai-paper-analysis',
		'새 AI 논문의 질문·방법·결과·한계를 풀어 읽고 실무 적용 가능성을 구분합니다.'
	);
	$business_id = odd_note_core_category(
		'사업 지식',
		'business-knowledge',
		'고객·시장·가격·수익모델·운영과 성장에 필요한 개념을 사례와 실행 질문으로 정리합니다.'
	);

	update_option(
		'odd_note_core_category_ids',
		array(
			'it-news'             => $it_news_id,
			'ai-paper-analysis'   => $ai_paper_id,
			'business-knowledge'  => $business_id,
		),
		false
	);

	$legacy_description = '직접 써보고 구축한 AI 도구, 맥 워크플로, 홈서버 활용법을 기록하는 실전 블로그';
	if ( $legacy_description === get_option( 'blogdescription' ) ) {
		update_option( 'blogdescription', 'IT 최신 뉴스, AI 논문 분석, 사업 지식을 기술을 만들고 사업을 운영하는 사람의 관점으로 정리합니다.' );
	}

	$owner = get_userdata( $owner_id );
	if ( $owner && 'AI 도구, 맥 워크플로, 홈서버 운영을 직접 실험하고 기록합니다.' === $owner->description ) {
		$result = wp_update_user(
			array(
				'ID'          => $owner_id,
				'description' => 'IT 최신 뉴스, AI 논문 분석, 사업 지식을 개발과 운영 관점에서 검증하고 기록합니다.',
			)
		);
		if ( is_wp_error( $result ) ) {
			odd_note_bootstrap_fail( '작성자 소개를 새 편집 방향으로 갱신하지 못했습니다.' );
		}
	}

	$about = get_page_by_path( 'about', OBJECT, 'page' );
	if ( $about && trim( (string) $about->post_content ) === trim( odd_note_legacy_about_content() ) ) {
		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $about->ID,
					'post_content' => odd_note_about_content(),
				)
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			odd_note_bootstrap_fail( '소개 페이지를 새 편집 방향으로 갱신하지 못했습니다.' );
		}
	}

	$stories = get_page_by_path( 'stories', OBJECT, 'page' );
	if ( ! $stories || ! $about ) {
		odd_note_bootstrap_fail( '핵심 메뉴에 필요한 전체 글 또는 소개 페이지를 찾지 못했습니다.' );
	}

	$new_items = array(
		array( 'title' => '전체 글', 'object_id' => (int) $stories->ID, 'object' => 'page', 'type' => 'post_type' ),
		array( 'title' => 'IT 최신 뉴스', 'object_id' => $it_news_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => 'AI 논문 분석', 'object_id' => $ai_paper_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => '사업 지식', 'object_id' => $business_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => '소개', 'object_id' => (int) $about->ID, 'object' => 'page', 'type' => 'post_type' ),
	);
	$new_menu_id = odd_note_bootstrap_menu( 'Odd Note Primary v2', $new_items );

	$legacy_menu = wp_get_nav_menu_object( 'Odd Note Primary' );
	$ai_tools    = get_term_by( 'slug', 'ai-tools', 'category' );
	$mac_flow    = get_term_by( 'slug', 'mac-workflow', 'category' );
	$home_server = get_term_by( 'slug', 'home-server', 'category' );
	$legacy_items = array();
	if ( $legacy_menu && $ai_tools && $mac_flow && $home_server ) {
		$legacy_items = array(
			array( 'title' => '전체 글', 'object_id' => (int) $stories->ID, 'object' => 'page', 'type' => 'post_type' ),
			array( 'title' => 'AI 도구', 'object_id' => (int) $ai_tools->term_id, 'object' => 'category', 'type' => 'taxonomy' ),
			array( 'title' => '맥 워크플로', 'object_id' => (int) $mac_flow->term_id, 'object' => 'category', 'type' => 'taxonomy' ),
			array( 'title' => '홈서버 실전', 'object_id' => (int) $home_server->term_id, 'object' => 'category', 'type' => 'taxonomy' ),
			array( 'title' => '소개', 'object_id' => (int) $about->ID, 'object' => 'page', 'type' => 'post_type' ),
		);
	}

	$theme_mods = (array) get_option( 'theme_mods_odd-note', array() );
	$locations  = isset( $theme_mods['nav_menu_locations'] ) ? (array) $theme_mods['nav_menu_locations'] : array();
	$current_id = isset( $locations['primary'] ) ? (int) $locations['primary'] : 0;
	$can_switch = ! $current_id || $current_id === $new_menu_id;
	if ( $legacy_menu && $current_id === (int) $legacy_menu->term_id && odd_note_menu_matches( $current_id, $legacy_items ) ) {
		$can_switch = true;
	}

	if ( $can_switch ) {
		$locations['primary']                 = $new_menu_id;
		$theme_mods['nav_menu_locations']     = $locations;
		update_option( 'theme_mods_odd-note', $theme_mods );
	} else {
		echo '알림: 사용자가 수정한 헤더 메뉴를 보존했습니다. 새 핵심 카테고리는 생성되었습니다.' . PHP_EOL;
	}
}

/**
 * Read one version-controlled editorial article body.
 *
 * @param string $slug Article slug and source filename stem.
 * @return string
 */
function odd_note_editorial_content( $slug ) {
	if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		odd_note_bootstrap_fail( '편집 글 슬러그 형식이 올바르지 않습니다.' );
	}

	$path = '/opt/odd-note/content/posts/' . $slug . '.html';
	if ( ! is_readable( $path ) ) {
		odd_note_bootstrap_fail( '편집 글 원본을 읽을 수 없습니다: ' . $slug );
	}

	$content = file_get_contents( $path );
	if ( false === $content || '' === trim( $content ) ) {
		odd_note_bootstrap_fail( '편집 글 원본이 비어 있습니다: ' . $slug );
	}

	return $content;
}

/**
 * Publish one article per core editorial desk.
 *
 * @param int $owner_id Author user ID.
 * @return array<string,int>
 */
function odd_note_publish_editorial_series( $owner_id ) {
	$specs = array(
		'supabase-realtime-binary-state-sync' => array(
			'title'    => 'Supabase Realtime이 바이너리를 품었다: 그래도 WebSocket만 믿으면 안 되는 이유',
			'excerpt'  => '2026년 6월 Supabase Broadcast에 바이너리 payload가 추가됐습니다. 전송은 가벼워졌지만 전달 보장은 별개입니다. 원본 상태, Broadcast 신호, 재연결 동기화와 polling 안전망을 나누는 기준을 정리했습니다.',
			'category' => 'it-news',
			'tags'     => array( 'Supabase', 'Realtime', 'WebSocket', 'Redis', '실시간 아키텍처' ),
		),
		'spatialvlm-paper-review' => array(
			'title'    => '사진 한 장으로 사물 사이 거리를 재는 AI: SpatialVLM은 어디까지 믿을 수 있나',
			'excerpt'  => 'SpatialVLM은 1천만 장의 이미지에서 20억 개 공간 질문을 합성해 VLM에 거리와 크기를 가르쳤습니다. 논문의 방법, 결과와 숫자 뒤에 숨은 한계를 함께 읽습니다.',
			'category' => 'ai-paper-analysis',
			'tags'     => array( 'SpatialVLM', 'VLM', 'CVPR 2024', '3D 공간 추론', '로보틱스' ),
		),
		'ai-mvp-before-model' => array(
			'title'    => 'AI MVP, 모델부터 만들면 늦는다: 앱 리뷰 → 대기자 명단 → 가짜 AI의 검증 순서',
			'excerpt'  => '모델 정확도를 높이기 전에 고객 문제와 핵심 행동부터 확인해야 합니다. 앱 리뷰에서 문제를 찾고, 대기자 명단으로 행동을 확인하고, mock AI로 핵심 경험을 검증하는 실전 프레임워크입니다.',
			'category' => 'business-knowledge',
			'tags'     => array( 'AI MVP', '제품 검증', '고객 개발', '프로토타입', '1인 창업' ),
		),
	);

	// Validate every dependency before inserting the first post. This keeps a
	// missing source file, category conflict or user-owned slug from producing a
	// partially published series.
	foreach ( $specs as $slug => &$spec ) {
		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $existing && '1' !== get_post_meta( (int) $existing->ID, '_odd_note_bootstrap', true ) ) {
			odd_note_bootstrap_fail( '같은 슬러그의 사용자 글이 있어 자동 발행을 중단했습니다: ' . $slug );
		}

		$category = get_term_by( 'slug', $spec['category'], 'category' );
		if ( ! $category ) {
			odd_note_bootstrap_fail( '편집 글 카테고리를 찾을 수 없습니다: ' . $spec['category'] );
		}

		$spec['category_id'] = (int) $category->term_id;
		$spec['content']     = odd_note_editorial_content( $slug );
	}
	unset( $spec );

	$post_ids = array();
	foreach ( $specs as $slug => $spec ) {
		$post_id = odd_note_bootstrap_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_name'      => $slug,
				'post_title'     => $spec['title'],
				'post_excerpt'   => $spec['excerpt'],
				'post_content'   => $spec['content'],
				'post_author'    => $owner_id,
				'post_category'  => array( $spec['category_id'] ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		wp_set_post_tags( $post_id, $spec['tags'], false );
		update_post_meta( $post_id, '_odd_note_primary_category_id', $spec['category_id'] );
		update_post_meta( $post_id, '_odd_note_editorial_revision', '1.4.0' );
		$post_ids[ $slug ] = $post_id;
	}

	update_option( 'odd_note_editorial_post_ids', $post_ids, false );
	return $post_ids;
}

/**
 * Publish the dated computer-vision research briefing.
 *
 * @param int $owner_id Author user ID.
 * @return int
 */
function odd_note_publish_ai_cv_briefing( $owner_id ) {
	$slug     = 'ai-cv-sota-briefing-2026-08-23';
	$existing = get_page_by_path( $slug, OBJECT, 'post' );

	if ( $existing && '1' !== get_post_meta( (int) $existing->ID, '_odd_note_bootstrap', true ) ) {
		odd_note_bootstrap_fail( '같은 슬러그의 사용자 글이 있어 자동 발행을 중단했습니다: ' . $slug );
	}

	$category = get_term_by( 'slug', 'ai-paper-analysis', 'category' );
	if ( ! $category ) {
		odd_note_bootstrap_fail( 'AI 논문 분석 카테고리를 찾을 수 없습니다.' );
	}

	$post_id = odd_note_bootstrap_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_name'      => $slug,
			'post_title'     => 'AI CV SOTA 브리핑 — ArmorOCR·STEP·DreamHand (2026.08.23)',
			'post_excerpt'   => '사람은 읽지만 VLM은 놓치는 OCR, 1ms 아래의 포즈 이상탐지, 비디오 확산 모델을 3D 손 인코더로 바꾼 연구까지. 최신 CV 논문 세 편의 성과와 공개 상태, 실무 한계를 함께 읽습니다.',
			'post_content'   => odd_note_editorial_content( $slug ),
			'post_author'    => $owner_id,
			'post_category'  => array( (int) $category->term_id ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);

	wp_set_post_tags(
		$post_id,
		array( 'Computer Vision', 'ArmorOCR', 'STEP', 'DreamHand', 'VLM', 'Video Anomaly Detection', '3D Hand Reconstruction' ),
		false
	);
	update_post_meta( $post_id, '_odd_note_primary_category_id', (int) $category->term_id );
	update_post_meta( $post_id, '_odd_note_editorial_revision', '1.6.1' );

	$post_ids          = (array) get_option( 'odd_note_editorial_post_ids', array() );
	$post_ids[ $slug ] = $post_id;
	update_option( 'odd_note_editorial_post_ids', $post_ids, false );

	return $post_id;
}

/**
 * Publish the August 25 computer-vision research briefing.
 *
 * @param int $owner_id Author user ID.
 * @return int
 */
function odd_note_publish_ai_cv_briefing_august_25( $owner_id ) {
	$slug     = 'ai-cv-sota-briefing-2026-08-25';
	$existing = get_page_by_path( $slug, OBJECT, 'post' );

	if ( $existing && '1' !== get_post_meta( (int) $existing->ID, '_odd_note_bootstrap', true ) ) {
		odd_note_bootstrap_fail( '같은 슬러그의 사용자 글이 있어 자동 발행을 중단했습니다: ' . $slug );
	}

	$category = get_term_by( 'slug', 'ai-paper-analysis', 'category' );
	if ( ! $category ) {
		odd_note_bootstrap_fail( 'AI 논문 분석 카테고리를 찾을 수 없습니다.' );
	}

	$post_id = odd_note_bootstrap_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_name'      => $slug,
			'post_title'     => 'AI CV SOTA 브리핑 — Video-ORA-9B · A2DINOv3 · Stream3Dv2 (2026.08.25)',
			'post_excerpt'   => '정답 annotation을 rollout으로 쓰는 Video-ORA-9B, RGB·IR 통신을 제한한 A2DINOv3, training-free 스트리밍 3D 인식 Stream3Dv2의 방법·데이터·비교 결과와 공개 상태를 검증했습니다.',
			'post_content'   => odd_note_editorial_content( $slug ),
			'post_author'    => $owner_id,
			'post_category'  => array( (int) $category->term_id ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);

	wp_set_post_tags(
		$post_id,
		array( 'Computer Vision', 'Video-ORA-9B', 'OraRL', 'A2DINOv3', 'Stream3Dv2', 'RGB-IR', 'Open-Vocabulary 3D', 'AI 논문 분석' ),
		false
	);
	update_post_meta( $post_id, '_odd_note_primary_category_id', (int) $category->term_id );
	update_post_meta( $post_id, '_odd_note_editorial_revision', '1.8.0' );

	$post_ids          = (array) get_option( 'odd_note_editorial_post_ids', array() );
	$post_ids[ $slug ] = $post_id;
	update_option( 'odd_note_editorial_post_ids', $post_ids, false );

	return $post_id;
}

/**
 * Publish the August 26 computer-vision research briefing.
 *
 * @param int $owner_id Author user ID.
 * @return int
 */
function odd_note_publish_ai_cv_briefing_august_26( $owner_id ) {
	$slug     = 'ai-cv-sota-briefing-2026-08-26';
	$existing = get_page_by_path( $slug, OBJECT, 'post' );

	if ( $existing && '1' !== get_post_meta( (int) $existing->ID, '_odd_note_bootstrap', true ) ) {
		odd_note_bootstrap_fail( '같은 슬러그의 사용자 글이 있어 자동 발행을 중단했습니다: ' . $slug );
	}

	$category = get_term_by( 'slug', 'ai-paper-analysis', 'category' );
	if ( ! $category ) {
		odd_note_bootstrap_fail( 'AI 논문 분석 카테고리를 찾을 수 없습니다.' );
	}

	$post_id = odd_note_bootstrap_post(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_name'      => $slug,
			'post_title'     => 'AI CV SOTA 브리핑 — E2S-Pruner · FixAnything · GeoWAM (2026.08.26)',
			'post_excerpt'   => '학습 없는 VLM token pruning E2S-Pruner, 범용 3D rendering 보정 FixAnything, 미래 geometry로 주행하는 GeoWAM의 입출력·과정·비교 결과와 공개 상태를 검증했습니다.',
			'post_content'   => odd_note_editorial_content( $slug ),
			'post_author'    => $owner_id,
			'post_category'  => array( (int) $category->term_id ),
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);

	wp_set_post_tags(
		$post_id,
		array( 'Computer Vision', 'E2S-Pruner', 'FixAnything', 'GeoWAM', 'Visual Token Pruning', '3D Reconstruction', 'World Action Model', 'AI 논문 분석' ),
		false
	);
	update_post_meta( $post_id, '_odd_note_primary_category_id', (int) $category->term_id );
	update_post_meta( $post_id, '_odd_note_editorial_revision', '1.9.0' );

	$post_ids          = (array) get_option( 'odd_note_editorial_post_ids', array() );
	$post_ids[ $slug ] = $post_id;
	update_option( 'odd_note_editorial_post_ids', $post_ids, false );

	return $post_id;
}

/**
 * Publish one standalone deep dive for each paper in the CV briefing.
 *
 * @param int $owner_id Author user ID.
 * @return array<string,int>
 */
function odd_note_publish_ai_cv_deep_dives( $owner_id ) {
	$specs = array(
		'armorocr-adversarial-ocr-paper-analysis' => array(
			'title'   => 'ArmorOCR 논문 분석: 사람이 읽는 글자를 VLM도 읽게 만드는 입력·출력·학습 과정',
			'excerpt' => '원본 이미지 한 장을 받는 Qwen3-VL-8B가 privileged-view self-distillation과 네 가지 GRPO 보상으로 위치·인식·spotting·VQA를 학습하는 과정을 데이터셋과 비교 결과까지 분석합니다.',
			'tags'    => array( 'ArmorOCR', 'VLM', 'OCR', 'Qwen3-VL', 'GRPO', 'AdvSpot', 'AI 논문 분석' ),
		),
		'step-pose-video-anomaly-detection-paper-analysis' => array(
			'title'   => 'STEP 논문 분석: 포즈 시퀀스를 PCA 공간에서 학습해 영상 이상행동을 찾는 법',
			'excerpt' => '18개 관절의 12-frame 입력을 PCA·whitening·energy model로 처리하는 전 과정과 ShanghaiTech·UBnormal·MSAD 결과, 1,026 FPS 수치의 정확한 범위를 분석합니다.',
			'tags'    => array( 'STEP', 'Video Anomaly Detection', 'Human Pose', 'PCA', 'Energy-Based Model', 'ECCV 2026', 'AI 논문 분석' ),
		),
		'dreamhand-video-diffusion-3d-hand-paper-analysis' => array(
			'title'   => 'DreamHand 논문 분석: 비디오 확산 모델로 가려진 손의 3D 움직임을 복원하는 법',
			'excerpt' => '81-frame RGB clip을 Wan의 clean latent와 양방향 decoder로 처리해 양손 MANO 궤적을 만드는 과정, 7-source 학습 데이터와 OOS·K-free 결과의 의미를 분석합니다.',
			'tags'    => array( 'DreamHand', 'Video Diffusion Model', '3D Hand Reconstruction', 'MANO', 'Egocentric Video', 'Embodied AI', 'AI 논문 분석' ),
		),
		'orarl-video-ora-9b-paper-analysis' => array(
			'title'   => 'OraRL 논문 분석: 정답 Annotation을 Rollout으로 쓰는 Video-ORA-9B 학습법',
			'excerpt' => '이미지·비디오와 자연어 지시를 받는 Video-ORA-9B가 annotation을 oracle rollout으로 활용하는 과정과 7개 task family의 데이터셋·비교 결과·효율성 범위를 분석합니다.',
			'tags'    => array( 'OraRL', 'Video-ORA-9B', 'Video MLLM', 'Reinforcement Learning', 'Video Grounding', 'GOT-10k', 'AI 논문 분석' ),
		),
		'a2dinov3-rgb-ir-object-detection-paper-analysis' => array(
			'title'   => 'A2DINOv3 논문 분석: RGB와 IR의 정보 교환을 제한한 객체 탐지 모델',
			'excerpt' => 'Paired RGB·IR 입력을 공유 DINOv3와 저차원 SCP 통신 경로로 처리하는 과정, GAIIC2024·M3FD·FLIR·LLVIP 결과와 재현 한계를 분석합니다.',
			'tags'    => array( 'A2DINOv3', 'RGB-IR', 'Multimodal Object Detection', 'DINOv3', 'Thermal Imaging', 'GAIIC2024', 'AI 논문 분석' ),
		),
		'stream3dv2-streaming-zero-shot-3d-paper-analysis' => array(
			'title'   => 'Stream3Dv2 논문 분석: 2D Mask를 스트리밍 3D Instance로 묶는 법',
			'excerpt' => 'RGB-D·camera pose·intrinsic을 받아 SAM2·SAM3 mask를 3D instance로 누적하는 과정과 ScanNet200 비교 결과, training-free와 11 FPS의 범위를 분석합니다.',
			'tags'    => array( 'Stream3Dv2', 'Streaming 3D', 'Zero-Shot 3D', 'SAM2', 'SAM3', 'ScanNet200', 'Open-Vocabulary 3D', 'AI 논문 분석' ),
		),
		'e2s-pruner-vlm-token-pruning-paper-analysis' => array(
			'title'   => 'E2S-Pruner 논문 분석: 학습 없이 VLM Visual Token을 줄이는 Evidence Fusion',
			'excerpt' => '이미지·prompt와 VLM attention을 받아 head·layer evidence를 결합하고 token을 줄이는 과정, 여섯 benchmark의 유지율과 latency·memory·OCR 한계를 분석합니다.',
			'tags'    => array( 'E2S-Pruner', 'VLM', 'Visual Token Pruning', 'Dempster-Shafer', 'LLaVA', 'Qwen2-VL', 'Inference Optimization', 'AI 논문 분석' ),
		),
		'fixanything-3d-reconstruction-repair-paper-analysis' => array(
			'title'   => 'FixAnything 논문 분석: 하나의 Video Model로 3DGS·NeRF·Mesh 렌더를 보정하는 법',
			'excerpt' => '3D rendering video와 clean-frame mask를 Wan2.1-I2V·LoRA·Flow-DPO로 처리하는 과정, DL3DV 데이터와 네 representation의 비교 결과를 분석합니다.',
			'tags'    => array( 'FixAnything', '3D Reconstruction', '3DGS', 'NeRF', 'Video Diffusion', 'Wan2.1', 'Flow-DPO', 'ECCV 2026', 'AI 논문 분석' ),
		),
		'geowam-geometry-world-action-model-paper-analysis' => array(
			'title'   => 'GeoWAM 논문 분석: 미래 RGB 대신 3D Geometry를 예측해 주행하는 World Action Model',
			'excerpt' => 'Historical multiview RGB에서 future point map과 ego trajectory를 만드는 두 단계 학습, pretraining 데이터와 nuScenes·NAVSIM v2 결과를 분석합니다.',
			'tags'    => array( 'GeoWAM', 'World Action Model', 'Autonomous Driving', 'Future Geometry', 'Point Map', 'NAVSIM v2', 'Physical AI', 'AI 논문 분석' ),
		),
	);

	$category = get_term_by( 'slug', 'ai-paper-analysis', 'category' );
	if ( ! $category ) {
		odd_note_bootstrap_fail( 'AI 논문 분석 카테고리를 찾을 수 없습니다.' );
	}

	// Validate the complete series before publishing the first post, so a
	// missing source or user-owned slug cannot leave a partial migration.
	foreach ( $specs as $slug => &$spec ) {
		$existing = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $existing && '1' !== get_post_meta( (int) $existing->ID, '_odd_note_bootstrap', true ) ) {
			odd_note_bootstrap_fail( '같은 슬러그의 사용자 글이 있어 자동 발행을 중단했습니다: ' . $slug );
		}

		$spec['content'] = odd_note_editorial_content( $slug );
	}
	unset( $spec );

	$post_ids = (array) get_option( 'odd_note_editorial_post_ids', array() );
	$created  = array();
	foreach ( $specs as $slug => $spec ) {
		$post_id = odd_note_bootstrap_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'post_name'      => $slug,
				'post_title'     => $spec['title'],
				'post_excerpt'   => $spec['excerpt'],
				'post_content'   => $spec['content'],
				'post_author'    => $owner_id,
				'post_category'  => array( (int) $category->term_id ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		wp_set_post_tags( $post_id, $spec['tags'], false );
		update_post_meta( $post_id, '_odd_note_primary_category_id', (int) $category->term_id );
		update_post_meta( $post_id, '_odd_note_editorial_revision', '1.9.0' );
		$post_ids[ $slug ] = $post_id;
		$created[ $slug ]  = $post_id;
	}

	update_option( 'odd_note_editorial_post_ids', $post_ids, false );
	return $created;
}

/**
 * Promote the new technology briefing when the original seed is still featured.
 *
 * @param int $it_post_id Technology briefing post ID.
 * @return void
 */
function odd_note_promote_editorial_feature( $it_post_id ) {
	$sticky_posts = array_map( 'intval', (array) get_option( 'sticky_posts', array() ) );
	$legacy_post  = get_page_by_path( 'm1-pro-wordpress-home-server', OBJECT, 'post' );
	$legacy_id    = $legacy_post ? (int) $legacy_post->ID : 0;

	if ( empty( $sticky_posts ) || ( 1 === count( $sticky_posts ) && $legacy_id === $sticky_posts[0] ) ) {
		update_option( 'sticky_posts', array( $it_post_id ) );
	}
}

$admin_user     = odd_note_bootstrap_input( '관리자 아이디' );
$admin_password = odd_note_bootstrap_input( '관리자 비밀번호' );
$admin_email    = odd_note_bootstrap_input( '관리자 이메일' );
$site_title     = odd_note_bootstrap_input( '사이트 제목' );
$site_url       = untrailingslashit( odd_note_bootstrap_input( '사이트 주소' ) );

if ( ! validate_username( $admin_user ) || strlen( $admin_user ) < 8 ) {
	odd_note_bootstrap_fail( '관리자 아이디 형식이 올바르지 않습니다.' );
}

if ( strlen( $admin_password ) < 32 ) {
	odd_note_bootstrap_fail( '관리자 비밀번호가 너무 짧습니다.' );
}

if ( ! is_email( $admin_email ) ) {
	odd_note_bootstrap_fail( '관리자 이메일 형식이 올바르지 않습니다.' );
}

if ( '' === $site_title ) {
	odd_note_bootstrap_fail( '사이트 제목이 비어 있습니다.' );
}

$site_url_parts = wp_parse_url( $site_url );
if (
	! is_array( $site_url_parts ) ||
	empty( $site_url_parts['scheme'] ) ||
	empty( $site_url_parts['host'] ) ||
	! in_array( $site_url_parts['scheme'], array( 'http', 'https' ), true )
) {
	odd_note_bootstrap_fail( '사이트 주소 형식이 올바르지 않습니다.' );
}

$target_version = '1.9.0';
$installed      = is_blog_installed();
$state          = $installed ? get_option( 'odd_note_bootstrap_state', '' ) : '';

if ( $installed && 'complete' === $state ) {
	update_option( 'siteurl', $site_url );
	update_option( 'home', $site_url );

	$current_version = (string) get_option( 'odd_note_bootstrap_version', '1.0.0' );
	if ( version_compare( $current_version, $target_version, '<' ) ) {
		$owner_id = (int) get_option( 'odd_note_owner_user_id', 0 );
		if ( ! $owner_id || ! get_userdata( $owner_id ) ) {
			odd_note_bootstrap_fail( '초기 작성자 계정을 찾지 못했습니다.' );
		}

		if ( version_compare( $current_version, '1.1.0', '<' ) ) {
			odd_note_publish_ai_blog_workflow( $owner_id );
		}

		if ( version_compare( $current_version, '1.2.0', '<' ) ) {
			odd_note_apply_editor_identity( $owner_id, $admin_email );
		}

		if ( version_compare( $current_version, '1.2.1', '<' ) ) {
			odd_note_publish_mac_image_workflow( $owner_id );
		}

		if ( version_compare( $current_version, '1.3.0', '<' ) ) {
			odd_note_apply_editorial_focus( $owner_id );
		}

		if ( version_compare( $current_version, '1.4.0', '<' ) ) {
			$editorial_ids = odd_note_publish_editorial_series( $owner_id );
			odd_note_promote_editorial_feature( $editorial_ids['supabase-realtime-binary-state-sync'] );
		}

		if ( version_compare( $current_version, '1.5.0', '<' ) ) {
			odd_note_publish_ai_cv_briefing( $owner_id );
		}

		if ( version_compare( $current_version, '1.6.1', '<' ) ) {
			if ( version_compare( $current_version, '1.5.0', '>=' ) ) {
				odd_note_publish_ai_cv_briefing( $owner_id );
			}
			odd_note_publish_ai_cv_deep_dives( $owner_id );
		}

		if ( version_compare( $current_version, '1.7.1', '<' ) ) {
			odd_note_publish_ai_cv_briefing_august_25( $owner_id );
		}

		if ( version_compare( $current_version, '1.8.0', '<' ) ) {
			odd_note_publish_ai_cv_deep_dives( $owner_id );
			odd_note_publish_ai_cv_briefing_august_25( $owner_id );
		}

		if ( version_compare( $current_version, '1.9.0', '<' ) ) {
			odd_note_publish_ai_cv_deep_dives( $owner_id );
			odd_note_publish_ai_cv_briefing_august_26( $owner_id );
		}

		update_option( 'odd_note_bootstrap_version', $target_version, false );
		echo 'Odd Note 공개 설정과 콘텐츠를 최신 상태로 갱신했습니다.' . PHP_EOL;
		exit( 0 );
	}

	echo 'Odd Note는 이미 설치 및 초기화되었습니다.' . PHP_EOL;
	exit( 0 );
}

if ( $installed && 'building' !== $state ) {
	odd_note_bootstrap_fail( '기존 WordPress 설치가 감지되어 자동 변경을 중단했습니다.', 2 );
}

if ( ! $installed ) {
	$install_result = wp_install(
		$site_title,
		$admin_user,
		$admin_email,
		false,
		'',
		$admin_password,
		'ko_KR'
	);

	$owner_id = isset( $install_result['user_id'] ) ? (int) $install_result['user_id'] : 0;

	if ( ! $owner_id ) {
		odd_note_bootstrap_fail( 'WordPress 관리자 계정을 만들지 못했습니다.' );
	}

	update_option( 'odd_note_bootstrap_state', 'building', false );
	update_option( 'odd_note_owner_user_id', $owner_id, false );
} else {
	$owner_id = (int) get_option( 'odd_note_owner_user_id', 0 );

	if ( ! $owner_id ) {
		$user = get_user_by( 'login', $admin_user );
		$owner_id = $user ? (int) $user->ID : 0;
	}

	if ( ! $owner_id ) {
		odd_note_bootstrap_fail( '초기화할 관리자 계정을 찾지 못했습니다.' );
	}
}

update_option( 'siteurl', $site_url );
update_option( 'home', $site_url );

wp_set_current_user( $owner_id );

$theme = wp_get_theme( 'odd-note' );
if ( ! $theme->exists() ) {
	odd_note_bootstrap_fail( 'Odd Note 테마를 찾지 못했습니다.' );
}
switch_theme( 'odd-note' );

odd_note_apply_editor_identity( $owner_id, $admin_email );

update_option( 'blogname', $site_title );
update_option( 'blogdescription', 'IT 최신 뉴스, AI 논문 분석, 사업 지식을 기술을 만들고 사업을 운영하는 사람의 관점으로 정리합니다.' );
update_option( 'admin_email', $admin_email );
update_option( 'blog_public', 0 );
update_option( 'timezone_string', 'Asia/Seoul' );
update_option( 'date_format', 'Y.m.d' );
update_option( 'time_format', 'H:i' );
update_option( 'start_of_week', 1 );
update_option( 'users_can_register', 0 );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
update_option( 'default_pingback_flag', 0 );
update_option( 'comment_moderation', 1 );
update_option( 'comments_notify', 0 );
update_option( 'posts_per_page', 9 );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'uploads_use_yearmonth_folders', 1 );

foreach (
	array(
		array( 'hello-world', 'post' ),
		array( 'sample-page', 'page' ),
	)
	as $default_content
) {
	$default_post = get_page_by_path( $default_content[0], OBJECT, $default_content[1] );
	if ( $default_post ) {
		wp_delete_post( (int) $default_post->ID, true );
	}
}

$default_category_id = (int) get_option( 'default_category', 1 );
$home_server_term    = wp_update_term(
	$default_category_id,
	'category',
	array(
		'name'        => '홈서버 실전',
		'slug'        => 'home-server',
		'description' => '집에 있는 장비로 안정적인 서비스를 직접 운영하며 얻은 기록입니다.',
	)
);

if ( is_wp_error( $home_server_term ) ) {
	$home_server_id = odd_note_bootstrap_category(
		'홈서버 실전',
		'home-server',
		'집에 있는 장비로 안정적인 서비스를 직접 운영하며 얻은 기록입니다.'
	);
} else {
	$home_server_id = (int) $home_server_term['term_id'];
}

$ai_tools_id = odd_note_bootstrap_category(
	'AI 도구',
	'ai-tools',
	'직접 사용해 본 AI 도구의 선택 기준, 활용법, 한계를 기록합니다.'
);
$mac_workflow_id = odd_note_bootstrap_category(
	'맥 워크플로',
	'mac-workflow',
	'Mac으로 일하고 만들고 운영하는 과정을 더 단단하게 다듬는 방법입니다.'
);
$it_news_id = odd_note_core_category(
	'IT 최신 뉴스',
	'it-news',
	'개발자와 만드는 사람이 알아야 할 제품·플랫폼·정책 변화를 원문 출처와 실제 영향까지 정리합니다.'
);
$ai_paper_id = odd_note_core_category(
	'AI 논문 분석',
	'ai-paper-analysis',
	'새 AI 논문의 질문·방법·결과·한계를 풀어 읽고 실무 적용 가능성을 구분합니다.'
);
$business_id = odd_note_core_category(
	'사업 지식',
	'business-knowledge',
	'고객·시장·가격·수익모델·운영과 성장에 필요한 개념을 사례와 실행 질문으로 정리합니다.'
);
update_option(
	'odd_note_core_category_ids',
	array(
		'it-news'            => $it_news_id,
		'ai-paper-analysis'  => $ai_paper_id,
		'business-knowledge' => $business_id,
	),
	false
);
update_option( 'default_category', $home_server_id );

$home_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'home',
		'post_title'   => '홈',
		'post_content' => '<p>Odd Note의 인터랙티브 첫 화면입니다.</p>',
		'post_author'  => $owner_id,
	)
);

$stories_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'stories',
		'post_title'   => '전체 글',
		'post_content' => '',
		'post_author'  => $owner_id,
	)
);

$about_content = odd_note_about_content();

$about_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'about',
		'post_title'   => '소개',
		'post_content' => $about_content,
		'post_author'  => $owner_id,
	)
);

$contact_content = odd_note_contact_content( $admin_email );

$contact_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'contact',
		'post_title'   => '문의',
		'post_content' => $contact_content,
		'post_author'  => $owner_id,
	)
);

$privacy_content = <<<'HTML'
<!-- wp:paragraph -->
<p><strong>공개 전 확인이 필요한 초기 운영 초안입니다.</strong> 실제 도메인, 연락처, 광고·분석 도구를 연결할 때 최종 내용으로 갱신합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">수집하는 정보</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>현재 사이트는 회원 가입과 일반 댓글을 받지 않습니다. WordPress 관리자 로그인 과정에서는 보안과 세션 유지를 위해 필수 쿠키가 사용될 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">광고와 통계</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>현재 광고 및 방문자 분석 도구는 연결하지 않았습니다. 향후 Google AdSense나 분석 서비스를 도입하면 사용되는 쿠키, 수집 목적, 제3자 제공 여부와 거부 방법을 이 페이지에 추가합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">보관과 문의</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>서비스 운영에 필요한 정보는 목적 달성 또는 법적 보관 기간이 끝나면 안전하게 삭제합니다. 개인정보 관련 문의는 문의 페이지의 연락처로 접수합니다.</p>
<!-- /wp:paragraph -->
HTML;

$privacy_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'privacy',
		'post_title'   => '개인정보처리방침',
		'post_content' => $privacy_content,
		'post_author'  => $owner_id,
	)
);

$disclosure_content = <<<'HTML'
<!-- wp:paragraph -->
<p>Odd Note의 일부 글에는 광고, 제휴 링크 또는 협찬 내용이 포함될 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><li>경제적 대가가 있는 콘텐츠는 글의 시작 부분처럼 독자가 쉽게 확인할 수 있는 위치에 표시합니다.</li><li>제휴 링크를 통해 구매가 발생하면 운영자가 수수료를 받을 수 있으나 구매 가격에는 영향을 주지 않습니다.</li><li>광고 또는 협찬 여부와 관계없이 실제 판단과 편집 방향은 운영자가 독립적으로 결정합니다.</li><li>직접 사용하지 않은 제품과 서비스를 사용한 것처럼 소개하지 않습니다.</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>광고 및 제휴와 관련된 문의는 문의 페이지를 이용해 주세요.</p>
<!-- /wp:paragraph -->
HTML;

$disclosure_id = odd_note_bootstrap_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'disclosure',
		'post_title'   => '광고·제휴 고지',
		'post_content' => $disclosure_content,
		'post_author'  => $owner_id,
	)
);

$home_server_post = <<<'HTML'
<!-- wp:paragraph -->
<p>사용하지 않는 M1 Pro 맥북이 있다면 WordPress를 돌릴 성능은 충분합니다. 실제 고민은 속도가 아니라 전원, 절전, 네트워크, 복구입니다. 이번 구축에서는 맥북을 단순한 테스트 장비가 아니라 작은 홈서버로 사용할 때 무엇을 먼저 결정해야 하는지 확인했습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">휴대폰보다 맥북이 나았던 이유</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>휴대폰도 웹 서버를 실행할 수 있지만 장시간 프로세스 유지, 저장 공간 관리, 운영체제의 백그라운드 제한, 배터리 발열 대응이 어렵습니다. 반면 M1 Pro 맥북은 ARM64 Docker 이미지를 그대로 사용하고, 데이터베이스와 WordPress를 분리하며, 백업 스크립트까지 일반적인 서버 방식으로 구성할 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">구성은 단순하게</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><li>WordPress와 MariaDB는 서로 다른 컨테이너로 실행합니다.</li><li>데이터베이스는 내부 네트워크에만 연결해 외부 포트를 열지 않습니다.</li><li>WordPress 확인 주소는 127.0.0.1에만 바인딩합니다.</li><li>외부 공개가 필요할 때만 Cloudflare Tunnel을 연결합니다.</li><li>테마 소스는 읽기 전용으로 마운트하고 데이터와 분리합니다.</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">가장 큰 운영 리스크</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>맥북이 잠들면 사이트도 멈춥니다. FileVault가 켜진 상태로 재부팅되면 사용자가 로그인하기 전 Docker Desktop이 시작되지 않을 수도 있습니다. 집의 전원과 인터넷 장애 역시 클라우드 서버보다 직접적인 영향을 줍니다. 따라서 전원 어댑터 사용 중 자동 잠자기 방지, 로그인 후 Docker 자동 시작, 외부 상태 확인, 오프사이트 백업이 필수입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">수익이 생기기 전과 후의 기준</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>초기 학습과 콘텐츠 검증 단계에는 집의 맥북이 좋은 선택입니다. 하지만 매출에 영향을 줄 만큼 장애 허용 시간이 짧아지면 동일한 Docker 구성을 VPS나 전용 장비로 이전하는 편이 합리적입니다. 홈서버의 목표는 영원히 무료로 버티는 것이 아니라, 비용을 쓰기 전에 운영을 이해하고 콘텐츠의 가능성을 검증하는 데 있습니다.</p>
<!-- /wp:paragraph -->
HTML;

$home_server_post_id = odd_note_bootstrap_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_name'     => 'm1-pro-wordpress-home-server',
		'post_title'    => 'M1 Pro 맥북을 WordPress 홈서버로 만들며 배운 것',
		'post_excerpt'  => '성능보다 중요했던 절전, 네트워크, 보안, 백업의 기준을 실제 구성에 맞춰 정리했습니다.',
		'post_content'  => $home_server_post,
		'post_author'   => $owner_id,
		'post_category' => array( $home_server_id ),
		'comment_status'=> 'closed',
		'ping_status'   => 'closed',
	)
);
wp_set_post_tags( $home_server_post_id, array( 'M1 Pro', 'WordPress', 'Docker', '홈서버' ), false );

$mac_post = <<<'HTML'
<!-- wp:paragraph -->
<p>맥북을 24시간 켜 두는 것과 안정적인 서버로 운영하는 것은 다른 문제입니다. 아래 항목은 복잡한 튜닝보다 먼저 확인해야 할 기본 체크리스트입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. 전원 연결 상태에서 잠들지 않게 하기</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>디스플레이가 꺼지는 것은 괜찮지만 시스템이 잠들면 컨테이너와 네트워크 연결도 멈춥니다. macOS 배터리 옵션에서 전원 어댑터 사용 중 자동 잠자기 방지를 켜고, 실제로 화면이 꺼진 뒤에도 사이트가 응답하는지 확인합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. 재부팅 이후의 복구 경로 확인하기</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Docker Desktop은 사용자 로그인 후 시작됩니다. FileVault 잠금 화면에 머물러 있으면 컨테이너의 재시작 정책만으로는 복구되지 않습니다. 정전 뒤 누가 로그인할지, 원격으로 확인할 방법이 있는지까지 운영 절차에 포함해야 합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. 데이터를 코드와 분리하기</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WordPress 파일과 데이터베이스는 Docker 볼륨에 두고, 직접 만든 테마는 작업 폴더에서 읽기 전용으로 연결했습니다. 컨테이너를 다시 만들어도 글과 업로드는 남고, 테마 변경 이력은 별도로 관리할 수 있습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. 백업은 생성보다 복구가 중요하다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>데이터베이스 덤프, WordPress 파일, 운영 설정을 같은 시점의 세트로 보관해야 합니다. 체크섬으로 파일 손상을 확인하고, 최근 백업은 맥북 밖에도 복사합니다. 백업 명령이 성공했다는 사실보다 빈 장비에서 실제로 복원되는지가 더 중요합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. 외부 공개 범위를 최소화하기</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>공유기의 80번과 443번 포트를 직접 열지 않고, 외부로 나가는 Tunnel 연결만 사용하면 공격 표면을 줄일 수 있습니다. 설치 화면과 관리자 설정이 끝나기 전에는 Tunnel도 시작하지 않는 것이 안전합니다.</p>
<!-- /wp:paragraph -->
HTML;

$mac_post_id = odd_note_bootstrap_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_name'     => 'macbook-always-on-server-checklist',
		'post_title'    => '맥북을 24시간 서버로 쓸 때 먼저 확인할 5가지',
		'post_excerpt'  => '잠자기, 재부팅, 데이터 분리, 복구, 외부 공개까지 맥북 홈서버의 기본 운영 체크리스트입니다.',
		'post_content'  => $mac_post,
		'post_author'   => $owner_id,
		'post_category' => array( $mac_workflow_id ),
		'comment_status'=> 'closed',
		'ping_status'   => 'closed',
	)
);
wp_set_post_tags( $mac_post_id, array( 'macOS', '맥북', '운영 체크리스트' ), false );

$ai_post = <<<'HTML'
<!-- wp:paragraph -->
<p>인터랙티브 웹은 첫인상을 강하게 만들지만, 수익형 블로그의 주인공은 결국 글입니다. 이번 테마는 “놀라움 30%, 읽기 70%”를 기준으로 효과를 선택했습니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">콘텐츠는 서버가 먼저 보여준다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>글 제목, 링크, 카테고리, 본문은 JavaScript가 없어도 HTML에 존재합니다. 검색엔진과 보조 기술이 콘텐츠를 읽을 수 있고, 스크립트 오류가 나도 방문자가 글을 잃지 않습니다. JavaScript는 커서 링, 카드 기울기, 스크롤 등장 효과처럼 없어도 되는 경험만 덧붙입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">네이티브 커서는 숨기지 않는다</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>커스텀 커서가 재미있어도 사용자가 클릭 위치를 잃으면 실패입니다. 기본 포인터는 그대로 유지하고, 정밀한 마우스가 있는 데스크톱에서만 작은 링이 따라오도록 했습니다. 터치 화면에서는 효과를 자동으로 끕니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">움직임을 선택할 권리</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>운영체제의 동작 줄이기 설정을 존중하고, 사이트 안에도 FX 끄기 버튼을 제공합니다. 효과가 꺼지면 자동 애니메이션뿐 아니라 부드러운 스크롤과 전환 효과도 즉시 멈춥니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">무거운 기술보다 작은 반응</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WebGL이나 대형 애니메이션 라이브러리 대신 CSS와 짧은 순수 JavaScript를 사용했습니다. 포인터가 멈추면 애니메이션 프레임도 멈추고, 화면 아래 이미지는 지연 로딩합니다. 화려함보다 글을 읽는 속도와 배터리를 우선한 선택입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">수익과 효과의 경계</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>광고를 메뉴나 인터랙티브 버튼처럼 보이게 만들면 안 됩니다. 향후 광고를 붙일 때도 히어로와 조작 요소에서는 떨어뜨리고, 글의 흐름을 끊지 않는 명확한 광고 영역만 사용할 계획입니다.</p>
<!-- /wp:paragraph -->
HTML;

$ai_post_id = odd_note_bootstrap_post(
	array(
		'post_type'     => 'post',
		'post_status'   => 'publish',
		'post_name'     => 'interactive-blog-without-sacrificing-speed',
		'post_title'    => '인터랙티브 블로그가 읽는 속도를 방해하지 않게 만든 방법',
		'post_excerpt'  => '커서와 카드 효과를 살리면서 검색성, 접근성, 배터리와 읽기 경험을 지키기 위해 선택한 기준입니다.',
		'post_content'  => $ai_post,
		'post_author'   => $owner_id,
		'post_category' => array( $ai_tools_id ),
		'comment_status'=> 'closed',
		'ping_status'   => 'closed',
	)
);
wp_set_post_tags( $ai_post_id, array( '인터랙션', '웹 접근성', '성능', 'AI 디자인' ), false );

odd_note_publish_ai_blog_workflow( $owner_id, $ai_tools_id );
odd_note_publish_mac_image_workflow( $owner_id, $mac_workflow_id );
$editorial_ids = odd_note_publish_editorial_series( $owner_id );
odd_note_publish_ai_cv_briefing( $owner_id );
odd_note_publish_ai_cv_deep_dives( $owner_id );
odd_note_publish_ai_cv_briefing_august_25( $owner_id );
odd_note_publish_ai_cv_briefing_august_26( $owner_id );

update_option( 'sticky_posts', array( $editorial_ids['supabase-realtime-binary-state-sync'] ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_id );
update_option( 'page_for_posts', $stories_id );
update_option( 'wp_page_for_privacy_policy', $privacy_id );

$primary_menu_id = odd_note_bootstrap_menu(
	'Odd Note Primary',
	array(
		array( 'title' => '전체 글', 'object_id' => $stories_id, 'object' => 'page', 'type' => 'post_type' ),
		array( 'title' => 'IT 최신 뉴스', 'object_id' => $it_news_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => 'AI 논문 분석', 'object_id' => $ai_paper_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => '사업 지식', 'object_id' => $business_id, 'object' => 'category', 'type' => 'taxonomy' ),
		array( 'title' => '소개', 'object_id' => $about_id, 'object' => 'page', 'type' => 'post_type' ),
	)
);

$footer_menu_id = odd_note_bootstrap_menu(
	'Odd Note Footer',
	array(
		array( 'title' => '소개', 'object_id' => $about_id, 'object' => 'page', 'type' => 'post_type' ),
		array( 'title' => '문의', 'object_id' => $contact_id, 'object' => 'page', 'type' => 'post_type' ),
		array( 'title' => '개인정보처리방침', 'object_id' => $privacy_id, 'object' => 'page', 'type' => 'post_type' ),
		array( 'title' => '광고·제휴 고지', 'object_id' => $disclosure_id, 'object' => 'page', 'type' => 'post_type' ),
	)
);

$locations             = array(
	'primary' => $primary_menu_id,
	'footer'  => $footer_menu_id,
);
$odd_note_theme_mods   = (array) get_option( 'theme_mods_odd-note', array() );
$odd_note_theme_mods['nav_menu_locations'] = $locations;
update_option( 'theme_mods_odd-note', $odd_note_theme_mods );

global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules( false );

update_option( 'fresh_site', 0 );
update_option( 'odd_note_bootstrap_version', $target_version, false );
update_option( 'odd_note_bootstrap_state', 'complete', false );

echo 'Odd Note 설치와 초기 콘텐츠 구성이 완료됐습니다.' . PHP_EOL;
echo '페이지 6개, 카테고리 6개, 글 20개, 메뉴 2개를 준비했습니다.' . PHP_EOL;
