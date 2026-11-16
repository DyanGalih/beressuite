#!/bin/sh

set -e
cd "$(dirname "$0")/.."

echo "Building BeresSuite production container..."
docker compose build beressuite-app

echo "Starting services..."
docker compose up -d

echo "Setting up application key and storage links..."
docker compose exec beressuite-app php artisan storage:link || true
docker compose exec beressuite-app php artisan key:generate --force

echo "BeresSuite production containers are ready."
