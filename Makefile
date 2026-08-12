PORT ?= 8000

start:
	php -S 0.0.0.0:$(PORT) -t public public/index.php

install:
	composer install

setup: install

# Проверка запуском: подъём версий slim ломается не в composer, а на старте.
check:
	@php -S 127.0.0.1:$(PORT) -t public public/index.php & \
	server=$$!; \
	trap "kill $$server 2>/dev/null" EXIT; \
	for i in $$(seq 1 30); do \
	  curl -sf http://127.0.0.1:$(PORT)/ >/dev/null && break; \
	  sleep 1; \
	done; \
	curl -sf http://127.0.0.1:$(PORT)/ >/dev/null && curl -sf http://127.0.0.1:$(PORT)/cars >/dev/null

compose:
	docker compose up

compose-bash:
	docker compose run web bash

compose-setup: compose-build
	docker compose run web make setup

compose-build:
	docker compose build

compose-down:
	docker compose down -v
