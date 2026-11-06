# secaware-api

The JSON API for SecAware, a free platform for short cybersecurity-awareness courses. The web and mobile apps both use this API. It is written in plain PHP with Composer and no framework.

This repo is a training project. It has no features yet: every endpoint returns a 404 until the issues add them.

## Run it

You need PHP 8.3 or newer with the SQLite extension, and Composer.

```sh
composer install
php -S 127.0.0.1:8000 -t public public/index.php
```

In another terminal:

```sh
curl -i http://127.0.0.1:8000/api/health
```

You should get a 404 with this body:

```json
{"error":{"code":"not_found","message":"No such endpoint."}}
```

## Licence

Code is [MIT](LICENSE). Course content in [`content/`](content/README.md) is CC BY 4.0.
