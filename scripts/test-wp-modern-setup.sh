#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

if [[ ! -x "node_modules/.bin/wp-env" ]]; then
    printf '%s\n' 'ENVIRONMENT_STARTUP_FAILURE: wp-env is not installed.' >&2
    exit 2
fi

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    printf '%s\n' 'ENVIRONMENT_STARTUP_FAILURE: Docker is unavailable or not initialized.' >&2
    exit 2
fi

if ! npm run --silent wp-env -- run cli wp core is-installed >/dev/null 2>&1; then
    printf '%s\n' 'ENVIRONMENT_STARTUP_FAILURE: wp-env could not start or reach WordPress.' >&2
    exit 2
fi

run_app_check() {
    npm run --silent wp-env -- run cli wp plugin activate wp-rag-ai-chatbot --quiet && \
        npm run --silent wp-env -- run cli wp eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-modern-setup.php
}

if ! run_app_check; then
    printf '%s\n' 'APPLICATION_ASSERTION_FAILURE: modern setup smoke assertions failed.' >&2
    exit 1
fi

printf '%s\n' 'Modern setup smoke passed.'
