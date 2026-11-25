up:
	docker-compose up -d
down:
	docker-compose down --remove-orphans
down-clear:
	docker-compose down -v --remove-orphans
build:
	docker-compose build
run:
	docker-compose run --rm -u appuser php-cli bash

composer-install:
	docker-compose run --rm php-cli composer install
composer-require:
	docker-compose run --rm php-cli composer require $(PACKAGES)
composer-update:
	docker-compose run --rm php-cli composer update $(PACKAGES)

assets-dev:
	docker-compose run --rm node npm run dev
assets-install:
	docker-compose run --rm node npm install
assets-build:
	docker-compose run --rm node npm run build

build-cli:
	docker-compose build php-cli
build-nginx:
	docker-compose build nginx