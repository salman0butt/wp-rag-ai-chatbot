#!/usr/bin/env bash
set -euo pipefail

zip_file="wp-rag-ai-chatbot.zip"
root_folder="wp-rag-ai-chatbot"

./node_modules/.bin/wp-scripts plugin-zip "$@"

forced_metadata=(
    "$root_folder/package.json"
    "$root_folder/README.md"
)

archive_entries="$(unzip -Z1 "$zip_file")"

for path in "${forced_metadata[@]}"; do
    if grep -Fxq "$path" <<< "$archive_entries"; then
        zip -dq "$zip_file" "$path"
    fi
done

forbidden='(^|/)(tests|docs|scripts|node_modules|\.github)(/|$)|(^|/)\.env([^/]*$|/)|(^|/)\.wp-env\.json$|(^|/)(composer\.json|composer\.lock|package\.json|package-lock\.json)$'

while IFS= read -r path; do
    [[ -z "$path" ]] && continue
    zip -dq "$zip_file" "$path"
done < <(unzip -Z1 "$zip_file" | grep -E "$forbidden" || true)
