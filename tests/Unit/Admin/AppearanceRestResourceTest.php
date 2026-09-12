<?php
/**
 * M14 administrator appearance REST resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Admin\Rest\AppearanceRestResource;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;

/** Proves administrator appearance read/write mapping stays bounded and fail-closed. */
final class AppearanceRestResourceTest extends TestCase {
	/** Read returns only normalized appearance for one valid bot identifier. */
	public function test_read_projects_normalized_appearance(): void {
		self::assertTrue( class_exists( AppearanceRestResource::class ), 'M14 Task 3 requires AppearanceRestResource.' );

		$bot_id     = new BotId( '0123456789abcdef0123456789abcdef' );
		$appearance = AppearanceConfig::from_array( array( 'primary_color' => '#ABCDEF' ) );
		$repository = $this->createMock( BotAppearanceRepository::class );
		$repository->expects( self::once() )->method( 'find' )->with( $bot_id )->willReturn( $appearance );

		self::assertSame(
			array( 'appearance' => $appearance->to_array() ),
			( new AppearanceRestResource( $repository ) )->read( $bot_id->value )
		);
	}

	/** Invalid bot identifiers fail closed before repository access. */
	public function test_read_rejects_invalid_bot_identifier(): void {
		self::assertTrue( class_exists( AppearanceRestResource::class ), 'M14 Task 3 requires AppearanceRestResource.' );

		$repository = $this->createMock( BotAppearanceRepository::class );
		$repository->expects( self::never() )->method( 'find' );

		self::assertSame(
			'invalid_bot_id',
			( new AppearanceRestResource( $repository ) )->read( 'bad-id' )['error']['code']
		);
	}

	/** Valid writes normalize and persist only the shared appearance authority. */
	public function test_write_normalizes_and_persists_appearance(): void {
		self::assertTrue( class_exists( AppearanceRestResource::class ), 'M14 Task 3 requires AppearanceRestResource.' );

		$bot_id     = new BotId( '0123456789abcdef0123456789abcdef' );
		$appearance = AppearanceConfig::from_array( array( 'primary_color' => '#ABCDEF' ) );
		$repository = $this->createMock( BotAppearanceRepository::class );
		$repository->expects( self::once() )
			->method( 'save' )
			->with( $bot_id, $appearance );

		self::assertSame(
			array( 'appearance' => $appearance->to_array() ),
			( new AppearanceRestResource( $repository ) )->write(
				$bot_id->value,
				array( 'primary_color' => '#ABCDEF' )
			)
		);
	}

	/** Unknown appearance fields and persistence failures expose stable non-sensitive errors. */
	public function test_write_rejects_unknown_fields_and_normalizes_persistence_failure(): void {
		self::assertTrue( class_exists( AppearanceRestResource::class ), 'M14 Task 3 requires AppearanceRestResource.' );

		$bot_id     = new BotId( '0123456789abcdef0123456789abcdef' );
		$repository = $this->createMock( BotAppearanceRepository::class );
		$resource   = new AppearanceRestResource( $repository );

		self::assertSame(
			'invalid_appearance',
			$resource->write( $bot_id->value, array( 'provider_id' => 'openai' ) )['error']['code']
		);

		$repository->method( 'save' )->willThrowException( new RuntimeException( 'Sensitive persistence detail.' ) );
		$response = $resource->write( $bot_id->value, array( 'primary_color' => '#ABCDEF' ) );

		self::assertSame( 'appearance_save_failed', $response['error']['code'] );
		self::assertStringNotContainsString( 'Sensitive persistence detail', (string) $response['error']['message'] );
	}
}
