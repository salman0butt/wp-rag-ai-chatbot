#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
zip_file="wp-rag-ai-chatbot.zip"

if [[ ! -f "$zip_file" ]]; then
    echo "Missing $zip_file" >&2
    exit 1
fi

entries="$(unzip -Z1 "$zip_file")"

required=(
    "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"
    "wp-rag-ai-chatbot/uninstall.php"
    "wp-rag-ai-chatbot/src/Core/Bootstrap.php"
    "wp-rag-ai-chatbot/src/Database/DatabaseUninstaller.php"
    "wp-rag-ai-chatbot/vendor/autoload.php"
)

for path in "${required[@]}"; do
    if ! grep -Fxq "$path" <<<"$entries"; then
        echo "Package is missing required path: $path" >&2
        exit 1
    fi
done

while IFS= read -r source_path; do
    relative_path="${source_path#"$repo_root/"}"
    package_path="wp-rag-ai-chatbot/$relative_path"
    if ! grep -Fxq "$package_path" <<<"$entries"; then
        echo "Package is missing provider runtime path: $package_path" >&2
        exit 1
    fi
done < <(find "$repo_root/src/Providers" -type f -name '*.php' -print | sort)

forbidden='(^|/)(tests|docs|scripts|node_modules|\.github)(/|$)|(^|/)\.env([^/]*$|/)|(^|/)\.wp-env\.json$|(^|/)(composer\.json|composer\.lock|package\.json|package-lock\.json)$'

if grep -E "$forbidden" <<<"$entries"; then
    echo "Package contains development/private files" >&2
    exit 1
fi
