<?php
/**
 * Knowledge-source runtime composition root.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge;

use LogicException;
use WpRagAiChatbot\Knowledge\Sources\FaqSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceRegistry;
use WpRagAiChatbot\Knowledge\Sources\ManualTextSource;
use WpRagAiChatbot\Knowledge\Sources\WordPressPostSource;
use WpRagAiChatbot\Knowledge\WordPress\NativeWordPressContentGateway;

/**
 * Composes native and third-party knowledge sources once WordPress is loaded.
 */
final class KnowledgeBootstrap {
	/**
	 * Composed knowledge-source registry.
	 *
	 * @var KnowledgeSourceRegistry|null
	 */
	private static ?KnowledgeSourceRegistry $registry = null;

	/**
	 * Compose native and filtered knowledge-source implementations once.
	 *
	 * @throws KnowledgeSourceException When an extension violates the source contract.
	 */
	public static function register(): void {
		if ( null !== self::$registry ) {
			return;
		}

		$extensions = apply_filters( 'wp_rag_ai_chatbot_knowledge_sources', array() );
		if ( ! is_array( $extensions ) ) {
			throw new KnowledgeSourceException( 'Knowledge source extensions must be provided as an array.' );
		}

		$registry = new KnowledgeSourceRegistry();
		$registry->register( new ManualTextSource() );
		$registry->register( new FaqSource() );
		$registry->register( new WordPressPostSource( new NativeWordPressContentGateway() ) );

		foreach ( $extensions as $extension ) {
			if ( ! $extension instanceof KnowledgeSource ) {
				throw new KnowledgeSourceException( 'Knowledge source extensions must implement KnowledgeSource.' );
			}
			$registry->register( $extension );
		}

		self::$registry = $registry;
	}

	/**
	 * Return the composed knowledge-source registry.
	 *
	 * @throws LogicException When knowledge sources have not been registered.
	 */
	public static function registry(): KnowledgeSourceRegistry {
		if ( null === self::$registry ) {
			throw new LogicException( 'Knowledge sources have not been registered.' );
		}

		return self::$registry;
	}
}
