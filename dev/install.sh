#!/bin/bash
set -e

docker run --rm \
    -v "$(pwd)":/app \
    -w /app \
    composer:2 install