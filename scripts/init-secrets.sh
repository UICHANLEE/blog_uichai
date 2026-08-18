#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
secrets_dir="$project_root/secrets"

if ! command -v openssl >/dev/null 2>&1; then
  echo "오류: openssl이 필요합니다." >&2
  exit 1
fi

umask 077
mkdir -p "$secrets_dir"

generate_if_missing() {
  target="$1"
  if [ ! -s "$target" ]; then
    openssl rand -hex 32 > "$target"
    echo "생성: $target"
  fi
  chmod 600 "$target"
}

generate_if_missing "$secrets_dir/db_password.txt"
generate_if_missing "$secrets_dir/db_root_password.txt"
generate_if_missing "$secrets_dir/wp_admin_password.txt"

admin_user_file="$secrets_dir/wp_admin_user.txt"
if [ ! -s "$admin_user_file" ]; then
  printf 'owner_%s\n' "$(openssl rand -hex 6)" > "$admin_user_file"
  echo "생성: $admin_user_file"
fi
chmod 600 "$admin_user_file"

token_file="$secrets_dir/cloudflare_tunnel_token.txt"
if [ ! -e "$token_file" ]; then
  printf '%s\n' 'PASTE_CLOUDFLARE_TUNNEL_TOKEN_HERE' > "$token_file"
  echo "생성: $token_file (Tunnel 연결 전에 토큰을 입력하세요)"
fi
chmod 600 "$token_file"

if [ ! -e "$project_root/.env" ]; then
  cp "$project_root/.env.example" "$project_root/.env"
  chmod 600 "$project_root/.env"
  echo "생성: $project_root/.env"
fi
