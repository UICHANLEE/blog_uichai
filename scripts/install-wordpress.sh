#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
admin_user_file="$project_root/secrets/wp_admin_user.txt"
admin_password_file="$project_root/secrets/wp_admin_password.txt"
bootstrap_file="/opt/odd-note/bootstrap-wordpress.php"

cd "$project_root"
./scripts/init-secrets.sh >/dev/null

if ! docker compose ps --services --status running | grep -qx wordpress; then
  echo "오류: wordpress 컨테이너가 실행 중이 아닙니다." >&2
  exit 1
fi

if ! docker compose exec -T wordpress test -r "$bootstrap_file"; then
  echo "오류: WordPress 초기화 도구가 연결되지 않았습니다. docker compose up -d를 다시 실행하세요." >&2
  exit 1
fi

if [ ! -s "$admin_user_file" ] || [ ! -s "$admin_password_file" ]; then
  echo "오류: WordPress 관리자 자격증명을 준비하지 못했습니다." >&2
  exit 1
fi

site_title="$(sed -n 's/^WORDPRESS_SITE_TITLE=//p' .env | tail -n 1)"
admin_email="$(sed -n 's/^WORDPRESS_ADMIN_EMAIL=//p' .env | tail -n 1)"
wordpress_port="$(sed -n 's/^WORDPRESS_PORT=//p' .env | tail -n 1)"
public_url="$(sed -n 's/^WORDPRESS_PUBLIC_URL=//p' .env | tail -n 1)"
site_title="${site_title:-Odd Note}"
admin_email="${admin_email:-owner@localhost.invalid}"
wordpress_port="${wordpress_port:-8090}"
site_url="${public_url:-http://127.0.0.1:$wordpress_port}"

{
  sed -n '1p' "$admin_user_file"
  sed -n '1p' "$admin_password_file"
  printf '%s\n' "$admin_email"
  printf '%s\n' "$site_title"
  printf '%s\n' "$site_url"
} | docker compose exec -T --user 33:33 -e ODD_NOTE_SITE_URL="$site_url" wordpress php "$bootstrap_file"

if ! docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; exit(get_option("odd_note_bootstrap_state") === "complete" && get_stylesheet() === "odd-note" ? 0 : 1);'; then
  echo "오류: WordPress 초기화 완료 상태를 확인하지 못했습니다." >&2
  exit 1
fi

echo "관리자 아이디: $admin_user_file"
echo "관리자 비밀번호: $admin_password_file"
echo "첫 로그인 후 실제 이메일과 새 비밀번호로 변경하세요."
