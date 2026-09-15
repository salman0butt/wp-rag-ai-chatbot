<?php
/**
 * Lead capture draft tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Leads;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the bounded public-to-persistence lead payload for M16.
 */
final class LeadDraftTest extends TestCase {
	/** Lead fields are normalized before any repository receives them. */
	public function test_normalizes_allowlisted_lead_fields(): void {
		$class = 'WpRagAiChatbot\\Leads\\LeadDraft';

		self::assertTrue( class_exists( $class ), 'LeadDraft is missing.' );
		if ( ! class_exists( $class ) ) {
			return;
		}

		$reflection = new ReflectionClass( $class );
		$draft      = $reflection->newInstance(
			' conversation-1 ',
			' bot-1 ',
			' Salman Butt ',
			' SALMAN@EXAMPLE.COM ',
			' +92 300 1234567 ',
			' Please contact me after 5 PM. ',
			' pre_chat '
		);

		self::assertSame( 'conversation-1', $reflection->getProperty( 'conversation_id' )->getValue( $draft ) );
		self::assertSame( 'bot-1', $reflection->getProperty( 'bot_id' )->getValue( $draft ) );
		self::assertSame( 'Salman Butt', $reflection->getProperty( 'name' )->getValue( $draft ) );
		self::assertSame( 'salman@example.com', $reflection->getProperty( 'email' )->getValue( $draft ) );
		self::assertSame( '+92 300 1234567', $reflection->getProperty( 'phone' )->getValue( $draft ) );
		self::assertSame( 'Please contact me after 5 PM.', $reflection->getProperty( 'note' )->getValue( $draft ) );
		self::assertSame( 'pre_chat', $reflection->getProperty( 'source' )->getValue( $draft ) );
	}

	/** Empty optional text is represented as null instead of arbitrary empty metadata. */
	public function test_normalizes_blank_optional_fields_to_null(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );
		$draft      = $reflection->newInstance( 'conversation-1', 'bot-1', ' ', null, '', "\n", ' ' );

		self::assertNull( $reflection->getProperty( 'name' )->getValue( $draft ) );
		self::assertNull( $reflection->getProperty( 'email' )->getValue( $draft ) );
		self::assertNull( $reflection->getProperty( 'phone' )->getValue( $draft ) );
		self::assertNull( $reflection->getProperty( 'note' )->getValue( $draft ) );
		self::assertNull( $reflection->getProperty( 'source' )->getValue( $draft ) );
	}

	/** Conversation identity is mandatory and bounded before ownership checks or persistence. */
	public function test_rejects_invalid_conversation_identity(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );

		$this->expectException( InvalidArgumentException::class );
		$reflection->newInstance( str_repeat( 'c', 65 ), 'bot-1' );
	}

	/** Bot identity is mandatory and bounded before ownership checks or persistence. */
	public function test_rejects_invalid_bot_identity(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );

		$this->expectException( InvalidArgumentException::class );
		$reflection->newInstance( 'conversation-1', '' );
	}

	/** Malformed email input fails closed instead of being persisted as contact data. */
	public function test_rejects_malformed_email(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );

		$this->expectException( InvalidArgumentException::class );
		$reflection->newInstance( 'conversation-1', 'bot-1', null, 'not-an-email' );
	}

	/** Identity and contact fields reject overflow instead of silently broadening the storage contract. */
	public function test_rejects_overlong_contact_fields(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );

		$this->expectException( InvalidArgumentException::class );
		$reflection->newInstance( 'conversation-1', 'bot-1', str_repeat( 'n', 161 ) );
	}

	/** Free-text note content is bounded to prevent unbounded public persistence. */
	public function test_rejects_overlong_note(): void {
		$reflection = new ReflectionClass( 'WpRagAiChatbot\\Leads\\LeadDraft' );

		$this->expectException( InvalidArgumentException::class );
		$reflection->newInstance( 'conversation-1', 'bot-1', null, null, null, str_repeat( 'n', 2001 ) );
	}
}
