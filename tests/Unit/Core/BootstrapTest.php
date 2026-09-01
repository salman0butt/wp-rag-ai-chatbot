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

    public function test_lifecycle_callback_class_exists(): void
    {
        self::assertTrue(class_exists(Lifecycle::class), 'Lifecycle callback class must exist before WordPress registers it.');
        self::assertTrue(is_callable([Lifecycle::class, 'activate']));
        self::assertTrue(is_callable([Lifecycle::class, 'deactivate']));
    }

    public function test_register_wires_only_the_foundation_hooks(): void
    {
        self::assertTrue(class_exists(Bootstrap::class), 'Bootstrap class must exist before hook wiring can be verified.');

        $pluginFile = '/tmp/wp-rag-ai-chatbot/wp-rag-ai-chatbot.php';

        Functions\expect('register_activation_hook')->once()->with($pluginFile, [Lifecycle::class, 'activate']);
        Functions\expect('register_deactivation_hook')->once()->with($pluginFile, [Lifecycle::class, 'deactivate']);
        Functions\expect('add_action')->once()->with('plugins_loaded', [Bootstrap::class, 'load']);

        Bootstrap::register($pluginFile);
    }

    public function test_load_emits_the_plugin_loaded_action(): void
    {
        self::assertTrue(class_exists(Bootstrap::class), 'Bootstrap class must exist before the loaded action can be verified.');

        Functions\expect('do_action')->once()->with('wp_rag_ai_chatbot_loaded');

        Bootstrap::load();
    }
}
