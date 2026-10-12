#!/bin/bash
set -e

docker run --rm \
    -v "$(pwd)":/app \
    -w /app \
    php:8.4-cli ./vendor/bin/pest "$@"