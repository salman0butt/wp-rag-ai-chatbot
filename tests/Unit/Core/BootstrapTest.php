<?php
/**
 * Bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\Bootstrap;
use WpRagAiChatbot\Core\Lifecycle;
use WpRagAiChatbot\Database\DatabaseBootstrap;
use WpRagAiChatbot\Jobs\JobWorkerBootstrap;
use WpRagAiChatbot\Knowledge\KnowledgeBootstrap;
use WpRagAiChatbot\Providers\ProviderBootstrap;

/**
 * Verifies WordPress foundation hook registration.
 */
final class BootstrapTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Lifecycle callbacks must resolve to real callable methods.
	 */
	public function test_lifecycle_callback_class_exists(): void {
		self::assertTrue( class_exists( Lifecycle::class ), 'Lifecycle callback class must exist before WordPress registers it.' );
		self::assertTrue( is_callable( array( Lifecycle::class, 'activate' ) ) );
		self::assertTrue( is_callable( array( Lifecycle::class, 'deactivate' ) ) );
	}

	/**
	 * Bootstrap registers foundation, database, provider, knowledge, and jobs hooks.
	 */
	public function test_register_wires_foundation_database_provider_knowledge_and_jobs_hooks(): void {
		self::assertTrue( class_exists( Bootstrap::class ), 'Bootstrap class must exist before hook wiring can be verified.' );
		self::assertTrue( class_exists( DatabaseBootstrap::class ), 'DatabaseBootstrap must exist before database hook wiring can pass.' );
		self::assertTrue( class_exists( ProviderBootstrap::class ), 'ProviderBootstrap must exist before provider hook wiring can pass.' );
		self::assertTrue( class_exists( KnowledgeBootstrap::class ), 'KnowledgeBootstrap must exist before knowledge hook wiring can pass.' );
		self::assertTrue( class_exists( JobWorkerBootstrap::class ), 'JobWorkerBootstrap must exist before jobs hook wiring can pass.' );

		$plugin_file = '/tmp/wp-rag-ai-chatbot/wp-rag-ai-chatbot.php';

		Functions\expect( 'register_activation_hook' )->once()->with( $plugin_file, array( Lifecycle::class, 'activate' ) );
		Functions\expect( 'register_deactivation_hook' )->once()->with( $plugin_file, array( Lifecycle::class, 'deactivate' ) );
		Functions\expect( 'add_action' )->once()->with( 'wp_rag_ai_chatbot_activate', array( DatabaseBootstrap::class, 'on_activate' ) );
		Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( DatabaseBootstrap::class, 'on_plugins_loaded' ), 5 );
		Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( ProviderBootstrap::class, 'register' ), 10 );
		Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( KnowledgeBootstrap::class, 'register' ), 10 );
		Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( JobWorkerBootstrap::class, 'register' ), 20 );
		Functions\expect( 'add_action' )->once()->with( 'plugins_loaded', array( Bootstrap::class, 'load' ) );

		Bootstrap::register( $plugin_file );
	}

	/**
	 * Bootstrap emits the stable plugin-loaded action.
	 */
	public function test_load_emits_the_plugin_loaded_action(): void {
		Functions\expect( 'do_action' )->once()->with( 'wp_rag_ai_chatbot_loaded' );
		Bootstrap::load();
	}
}
