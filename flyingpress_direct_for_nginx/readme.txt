Вам нужно добавить этот код в первый server блок (который обрабатывает HTTPS-соединения) в вашем конфиге Nginx. Вот куда именно и почему:

Куда поместить:

server {
    server_name procarmanuals.com www.procarmanuals.com;
    listen 154.38.190.240:443 ssl;
    # ... остальные listen директивы ...

    # ДОБАВИТЬ ЗДЕСЬ - после общих настроек, но до location блоков
    set $flying_press_cache 1;
    set $flying_press_url "/wp-content/cache/flying-press/$http_host/$request_uri/index.html.gz";
    set $flying_press_file "$document_root/wp-content/cache/flying-press/$http_host/$request_uri/index.html.gz";

    if ($request_method = POST) {
        set $flying_press_cache 0;
    }

    if ($is_args) {
        set $flying_press_cache 0;
    }

    if ($http_cookie ~* "(wp\-postpass|wordpress_logged_in|comment_author|woocommerce_cart_hash|edd_items_in_cart)") {
        set $flying_press_cache 0;
    }

    if (!-f "$flying_press_file") {
        set $flying_press_cache 0;
    }

    if ($flying_press_cache = 1) {
        rewrite .* "$flying_press_url" last;
    }

    # ... остальные настройки ...

    location / {
        index index.php;
        try_files $uri $uri/ /index.php?$args;
    }

    # ДОБАВИТЬ ЭТОТ БЛОК ПОСЛЕ location /, но до location ~ \.php$
    location ~* \.html\.gz$ {
        gzip off;
        brotli off;
        add_header x-flying-press-cache HIT;
        add_header x-flying-press-source "Web Server";
        add_header cache-control "no-cache, must-revalidate, max-age=0";
        add_header CDN-Cache-Control "max-age=2592000";
        add_header Cache-Tag $host;
        add_header Content-Encoding gzip;
        add_header Content-Type "text/html; charset=UTF-8";
        try_files $uri =404;
    }

    location ~ \.php$ {
        # ... существующий код ...
    }

    # ... остальные location блоки ...
}


Почему именно так:
Условия и переменные (set $flying_press_cache и блоки if) должны быть на уровне server контекста, 
чтобы они обрабатывались до любых location блоков.

Блок location для .html.gz файлов должен быть после location /, но до location для PHP файлов, 
чтобы Nginx правильно обрабатывал приоритеты.


Логика работы:
Сначала проверяются условия (метод POST, наличие параметров, куки и т.д.)

Если все условия проходят, запрос переписывается на статический gzip-файл

Блок location \.html\.gz$ обрабатывает отдачу сжатого контента с правильными заголовками


Важные замечания:
Убедитесь, что путь /wp-content/cache/flying-press/ существует и доступен для записи WordPress плагином

После изменений перезагрузите Nginx: nginx -t && systemctl reload nginx

Проверьте, что кэшированные файлы создаются в указанной директории

Этот код реализует механизм кэширования Flying Press для WordPress, который отдает предварительно 
сжатые HTML-файлы для ускорения загрузки страниц.

