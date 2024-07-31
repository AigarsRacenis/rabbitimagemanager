DOCKER = /usr/bin/docker

docker_start:
	docker compose -f compose.yml -f compose.local.yml up -d

docker_stop:
	docker compose -f compose.yml -f compose.local.yml stop

docker_logs:
	docker compose -f compose.yml -f compose.local.yml logs -f

magento_production:
	d/composer install --no-interaction --ignore-platform-req=ext-sodium
	d/magento cron:remove
	d/magento setup:upgrade
	d/magento setup:di:compile
	d/magento setup:static-content:deploy en_US lv_LV ru_RU -t MageBig/martfury_SBunPartneri -t Magento/luma -t Magento/backend -f
	d/magento cron:install
	d/magento cache:flush

magento_developer:
	d/composer install --no-interaction
	d/magento cron:remove
	d/magento setup:upgrade
	d/magento setup:di:compile
	d/magento cron:install
	d/magento cache:flush

phpcs:
	$(DOCKER) exec sbunpartneri-php vendor/bin/phpcs --standard=Magento2,PSR12 $(PATH)

phpcbf:
	$(DOCKER) exec sbunpartneri-php vendor/bin/phpcbf --standard=Magento2,PSR12 $(PATH)
