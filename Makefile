# Semua perintah dijalankan lewat Docker. Lihat docs/DOCKER.md.
DC = docker compose
DCP = docker compose -f compose.prod.yaml --env-file .env.prod-local

.PHONY: build up down install sh artisan composer npm test fresh assets tunnel tools logs prod-local prod-local-down

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

install:
	$(DC) exec app composer install
	$(DC) exec app php artisan key:generate
	$(DC) exec app php artisan migrate --seed
	$(DC) exec app php artisan storage:link
	$(DC) run --rm vite npm install

sh:
	$(DC) exec app bash

artisan:
	$(DC) exec app php artisan $(cmd)

composer:
	$(DC) exec app composer $(cmd)

npm:
	$(DC) run --rm vite npm $(cmd)

test:
	$(DC) exec app php artisan test

fresh:
	$(DC) exec app php artisan migrate:fresh --seed

assets:
	$(DC) run --rm vite npm run build
	rm -f public/hot

tunnel:
	$(DC) --profile tunnel up -d tunnel
	@sleep 5
	@$(DC) logs tunnel | grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' | tail -1

tools:
	$(DC) --profile tools up -d phpmyadmin

logs:
	$(DC) logs -f

prod-local:
	APP_DOMAIN=localhost $(DCP) up -d --build

prod-local-down:
	$(DCP) down
