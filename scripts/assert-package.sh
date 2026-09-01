#!/usr/bin/env bash
set -euo pipefail

zip_file="wp-rag-ai-chatbot.zip"

if [[ ! -f "$zip_file" ]]; then
    echo "Missing $zip_file" >&2
    exit 1
fi

entries="$(unzip -Z1 "$zip_file")"

required=(
    "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"
    "wp-rag-ai-chatbot/src/Core/Bootstrap.php"
    "wp-rag-ai-chatbot/vendor/autoload.php"
)

for path in "${required[@]}"; do
    if ! grep -Fxq "$path" <<<"$entries"; then
        echo "Package is missing required path: $path" >&2
        exit 1
    fi
done

forbidden='(^|/)(tests|docs|node_modules|\.github)(/|$)|(^|/)\.env([^/]*$|/)|(^|/)\.wp-env\.json$|(^|/)(composer\.json|composer\.lock|package\.json|package-lock\.json)$'

if grep -E "$forbidden" <<<"$entries"; then
    echo "Package contains development/private files" >&2
    exit 1
fi
