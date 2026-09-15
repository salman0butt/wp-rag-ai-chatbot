<?php
/**
 * WordPress lead repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Leads\Lead;
use WpRagAiChatbot\Leads\LeadDraft;
use WpRagAiChatbot\Leads\LeadRepository;

/**
 * Persists normalized lead captures in the dedicated leads table.
 */
final class WpdbLeadRepository implements LeadRepository {
	/**
	 * Create the repository.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Plugin table names.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Persist one normalized lead capture.
	 *
	 * @param LeadDraft $draft Normalized lead payload.
	 * @throws DatabaseException When persistence fails.
	 */
	public function create( LeadDraft $draft ): Lead {
		$lead_id = bin2hex( random_bytes( 16 ) );
		$now     = gmdate( 'Y-m-d H:i:s' );
		$data    = array(
			'lead_id'         => $lead_id,
			'conversation_id' => $draft->conversation_id,
			'bot_id'          => $draft->bot_id,
			'name'            => $draft->name,
			'email'           => $draft->email,
			'phone'           => $draft->phone,
			'note'            => $draft->note,
			'source'          => $draft->source,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$result = $this->connection->insert(
			$this->tables->leads(),
			$data,
			array_fill( 0, count( $data ), '%s' )
		);

		if ( false === $result ) {
			throw new DatabaseException( 'Could not create lead.' );
		}

		return new Lead(
			$lead_id,
			$draft->conversation_id,
			$draft->bot_id,
			$draft->name,
			$draft->email,
			$draft->phone,
			$draft->note,
			$draft->source,
			$now,
			$now
		);
	}
}
