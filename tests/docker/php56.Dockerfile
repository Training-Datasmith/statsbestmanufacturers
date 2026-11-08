FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

RUN sed -i 's|deb.debian.org|archive.debian.org|g' /etc/apt/sources.list \
    && sed -i 's|security.debian.org|archive.debian.org|g' /etc/apt/sources.list \
    && sed -i '/stretch-updates/d' /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\nAcquire::AllowInsecureRepositories "true";\nAcquire::AllowDowngradeToInsecureRepositories "true";\n' > /etc/apt/apt.conf.d/99archive-stretch \
    && apt-get -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends --allow-unauthenticated \
        default-libmysqlclient-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql \
    && php -m | grep -q pdo_mysql
