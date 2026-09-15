<?php
/**
 * Lead persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Leads;

/**
 * Persists normalized lead capture payloads.
 */
interface LeadRepository {
	/** Persist one normalized lead capture. */
	public function create( LeadDraft $draft ): Lead;
}
