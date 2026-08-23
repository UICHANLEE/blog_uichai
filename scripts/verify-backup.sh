#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
backup_dir="${1:-$project_root/backups/latest}"

if [ ! -d "$backup_dir" ]; then
  echo "오류: 백업 디렉터리를 찾을 수 없습니다: $backup_dir" >&2
  exit 1
fi

for file in SHA256SUMS database.sql.gz wordpress-files.tar.gz configuration.tar.gz; do
  if [ ! -s "$backup_dir/$file" ]; then
    echo "오류: 누락되거나 빈 파일입니다: $backup_dir/$file" >&2
    exit 1
  fi
done

if command -v shasum >/dev/null 2>&1; then
  (cd "$backup_dir" && shasum -a 256 -c SHA256SUMS)
elif command -v sha256sum >/dev/null 2>&1; then
  (cd "$backup_dir" && sha256sum -c SHA256SUMS)
else
  echo "오류: shasum 또는 sha256sum이 필요합니다." >&2
  exit 1
fi

gzip -t "$backup_dir/database.sql.gz"
tar -tzf "$backup_dir/wordpress-files.tar.gz" >/dev/null
tar -tzf "$backup_dir/configuration.tar.gz" >/dev/null

if ! tar -tzf "$backup_dir/configuration.tar.gz" | grep -q '^themes/odd-note/style.css$'; then
  echo "오류: 운영 설정 백업에 Odd Note 테마 원본이 없습니다." >&2
  exit 1
fi

if ! tar -tzf "$backup_dir/configuration.tar.gz" | grep -q '^themes/odd-note/assets/images/og.png$'; then
  echo "오류: 운영 설정 백업에 Odd Note 공유 이미지가 없습니다." >&2
  exit 1
fi

if ! tar -tzf "$backup_dir/configuration.tar.gz" | grep -q '^scripts/bootstrap-wordpress.php$'; then
  echo "오류: 운영 설정 백업에 WordPress 초기화 스크립트가 없습니다." >&2
  exit 1
fi

for article in \
  supabase-realtime-binary-state-sync \
  spatialvlm-paper-review \
  ai-mvp-before-model \
  ai-cv-sota-briefing-2026-08-23; do
  if ! tar -tzf "$backup_dir/configuration.tar.gz" | grep -q "^content/posts/$article.html$"; then
    echo "오류: 운영 설정 백업에 편집 글 원본이 없습니다: $article" >&2
    exit 1
  fi
done

if ! tar -tzf "$backup_dir/wordpress-files.tar.gz" | grep -q '^\./wp-content/themes/odd-note/style.css$'; then
  echo "오류: WordPress 파일 백업에 Odd Note 테마가 없습니다." >&2
  exit 1
fi


if ! tar -tzf "$backup_dir/wordpress-files.tar.gz" | grep -q '^\./wp-content/themes/odd-note/assets/images/og.png$'; then
  echo "오류: WordPress 파일 백업에 Odd Note 공유 이미지가 없습니다." >&2
  exit 1
fi

echo "백업 파일과 체크섬 검증을 통과했습니다: $backup_dir"
