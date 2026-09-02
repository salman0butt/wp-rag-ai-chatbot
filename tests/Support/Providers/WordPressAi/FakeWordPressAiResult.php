<?php
/**
 * WordPress AI result test double.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\WordPressAi;

use RuntimeException;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Test double mirrors documented WordPress AI result method names exactly.
/**
 * Deterministic fake for the documented WordPress AI result methods.
 */
final class FakeWordPressAiResult {
	/**
	 * Create a fake result.
	 *
	 * @param string               $text Text returned by toText().
	 * @param string               $id Safe result identifier.
	 * @param array<string, mixed> $data Public normalized result data.
	 * @param bool                 $throw_on_text Whether toText() should fail.
	 */
	public function __construct(
		private string $text,
		private string $id,
		private array $data,
		private bool $throw_on_text = false
	) {
	}

	/**
	 * Return deterministic generated text.
	 *
	 * @throws RuntimeException When configured to simulate missing text.
	 */
	public function toText(): string {
		if ( $this->throw_on_text ) {
			throw new RuntimeException( 'No text result available.' );
		}

		return $this->text;
	}

	/**
	 * Return deterministic safe result ID.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Return deterministic public result metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->data;
	}
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
