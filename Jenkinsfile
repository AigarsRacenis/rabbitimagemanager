def composeSpec = ["compose.yml", "compose.dev.yml"]

pipeline {
    agent { node { label "${JOB_NAME}" } }

    stages {
        stage('Build') {
            steps {
                cleanWs()
                checkout scm

                loadSecrets(['CRYPT_KEY'])

                dockerECRLogin()
                dockerHttpServices(["traefik", "portainer", "elasticsearch", "mailpit"])

                envSubst(".docker/.env.dev.template", ".docker/.env.dev")
                envSubst("app/etc/env.template.php", "app/etc/env.php")
                envSubst("compose.dev.yml.template", "compose.dev.yml")

                dockerComposeRestart(composeSpec)

                dockerComposeExec(composeSpec, "php", "composer install --no-interaction --no-dev", "app")

                symLink(['${HOME}/shared/media': '${WORKSPACE}/pub/media'], true)

                dockerComposeExec(composeSpec, "php", "bin/magento cron:remove", "app")

                dockerComposeExec(composeSpec, "php", "bin/magento maintenance:enable", "app")

                dockerComposeExec(composeSpec, "php", "bin/magento setup:di:c", "app")
                dockerComposeExec(composeSpec, "php", "php -d memory_limit=-1 bin/magento s:s:d -f", "app")
                dockerComposeExec(composeSpec, "php", "bin/magento setup:up --keep-generated --no-interaction", "app")

                dockerComposeExec(composeSpec, "php", "bin/magento maintenance:disable", "app")

                dockerComposeExec(composeSpec, "php", "bin/magento cron:install", "app")

                dockerComposeExec(composeSpec, "php", "bin/magento cac:f", "app")
            }
        }
    }

    post {
        always {
            removeHooks()
        }
    }
}
