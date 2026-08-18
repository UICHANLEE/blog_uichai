#!/bin/sh
set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
project_root="$(dirname "$script_dir")"
mode="${1:-missing}"

if [ "$mode" != missing ] && [ "$mode" != --refresh ]; then
  echo "사용법: $0 [--refresh]" >&2
  exit 2
fi

cd "$project_root"

engine_host="${DOCKER_HOST:-$(docker context inspect --format '{{.Endpoints.docker.Host}}')}"
anonymous_config="$(mktemp -d "${TMPDIR:-/tmp}/revenue-blog-docker.XXXXXX")"

cleanup() {
  if [ -n "${anonymous_config:-}" ] && [ -d "$anonymous_config" ]; then
    rm -rf -- "$anonymous_config"
  fi
}
trap cleanup EXIT HUP INT TERM

images="$(docker compose --profile tunnel --profile quick-tunnel config --images | sort -u)"

for image in $images; do
  if [ "$mode" = missing ] && docker image inspect "$image" >/dev/null 2>&1; then
    echo "이미 있음: $image"
    continue
  fi

  echo "공개 이미지 다운로드: $image"
  DOCKER_CONFIG="$anonymous_config" DOCKER_HOST="$engine_host" docker pull "$image"
done
