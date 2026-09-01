# M02 Database Schema, Migrations & Domain Repositories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a versioned, lock-protected WordPress database layer with incremental source/document tables, prepared and paginated repositories, upgrade/failure recovery, and uninstall retention behavior.

**Architecture:** Keep persistence behind narrow PHP contracts. A pure `MigrationRunner` coordinates ordered migrations, a schema-version store, and a database advisory lock; WordPress-specific adapters compose those pieces around `$wpdb`. M02 introduces only the two near-term application tables required by M04/M05 (`rag_ai_sources` and `rag_ai_documents`) and deliberately defers chunks, vectors, jobs, conversations, analytics, and other later-milestone tables.

**Tech Stack:** PHP 8.2+, WordPress 6.9+, `$wpdb`, `dbDelta()`, WordPress Options API, MySQL/MariaDB named locks, PHPUnit 10, Brain Monkey, WPCS, PHPStan, `wp-env`, WP-CLI, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-01-wp-rag-ai-chatbot-master-design.md`

## Global Constraints

- WordPress/PHP is the mandatory server runtime.
- Baseline remains WordPress 6.9+ and PHP 8.2+.
- No mandatory Node.js/Python/LangChain/LangGraph server runtime.
- No Redis, RabbitMQ, Kafka, Temporal, or other mandatory external queue.
- Use dedicated tables for application-scale data; do not store growing records in giant `wp_options` blobs.
- Migrations are versioned, idempotent, lock-protected, upgrade-tested, and failure-aware.
- SQL values are prepared/bounded; repositories isolate SQL from domain/application code.
- Introduce tables incrementally; M02 MUST NOT pre-create chunks, vectors, jobs, conversations, analytics, eval, lead, form, or action-audit tables.
- Use `$wpdb->prefix` for per-site tables. Do not use `$wpdb->base_prefix`.
- Store structured metadata/config as JSON-encoded `longtext`, not MySQL `json`, to keep MySQL/MariaDB portability.
- Do not add foreign-key constraints; WordPress compatibility and future deletion semantics stay application-managed.
- Store database timestamps in UTC.
- Uninstall defaults to retaining data. Destructive removal occurs only when the explicit `wp_rag_ai_delete_data_on_uninstall` option is true.
- No visible UI is introduced in M02.
- No merge/release without explicit user authorization.

---

## M02 Implementation Rulings

These are implementation choices inside the approved master architecture and must be recorded in `docs/DECISIONS.md` during Task 1.

1. **Schema versioning:** `wp_rag_ai_db_version` is a small WordPress option and is the authoritative applied integer schema version. The runner updates it only after each migration succeeds.
2. **Target version:** M02 ends at schema version `2`.
3. **Migration sequence:** V001 creates sources; V002 creates documents. This gives a genuine incremental upgrade path instead of one artificial all-in-one migration.
4. **Concurrency:** use a connection-scoped MySQL/MariaDB `GET_LOCK()`/`RELEASE_LOCK()` advisory lock with a deterministic per-database/per-site lock name. Lock contention returns a non-fatal `LOCKED` result; migration errors remain fatal during activation and are surfaced during normal upgrade checks.
5. **Initial tables:** only `{$wpdb->prefix}rag_ai_sources` and `{$wpdb->prefix}rag_ai_documents`.
6. **Repository contracts:** source/document repository interfaces live with their domain records; `$wpdb` implementations live under `Database/Repository`.
7. **Transactions:** M02 does not invent a generic transaction abstraction before a multi-write invariant exists. Single-row repository mutations rely on database statement atomicity. Migration version advancement occurs only after a migration completes, so failure recovery reruns the idempotent migration.
8. **Multisite:** tables are per-site through `$wpdb->prefix`. Network-wide activation fan-out is not implemented in M02; it remains an explicit compatibility item for M24. This avoids hardening data into network-global tables.
9. **Uninstall:** data is retained by default. `uninstall.php` may drop the two M02 tables and schema option only when the explicit delete-data option is true.

## File Map

### Database core
- `src/Database/Connection.php` — narrow database operations used by migrations/repositories.
- `src/Database/WpdbConnection.php` — production `$wpdb` adapter including `dbDelta`.
- `src/Database/DatabaseException.php` — persistence/migration failure type.
- `src/Database/TableNames.php` — derives internal per-site table names from `$wpdb->prefix`.
- `src/Database/DatabaseSchema.php` — option names and target schema version.
- `src/Database/SchemaVersionStore.php` — schema-version persistence contract.
- `src/Database/WordPressSchemaVersionStore.php` — Options API implementation.
- `src/Database/Migration.php` — migration contract.
- `src/Database/MigrationLock.php` — migration lock contract.
- `src/Database/WpdbNamedMigrationLock.php` — `GET_LOCK`/`RELEASE_LOCK` implementation.
- `src/Database/MigrationStatus.php` — `UP_TO_DATE`, `MIGRATED`, `LOCKED`.
- `src/Database/MigrationRunner.php` — ordered, failure-aware migration coordinator.
- `src/Database/DatabaseBootstrap.php` — WordPress composition boundary for activation/upgrade.
- `src/Database/Migrations/V001CreateSourcesTable.php` — sources DDL.
- `src/Database/Migrations/V002CreateDocumentsTable.php` — documents DDL.
- `src/Database/DatabaseUninstaller.php` — explicit destructive cleanup used by uninstall only.

### Domain records/contracts
- `src/Core/PagedResult.php` — bounded pagination result.
- `src/Knowledge/KnowledgeSourceRecord.php`
- `src/Knowledge/KnowledgeSourceRepository.php`
- `src/Documents/DocumentRecord.php`
- `src/Documents/DocumentRepository.php`
- `src/Database/Repository/WpdbKnowledgeSourceRepository.php`
- `src/Database/Repository/WpdbDocumentRepository.php`

### Lifecycle/integration
- Modify `src/Core/Bootstrap.php`
- Modify `src/Core/Lifecycle.php`
- Create `uninstall.php`
- Create `scripts/test-wp-database.sh`
- Create `scripts/test-wp-database.php`
- Modify `package.json`
- Modify `.github/workflows/ci.yml`

### Tests/docs
- `tests/Unit/Database/MigrationRunnerTest.php`
- `tests/Unit/Database/WpdbNamedMigrationLockTest.php`
- `tests/Unit/Database/MigrationSqlTest.php`
- `tests/Unit/Core/LifecycleTest.php`
- update `docs/DECISIONS.md`
- update `docs/milestones/M02-database-schema-migrations-repositories.md`
- update `docs/progress/STATUS.md`
- update `docs/progress/TEST-MATRIX.md`
- update `docs/progress/SECURITY.md`
- update `docs/progress/KNOWN-ISSUES.md`
- update `docs/progress/TECH-DEBT.md`

---

### Task 1: Migration Primitives and Schema Decisions

**Files:**
- Create: `src/Database/DatabaseException.php`
- Create: `src/Database/Connection.php`
- Create: `src/Database/TableNames.php`
- Create: `src/Database/DatabaseSchema.php`
- Create: `src/Database/SchemaVersionStore.php`
- Create: `src/Database/Migration.php`
- Create: `src/Database/MigrationLock.php`
- Create: `src/Database/MigrationStatus.php`
- Test: `tests/Unit/Database/MigrationRunnerTest.php`
- Modify: `docs/DECISIONS.md`
- Modify: `docs/progress/STATUS.md`

**Interfaces:**
- Produces `Connection`, `SchemaVersionStore`, `Migration`, `MigrationLock`, `MigrationStatus`, and schema constants used by every later M02 task.

- [ ] **Step 1: Write the migration-runner RED tests using local fakes**

Create `tests/Unit/Database/MigrationRunnerTest.php` with the following behavioral cases. The test-local fake classes must implement the real interfaces and keep state in arrays/properties so no WordPress database is required.

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\MigrationLock;
use WpRagAiChatbot\Database\MigrationRunner;
use WpRagAiChatbot\Database\MigrationStatus;
use WpRagAiChatbot\Database\SchemaVersionStore;

final class MigrationRunnerTest extends TestCase {
	public function test_applies_pending_migrations_in_version_order(): void {
		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( true );
		$log   = array();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 2, $log ),
				new RecordingMigration( 1, $log ),
			)
		);

		self::assertSame( MigrationStatus::MIGRATED, $runner->run() );
		self::assertSame( array( 1, 2 ), $log );
		self::assertSame( array( 1, 2 ), $store->writes );
		self::assertTrue( $lock->released );
	}

	public function test_skips_versions_already_applied(): void {
		$store = new FakeVersionStore( 1 );
		$lock  = new FakeMigrationLock( true );
		$log   = array();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 1, $log ),
				new RecordingMigration( 2, $log ),
			)
		);

		self::assertSame( MigrationStatus::MIGRATED, $runner->run() );
		self::assertSame( array( 2 ), $log );
		self::assertSame( array( 2 ), $store->writes );
	}

	public function test_returns_up_to_date_without_taking_lock(): void {
		$store = new FakeVersionStore( 2 );
		$lock  = new FakeMigrationLock( true );

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array( new RecordingMigration( 1, $ignored = array() ), new RecordingMigration( 2, $ignored ) )
		);

		self::assertSame( MigrationStatus::UP_TO_DATE, $runner->run() );
		self::assertFalse( $lock->attempted );
	}

	public function test_lock_contention_does_not_run_migrations(): void {
		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( false );
		$log   = array();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array( new RecordingMigration( 1, $log ) )
		);

		self::assertSame( MigrationStatus::LOCKED, $runner->run() );
		self::assertSame( array(), $log );
		self::assertSame( array(), $store->writes );
	}

	public function test_failure_does_not_advance_failed_version_and_releases_lock(): void {
		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( true );
		$log   = array();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 1, $log ),
				new FailingMigration( 2 ),
			)
		);

		$this->expectException( RuntimeException::class );

		try {
			$runner->run();
		} finally {
			self::assertSame( array( 1 ), $store->writes );
			self::assertTrue( $lock->released );
		}
	}
}
```

Add the exact test-local fakes below the test class: `FakeVersionStore`, `FakeMigrationLock`, `RecordingMigration`, `FailingMigration`, and `NullConnection`. `NullConnection` must implement every method declared in `Connection` and return harmless values.

- [ ] **Step 2: Commit/run RED before `MigrationRunner` exists**

Run in CI:

```bash
composer test -- tests/Unit/Database/MigrationRunnerTest.php
```

Expected: FAIL because the M02 database interfaces/classes and `MigrationRunner` do not exist.

- [ ] **Step 3: Add the exact foundational interfaces/constants**

`src/Database/DatabaseSchema.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

final class DatabaseSchema {
	public const VERSION = 2;
	public const VERSION_OPTION = 'wp_rag_ai_db_version';
	public const DELETE_DATA_OPTION = 'wp_rag_ai_delete_data_on_uninstall';

	private function __construct() {}
}
```

`src/Database/MigrationStatus.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

enum MigrationStatus: string {
	case UP_TO_DATE = 'up_to_date';
	case MIGRATED = 'migrated';
	case LOCKED = 'locked';
}
```

`src/Database/Migration.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

interface Migration {
	public function version(): int;
	public function up( Connection $connection ): void;
}
```

`src/Database/SchemaVersionStore.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

interface SchemaVersionStore {
	public function current(): int;
	public function save( int $version ): void;
}
```

`src/Database/MigrationLock.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

interface MigrationLock {
	public function acquire(): bool;
	public function release(): void;
}
```

`src/Database/Connection.php`:

```php
<?php
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
	public function update( string $table, array $data, array $where, array $format = array(), array $whereFormat = array() ): int|bool;
	public function delete( string $table, array $where, array $whereFormat = array() ): int|bool;
	public function insertId(): int;
	public function dbDelta( string $sql ): array;
	public function tableExists( string $table ): bool;
}
```

`src/Database/DatabaseException.php` extends `RuntimeException`.

`src/Database/TableNames.php`:

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

final class TableNames {
	public function __construct( private readonly string $prefix ) {}

	public function sources(): string {
		return $this->prefix . 'rag_ai_sources';
	}

	public function documents(): string {
		return $this->prefix . 'rag_ai_documents';
	}

	public function all(): array {
		return array( $this->sources(), $this->documents() );
	}
}
```

- [ ] **Step 4: Record M02 decisions before production persistence expands**

Append ADR-019 through ADR-023 to `docs/DECISIONS.md` covering: integer option schema version; two incremental M02 tables; per-site prefix/no foreign keys/longtext JSON; named advisory migration lock; retain-by-default uninstall.

Update `docs/progress/STATUS.md` to M02 Task 1 / RED→GREEN foundation execution.

- [ ] **Step 5: Commit the Task 1 RED/foundation boundary separately**

```bash
git add src/Database tests/Unit/Database docs/DECISIONS.md docs/progress/STATUS.md
git commit -m "test: define M02 migration behavior"
```

Do not add `MigrationRunner.php` in this commit.

---

### Task 2: Pure Migration Runner, WordPress Version Store, and Named Lock

**Files:**
- Create: `src/Database/MigrationRunner.php`
- Create: `src/Database/WordPressSchemaVersionStore.php`
- Create: `src/Database/WpdbNamedMigrationLock.php`
- Test: `tests/Unit/Database/WpdbNamedMigrationLockTest.php`
- Modify: `tests/Unit/Database/MigrationRunnerTest.php`

**Interfaces:**
- Consumes Task 1 interfaces.
- Produces `MigrationRunner::run(): MigrationStatus`.

- [ ] **Step 1: Implement the minimal ordered/failure-aware runner**

```php
<?php
declare(strict_types=1);

namespace WpRagAiChatbot\Database;

final class MigrationRunner {
	/** @param Migration[] $migrations */
	public function __construct(
		private readonly Connection $connection,
		private readonly SchemaVersionStore $versions,
		private readonly MigrationLock $lock,
		private array $migrations
	) {
		usort(
			$this->migrations,
			static fn ( Migration $left, Migration $right ): int => $left->version() <=> $right->version()
		);
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

- [ ] **Step 2: Run Task 1 tests GREEN**

```bash
composer test -- tests/Unit/Database/MigrationRunnerTest.php
```

Expected: all migration-runner cases pass.

- [ ] **Step 3: Write RED tests for deterministic advisory-lock SQL**

Create `tests/Unit/Database/WpdbNamedMigrationLockTest.php` around a recording `Connection` fake.

Required assertions:
- lock name starts `wp_rag_ai_migrate_`;
- lock name is <= 64 bytes;
- two instances with same database/prefix derive the same name;
- different prefixes derive different names;
- `acquire()` returns true only for database scalar `1`;
- `release()` issues `SELECT RELEASE_LOCK(...)`.

- [ ] **Step 4: Implement the named lock**

`WpdbNamedMigrationLock` derives:

```php
$identity = $connection->databaseName() . '|' . $connection->prefix();
$this->name = 'wp_rag_ai_migrate_' . substr( sha1( $identity ), 0, 40 );
```

`acquire()` executes a prepared `SELECT GET_LOCK(%s, 0)` and accepts only `1`/`'1'`. `release()` executes prepared `SELECT RELEASE_LOCK(%s)`.

- [ ] **Step 5: Implement the Options API version store**

`WordPressSchemaVersionStore::current()` casts `get_option( DatabaseSchema::VERSION_OPTION, 0 )` to int. `save()` uses `update_option( DatabaseSchema::VERSION_OPTION, $version, false )` and throws `DatabaseException` only when a failed update is distinguishable from “unchanged value”.

- [ ] **Step 6: Run focused + full PHP quality**

```bash
composer test -- tests/Unit/Database
composer verify:php
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add src/Database tests/Unit/Database
git commit -m "feat: add versioned migration runner"
```

---

### Task 3: `$wpdb` Adapter and Incremental Source/Document Migrations

**Files:**
- Create: `src/Database/WpdbConnection.php`
- Create: `src/Database/Migrations/V001CreateSourcesTable.php`
- Create: `src/Database/Migrations/V002CreateDocumentsTable.php`
- Test: `tests/Unit/Database/MigrationSqlTest.php`

**Interfaces:**
- Produces a real WordPress `Connection`.
- V001 must create only sources; V002 must create only documents.

- [ ] **Step 1: Write RED SQL-shape tests**

`MigrationSqlTest` uses a fake `Connection` whose `dbDelta()` stores SQL and whose `tableExists()` returns true.

Assert V001 SQL contains exactly:
- table `${prefix}rag_ai_sources`;
- `id bigint(20) unsigned NOT NULL AUTO_INCREMENT`;
- `source_key varchar(191) NOT NULL`;
- unique key on `source_key`;
- indexes on `source_type`, `status`, `external_id`;
- UTC timestamp columns `created_at`, `updated_at`.

Assert V002 SQL contains exactly:
- table `${prefix}rag_ai_documents`;
- `document_key varchar(191) NOT NULL`;
- `source_id bigint(20) unsigned NOT NULL`;
- `content longtext NOT NULL`;
- `metadata_json longtext`;
- indexes on `source_id`, `content_hash`, `external_id`, `document_type`;
- unique key on `document_key`.

Assert neither migration SQL contains `FOREIGN KEY`, `json`, `vector`, `embedding`, `conversation`, or `job`.

- [ ] **Step 2: Run RED**

```bash
composer test -- tests/Unit/Database/MigrationSqlTest.php
```

Expected: FAIL because migration classes do not exist.

- [ ] **Step 3: Implement V001 sources DDL**

The SQL passed to `dbDelta()` must be:

```sql
CREATE TABLE <prefix>rag_ai_sources (
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

After `dbDelta()`, call `tableExists()` and throw `DatabaseException` if the table does not exist.

- [ ] **Step 4: Implement V002 documents DDL**

```sql
CREATE TABLE <prefix>rag_ai_documents (
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

- [ ] **Step 5: Implement `WpdbConnection`**

Wrap injected `wpdb`; do not access the global inside repository code. `dbDelta()` must load `ABSPATH . 'wp-admin/includes/upgrade.php'` when needed. `prepare()` throws if WordPress returns null. `tableExists()` uses a prepared `SHOW TABLES LIKE %s`. `insertId()` returns `(int) $wpdb->insert_id`.

- [ ] **Step 6: Run focused and static-quality gates**

```bash
composer test -- tests/Unit/Database/MigrationSqlTest.php
composer verify:php
```

Expected: pass with WPCS/PHPStan clean.

- [ ] **Step 7: Commit**

```bash
git add src/Database/WpdbConnection.php src/Database/Migrations tests/Unit/Database/MigrationSqlTest.php
git commit -m "feat: add incremental database migrations"
```

---

### Task 4: Database Bootstrap and Lifecycle Upgrade Path

**Files:**
- Create: `src/Database/DatabaseBootstrap.php`
- Modify: `src/Core/Lifecycle.php`
- Modify: `src/Core/Bootstrap.php`
- Create: `tests/Unit/Core/LifecycleTest.php`
- Create: `scripts/test-wp-database.sh`
- Create: `scripts/test-wp-database.php`
- Modify: `package.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- `DatabaseBootstrap::migrate(): MigrationStatus`
- `DatabaseBootstrap::migrateIfNeeded(): MigrationStatus`
- Activation runs migration.
- Normal plugin load runs only when current version < 2.

- [ ] **Step 1: Write a lifecycle RED assertion**

`LifecycleTest` must prove activation emits a dedicated action that database bootstrap can attach to, instead of hard-coding SQL into lifecycle code:

```php
public function test_activation_emits_database_migration_hook(): void {
	\Brain\Monkey\Functions\expect( 'do_action' )
		->once()
		->with( 'wp_rag_ai_chatbot_activate' );

	Lifecycle::activate();
}
```

Add a load test asserting `Bootstrap::load()` invokes the database upgrade hook before `wp_rag_ai_chatbot_loaded`.

- [ ] **Step 2: Run RED**

```bash
composer test -- tests/Unit/Core/LifecycleTest.php
```

Expected: FAIL because M01 lifecycle does not emit the M02 migration hook yet.

- [ ] **Step 3: Implement hook-based lifecycle composition**

`Lifecycle::activate()`:

```php
public static function activate(): void {
	do_action( 'wp_rag_ai_chatbot_activate' );
}
```

In `Bootstrap::register()` add:

```php
add_action( 'wp_rag_ai_chatbot_activate', array( DatabaseBootstrap::class, 'migrate' ) );
add_action( 'plugins_loaded', array( DatabaseBootstrap::class, 'migrateIfNeeded' ), 5 );
```

Keep the existing plugin-loaded signal at its existing later/default priority.

- [ ] **Step 4: Implement `DatabaseBootstrap` composition**

`DatabaseBootstrap` obtains global `$wpdb`, wraps it in `WpdbConnection`, creates `TableNames`, `WordPressSchemaVersionStore`, `WpdbNamedMigrationLock`, and a `MigrationRunner` with V001/V002.

`migrateIfNeeded()` first reads the schema option; if it is already `2`, return `UP_TO_DATE` without taking a DB lock. If stale, run the runner.

- [ ] **Step 5: Create the real WordPress database test harness before declaring GREEN**

Add package script:

```json
"test:wp:database": "bash scripts/test-wp-database.sh"
```

`scripts/test-wp-database.sh` must:
1. deactivate plugin;
2. drop `rag_ai_sources` / `rag_ai_documents` through WP-CLI using the actual prefix;
3. delete `wp_rag_ai_db_version`;
4. activate plugin;
5. run `scripts/test-wp-database.php`;
6. set version to 1 and drop only documents;
7. start a fresh WP-CLI process while plugin is active;
8. assert the normal `plugins_loaded` upgrade recreated documents and version 2;
9. run the test PHP again to verify repeat/idempotent behavior.

- [ ] **Step 6: `scripts/test-wp-database.php` exact acceptance assertions**

The script must exit non-zero unless:
- `get_option( 'wp_rag_ai_db_version' ) === 2`;
- both prefixed tables exist;
- sources has the `source_key` unique index;
- documents has the `document_key` unique index and `source_id` index;
- running `DatabaseBootstrap::migrate()` again returns `UP_TO_DATE`;
- no `rag_ai_chunks`, `rag_ai_vectors`, `rag_ai_jobs`, or `rag_ai_conversations` table exists.

- [ ] **Step 7: Add database test to permanent WordPress CI job**

After `npm run test:wp:activation`, add:

```yaml
- run: npm run test:wp:database
```

Keep `env:stop` under `if: always()`.

- [ ] **Step 8: Run RED then GREEN in GitHub Actions**

RED commit contains lifecycle/integration tests before production migration hookup. Expected failure must be missing migration hooks/classes/tables, not environment setup.

GREEN commit adds `DatabaseBootstrap` and lifecycle modifications. The `wordpress-smoke` job must pass activation + database tests on WordPress 6.9/PHP 8.2.

- [ ] **Step 9: Commit**

```bash
git add src/Core src/Database/DatabaseBootstrap.php tests/Unit/Core scripts package.json .github/workflows/ci.yml
git commit -m "feat: run database migrations on activation and upgrade"
```

---

### Task 5: Source Repository with Prepared Lookups and Bounded Pagination

**Files:**
- Create: `src/Core/PagedResult.php`
- Create: `src/Knowledge/KnowledgeSourceRecord.php`
- Create: `src/Knowledge/KnowledgeSourceRepository.php`
- Create: `src/Database/Repository/WpdbKnowledgeSourceRepository.php`
- Extend: `scripts/test-wp-database.php`

**Interfaces:**
- `save(KnowledgeSourceRecord $record): KnowledgeSourceRecord`
- `findById(int $id): ?KnowledgeSourceRecord`
- `findByKey(string $sourceKey): ?KnowledgeSourceRecord`
- `paginate(int $page = 1, int $perPage = 20): PagedResult`
- `delete(int $id): void`
- max `perPage` = 100.

- [ ] **Step 1: Extend integration harness in RED**

Add acceptance behavior that tries source keys/titles containing apostrophes and SQL-like payload text such as:

```text
source-' OR 1=1 --
```

The test must prove exact round-trip lookup, no unintended rows, and pagination of 25 inserted sources into page sizes 10/10/5 with total 25.

Expected RED: repository classes do not exist.

- [ ] **Step 2: Implement immutable pagination result**

`PagedResult` constructor takes `array $items`, `int $total`, `int $page`, `int $perPage`, validates page/perPage >= 1, and exposes readonly properties.

- [ ] **Step 3: Implement source record**

`KnowledgeSourceRecord` readonly fields:

```php
?int $id;
string $sourceKey;
string $sourceType;
?string $externalId;
string $title;
?string $canonicalUrl;
string $status;
array $config;
?string $sourceHash;
?DateTimeImmutable $lastSyncedAt;
DateTimeImmutable $createdAt;
DateTimeImmutable $updatedAt;
```

Provide `withId(int $id): self`.

- [ ] **Step 4: Implement prepared/paginated source repository**

Rules:
- table comes only from `TableNames`;
- JSON encode with `wp_json_encode`, throw on false;
- UTC datetimes use `Y-m-d H:i:s`;
- new record uses `Connection::insert`;
- existing record uses `Connection::update` by integer ID;
- find queries use `prepare()` with `%i`, `%d`, `%s`;
- pagination normalizes `page=max(1,page)` and `perPage=min(100,max(1,perPage))`;
- count query returns integer total;
- list query uses `ORDER BY id ASC LIMIT %d OFFSET %d`;
- DB false results throw `DatabaseException`.

- [ ] **Step 5: Run full database integration and PHP quality**

```bash
npm run test:wp:database
composer verify:php
```

Expected: malicious-looking source key is treated as data; pagination is bounded and exact.

- [ ] **Step 6: Commit**

```bash
git add src/Core/PagedResult.php src/Knowledge src/Database/Repository/WpdbKnowledgeSourceRepository.php scripts/test-wp-database.php
git commit -m "feat: add knowledge source repository"
```

---

### Task 6: Document Repository and Source-Scoped Pagination

**Files:**
- Create: `src/Documents/DocumentRecord.php`
- Create: `src/Documents/DocumentRepository.php`
- Create: `src/Database/Repository/WpdbDocumentRepository.php`
- Extend: `scripts/test-wp-database.php`

**Interfaces:**
- `save(DocumentRecord $record): DocumentRecord`
- `findByKey(string $documentKey): ?DocumentRecord`
- `paginateBySource(int $sourceId, int $page = 1, int $perPage = 20): PagedResult`
- `deleteBySource(int $sourceId): int`

- [ ] **Step 1: Add RED integration behavior**

Insert two sources and at least 23 documents across them. Include document content/metadata with quotes, Unicode, HTML-like text, and SQL-like payload strings.

Assertions:
- exact `document_key` round trip;
- metadata JSON round trip;
- source A pagination is isolated from source B;
- per-page clamp to 100;
- `deleteBySource(sourceA)` deletes only A documents and returns the affected count.

Expected RED: document repository classes absent.

- [ ] **Step 2: Implement document record**

Readonly fields:

```php
?int $id;
string $documentKey;
int $sourceId;
?string $externalId;
string $documentType;
string $title;
?string $canonicalUrl;
string $content;
array $metadata;
?string $sourceVersion;
string $contentHash;
?string $language;
string $visibility;
DateTimeImmutable $createdAt;
DateTimeImmutable $updatedAt;
```

Validate `sourceId > 0`, `documentKey !== ''`, and `contentHash` is exactly 64 lowercase hex characters.

- [ ] **Step 3: Implement document repository**

Use the same prepared/bounded rules as Task 5. Do not introduce joins or foreign keys. `deleteBySource()` uses `Connection::delete()` with integer format and returns affected rows.

- [ ] **Step 4: Run database integration + PHP quality**

```bash
npm run test:wp:database
composer verify:php
```

Expected: all source/document persistence behaviors pass.

- [ ] **Step 5: Commit**

```bash
git add src/Documents src/Database/Repository/WpdbDocumentRepository.php scripts/test-wp-database.php
git commit -m "feat: add document repository"
```

---

### Task 7: Retain-by-Default Uninstall and Destructive Opt-In

**Files:**
- Create: `src/Database/DatabaseUninstaller.php`
- Create: `uninstall.php`
- Extend: `scripts/test-wp-database.sh`
- Extend: `scripts/test-wp-database.php`
- Modify: `scripts/assert-package.sh`
- Modify: `package.json` release file allow-list if needed.

**Interfaces:**
- `DatabaseUninstaller::run(): void`.
- Default uninstall preserves tables/data.
- Explicit option true drops current M02 tables and deletes M02 database options.

- [ ] **Step 1: Write RED integration acceptance**

The script must:
1. seed one source/document;
2. set delete-data option false;
3. invoke `uninstall.php` with `WP_UNINSTALL_PLUGIN` defined;
4. prove tables and seeded rows still exist;
5. set delete-data option true;
6. invoke uninstall again;
7. prove both M02 tables are absent;
8. prove `wp_rag_ai_db_version` and `wp_rag_ai_delete_data_on_uninstall` are deleted;
9. reactivate plugin and prove a clean schema can be recreated.

Expected RED: `uninstall.php`/uninstaller absent.

- [ ] **Step 2: Implement guarded `uninstall.php`**

```php
<?php
declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	return;
}

require $autoload;

\WpRagAiChatbot\Database\DatabaseUninstaller::run();
```

- [ ] **Step 3: Implement opt-in destructive cleanup**

`DatabaseUninstaller::run()`:
- returns immediately unless `get_option( DatabaseSchema::DELETE_DATA_OPTION, false ) === true`;
- creates `WpdbConnection` + `TableNames`;
- drops documents first, then sources using prepared `%i` identifiers;
- deletes schema version and delete-data options;
- throws no fatal error merely because a table is already absent.

- [ ] **Step 4: Ensure production ZIP includes uninstall runtime**

Update strict package assertion to require:
- `wp-rag-ai-chatbot/uninstall.php`
- `wp-rag-ai-chatbot/src/Database/DatabaseUninstaller.php`

and continue excluding tests/docs/manifests/Node dependencies.

- [ ] **Step 5: Run integration/package GREEN**

```bash
npm run test:wp:database
npm run plugin-zip -- --root-folder=wp-rag-ai-chatbot
bash scripts/assert-package.sh
```

Expected: retain-default and destructive-opt-in paths pass; strict ZIP passes.

- [ ] **Step 6: Commit**

```bash
git add src/Database/DatabaseUninstaller.php uninstall.php scripts package.json
git commit -m "feat: add retain-by-default uninstall policy"
```

---

### Task 8: M02 Review, Security/Performance Audit, Durable Evidence, and Fresh CI

**Files:**
- Modify: `docs/milestones/M02-database-schema-migrations-repositories.md`
- Modify: `docs/progress/STATUS.md`
- Modify: `docs/progress/TEST-MATRIX.md`
- Modify: `docs/progress/SECURITY.md`
- Modify: `docs/progress/KNOWN-ISSUES.md`
- Modify: `docs/progress/TECH-DEBT.md`

**Interfaces:**
- No new runtime behavior.
- Produces the authoritative recovery state for M03.

- [ ] **Step 1: Independent review gate**

Because this chat runtime lacks the subagent dispatcher, perform the ADR-017 inline fallback but review against a fresh context:
- compare `main...feat/m02-database-schema`;
- inspect every runtime M02 file;
- verify no later-milestone tables/features leaked in;
- verify no raw user-controlled SQL;
- verify `perPage <= 100`;
- verify migration lock release is in `finally`;
- verify failed migration version is not advanced;
- verify default uninstall retains data;
- verify no secrets/credentials are introduced.

Any Critical or Important issue must be fixed and re-reviewed before completion.

- [ ] **Step 2: Security audit**

Record:
- SQL injection test payload results;
- WPCS prepared-SQL results;
- advisory lock behavior;
- uninstall authorization boundary (`WP_UNINSTALL_PLUGIN` + explicit option);
- no admin/public migration endpoint exists;
- no sensitive provider data is introduced in M02.

- [ ] **Step 3: Performance audit**

Record:
- indexes actually created;
- bounded pagination max 100;
- normal request migration path exits after one small option read when version is current;
- no table scans are introduced for normal source/document lookup keys;
- no growing data stored in `wp_options`.

- [ ] **Step 4: Fresh verification on exact candidate SHA**

Permanent CI must pass all jobs with the updated WordPress database integration:
- `php-quality`
- `js-quality`
- `wordpress-smoke` including activation + database migration/repository/uninstall tests
- `package`

Also confirm package artifact exists for the exact candidate SHA.

- [ ] **Step 5: Update durable ledgers**

M02 milestone must include:
- exact RED and GREEN run/commit evidence;
- fresh install, V1→V2 upgrade, idempotency, failure-recovery evidence;
- repository injection/pagination evidence;
- uninstall retain/delete evidence;
- review findings/fixes;
- fresh CI run ID and exact SHA;
- files changed;
- known limitations;
- next milestone M03.

`STATUS.md` must move to:
- current milestone M03;
- current task M03 detailed implementation planning;
- completed milestones M00, M01, M02;
- latest verified M02 candidate;
- exact next action: invoke writing-plans for M03.

- [ ] **Step 6: Commit docs and re-run permanent CI**

```bash
git add docs
git commit -m "docs: complete M02 verification ledgers"
```

Run permanent CI on this documentation-complete branch head. Do not claim M02 complete until all jobs pass on that exact SHA.

- [ ] **Step 7: Finish branch**

Invoke `superpowers:finishing-a-development-branch`. The user previously chose fast-forward merge for completed milestones, but integration still requires an explicit finishing action at that point; never silently merge a future milestone if user instructions change.
