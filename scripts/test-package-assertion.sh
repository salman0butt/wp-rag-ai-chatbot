#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

make_base_package() {
  local target="$1"
  mkdir -p \
    "$target/wp-rag-ai-chatbot/src/Core" \
    "$target/wp-rag-ai-chatbot/src/Database" \
    "$target/wp-rag-ai-chatbot/vendor/smalot/pdfparser/src/Smalot/PdfParser" \
    "$target/wp-rag-ai-chatbot/vendor/phpoffice/phpword/src/PhpWord"
  touch \
    "$target/wp-rag-ai-chatbot/wp-rag-ai-chatbot.php" \
    "$target/wp-rag-ai-chatbot/uninstall.php" \
    "$target/wp-rag-ai-chatbot/src/Core/Bootstrap.php" \
    "$target/wp-rag-ai-chatbot/src/Database/DatabaseUninstaller.php" \
    "$target/wp-rag-ai-chatbot/vendor/autoload.php" \
    "$target/wp-rag-ai-chatbot/vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php" \
    "$target/wp-rag-ai-chatbot/vendor/phpoffice/phpword/src/PhpWord/IOFactory.php"
}

missing_provider="$workdir/missing-provider"
mkdir -p "$missing_provider"
make_base_package "$missing_provider"
(
  cd "$missing_provider"
  zip -qr wp-rag-ai-chatbot.zip wp-rag-ai-chatbot
  if bash "$repo_root/scripts/assert-package.sh" >/dev/null 2>&1; then
    echo "Package assertion accepted an archive missing provider runtime files." >&2
    exit 1
  fi
)

missing_parser="$workdir/missing-parser"
mkdir -p "$missing_parser"
make_base_package "$missing_parser"
mkdir -p "$missing_parser/wp-rag-ai-chatbot/src"
cp -R "$repo_root/src/Providers" "$missing_parser/wp-rag-ai-chatbot/src/Providers"
rm "$missing_parser/wp-rag-ai-chatbot/vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php"
(
  cd "$missing_parser"
  zip -qr wp-rag-ai-chatbot.zip wp-rag-ai-chatbot
  if bash "$repo_root/scripts/assert-package.sh" >/dev/null 2>&1; then
    echo "Package assertion accepted an archive missing parser runtime files." >&2
    exit 1
  fi
)

script_leak="$workdir/script-leak"
mkdir -p "$script_leak"
make_base_package "$script_leak"
mkdir -p "$script_leak/wp-rag-ai-chatbot/src"
cp -R "$repo_root/src/Providers" "$script_leak/wp-rag-ai-chatbot/src/Providers"
mkdir -p "$script_leak/wp-rag-ai-chatbot/scripts"
touch "$script_leak/wp-rag-ai-chatbot/scripts/test-wp-providers.php"
(
  cd "$script_leak"
  zip -qr wp-rag-ai-chatbot.zip wp-rag-ai-chatbot
  if bash "$repo_root/scripts/assert-package.sh" >/dev/null 2>&1; then
    echo "Package assertion accepted an archive containing development scripts." >&2
    exit 1
  fi
)

echo "Package assertion rejects missing provider/parser runtime and development scripts."
