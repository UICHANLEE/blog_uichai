#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
token_file="$project_root/secrets/cloudflare_tunnel_token.txt"

if [ ! -s "$token_file" ] || grep -q '^PASTE_' "$token_file"; then
  echo "오류: $token_file 에 Cloudflare Tunnel 토큰을 먼저 입력하세요." >&2
  exit 1
fi

cd "$project_root"
docker compose --profile tunnel up -d
docker compose --profile tunnel ps
