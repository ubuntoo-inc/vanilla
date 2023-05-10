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
    git \
    curl \
    sudo \
  && mkdir -p /ebs/vanilla \
  && mkdir -p /ebs/nginx/conf \
  && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y \
    nodejs \
    yarn \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN node -v

#PHP
RUN apt-get update && apt-get install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN apt-get update && apt-get install -y \
    php8.0 \
    php8.0-cli \
    php8.0-common \
    php8.0-intl \
    php8.0-dom \
    php8.0-mbstring \
    php8.0-curl \
    php8.0-gd \
    php8.0-pdo \
    php8.0-mysqli \
    php8.0-fpm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
RUN systemctl enable php8.0-fpm

#VANILLA
COPY . /ebs/vanilla
RUN mkdir -p /ebs/vanilla/cache
RUN chmod -R 777 /ebs/vanilla/cache

COPY ./static/start-server.sh /ebs
COPY ./static/nginx/conf/fastcgi.conf.tpl /ebs/nginx/conf
COPY ./static/nginx/conf/vanilla-web.conf /ebs/nginx/conf
COPY ./static/nginx/conf/index.html /ebs/nginx/conf

RUN chown -R www-data:www-data /ebs/vanilla \
    && chmod 777 /ebs/vanilla/conf \
    && chmod 777 /ebs/vanilla/uploads

RUN chmod +x /ebs/start-server.sh
RUN cd /etc/nginx/sites-enabled && unlink default \
  && cp /ebs/nginx/conf/fastcgi.conf.tpl /etc/nginx/conf \
  && cp /ebs/nginx/conf/vanilla-web.conf /etc/nginx/sites-available \
  && ln -s /etc/nginx/sites-available/vanilla-web.conf /etc/nginx/sites-enabled

EXPOSE 80
EXPOSE 443
ENTRYPOINT ["/ebs/start-server.sh"]
