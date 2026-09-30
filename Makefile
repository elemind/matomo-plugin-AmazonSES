COMPOSE = docker compose
EXEC = $(COMPOSE) exec -T -u www-data matomo
EXEC_ROOT = $(COMPOSE) exec -T matomo
CONSOLE = $(EXEC) php /var/www/html/console

.PHONY: up down reset install logs shell test-email mails unit-test vue-build test

up: ## start the stack
	$(COMPOSE) up -d
	@echo "Matomo: http://localhost:8080  (run 'make install' the first time)"
	@echo "Mock SES inbox: http://localhost:8005"

down: ## stop the stack
	$(COMPOSE) down

reset: ## stop and delete all data (database + Matomo files)
	$(COMPOSE) down -v

install: ## headless Matomo install + activate the plugin
	@until $(EXEC) test -f /var/www/html/console; do echo "waiting for Matomo files..."; sleep 2; done
	$(EXEC_ROOT) chown -R www-data:www-data /var/www/html/tmp /var/www/html/config
	$(EXEC) php /var/www/html/plugins/AmazonSES/.docker/install.php
	$(CONSOLE) core:update --yes
	$(CONSOLE) development:enable
	$(CONSOLE) plugin:activate AmazonSES
	@echo "Ready: http://localhost:8080  (admin / admin123)"

logs:
	$(COMPOSE) logs -f matomo ses-mock

shell:
	$(COMPOSE) exec matomo bash

test-email: ## send a test email with the core console command, e.g. make test-email TO=you@example.com
	$(CONSOLE) core:test-email $(or $(TO),admin@example.com)

mails: ## list emails received by the SES mock
	@curl -s http://localhost:8005/store | python3 -m json.tool

unit-test: ## run unit tests in a throwaway PHP container (no Matomo needed)
	docker run --rm -v $(PWD):/plugin -w /tmp/deps composer:2 sh -c '\
		composer require -q --no-interaction phpunit/phpunit:^9.6 phpmailer/phpmailer:^7.0 && \
		cd /plugin && AMAZONSES_VENDOR_AUTOLOAD=/tmp/deps/vendor/autoload.php /tmp/deps/vendor/bin/phpunit -c tests/phpunit.standalone.xml'

MATOMO_TAG ?= 5.14.0
MATOMO_SRC ?= .cache/matomo

$(MATOMO_SRC):
	git clone --depth 1 --branch $(MATOMO_TAG) https://github.com/matomo-org/matomo.git $(MATOMO_SRC)

vue-build: $(MATOMO_SRC) ## compile vue/src into vue/dist (commit the result)
	docker run --rm -v $(abspath $(MATOMO_SRC)):/matomo -v $(PWD):/matomo/plugins/AmazonSES -w /matomo node:16 sh -c '\
		set -e; \
		test -d node_modules/@vue/cli-service || npm ci --ignore-scripts --no-audit --no-fund; \
		ALL=$$(ls -d plugins/*/vue/src/index.ts | cut -d/ -f2 | paste -sd, -); \
		build() { BROWSERSLIST_IGNORE_OLD_DATA=1 MATOMO_CURRENT_PLUGIN=plugins/$$1 MATOMO_ALL_PLUGINS=$$ALL \
			node plugins/CoreVue/scripts/cli-service-proxy.js build --target lib --name $$1 \
			plugins/$$1/vue/src/index.ts --dest plugins/$$1/vue/dist; }; \
		for dep in CoreHome CorePluginsAdmin; do test -f @types/$$dep/index.d.ts || build $$dep; done; \
		build AmazonSES'

test: $(MATOMO_SRC) ## run unit + integration tests with the Matomo test framework (SUITE=unit|integration)
	MATOMO_SRC=$(abspath $(MATOMO_SRC)) SUITE=$(or $(SUITE),all) $(COMPOSE) --profile test run --rm --build tests
