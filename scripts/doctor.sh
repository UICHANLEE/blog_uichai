#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
cd "$project_root"

failures=0

ok() {
  printf '✓ %s\n' "$1"
}

warn() {
  printf '! %s\n' "$1"
}

fail() {
  printf '✗ %s\n' "$1" >&2
  failures=$((failures + 1))
}

if command -v docker >/dev/null 2>&1; then
  ok "Docker 명령을 찾았습니다."
else
  fail "Docker를 찾지 못했습니다."
fi

if docker info >/dev/null 2>&1; then
  ok "Docker 엔진이 실행 중입니다."
else
  fail "Docker 엔진에 연결할 수 없습니다."
fi

if docker compose config --quiet >/dev/null 2>&1; then
  ok "Compose 설정 문법이 정상입니다."
else
  fail "Compose 설정이 유효하지 않습니다. docker compose config를 확인하세요."
fi

for theme_file in \
  themes/odd-note/style.css \
  themes/odd-note/functions.php \
  themes/odd-note/front-page.php \
  themes/odd-note/theme.json \
  themes/odd-note/assets/css/site.css \
  themes/odd-note/assets/js/site.js \
  themes/odd-note/assets/images/og.png \
  themes/odd-note/assets/images/og-tech-business.png \
  content/posts/supabase-realtime-binary-state-sync.html \
  content/posts/spatialvlm-paper-review.html \
  content/posts/ai-mvp-before-model.html \
  content/posts/ai-cv-sota-briefing-2026-08-23.html; do
  if [ -s "$theme_file" ]; then
    ok "$theme_file 이 준비됐습니다."
  else
    fail "$theme_file 이 없거나 비어 있습니다."
  fi
done

if command -v node >/dev/null 2>&1 && node --check themes/odd-note/assets/js/site.js >/dev/null 2>&1; then
  ok "Odd Note JavaScript 문법이 정상입니다."
elif command -v node >/dev/null 2>&1; then
  fail "Odd Note JavaScript 문법 오류가 있습니다."
else
  warn "Node.js가 없어 Odd Note JavaScript 문법 검사를 건너뜁니다."
fi

for secret in secrets/db_password.txt secrets/db_root_password.txt; do
  if [ -s "$secret" ] && [ "$(wc -c < "$secret" | tr -d ' ')" -ge 32 ]; then
    ok "$secret 이 준비됐습니다."
  else
    fail "$secret 이 없거나 너무 짧습니다."
  fi
done

if [ -s secrets/wp_admin_user.txt ]; then
  ok "secrets/wp_admin_user.txt 이 준비됐습니다."
else
  fail "secrets/wp_admin_user.txt 이 없습니다."
fi

if [ -s secrets/wp_admin_password.txt ] && [ "$(wc -c < secrets/wp_admin_password.txt | tr -d ' ')" -ge 32 ]; then
  ok "secrets/wp_admin_password.txt 이 준비됐습니다."
else
  fail "secrets/wp_admin_password.txt 이 없거나 너무 짧습니다."
fi

if [ -s secrets/cloudflare_tunnel_token.txt ] && ! grep -q '^PASTE_' secrets/cloudflare_tunnel_token.txt; then
  ok "Cloudflare Tunnel 토큰이 준비됐습니다."
else
  warn "Cloudflare Tunnel 토큰은 아직 입력되지 않았습니다. 로컬 실행에는 문제없습니다."
fi

database_id="$(docker compose ps -q database 2>/dev/null || true)"
wordpress_id="$(docker compose ps -q wordpress 2>/dev/null || true)"

if [ -n "$database_id" ]; then
  database_health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$database_id" 2>/dev/null || true)"
  if [ "$database_health" = healthy ]; then
    ok "MariaDB가 healthy 상태입니다."
  else
    fail "MariaDB 상태: ${database_health:-unknown}"
  fi
else
  warn "MariaDB 컨테이너가 아직 시작되지 않았습니다."
fi

if [ -n "$wordpress_id" ]; then
  wordpress_health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$wordpress_id" 2>/dev/null || true)"
  if [ "$wordpress_health" = healthy ]; then
    ok "WordPress가 healthy 상태입니다."
  else
    fail "WordPress 상태: ${wordpress_health:-unknown}"
  fi

  if docker compose exec -T wordpress test -r /var/www/html/wp-content/themes/odd-note/front-page.php >/dev/null 2>&1; then
    ok "Odd Note 테마가 WordPress에 연결됐습니다."
  else
    fail "Odd Note 테마 마운트를 확인하지 못했습니다."
  fi

  if docker compose exec -T wordpress test -r /opt/odd-note/content/posts/ai-cv-sota-briefing-2026-08-23.html >/dev/null 2>&1; then
    ok "버전 관리되는 편집 글 원본이 WordPress에 연결됐습니다."
  else
    fail "편집 글 원본 마운트를 확인하지 못했습니다."
  fi

  if docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; exit(get_option("odd_note_bootstrap_state") === "complete" ? 0 : 1);' >/dev/null 2>&1; then
    ok "Odd Note 초기 콘텐츠 구성이 완료됐습니다."
  else
    fail "Odd Note 초기 콘텐츠 구성이 완료되지 않았습니다. make install을 실행하세요."
  fi

  if docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; exit(get_option("odd_note_bootstrap_version") === "1.5.0" ? 0 : 1);' >/dev/null 2>&1; then
    ok "Odd Note 콘텐츠 스키마가 1.5.0입니다."
  else
    fail "Odd Note 콘텐츠 스키마가 1.5.0이 아닙니다. make install을 실행하세요."
  fi

  if docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; $expected = array("supabase-realtime-binary-state-sync" => "it-news", "spatialvlm-paper-review" => "ai-paper-analysis", "ai-mvp-before-model" => "business-knowledge", "ai-cv-sota-briefing-2026-08-23" => "ai-paper-analysis"); foreach ($expected as $slug => $category) { $post = get_page_by_path($slug, OBJECT, "post"); if (!$post || $post->post_status !== "publish" || !has_category($category, $post)) { exit(1); } } exit(0);' >/dev/null 2>&1; then
    ok "핵심 편집 글이 올바른 카테고리에 공개됐습니다."
  else
    fail "핵심 편집 글의 공개 상태 또는 카테고리가 올바르지 않습니다."
  fi

  if docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; exit(has_nav_menu("primary") && has_nav_menu("footer") ? 0 : 1);' >/dev/null 2>&1; then
    ok "주 메뉴와 하단 정책 메뉴가 연결됐습니다."
  else
    fail "WordPress 메뉴 위치 연결을 확인하지 못했습니다."
  fi
else
  warn "WordPress 컨테이너가 아직 시작되지 않았습니다."
fi

if [ -f .env ]; then
  wordpress_port="$(sed -n 's/^WORDPRESS_PORT=//p' .env | tail -n 1)"
else
  wordpress_port=""
fi
wordpress_port="${wordpress_port:-8090}"

if [ -n "$wordpress_id" ]; then
  final_url="$(curl -fsSL --max-time 10 -o /dev/null -w '%{url_effective}' "http://127.0.0.1:$wordpress_port/" 2>/dev/null || true)"
  if [ -n "$final_url" ]; then
    ok "로컬 HTTP 응답이 정상입니다: http://127.0.0.1:$wordpress_port"
  else
    fail "로컬 HTTP 응답을 확인하지 못했습니다."
  fi

  case "$final_url" in
    */wp-admin/install.php*) fail "WordPress 설치가 아직 완료되지 않았습니다. make install을 실행하세요." ;;
    *) ok "WordPress 설치가 완료됐습니다." ;;
  esac
fi

if [ "$failures" -gt 0 ]; then
  printf '\n진단 실패 항목: %s\n' "$failures" >&2
  exit 1
fi

printf '\n기본 진단을 통과했습니다.\n'
