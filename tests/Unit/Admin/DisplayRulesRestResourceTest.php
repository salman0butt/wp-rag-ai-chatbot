<?php
/**
 * M15 administrator display-rules REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\DisplayRulesRestResource;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\BotDisplayRulesRepository;
use WpRagAiChatbot\Frontend\DisplayRulesConfig;

/** Proves administrator display-rule read/write mapping stays bounded and fail-closed. */
final class DisplayRulesRestResourceTest extends TestCase {
	/** Read returns only normalized display rules for one valid bot identifier. */
	public function test_read_projects_normalized_display_rules(): void {
		self::assertTrue(
			class_exists( DisplayRulesRestResource::class ),
			'M15 Task 2C requires DisplayRulesRestResource.'
		);

		$bot_id        = new BotId( '0123456789abcdef0123456789abcdef' );
		$display_rules = DisplayRulesConfig::from_array( array( 'enabled' => false ) );
		$repository    = $this->createMock( BotDisplayRulesRepository::class );
		$repository->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $display_rules );

		self::assertSame(
			array( 'display_rules' => $display_rules->to_array() ),
			( new DisplayRulesRestResource( $repository ) )->read( $bot_id->value )
		);
	}

	/** Invalid bot identifiers fail closed before repository access. */
	public function test_read_rejects_invalid_bot_identifier(): void {
		self::assertTrue(
			class_exists( DisplayRulesRestResource::class ),
			'M15 Task 2C requires DisplayRulesRestResource.'
		);

		$repository = $this->createMock( BotDisplayRulesRepository::class );
		$repository->expects( self::never() )->method( 'find' );

		self::assertSame(
			'invalid_bot_id',
			( new DisplayRulesRestResource( $repository ) )->read( 'bad-id' )['error']['code']
		);
	}

	/** Valid writes normalize and persist only the shared display-rule authority. */
	public function test_write_normalizes_and_persists_display_rules(): void {
		self::assertTrue(
			class_exists( DisplayRulesRestResource::class ),
			'M15 Task 2C requires DisplayRulesRestResource.'
		);

		$bot_id        = new BotId( '0123456789abcdef0123456789abcdef' );
		$display_rules = DisplayRulesConfig::from_array( array( 'enabled' => false ) );
		$repository    = $this->createMock( BotDisplayRulesRepository::class );
		$repository->expects( self::once() )
			->method( 'save' )
			->with( $bot_id, $display_rules );

		self::assertSame(
			array( 'display_rules' => $display_rules->to_array() ),
			( new DisplayRulesRestResource( $repository ) )->write(
				$bot_id->value,
				array( 'enabled' => false )
			)
		);
	}

	/** Unknown runtime fields and persistence failures expose stable non-sensitive errors. */
	public function test_write_rejects_runtime_overrides_and_normalizes_persistence_failure(): void {
		self::assertTrue(
			class_exists( DisplayRulesRestResource::class ),
			'M15 Task 2C requires DisplayRulesRestResource.'
		);

		$bot_id     = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotDisplayRulesRepository::class );
		$resource   = new DisplayRulesRestResource( $repository );

		self::assertSame(
			'invalid_display_rules',
			$resource->write( $bot_id->value, array( 'provider_id' => 'openai' ) )['error']['code']
		);

		$repository->method( 'save' )->willThrowException( new RuntimeException( 'Sensitive persistence detail.' ) );
		$response = $resource->write( $bot_id->value, array( 'enabled' => false ) );

		self::assertSame( 'display_rules_save_failed', $response['error']['code'] );
		self::assertStringNotContainsString(
			'Sensitive persistence detail',
			(string) $response['error']['message']
		);
	}
}
