SHELL := /bin/sh

.PHONY: init config pull refresh-images up install tunnel quick-tunnel status doctor logs backup verify-backup export static-build static-verify static-preview update restart stop down

init:
	./scripts/init-secrets.sh

config: init
	docker compose config --quiet

pull: config
	./scripts/pull-images.sh

refresh-images: config
	./scripts/pull-images.sh --refresh

up: pull
	docker compose up -d --pull never

install: up
	./scripts/install-wordpress.sh

tunnel: config
	./scripts/start-tunnel.sh

quick-tunnel: config
	docker compose --profile quick-tunnel up -d quick-tunnel
	@echo "임시 공개 URL 확인: docker compose logs quick-tunnel"

status:
	docker compose --profile tunnel --profile quick-tunnel ps

doctor:
	./scripts/doctor.sh

logs:
	docker compose --profile tunnel --profile quick-tunnel logs --tail=100 -f

backup:
	./scripts/backup.sh

verify-backup:
	./scripts/verify-backup.sh

export:
	npm run export

static-build:
	npm run build

static-verify:
	npm run verify:static

static-preview:
	npm run preview

update: backup refresh-images
	docker compose up -d --pull never

restart:
	docker compose restart

stop:
	docker compose stop

down:
	docker compose down
