# M02 Database Schema, Migrations & Domain Repositories Implementation Plan — V2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Supersedes:** `docs/superpowers/plans/2026-09-02-m02-database-schema-migrations-repositories.md`. The first draft remains historical evidence; this V2 fixes its self-review gaps and is authoritative for execution.

**Goal:** Add a versioned, lock-protected WordPress persistence foundation with incremental source/document tables, prepared and paginated repositories, real upgrade/failure recovery tests, and retain-by-default uninstall behavior.

**Architecture:** Persistence stays behind narrow PHP contracts. A pure `MigrationRunner` coordinates ordered migrations, a version store, and an advisory lock; WordPress adapters compose those pieces around `$wpdb`. M02 creates only `rag_ai_sources` and `rag_ai_documents`; all chunk/vector/job/chat/provider/UI data remains deferred to its approved milestone.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, `$wpdb`, `dbDelta()`, WordPress Options API, MySQL/MariaDB named locks, PHPUnit 10, Brain Monkey, WPCS, PHPStan, `wp-env`, WP-CLI, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-01-wp-rag-ai-chatbot-master-design.md`

## Global Constraints

- WordPress/PHP is the mandatory server runtime.
- Baseline: WordPress 6.9+ and PHP 8.2+.
- Node remains build/test tooling only.
- No Redis/RabbitMQ/Kafka/Temporal or external database is required.
- Growing application records must not live in `wp_options`; only the small schema version/uninstall policy may use options.
- Migrations are versioned, ordered, idempotent, lock-protected, upgrade-tested, and failure-aware.
- SQL values are prepared and list queries are bounded.
- Use `$wpdb->prefix`; never `$wpdb->base_prefix` for M02 tables.
- Use JSON-encoded `longtext`, not a native MySQL JSON column.
- No foreign-key constraints.
- Store database timestamps as UTC `Y-m-d H:i:s`.
- `perPage` is clamped to 1..100.
- Uninstall retains data unless `wp_rag_ai_delete_data_on_uninstall` is exactly boolean `true`.
- No visible UI, providers, RAG, chunks, vectors, jobs, conversations, analytics, actions, or WooCommerce behavior in M02.
- No merge/release without explicit user authorization.

## Approved M02 Implementation Rulings

Record these in `docs/DECISIONS.md` as ADR-019 through ADR-023:

- ADR-019: schema version is integer option `wp_rag_ai_db_version`; target M02 version is `2`; version advances only after a migration succeeds.
- ADR-020: V001 creates sources; V002 creates documents. This is a real V1→V2 upgrade path.
- ADR-021: tables are per-site via `$wpdb->prefix`, no foreign keys, portable JSON-in-longtext; network activation fan-out is deferred to M24.
- ADR-022: migration concurrency uses `GET_LOCK`/`RELEASE_LOCK` with a deterministic database+site identity; lock contention returns `LOCKED` rather than running concurrent DDL.
- ADR-023: uninstall retains data by default and deletes only after explicit boolean opt-in.

## File Map

Create:
- `src/Database/Connection.php`
- `src/Database/DatabaseException.php`
- `src/Database/DatabaseSchema.php`
- `src/Database/TableNames.php`
- `src/Database/SchemaVersionStore.php`
- `src/Database/Migration.php`
- `src/Database/MigrationLock.php`
- `src/Database/MigrationStatus.php`
- `src/Database/MigrationRunner.php`
- `src/Database/WpdbConnection.php`
- `src/Database/WordPressSchemaVersionStore.php`
- `src/Database/WpdbNamedMigrationLock.php`
- `src/Database/Migrations/V001CreateSourcesTable.php`
- `src/Database/Migrations/V002CreateDocumentsTable.php`
- `src/Database/DatabaseBootstrap.php`
- `src/Core/PagedResult.php`
- `src/Knowledge/KnowledgeSourceRecord.php`
- `src/Knowledge/KnowledgeSourceRepository.php`
- `src/Documents/DocumentRecord.php`
- `src/Documents/DocumentRepository.php`
- `src/Database/Repository/WpdbKnowledgeSourceRepository.php`
- `src/Database/Repository/WpdbDocumentRepository.php`
- `src/Database/DatabaseUninstaller.php`
- `tests/Unit/Database/MigrationRunnerTest.php`
- `tests/Unit/Database/WpdbNamedMigrationLockTest.php`
- `tests/Unit/Database/MigrationSqlTest.php`
- `tests/Unit/Core/LifecycleTest.php`
- `scripts/test-wp-database.sh`
- `scripts/test-wp-database.php`
- `uninstall.php`

Modify:
- `src/Core/Bootstrap.php`
- `src/Core/Lifecycle.php`
- `tests/Unit/Core/BootstrapTest.php`
- `package.json`
- `.github/workflows/ci.yml`
- `scripts/assert-package.sh`
- `docs/DECISIONS.md`
- `docs/milestones/M02-database-schema-migrations-repositories.md`
- `docs/progress/STATUS.md`
- `docs/progress/TEST-MATRIX.md`
- `docs/progress/SECURITY.md`
- `docs/progress/KNOWN-ISSUES.md`
- `docs/progress/TECH-DEBT.md`

---

### Task 1: Define Database Contracts and Durable Decisions

**Behavior status:** These interfaces/constants are compile-time seams with no runtime behavior. TDD begins in Task 2 before `MigrationRunner` production behavior exists.

**Files:** Database contracts/constants + `docs/DECISIONS.md` + `docs/progress/STATUS.md`.

- [ ] **Step 1: Add exact contracts**

`src/Database/Connection.php`:

```php
<?php
/** Database connection contract. @package WpRagAiChatbot */
declare(strict_types=1);
namespace WpRagAiChatbot\Database;

interface Connection {
	public function prefix(): string;
	public function databaseName(): string;
	public function charsetCollate(): string;
	public function prepare( string $query, mixed ...$args ): string;
	public function query( string $query ): int|bool;
	public function getVar( string $query ): string|int|float|null;
	public function getRow( string $query ): ?array;
	public function getResults( string $query ): array;
	public function insert( string $table, array $data, array $format = array() ): int|bool;
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool;
	public function delete( string $table, array $where, array $where_format = array() ): int|bool;
	public function insertId(): int;
	public function dbDelta( string $sql ): array;
	public function tableExists( string $table ): bool;
}
```

`DatabaseException` extends `\RuntimeException` with no additional behavior.

`DatabaseSchema` constants:

```php
public const VERSION = 2;
public const VERSION_OPTION = 'wp_rag_ai_db_version';
public const DELETE_DATA_OPTION = 'wp_rag_ai_delete_data_on_uninstall';
```

`TableNames` constructor accepts a prefix and exposes `sources()`, `documents()`, and `all()` returning exactly those two prefixed names.

`SchemaVersionStore`:

```php
interface SchemaVersionStore {
	public function current(): int;
	public function save( int $version ): void;
}
```

`Migration`:

```php
interface Migration {
	public function version(): int;
	public function up( Connection $connection ): void;
}
```

`MigrationLock`:

```php
interface MigrationLock {
	public function acquire(): bool;
	public function release(): void;
}
```

`MigrationStatus`:

```php
enum MigrationStatus: string {
	case UP_TO_DATE = 'up_to_date';
	case MIGRATED = 'migrated';
	case LOCKED = 'locked';
}
```

- [ ] **Step 2: Record ADR-019..023 and set STATUS to M02 Task 2 / RED**

No schema/table data may be created by Task 1.

- [ ] **Step 3: Run static quality**

```bash
composer lint:php
composer analyse
```

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
git add src/Database docs/DECISIONS.md docs/progress/STATUS.md
git commit -m "build: define database migration contracts"
```

---

### Task 2: Migration Runner — RED → GREEN

**Files:** `tests/Unit/Database/MigrationRunnerTest.php`, then `src/Database/MigrationRunner.php`.

**Produces:** `MigrationRunner::run(): MigrationStatus`.

- [ ] **Step 1: Write the complete RED test fixture**

Use these fakes in the same test file:

```php
final class FakeVersionStore implements SchemaVersionStore {
	/** @var int[] */
	public array $writes = array();
	public function __construct( private int $version ) {}
	public function current(): int { return $this->version; }
	public function save( int $version ): void { $this->version = $version; $this->writes[] = $version; }
}

final class FakeMigrationLock implements MigrationLock {
	public bool $attempted = false;
	public bool $released = false;
	public function __construct( private readonly bool $acquired ) {}
	public function acquire(): bool { $this->attempted = true; return $this->acquired; }
	public function release(): void { $this->released = true; }
}

final class RecordingMigration implements Migration {
	/** @param int[] $log */
	public function __construct( private readonly int $migration_version, private array &$log ) {}
	public function version(): int { return $this->migration_version; }
	public function up( Connection $connection ): void { $this->log[] = $this->migration_version; }
}

final class FailingMigration implements Migration {
	public function __construct( private readonly int $migration_version ) {}
	public function version(): int { return $this->migration_version; }
	public function up( Connection $connection ): void { throw new \RuntimeException( 'migration failed' ); }
}

final class NullConnection implements Connection {
	public function prefix(): string { return 'wp_'; }
	public function databaseName(): string { return 'wordpress'; }
	public function charsetCollate(): string { return ''; }
	public function prepare( string $query, mixed ...$args ): string { return $query; }
	public function query( string $query ): int|bool { return 0; }
	public function getVar( string $query ): string|int|float|null { return null; }
	public function getRow( string $query ): ?array { return null; }
	public function getResults( string $query ): array { return array(); }
	public function insert( string $table, array $data, array $format = array() ): int|bool { return 1; }
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool { return 1; }
	public function delete( string $table, array $where, array $where_format = array() ): int|bool { return 1; }
	public function insertId(): int { return 1; }
	public function dbDelta( string $sql ): array { return array(); }
	public function tableExists( string $table ): bool { return true; }
}
```

Tests must assert:
1. pending migrations supplied as [2,1] execute [1,2] and save versions [1,2];
2. current version 1 executes only V2;
3. current version 2 returns `UP_TO_DATE` without calling lock;
4. failed lock returns `LOCKED` with no migration/version write;
5. V2 exception after successful V1 leaves stored version 1 and releases lock.

For the up-to-date test use a dedicated `$log = array();`; do not use assignment expressions in constructor arguments.

- [ ] **Step 2: Run RED**

```bash
composer test -- tests/Unit/Database/MigrationRunnerTest.php
```

Expected: FAIL specifically because `WpRagAiChatbot\Database\MigrationRunner` does not exist.

- [ ] **Step 3: Implement only enough runner behavior**

```php
final class MigrationRunner {
	/** @param Migration[] $migrations */
	public function __construct(
		private readonly Connection $connection,
		private readonly SchemaVersionStore $versions,
		private readonly MigrationLock $lock,
		private array $migrations
	) {
		usort( $this->migrations, static fn ( Migration $a, Migration $b ): int => $a->version() <=> $b->version() );
	}

	public function run(): MigrationStatus {
		$current = $this->versions->current();
		if ( $current >= DatabaseSchema::VERSION ) {
			return MigrationStatus::UP_TO_DATE;
		}
		if ( ! $this->lock->acquire() ) {
			return MigrationStatus::LOCKED;
		}
		try {
			foreach ( $this->migrations as $migration ) {
				if ( $migration->version() <= $current ) {
					continue;
				}
				$migration->up( $this->connection );
				$this->versions->save( $migration->version() );
				$current = $migration->version();
			}
		} finally {
			$this->lock->release();
		}
		return MigrationStatus::MIGRATED;
	}
}
```

- [ ] **Step 4: Run GREEN and PHP quality**

```bash
composer test -- tests/Unit/Database/MigrationRunnerTest.php
composer verify:php
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Database/MigrationRunnerTest.php src/Database/MigrationRunner.php
git commit -m "feat: add failure-aware migration runner"
```

---

### Task 3: WordPress Database Adapters and V001/V002 — TDD

**Files:** `WpdbConnection`, version store, named lock, migrations, unit tests.

- [ ] **Step 1: Write lock RED tests**

Use a recording `Connection` fake and assert:
- same database+prefix produces same deterministic lock query;
- different prefix changes lock query;
- acquired only when `getVar()` returns `1` or `'1'`;
- release issues prepared `SELECT RELEASE_LOCK(%s)`;
- derived lock identifier begins `wp_rag_ai_migrate_` and is <=64 bytes.

- [ ] **Step 2: Run lock RED**

```bash
composer test -- tests/Unit/Database/WpdbNamedMigrationLockTest.php
```

Expected: FAIL because `WpdbNamedMigrationLock` does not exist.

- [ ] **Step 3: Implement lock exactly**

```php
final class WpdbNamedMigrationLock implements MigrationLock {
	private string $name;
	public function __construct( private readonly Connection $connection ) {
		$identity   = $connection->databaseName() . '|' . $connection->prefix();
		$this->name = 'wp_rag_ai_migrate_' . substr( sha1( $identity ), 0, 40 );
	}
	public function acquire(): bool {
		$sql = $this->connection->prepare( 'SELECT GET_LOCK(%s, 0)', $this->name );
		return 1 === (int) $this->connection->getVar( $sql );
	}
	public function release(): void {
		$sql = $this->connection->prepare( 'SELECT RELEASE_LOCK(%s)', $this->name );
		$this->connection->getVar( $sql );
	}
}
```

- [ ] **Step 4: Write migration SQL RED tests**

`MigrationSqlTest` records the SQL passed to `dbDelta()` and asserts V001 and V002 separately.

V001 required SQL body:

```sql
CREATE TABLE <sources> (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	source_key varchar(191) NOT NULL,
	source_type varchar(64) NOT NULL,
	external_id varchar(191) DEFAULT NULL,
	title text NOT NULL,
	canonical_url text NULL,
	status varchar(32) NOT NULL DEFAULT 'active',
	config_json longtext NULL,
	source_hash char(64) DEFAULT NULL,
	last_synced_at datetime DEFAULT NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY source_key (source_key),
	KEY source_type (source_type),
	KEY status (status),
	KEY external_id (external_id)
) <charset-collate>;
```

V002 required SQL body:

```sql
CREATE TABLE <documents> (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	document_key varchar(191) NOT NULL,
	source_id bigint(20) unsigned NOT NULL,
	external_id varchar(191) DEFAULT NULL,
	document_type varchar(64) NOT NULL,
	title text NOT NULL,
	canonical_url text NULL,
	content longtext NOT NULL,
	metadata_json longtext NULL,
	source_version varchar(191) DEFAULT NULL,
	content_hash char(64) NOT NULL,
	language varchar(20) DEFAULT NULL,
	visibility varchar(32) NOT NULL DEFAULT 'public',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY document_key (document_key),
	KEY source_id (source_id),
	KEY external_id (external_id),
	KEY document_type (document_type),
	KEY content_hash (content_hash)
) <charset-collate>;
```

Each migration calls `tableExists()` after `dbDelta()` and throws `DatabaseException` if false. Tests also assert SQL does not contain `FOREIGN KEY`, native ` json `, `embedding`, `vector`, `job`, or `conversation`.

- [ ] **Step 5: Run migration RED**

```bash
composer test -- tests/Unit/Database/MigrationSqlTest.php
```

Expected: FAIL because V001/V002 do not exist.

- [ ] **Step 6: Implement V001/V002 and WordPress adapters**

`WordPressSchemaVersionStore`:

```php
public function current(): int {
	return (int) get_option( DatabaseSchema::VERSION_OPTION, 0 );
}
public function save( int $version ): void {
	if ( $this->current() === $version ) {
		return;
	}
	if ( false === update_option( DatabaseSchema::VERSION_OPTION, $version, false ) && $this->current() !== $version ) {
		throw new DatabaseException( 'Could not persist database schema version.' );
	}
}
```

`WpdbConnection` constructor accepts `\wpdb`. Required mappings:
- `prefix()` => `$wpdb->prefix`;
- `databaseName()` => `(string) DB_NAME` when defined, else `(string) $wpdb->dbname`;
- `charsetCollate()` => `$wpdb->get_charset_collate()`;
- `prepare()` => `$wpdb->prepare()`, throw if null;
- `getRow()`/`getResults()` use `ARRAY_A`;
- insert/update/delete call corresponding `$wpdb` methods;
- `dbDelta()` conditionally requires `ABSPATH . 'wp-admin/includes/upgrade.php'` then calls global `dbDelta($sql)`;
- `tableExists($table)` prepares `SHOW TABLES LIKE %s` and compares returned scalar exactly to `$table`.

- [ ] **Step 7: Run GREEN + full PHP quality**

```bash
composer test -- tests/Unit/Database
composer verify:php
```

- [ ] **Step 8: Commit**

```bash
git add src/Database tests/Unit/Database
git commit -m "feat: add WordPress database migrations"
```

---

### Task 4: Activation/Upgrade Integration and Real WordPress Migration Tests

**Files:** `DatabaseBootstrap`, lifecycle/bootstrap modifications, existing Bootstrap test update, lifecycle test, WP scripts, package/CI scripts.

- [ ] **Step 1: Write lifecycle/bootstrap RED tests before production hook changes**

`LifecycleTest` uses Brain Monkey setup/teardown and asserts:

```php
Functions\expect( 'do_action' )->once()->with( 'wp_rag_ai_chatbot_activate' );
Lifecycle::activate();
```

Modify existing `BootstrapTest::test_register_wires_only_the_foundation_hooks()` expectations to require all five registrations:

```php
Functions\expect( 'register_activation_hook' )->once()->with( $plugin_file, array( Lifecycle::class, 'activate' ) );
Functions\expect( 'register_deactivation_hook' )->once()->with( $plugin_file, array( Lifecycle::class, 'deactivate' ) );
Functions\expect( 'add_action' )->once()->with( 'wp_rag_ai_chatbot_activate', array( DatabaseBootstrap::class, 'migrate' ) );
Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( DatabaseBootstrap::class, 'migrateIfNeeded' ), 5 );
Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( Bootstrap::class, 'load' ) );
```

- [ ] **Step 2: Run RED**

```bash
composer test -- tests/Unit/Core
```

Expected: FAIL because activation/database hooks are not wired and `DatabaseBootstrap` does not exist.

- [ ] **Step 3: Implement lifecycle hook and DB composition**

`Lifecycle::activate()` emits `wp_rag_ai_chatbot_activate` only.

`Bootstrap::register()` keeps M01 activation/deactivation and plugin-loaded hooks, and adds the two database hooks exactly as the RED test expects.

`DatabaseBootstrap`:

```php
public static function migrate(): MigrationStatus {
	return self::runner()->run();
}
public static function migrateIfNeeded(): MigrationStatus {
	$versions = new WordPressSchemaVersionStore();
	if ( $versions->current() >= DatabaseSchema::VERSION ) {
		return MigrationStatus::UP_TO_DATE;
	}
	return self::runner( $versions )->run();
}
```

`runner()` obtains global `$wpdb`, creates `WpdbConnection`, `WordPressSchemaVersionStore`, `WpdbNamedMigrationLock`, and migrations `[new V001CreateSourcesTable(new TableNames(...)), new V002CreateDocumentsTable(new TableNames(...))]`.

- [ ] **Step 4: Add exact `scripts/test-wp-database.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail

WP="npm run --silent wp-env -- run cli wp"

$WP plugin deactivate wp-rag-ai-chatbot --quiet || true
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_sources"); delete_option("wp_rag_ai_db_version"); delete_option("wp_rag_ai_delete_data_on_uninstall");'
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php

# Simulate a site that completed only V001.
$WP eval '$p=$GLOBALS["wpdb"]->prefix; $GLOBALS["wpdb"]->query("DROP TABLE IF EXISTS {$p}rag_ai_documents"); update_option("wp_rag_ai_db_version", 1, false);'
# A new WP-CLI process loads active plugins and must perform the normal plugins_loaded upgrade.
$WP eval 'if ((int) get_option("wp_rag_ai_db_version", 0) !== 2) { fwrite(STDERR, "Automatic V1 to V2 upgrade failed\n"); exit(1); }'
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
```

- [ ] **Step 5: Add exact base `scripts/test-wp-database.php`**

```php
<?php
declare(strict_types=1);

use WpRagAiChatbot\Database\DatabaseBootstrap;
use WpRagAiChatbot\Database\MigrationStatus;

global $wpdb;
$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$prefix    = $wpdb->prefix;
$sources   = $prefix . 'rag_ai_sources';
$documents = $prefix . 'rag_ai_documents';

if ( 2 !== (int) get_option( 'wp_rag_ai_db_version', 0 ) ) { $fail( 'Schema version is not 2.' ); }
foreach ( array( $sources, $documents ) as $table ) {
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $found !== $table ) { $fail( 'Missing table: ' . $table ); }
}
$source_indexes = $wpdb->get_results( "SHOW INDEX FROM `{$sources}`", ARRAY_A );
$doc_indexes    = $wpdb->get_results( "SHOW INDEX FROM `{$documents}`", ARRAY_A );
$index_names    = static fn ( array $rows ): array => array_values( array_unique( array_column( $rows, 'Key_name' ) ) );
if ( ! in_array( 'source_key', $index_names( $source_indexes ), true ) ) { $fail( 'Missing source_key index.' ); }
if ( ! in_array( 'document_key', $index_names( $doc_indexes ), true ) || ! in_array( 'source_id', $index_names( $doc_indexes ), true ) ) { $fail( 'Missing document indexes.' ); }
if ( MigrationStatus::UP_TO_DATE !== DatabaseBootstrap::migrate() ) { $fail( 'Repeat migration was not idempotent.' ); }
foreach ( array( 'rag_ai_chunks', 'rag_ai_vectors', 'rag_ai_jobs', 'rag_ai_conversations' ) as $suffix ) {
	$table = $prefix . $suffix;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { $fail( 'Unexpected future table: ' . $table ); }
}
```

The raw `SHOW INDEX` statements use plugin-owned table identifiers only; add the narrowly scoped PHPCS annotation required by the exact sniff if WPCS flags them. Do not suppress prepared-SQL rules for value-bearing queries.

- [ ] **Step 6: Wire permanent scripts/CI**

Add package script:

```json
"test:wp:database": "bash scripts/test-wp-database.sh"
```

In `wordpress-smoke`, after activation test, add:

```yaml
- run: npm run test:wp:database
```

Keep `env:stop` under `if: always()`.

- [ ] **Step 7: Commit RED first, observe expected CI failure, then GREEN implementation**

RED commit: tests + scripts/CI only. Expected failure must be missing activation/database behavior, not package install/environment setup.

GREEN commit: lifecycle/bootstrap/database composition. Then run permanent CI and require PHP + WordPress database integration green.

---

### Task 5: Prepared, Bounded Source and Document Repositories

**Files:** pagination value, records/contracts, two `$wpdb` repositories, extend WP integration script.

- [ ] **Step 1: Extend the real WP script in RED before repository implementations exist**

After the schema assertions, instantiate production repositories using `new WpdbConnection($wpdb)` and `new TableNames($wpdb->prefix)`.

Use this source key verbatim:

```text
source-' OR 1=1 --
```

Create 25 sources with deterministic keys. Assert:
- the malicious-looking key round trips exactly with `findByKey()`;
- there is exactly one matching returned record, not all rows;
- `paginate(1,10)`, `(2,10)`, `(3,10)` return 10/10/5 items with total 25;
- `paginate(1,1000)->perPage === 100`.

Create one second source, then 23 documents split across two sources with content containing apostrophes, `<script>literal test data</script>`, Unicode `مرحبا`, and `" OR 1=1 --`. Assert:
- `findByKey()` exact round trip;
- decoded metadata equals encoded metadata;
- `paginateBySource()` never includes the other source;
- `deleteBySource($sourceA)` deletes only A documents and returns exact affected count.

Expected RED: repository/value classes do not exist.

- [ ] **Step 2: Add exact public contracts**

`PagedResult` readonly properties: `array $items`, `int $total`, `int $page`, `int $perPage`; constructor rejects page/perPage <1.

`KnowledgeSourceRepository`:

```php
interface KnowledgeSourceRepository {
	public function save( KnowledgeSourceRecord $record ): KnowledgeSourceRecord;
	public function findById( int $id ): ?KnowledgeSourceRecord;
	public function findByKey( string $source_key ): ?KnowledgeSourceRecord;
	public function paginate( int $page = 1, int $per_page = 20 ): PagedResult;
	public function delete( int $id ): void;
}
```

`DocumentRepository`:

```php
interface DocumentRepository {
	public function save( DocumentRecord $record ): DocumentRecord;
	public function findByKey( string $document_key ): ?DocumentRecord;
	public function paginateBySource( int $source_id, int $page = 1, int $per_page = 20 ): PagedResult;
	public function deleteBySource( int $source_id ): int;
}
```

`KnowledgeSourceRecord` readonly fields:
`?int id, string sourceKey, string sourceType, ?string externalId, string title, ?string canonicalUrl, string status, array config, ?string sourceHash, ?DateTimeImmutable lastSyncedAt, DateTimeImmutable createdAt, DateTimeImmutable updatedAt` plus `withId()`.

`DocumentRecord` readonly fields:
`?int id, string documentKey, int sourceId, ?string externalId, string documentType, string title, ?string canonicalUrl, string content, array metadata, ?string sourceVersion, string contentHash, ?string language, string visibility, DateTimeImmutable createdAt, DateTimeImmutable updatedAt` plus `withId()`.

`DocumentRecord` constructor rejects `sourceId < 1`, empty document key, and hashes not matching `/^[a-f0-9]{64}$/`.

- [ ] **Step 3: Implement source repository with these exact SQL boundaries**

Hydration lookup:

```php
$sql = $connection->prepare( 'SELECT * FROM %i WHERE source_key = %s LIMIT 1', $tables->sources(), $source_key );
```

Pagination:

```php
$page     = max( 1, $page );
$per_page = min( 100, max( 1, $per_page ) );
$offset   = ( $page - 1 ) * $per_page;
$count    = (int) $connection->getVar( $connection->prepare( 'SELECT COUNT(*) FROM %i', $tables->sources() ) );
$sql      = $connection->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT %d OFFSET %d', $tables->sources(), $per_page, $offset );
```

Save uses `Connection::insert()` for null ID and `Connection::update()` by integer ID otherwise. JSON config uses `wp_json_encode()` and throws `DatabaseException` on false. Datetimes format UTC `Y-m-d H:i:s`. Delete uses `Connection::delete()` with `%d` format. Any DB `false` write result throws.

- [ ] **Step 4: Implement document repository with these exact SQL boundaries**

Find key:

```php
$sql = $connection->prepare( 'SELECT * FROM %i WHERE document_key = %s LIMIT 1', $tables->documents(), $document_key );
```

Source pagination:

```php
$page     = max( 1, $page );
$per_page = min( 100, max( 1, $per_page ) );
$offset   = ( $page - 1 ) * $per_page;
$count    = (int) $connection->getVar( $connection->prepare( 'SELECT COUNT(*) FROM %i WHERE source_id = %d', $tables->documents(), $source_id ) );
$sql      = $connection->prepare( 'SELECT * FROM %i WHERE source_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', $tables->documents(), $source_id, $per_page, $offset );
```

`deleteBySource()` uses `Connection::delete($table, array('source_id'=>$source_id), array('%d'))` and returns the integer affected count.

- [ ] **Step 5: Run GREEN + security-oriented integration**

```bash
npm run test:wp:database
composer verify:php
```

The SQL-like strings must survive as literal data and must not alter row counts or query scope.

- [ ] **Step 6: Commit**

```bash
git add src/Core/PagedResult.php src/Knowledge src/Documents src/Database/Repository scripts/test-wp-database.php
git commit -m "feat: add source and document repositories"
```

---

### Task 6: Retain-by-Default Uninstall — RED → GREEN

**Files:** `DatabaseUninstaller`, `uninstall.php`, integration script, package guard.

- [ ] **Step 1: Extend database integration in RED**

At the end of `scripts/test-wp-database.sh`:

```bash
# Seeded repository rows exist at this point.
$WP option update wp_rag_ai_delete_data_on_uninstall 0 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php";'
$WP eval '$p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_sources","rag_ai_documents"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) !== $t) { exit(1); } }'
$WP option update wp_rag_ai_delete_data_on_uninstall 1 --format=json
$WP eval 'define("WP_UNINSTALL_PLUGIN", "wp-rag-ai-chatbot/wp-rag-ai-chatbot.php"); include WP_PLUGIN_DIR . "/wp-rag-ai-chatbot/uninstall.php";'
$WP eval '$p=$GLOBALS["wpdb"]->prefix; foreach (["rag_ai_documents","rag_ai_sources"] as $s) { $t=$p.$s; if ($GLOBALS["wpdb"]->get_var($GLOBALS["wpdb"]->prepare("SHOW TABLES LIKE %s",$t)) === $t) { exit(1); } } if (false !== get_option("wp_rag_ai_db_version", false) || false !== get_option("wp_rag_ai_delete_data_on_uninstall", false)) { exit(1); }'
$WP plugin deactivate wp-rag-ai-chatbot --quiet || true
$WP plugin activate wp-rag-ai-chatbot --quiet
$WP eval-file wp-content/plugins/wp-rag-ai-chatbot/scripts/test-wp-database.php
```

Expected RED: uninstall runtime does not exist / tables are not deleted on opt-in.

- [ ] **Step 2: Add exact guarded `uninstall.php`**

```php
<?php
/** WP RAG AI Chatbot uninstall entry point. @package WpRagAiChatbot */
declare(strict_types=1);
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
$autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $autoload ) ) {
	return;
}
require $autoload;
\WpRagAiChatbot\Database\DatabaseUninstaller::run();
```

- [ ] **Step 3: Implement exact destructive boundary**

```php
public static function run(): void {
	if ( true !== get_option( DatabaseSchema::DELETE_DATA_OPTION, false ) ) {
		return;
	}
	global $wpdb;
	$connection = new WpdbConnection( $wpdb );
	$tables     = new TableNames( $connection->prefix() );
	$connection->query( $connection->prepare( 'DROP TABLE IF EXISTS %i', $tables->documents() ) );
	$connection->query( $connection->prepare( 'DROP TABLE IF EXISTS %i', $tables->sources() ) );
	delete_option( DatabaseSchema::VERSION_OPTION );
	delete_option( DatabaseSchema::DELETE_DATA_OPTION );
}
```

- [ ] **Step 4: Update strict package guard**

Require these ZIP members:
- `wp-rag-ai-chatbot/uninstall.php`
- `wp-rag-ai-chatbot/src/Database/DatabaseUninstaller.php`

Continue rejecting tests/docs/.github/package/composer manifests and Node dependencies from the runtime archive according to M01 policy.

- [ ] **Step 5: Run GREEN**

```bash
npm run test:wp:database
npm run plugin-zip -- --root-folder=wp-rag-ai-chatbot
bash scripts/assert-package.sh
composer verify:php
```

- [ ] **Step 6: Commit**

```bash
git add src/Database/DatabaseUninstaller.php uninstall.php scripts package.json
git commit -m "feat: add explicit database uninstall policy"
```

---

### Task 7: Independent Review, Fresh Verification, Durable M02 State

**No runtime behavior is added here.**

- [ ] **Step 1: Request independent code review**

Use `superpowers:requesting-code-review`. If no subagent dispatcher is available, use ADR-017’s inline fresh-context review fallback. Compare `main...feat/m02-database-schema` and inspect every M02 runtime/test/integration file.

Reject completion for any unresolved Critical/Important issue. Specific checks:
- only sources/documents tables exist;
- migration version is written after successful migration only;
- lock always releases in `finally`;
- lock contention cannot run DDL;
- repository values are prepared or passed through `$wpdb` insert/update/delete APIs;
- list queries are bounded to <=100;
- no user-supplied identifier becomes SQL;
- uninstall is guarded and opt-in;
- normal `plugins_loaded` path does only a small option read when current;
- no credentials/provider/RAG/UI scope appears.

- [ ] **Step 2: Fresh permanent CI on exact candidate**

Require all four jobs success:
- `php-quality` including Composer audit + WPCS + PHPStan + PHPUnit;
- `js-quality` unchanged baseline;
- `wordpress-smoke` including activation plus `test:wp:database` fresh-install/V1→V2/idempotency/repository/uninstall coverage;
- `package` with strict runtime archive validation and uploaded artifact.

- [ ] **Step 3: Update ledgers with exact evidence**

`M02` milestone records RED/GREEN commits/runs, integration evidence, review findings/fixes, security/performance evidence, exact candidate SHA/CI run/artifact digest, changed files, limitations, and M03 next milestone.

`STATUS.md` moves to M03 planning only after the exact M02 candidate is green and review has no unresolved Critical/Important issues.

`TEST-MATRIX.md` marks migration/repository SQL coverage active. `SECURITY.md` records SQL-injection payload, lock/uninstall boundary, and no new secrets. `KNOWN-ISSUES.md` / `TECH-DEBT.md` record only evidenced limitations such as deferred network-wide multisite activation behavior.

- [ ] **Step 4: Commit documentation and verify that exact documentation-complete SHA**

```bash
git add docs
git commit -m "docs: complete M02 verification ledgers"
```

Run permanent CI again on that exact SHA. No completion claim before overall workflow conclusion `success` and all four jobs `success`.

- [ ] **Step 5: Invoke `superpowers:finishing-a-development-branch`**

Present the integration choices. Do not infer that the M01 merge choice automatically authorizes M02 integration.

## Self-Review Result

- **Spec coverage:** M02 scope is fully mapped: versioning, locking, fresh/upgrade/idempotent/failure-aware migrations, initial bounded tables, prepared/paginated repositories, uninstall retention, integration testing, security/performance, review, and durable state. Later milestones remain excluded.
- **Placeholder scan:** no `TBD`, `TODO`, “implement later”, “similar to”, or undefined “write tests for above” steps remain. Exact behavioral scripts and critical production snippets are included.
- **Type consistency:** `Connection`, migration interfaces/status, repository signatures, schema constants, table names, and lifecycle hook names are identical across producer/consumer tasks. Existing `BootstrapTest` is explicitly updated when hook wiring changes.
