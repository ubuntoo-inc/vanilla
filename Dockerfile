ARG BASE_IMAGE
FROM ${BASE_IMAGE}
ENV DEBIAN_FRONTEND=noninteractive
#NGINX
RUN apt-get update && apt-get install -y \
    net-tools \
    nginx \
    wget \
    unzip \
    vim \
    mysql-client \
    git \
    curl \
    sudo \
  && mkdir -p /ebs/vanilla \
  && mkdir -p /ebs/nginx/conf \
  && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    yarn \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN node -v

#PHP
RUN apt-get update && apt-get install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN apt-get update && apt-get install -y \
    php7.3 \
    php7.3-cli \
    php7.3-common \
    php7.3-intl \
    php7.3-dom \
    php7.3-mbstring \
    php7.3-curl \
    php7.3-gd \
    php7.3-pdo \
    php7.3-mysqli \
    php7.3-fpm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
RUN systemctl enable php7.3-fpm

#VANILLA
COPY . /ebs/vanilla
RUN mkdir -p /ebs/vanilla/cache
RUN chmod -R 777 /ebs/vanilla/cache
COPY ./static/start-server.sh /ebs
COPY ./static/nginx/conf/vanilla-web.conf /ebs/nginx/conf
COPY ./static/nginx/conf/index.html /ebs/nginx/conf
RUN service php7.3-fpm restart
RUN service nginx restart
RUN apt-get update && \
    apt-get install -y git && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN npm i -g yarn
RUN composer self-update --1
RUN cd /ebs/vanilla && composer install
RUN chown -R www-data:www-data /ebs/vanilla \
    && chmod 777 /ebs/vanilla/conf \
    && chmod 777 /ebs/vanilla/uploads

RUN chmod +x /ebs/start-server.sh
RUN cd /etc/nginx/sites-enabled && unlink default \
  && cp /ebs/nginx/conf/vanilla-web.conf /etc/nginx/sites-available \
  && ln -s /etc/nginx/sites-available/vanilla-web.conf /etc/nginx/sites-enabled

EXPOSE 80
EXPOSE 443
ENTRYPOINT ["/ebs/start-server.sh"]
