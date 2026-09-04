<?php
/**
 * Base vector-store contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Exposes store identity, capabilities, and health only.
 */
interface VectorStore {
	/**
	 * Return the stable store ID.
	 */
	public function store_id(): string;

	/**
	 * Return truthful optional operation capabilities.
	 */
	public function capabilities(): VectorStoreCapabilities;

	/**
	 * Return current adapter health.
	 */
	public function health(): VectorStoreHealth;
}
