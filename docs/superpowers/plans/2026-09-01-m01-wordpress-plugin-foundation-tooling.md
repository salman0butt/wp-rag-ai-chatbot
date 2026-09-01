# M01 WordPress Plugin Foundation & Tooling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish a minimal activatable WordPress plugin with PHP/TypeScript quality tooling, reproducible WordPress smoke testing, CI, and a clean distribution package foundation.

**Architecture:** Keep the WordPress entry file declarative and thin. It loads Composer, delegates hook registration to `WpRagAiChatbot\Core\Bootstrap`, and keeps lifecycle callbacks in `WpRagAiChatbot\Core\Lifecycle`; there is no product/RAG/database behavior in M01. JavaScript tooling uses official `@wordpress/scripts` and `@wordpress/env`, but Node remains development/build tooling only.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, Composer 2, PHPUnit 10.5, Brain Monkey 2.7, PHPStan 2.2 + `szepeviktor/phpstan-wordpress`, WordPress Coding Standards 3.4.1+, Node.js LTS (22+ accepted), TypeScript 5.9+, `@wordpress/scripts` 34.2+, `@wordpress/env` 11.14+, Jest through `wp-scripts`, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-01-wp-rag-ai-chatbot-master-design.md`

## Global Constraints

- WordPress/PHP is the mandatory server runtime.
- WordPress baseline is 6.9 and PHP baseline is 8.2 for M01.
- Do not introduce a mandatory Node.js/Python/LangChain/LangGraph service.
- Do not introduce Redis, RabbitMQ, Kafka, Temporal, or another mandatory external queue.
- External vector databases remain optional adapters.
- No database schema, provider, RAG, admin UI, or frontend widget product behavior is implemented in M01.
- No production behavior is written before its failing behavior test unless the TDD skill explicitly permits an exception.
- WordPress Coding Standards remain mandatory; the single PSR-4 filename sniff is narrowly excluded because Composer PSR-4 filenames are an approved repository convention.
- Secrets, `.env` files, tests, docs, CI files, development manifests, and `node_modules` must not enter the plugin ZIP.

---

## File Structure Locked by This Plan

- `wp-rag-ai-chatbot.php` — sole WordPress plugin entry file and headers; delegates immediately to Composer + `Bootstrap`.
- `src/Core/Bootstrap.php` — registers lifecycle and `plugins_loaded` hooks only.
- `src/Core/Lifecycle.php` — activation/deactivation boundary; callbacks are intentionally side-effect free in M01.
- `composer.json` / `composer.lock` — PHP runtime floor, PSR-4 autoload and PHP QA/test dependencies.
- `phpunit.xml.dist` / `tests/bootstrap.php` — isolated unit runtime.
- `tests/Unit/PluginEntryPointTest.php` — validates plugin metadata/guard/delegation.
- `tests/Unit/Core/BootstrapTest.php` — verifies exact WordPress hook registration.
- `phpcs.xml.dist` — WordPress Coding Standards.
- `phpstan.neon.dist` — WordPress-aware static analysis.
- `package.json` / `package-lock.json` / `tsconfig.json` — JS/TS build, lint, test, wp-env and package commands.
- `src-js/index.test.ts` / `src-js/index.ts` — tiny TDD tooling fixture; it is not enqueued by WordPress.
- `.wp-env.json` / `scripts/test-wp-activation.sh` — real WordPress 6.9/PHP 8.2 activation harness.
- `scripts/assert-package.sh` — distribution-content gate.
- `readme.txt` / `LICENSE` — initial distribution metadata/license notice.
- `.github/workflows/ci.yml` — PHP, JS, WordPress smoke and packaging gates.
- `docs/milestones/M01-wordpress-plugin-foundation-tooling.md` and progress ledgers — durable verification/recovery state.

---

### Task 1: PHP Toolchain, Entry Point, and Bootstrap Boundary

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `phpcs.xml.dist`
- Create: `phpstan.neon.dist`
- Create: `tests/bootstrap.php`
- Create test before implementation: `tests/Unit/PluginEntryPointTest.php`
- Create test before implementation: `tests/Unit/Core/BootstrapTest.php`
- Create after RED: `wp-rag-ai-chatbot.php`
- Create after RED: `src/Core/Bootstrap.php`
- Create after RED: `src/Core/Lifecycle.php`

**Interfaces:**
- Produces: `WpRagAiChatbot\Core\Bootstrap::register(string $pluginFile): void`
- Produces: `WpRagAiChatbot\Core\Bootstrap::load(): void`
- Produces: `WpRagAiChatbot\Core\Lifecycle::activate(): void`
- Produces: `WpRagAiChatbot\Core\Lifecycle::deactivate(): void`
- Consumes: WordPress `register_activation_hook`, `register_deactivation_hook`, `add_action`, `do_action`.

- [ ] **Step 1: Add development manifests only**

Create `composer.json`:

```json
{
  "name": "salman0butt/wp-rag-ai-chatbot",
  "description": "WordPress-native RAG AI chatbot and customer-support platform.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=8.2"
  },
  "require-dev": {
    "brain/monkey": "^2.7",
    "dealerdirect/phpcodesniffer-composer-installer": "^1.2",
    "phpstan/phpstan": "^2.2",
    "phpunit/phpunit": "^10.5",
    "szepeviktor/phpstan-wordpress": "^2.0",
    "wp-coding-standards/wpcs": "^3.4.1"
  },
  "autoload": {
    "psr-4": {
      "WpRagAiChatbot\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "WpRagAiChatbot\\Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit",
    "lint:php": "phpcs",
    "lint:php:fix": "phpcbf",
    "analyse": "phpstan analyse --configuration=phpstan.neon.dist",
    "verify:php": ["@lint:php", "@analyse", "@test"]
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    },
    "sort-packages": true
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

Create `tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
```

Create `phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    cacheDirectory=".cache/phpunit"
    failOnRisky="true"
    failOnWarning="true"
>
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
            <file>wp-rag-ai-chatbot.php</file>
        </include>
    </source>
</phpunit>
```

Create `phpstan.neon.dist`:

```neon
includes:
    - vendor/szepeviktor/phpstan-wordpress/extension.neon

parameters:
    level: 8
    paths:
        - src
        - wp-rag-ai-chatbot.php
    tmpDir: .cache/phpstan
```

Create `phpcs.xml.dist`:

```xml
<?xml version="1.0"?>
<ruleset name="WP RAG AI Chatbot">
    <description>WordPress Coding Standards for WP RAG AI Chatbot.</description>
    <arg name="basepath" value="."/>
    <arg name="extensions" value="php"/>
    <arg name="parallel" value="8"/>
    <config name="minimum_supported_wp_version" value="6.9"/>

    <file>src</file>
    <file>tests</file>
    <file>wp-rag-ai-chatbot.php</file>

    <exclude-pattern>vendor/*</exclude-pattern>
    <exclude-pattern>node_modules/*</exclude-pattern>
    <exclude-pattern>build/*</exclude-pattern>

    <rule ref="WordPress">
        <exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
    </rule>
</ruleset>
```

Run:

```bash
composer validate --strict
composer install --no-interaction --prefer-dist
```

Expected: Composer succeeds; there are still no production PHP files.

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/PluginEntryPointTest.php`:

```php
<?php

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginEntryPointTest extends TestCase
{
    public function test_plugin_entry_point_declares_runtime_and_delegates_bootstrap(): void
    {
        $path = dirname(__DIR__, 2) . '/wp-rag-ai-chatbot.php';

        self::assertFileExists($path);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('Plugin Name: WP RAG AI Chatbot', $contents);
        self::assertStringContainsString('Requires at least: 6.9', $contents);
        self::assertStringContainsString('Requires PHP: 8.2', $contents);
        self::assertStringContainsString('Text Domain: wp-rag-ai-chatbot', $contents);
        self::assertStringContainsString("defined( 'ABSPATH' ) || exit;", $contents);
        self::assertStringContainsString('Bootstrap::register( __FILE__ );', $contents);
    }
}
```

Create `tests/Unit/Core/BootstrapTest.php`:

```php
<?php

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\Bootstrap;
use WpRagAiChatbot\Core\Lifecycle;

final class BootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_wires_only_the_foundation_hooks(): void
    {
        $pluginFile = '/tmp/wp-rag-ai-chatbot/wp-rag-ai-chatbot.php';

        Functions\expect('register_activation_hook')
            ->once()
            ->with($pluginFile, [Lifecycle::class, 'activate']);
        Functions\expect('register_deactivation_hook')
            ->once()
            ->with($pluginFile, [Lifecycle::class, 'deactivate']);
        Functions\expect('add_action')
            ->once()
            ->with('plugins_loaded', [Bootstrap::class, 'load']);

        Bootstrap::register($pluginFile);
    }

    public function test_load_emits_the_plugin_loaded_action(): void
    {
        Functions\expect('do_action')
            ->once()
            ->with('wp_rag_ai_chatbot_loaded');

        Bootstrap::load();
    }
}
```

- [ ] **Step 3: Run RED**

```bash
composer test -- --filter 'PluginEntryPointTest|BootstrapTest'
```

Expected: FAIL because `wp-rag-ai-chatbot.php` and `WpRagAiChatbot\Core\Bootstrap` do not exist.

- [ ] **Step 4: Add the minimal production PHP**

Create `src/Core/Lifecycle.php`:

```php
<?php
/**
 * Plugin lifecycle boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

final class Lifecycle
{
    public static function activate(): void
    {
    }

    public static function deactivate(): void
    {
    }
}
```

Create `src/Core/Bootstrap.php`:

```php
<?php
/**
 * WordPress hook bootstrap.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Core;

final class Bootstrap
{
    public static function register(string $pluginFile): void
    {
        register_activation_hook($pluginFile, [Lifecycle::class, 'activate']);
        register_deactivation_hook($pluginFile, [Lifecycle::class, 'deactivate']);
        add_action('plugins_loaded', [self::class, 'load']);
    }

    public static function load(): void
    {
        do_action('wp_rag_ai_chatbot_loaded');
    }
}
```

Create `wp-rag-ai-chatbot.php`:

```php
<?php
/**
 * Plugin Name: WP RAG AI Chatbot
 * Description: WordPress-native AI chatbot and RAG platform.
 * Version: 0.1.0-dev
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Salman Butt
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-rag-ai-chatbot
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
    throw new RuntimeException( 'WP RAG AI Chatbot dependencies are missing. Run composer install or install a packaged release.' );
}

require $autoload;

use WpRagAiChatbot\Core\Bootstrap;

Bootstrap::register( __FILE__ );
```

- [ ] **Step 5: Run GREEN and the PHP quality group**

```bash
composer test -- --filter 'PluginEntryPointTest|BootstrapTest'
composer lint:php
composer analyse
```

Expected: all commands exit 0. If PHPCS/PHPStan surfaces a genuine incompatibility with the exact snippets, fix only the reported issue and record the ruling if it changes a convention.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist phpcs.xml.dist phpstan.neon.dist tests wp-rag-ai-chatbot.php src/Core
git commit -m "feat: establish WordPress plugin bootstrap"
```

---

### Task 2: TypeScript Build/Test/Lint Foundation

**Files:**
- Create: `package.json`
- Create: `package-lock.json`
- Create: `tsconfig.json`
- Create test before implementation: `src-js/index.test.ts`
- Create after RED: `src-js/index.ts`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `pluginIdentity: Readonly<{ slug: 'wp-rag-ai-chatbot'; version: '0.1.0-dev' }>` as a tooling-only fixture.
- Produces: `build/index.js` from `wp-scripts build --source-path=src-js`; build output is generated, not committed in M01.

- [ ] **Step 1: Add the JS/TS manifest and compiler config**

Create `package.json`:

```json
{
  "name": "wp-rag-ai-chatbot",
  "version": "0.1.0-dev",
  "description": "WordPress-native RAG AI chatbot and customer-support platform.",
  "private": true,
  "license": "GPL-2.0-or-later",
  "engines": {
    "node": ">=22.0.0",
    "npm": ">=10.0.0"
  },
  "scripts": {
    "build": "wp-scripts build --source-path=src-js",
    "check-engines": "wp-scripts check-engines",
    "lint:js": "wp-scripts lint-js src-js",
    "lint:pkg": "wp-scripts lint-pkg-json",
    "test:js": "wp-scripts test-unit-js",
    "typecheck": "tsc --noEmit",
    "verify:js": "npm run check-engines && npm run lint:pkg && npm run lint:js && npm run typecheck && npm run test:js -- --runInBand && npm run build",
    "wp-env": "wp-env",
    "plugin-zip": "wp-scripts plugin-zip"
  },
  "devDependencies": {
    "@types/jest": "^29.5.0",
    "@wordpress/env": "^11.14.0",
    "@wordpress/scripts": "^34.2.0",
    "typescript": "^5.9.0"
  }
}
```

Create `tsconfig.json`:

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "lib": ["DOM", "ES2022"],
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "strict": true,
    "noEmit": true,
    "esModuleInterop": true,
    "forceConsistentCasingInFileNames": true,
    "skipLibCheck": true,
    "types": ["jest"]
  },
  "include": ["src-js/**/*.ts", "src-js/**/*.tsx"]
}
```

Add to `.gitignore`:

```text
/vendor/
/node_modules/
/coverage/
/.cache/
/build/
/wp-rag-ai-chatbot.zip
.env
.env.*
!.env.example
```

Run `npm install` to generate `package-lock.json`.

- [ ] **Step 2: Write the failing TS test**

Create `src-js/index.test.ts`:

```ts
import { pluginIdentity } from './index';

describe('pluginIdentity', () => {
    it('uses the canonical plugin slug and development version', () => {
        expect(pluginIdentity).toEqual({
            slug: 'wp-rag-ai-chatbot',
            version: '0.1.0-dev',
        });
    });
});
```

- [ ] **Step 3: Run RED**

```bash
npm run test:js -- --runInBand
```

Expected: FAIL because `./index` does not exist.

- [ ] **Step 4: Add minimal implementation**

Create `src-js/index.ts`:

```ts
export const pluginIdentity = Object.freeze({
    slug: 'wp-rag-ai-chatbot',
    version: '0.1.0-dev',
} as const);
```

- [ ] **Step 5: Run GREEN and the JS quality group**

```bash
npm run test:js -- --runInBand
npm run lint:js
npm run typecheck
npm run build
```

Expected: all commands exit 0 and `build/index.js` exists. Nothing enqueues this file in M01.

- [ ] **Step 6: Commit**

```bash
git add package.json package-lock.json tsconfig.json src-js .gitignore
git commit -m "build: add WordPress TypeScript toolchain"
```

---

### Task 3: WordPress Smoke Harness and Distribution Metadata

**Files:**
- Create: `.wp-env.json`
- Create: `scripts/test-wp-activation.sh`
- Modify: `package.json`
- Create: `readme.txt`
- Create: `LICENSE`

**Interfaces:**
- Produces commands `npm run env:start`, `npm run test:wp:activation`, `npm run env:stop`.
- Verifies real WordPress can activate/deactivate/reactivate the plugin and resolve the Composer-loaded bootstrap class.

- [ ] **Step 1: Add the baseline wp-env configuration**

Create `.wp-env.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/wp-env.json",
  "core": "WordPress/WordPress#6.9",
  "phpVersion": "8.2",
  "plugins": ["."]
}
```

Add these `package.json` scripts:

```json
"env:start": "wp-env start --update",
"env:stop": "wp-env stop",
"test:wp:activation": "bash scripts/test-wp-activation.sh"
```

- [ ] **Step 2: Add the smoke test script**

Create executable `scripts/test-wp-activation.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

npm run wp-env -- run cli wp plugin deactivate wp-rag-ai-chatbot --quiet || true
npm run wp-env -- run cli wp plugin activate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin is-active wp-rag-ai-chatbot
npm run wp-env -- run cli wp eval 'if (!class_exists("WpRagAiChatbot\\Core\\Bootstrap")) { fwrite(STDERR, "Bootstrap class not loaded\n"); exit(1); }'
npm run wp-env -- run cli wp plugin deactivate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin activate wp-rag-ai-chatbot --quiet
npm run wp-env -- run cli wp plugin is-active wp-rag-ai-chatbot
```

This task adds test infrastructure rather than new product behavior; do not add production code merely to force a RED state.

- [ ] **Step 3: Add exact distribution metadata**

Create `readme.txt`:

```text
=== WP RAG AI Chatbot ===
Contributors: salman0butt
Tags: ai, chatbot, rag, customer support, woocommerce
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress-native AI chatbot and RAG platform under active development.

== Description ==

This pre-release establishes the plugin foundation and development tooling. Production RAG, provider, knowledge, commerce, and chatbot features are delivered by later milestones.

== Installation ==

1. Install a packaged release into `wp-content/plugins/wp-rag-ai-chatbot`.
2. Activate WP RAG AI Chatbot in WordPress.

== Changelog ==

= 0.1.0 =
* Initial plugin foundation.
```

Create `LICENSE`:

```text
WP RAG AI Chatbot
Copyright (C) 2026 Salman Butt

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

License text: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
```

- [ ] **Step 4: Run real WordPress verification**

```bash
npm run env:start
npm run test:wp:activation
npm run env:stop
```

Expected: activation, class-resolution check, deactivation and reactivation all exit 0. If Docker is unavailable, invoke systematic-debugging and preserve this gate for CI rather than replacing it with a unit-only claim.

- [ ] **Step 5: Commit**

```bash
git add .wp-env.json scripts/test-wp-activation.sh package.json package-lock.json readme.txt LICENSE
git commit -m "test: add WordPress activation smoke harness"
```

---

### Task 4: Package Guardrails and CI

**Files:**
- Modify: `package.json`
- Create test before packaging: `scripts/assert-package.sh`
- Create: `.github/workflows/ci.yml`

**Interfaces:**
- Produces package allow-list and `wp-rag-ai-chatbot.zip` verification.
- Produces CI jobs `php-quality`, `js-quality`, `wordpress-smoke`, `package`.

- [ ] **Step 1: Add the explicit release allow-list**

Add to `package.json`:

```json
"files": [
  "wp-rag-ai-chatbot.php",
  "src/**",
  "build/**",
  "vendor/**",
  "readme.txt",
  "LICENSE"
]
```

- [ ] **Step 2: Write the failing package assertion**

Create executable `scripts/assert-package.sh`:

```bash
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
```

- [ ] **Step 3: Run package RED**

```bash
rm -f wp-rag-ai-chatbot.zip
bash scripts/assert-package.sh
```

Expected: FAIL with `Missing wp-rag-ai-chatbot.zip`.

- [ ] **Step 4: Build a production package and run GREEN**

Use a clean verification workspace so production Composer installation does not destroy the development vendor tree:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
npm run plugin-zip -- --root-folder=wp-rag-ai-chatbot
bash scripts/assert-package.sh
```

Expected: assertion exits 0.

- [ ] **Step 5: Add exact CI workflow**

Create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
  pull_request:

permissions:
  contents: read

jobs:
  php-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none
          tools: composer:v2
      - run: composer validate --strict
      - run: composer install --no-interaction --prefer-dist
      - run: composer audit
      - run: composer verify:php

  js-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: actions/setup-node@v5
        with:
          node-version: '22'
          cache: npm
      - run: npm ci
      - run: npm audit --audit-level=critical
      - run: npm run verify:js

  wordpress-smoke:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none
          tools: composer:v2
      - uses: actions/setup-node@v5
        with:
          node-version: '22'
          cache: npm
      - run: composer install --no-interaction --prefer-dist
      - run: npm ci
      - run: npm run build
      - run: npm run env:start
      - run: npm run test:wp:activation
      - if: always()
        run: npm run env:stop

  package:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none
          tools: composer:v2
      - uses: actions/setup-node@v5
        with:
          node-version: '22'
          cache: npm
      - run: composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
      - run: npm ci
      - run: npm run build
      - run: npm run plugin-zip -- --root-folder=wp-rag-ai-chatbot
      - run: bash scripts/assert-package.sh
      - uses: actions/upload-artifact@v4
        with:
          name: wp-rag-ai-chatbot
          path: wp-rag-ai-chatbot.zip
```

CI creates an artifact only; it does not publish a GitHub release, merge, or upload to WordPress.org.

- [ ] **Step 6: Run locally executable quality gates**

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit
composer verify:php
npm ci
npm audit --audit-level=critical
npm run verify:js
```

Run WordPress/package gates as well wherever Docker/zip support is available.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json scripts/assert-package.sh .github/workflows/ci.yml
git commit -m "ci: enforce quality and package gates"
```

---

### Task 5: Independent Review, Fresh Verification, and Durable M01 State

**Files:**
- Modify: `docs/milestones/M00-repository-discovery-master-specification.md`
- Modify: `docs/milestones/M01-wordpress-plugin-foundation-tooling.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md`
- Modify: `docs/progress/KNOWN-ISSUES.md`
- Modify: `docs/progress/TECH-DEBT.md`
- Modify: `docs/progress/SECURITY.md`
- Modify: `docs/DECISIONS.md` only for actual implementation rulings.

**Interfaces:**
- Consumes all verification output/reviewer findings.
- Produces M01 `COMPLETE` only after every gate passes and an exact M02 next action.

- [ ] **Step 1: Request independent review of the complete M01 diff**

Review against the approved master spec, this M01 plan, WordPress quality/security conventions, and code quality. Critical and Important findings block completion.

- [ ] **Step 2: Resolve review findings through required workflows**

For behavioral defects: invoke systematic-debugging, reproduce, add a failing regression test, make the minimum root-cause fix, run focused and affected suites, and re-review the changed scope.

- [ ] **Step 3: Run fresh verification**

Development dependency state:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit
composer verify:php
npm ci
npm audit --audit-level=critical
npm run verify:js
npm run env:start
npm run test:wp:activation
npm run env:stop
```

Production package state in a clean workspace:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
npm run plugin-zip -- --root-folder=wp-rag-ai-chatbot
bash scripts/assert-package.sh
```

Inspect every exit code and actual PHPUnit/Jest counts. Do not reuse earlier successful output as completion evidence.

- [ ] **Step 4: Verify CI on the exact candidate commit**

All four jobs must pass. Any unexpected CI/build/test failure invokes systematic-debugging before edits.

- [ ] **Step 5: Update durable state**

Record the exact commands/results/test counts, review findings/fixes, commit SHAs, changed files, known limitations, security review, and CI state in the M01 and progress ledgers. Mark M00 `COMPLETE` and M01 `COMPLETE` only when their remaining gates are actually satisfied. Set `docs/progress/STATUS.md` to M02 planning with the latest verified commit and exact next action.

- [ ] **Step 6: Commit the verified ledger**

```bash
git add docs
git commit -m "docs: complete M01 verification ledger"
```

Do not merge to `main` and do not publish to WordPress.org.

---

## Plan Self-Review Result

- Spec coverage: M01 acceptance criteria map to Tasks 1-5; M02+ product behavior is excluded.
- Placeholder scan: no TBD/TODO/"implement later" instructions remain.
- Type/signature consistency: `Bootstrap::register`, `Bootstrap::load`, `Lifecycle::activate`, and `Lifecycle::deactivate` are consistent across tests and implementation.
- Runtime consistency: PHP 8.2 / WordPress 6.9 match the approved spec; Node is development-only.
- TDD consistency: production PHP and TypeScript behavior has an explicit RED step before implementation; smoke/CI harness additions do not manufacture fake production changes merely to force RED.
- Packaging consistency: distribution must include Composer autoload and exclude development/private files.
