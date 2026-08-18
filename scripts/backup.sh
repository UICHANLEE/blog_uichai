#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
backup_root="${BACKUP_DIR:-$project_root/backups}"
timestamp="$(date '+%Y%m%d-%H%M%S')"
final_dir="$backup_root/$timestamp"

umask 077
mkdir -p "$backup_root"
tmp_dir="$(mktemp -d "$backup_root/.tmp-$timestamp-XXXXXX")"

cleanup() {
  if [ -n "${tmp_dir:-}" ] && [ -d "$tmp_dir" ]; then
    rm -rf -- "$tmp_dir"
  fi
}
trap cleanup EXIT HUP INT TERM

cd "$project_root"

if ! docker compose ps --services --status running | grep -qx database; then
  echo "오류: database 컨테이너가 실행 중이 아닙니다." >&2
  exit 1
fi

if ! docker compose ps --services --status running | grep -qx wordpress; then
  echo "오류: wordpress 컨테이너가 실행 중이 아닙니다." >&2
  exit 1
fi

echo "MariaDB 논리 백업 중..."
docker compose exec -T database sh -ec '
  export MYSQL_PWD="$(cat /run/secrets/db_root_password)"
  exec mariadb-dump \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --events \
    --triggers \
    --default-character-set=utf8mb4 \
    --databases "$MARIADB_DATABASE"
' | gzip -9 > "$tmp_dir/database.sql.gz"

echo "WordPress 파일 백업 중..."
docker compose exec -T wordpress tar -C /var/www/html --exclude='./wp-content/cache/*' -czf - . > "$tmp_dir/wordpress-files.tar.gz"

echo "운영 설정 백업 중..."
tar -C "$project_root" -czf "$tmp_dir/configuration.tar.gz" compose.yaml .env config scripts themes README.md

if command -v shasum >/dev/null 2>&1; then
  (
    cd "$tmp_dir"
    shasum -a 256 database.sql.gz wordpress-files.tar.gz configuration.tar.gz > SHA256SUMS
  )
elif command -v sha256sum >/dev/null 2>&1; then
  (
    cd "$tmp_dir"
    sha256sum database.sql.gz wordpress-files.tar.gz configuration.tar.gz > SHA256SUMS
  )
else
  echo "오류: shasum 또는 sha256sum이 필요합니다." >&2
  exit 1
fi

mv "$tmp_dir" "$final_dir"
tmp_dir=""
ln -sfn "$(basename "$final_dir")" "$backup_root/latest"

echo "백업 완료: $final_dir"
echo "중요: secrets/는 의도적으로 제외했습니다. 암호화해 별도 보관하세요."
