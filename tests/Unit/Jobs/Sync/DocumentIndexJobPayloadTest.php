<?php
/**
 * M09 document-index job payload contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;

/**
 * Proves queued synchronization payloads contain stable identifiers only.
 */
final class DocumentIndexJobPayloadTest extends TestCase {
	/**
	 * A valid payload round-trips through the persisted array contract.
	 */
	public function test_identifier_only_payload_round_trips(): void {
		$payload = new DocumentIndexJobPayload(
			'doc-42',
			42,
			'collection-main',
			'index-profile-default',
			'generation-7'
		);

		$persisted = $payload->to_array();

		self::assertSame(
			array(
				'document_key'     => 'doc-42',
				'source_id'        => 42,
				'collection_id'    => 'collection-main',
				'configuration_id' => 'index-profile-default',
				'generation'       => 'generation-7',
			),
			$persisted
		);
		self::assertEquals( $payload, DocumentIndexJobPayload::from_array( $persisted ) );
	}

	/**
	 * Arbitrary source content cannot cross the durable queue boundary.
	 */
	public function test_unknown_payload_fields_are_rejected(): void {
		$this->expectException( JobQueueException::class );

		DocumentIndexJobPayload::from_array(
			array(
				'document_key'     => 'doc-42',
				'source_id'        => 42,
				'collection_id'    => 'collection-main',
				'configuration_id' => 'index-profile-default',
				'generation'       => 'generation-7',
				'content'          => 'large source body must never be queued',
			)
		);
	}

	/**
	 * Stable identities must remain bounded and safe for server-side resolution.
	 */
	public function test_invalid_stable_identifier_is_rejected(): void {
		$this->expectException( JobQueueException::class );

		new DocumentIndexJobPayload(
			'../unsafe-document',
			42,
			'collection-main',
			'index-profile-default',
			'generation-7'
		);
	}
}
