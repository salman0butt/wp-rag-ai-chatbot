<?php
/**
 * Real WordPress M14 public widget surface smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use Throwable;
use WP_Block_Type_Registry;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

$enabled_bot_id      = null;
$disabled_bot_id     = null;
$widget_asset_handle = 'wp-rag-ai-chatbot-widget';

$repository = static function (): WpdbBotRepository {
	global $wpdb;
	$connection = new WpdbConnection( $wpdb );

	return new WpdbBotRepository( $connection, new TableNames( $connection->prefix() ) );
};

$cleanup = static function () use ( &$enabled_bot_id, &$disabled_bot_id, $repository ): void {
	$bot_repository = $repository();
	foreach ( array( &$enabled_bot_id, &$disabled_bot_id ) as &$bot_id ) {
		if ( null !== $bot_id ) {
			$bot_repository->delete( new BotId( $bot_id ) );
			$bot_id = null;
		}
	}
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	$cleanup();
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$assert_surface = static function ( string $html, string $bot_id, string $surface, string $label ) use ( $fail ): void {
	if ( ! str_contains( $html, 'class="wp-rag-ai-chatbot-widget"' ) ) {
		$fail( $label . ' did not render the shared widget mount.' );
	}
	if ( ! str_contains( $html, 'data-wp-rag-ai-chatbot-bot="' . esc_attr( $bot_id ) . '"' ) ) {
		$fail( $label . ' did not render the expected bot identifier.' );
	}
	if ( ! str_contains( $html, 'data-wp-rag-ai-chatbot-surface="' . esc_attr( $surface ) . '"' ) ) {
		$fail( $label . ' did not render the expected finite surface.' );
	}
};

try {
	foreach ( array( 'wp_rag_ai_chatbot', 'wp_rag_ai_chatbot_embed', 'wp_rag_ai_chatbot_fullscreen' ) as $shortcode ) {
		if ( ! shortcode_exists( $shortcode ) ) {
			$fail( 'Expected public widget shortcode is not registered: ' . $shortcode );
		}
	}

	if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'wp-rag-ai-chatbot/chatbot' ) ) {
		$fail( 'Dynamic Gutenberg chatbot block is not registered.' );
	}

	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Public widget assets were enqueued before a valid surface rendered.' );
	}

	$missing_bot_id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
	foreach (
		array(
			'floating shortcode'   => '[wp_rag_ai_chatbot bot="' . $missing_bot_id . '"]',
			'embedded shortcode'   => '[wp_rag_ai_chatbot_embed bot="' . $missing_bot_id . '"]',
			'fullscreen shortcode' => '[wp_rag_ai_chatbot_fullscreen bot="' . $missing_bot_id . '"]',
		) as $label => $shortcode
	) {
		if ( '' !== do_shortcode( $shortcode ) ) {
			$fail( 'Invalid bot did not fail closed for ' . $label . '.' );
		}
	}
	$missing_block = '<!-- wp:wp-rag-ai-chatbot/chatbot ' . wp_json_encode( array( 'bot' => $missing_bot_id ) ) . ' /-->';
	if ( '' !== trim( do_blocks( $missing_block ) ) ) {
		$fail( 'Invalid bot did not fail closed for the Gutenberg block.' );
	}
	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Invalid widget surfaces enqueued public assets.' );
	}

	$bot_repository  = $repository();
	$disabled_bot     = $bot_repository->create( 'M14 disabled surface smoke bot', false, 'disabled-secret-provider', 'disabled-secret-model' );
	$disabled_bot_id  = $disabled_bot->id->value;
	$disabled_surfaces = array(
		'[wp_rag_ai_chatbot bot="' . esc_attr( $disabled_bot_id ) . '"]',
		'[wp_rag_ai_chatbot_embed bot="' . esc_attr( $disabled_bot_id ) . '"]',
		'[wp_rag_ai_chatbot_fullscreen bot="' . esc_attr( $disabled_bot_id ) . '"]',
		'<!-- wp:wp-rag-ai-chatbot/chatbot ' . wp_json_encode( array( 'bot' => $disabled_bot_id ) ) . ' /-->',
	);
	foreach ( $disabled_surfaces as $surface_markup ) {
		$rendered = str_starts_with( $surface_markup, '<!-- wp:' ) ? do_blocks( $surface_markup ) : do_shortcode( $surface_markup );
		if ( '' !== trim( $rendered ) ) {
			$fail( 'Disabled bot surface did not fail closed.' );
		}
	}
	if ( wp_script_is( $widget_asset_handle, 'enqueued' ) || wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Disabled widget surfaces enqueued public assets.' );
	}

	$enabled_bot    = $bot_repository->create( 'M14 enabled surface smoke bot', true, 'surface-secret-provider', 'surface-secret-model' );
	$enabled_bot_id = $enabled_bot->id->value;

	$floating_html = do_shortcode( '[wp_rag_ai_chatbot bot="' . esc_attr( $enabled_bot_id ) . '"]' );
	$embedded_html = do_shortcode( '[wp_rag_ai_chatbot_embed bot="' . esc_attr( $enabled_bot_id ) . '"]' );
	$fullscreen_html = do_shortcode( '[wp_rag_ai_chatbot_fullscreen bot="' . esc_attr( $enabled_bot_id ) . '"]' );
	$block_markup = '<!-- wp:wp-rag-ai-chatbot/chatbot ' . wp_json_encode( array( 'bot' => $enabled_bot_id ) ) . ' /-->';
	$block_html   = do_blocks( $block_markup );

	$assert_surface( $floating_html, $enabled_bot_id, 'floating', 'Floating shortcode' );
	$assert_surface( $embedded_html, $enabled_bot_id, 'embedded', 'Embedded shortcode' );
	$assert_surface( $fullscreen_html, $enabled_bot_id, 'fullscreen', 'Fullscreen shortcode' );
	$assert_surface( $block_html, $enabled_bot_id, 'embedded', 'Gutenberg chatbot block' );

	if ( ! wp_script_is( $widget_asset_handle, 'enqueued' ) || ! wp_style_is( $widget_asset_handle, 'enqueued' ) ) {
		$fail( 'Enabled widget surfaces did not enqueue the shared public runtime assets.' );
	}

	$inline_before = wp_scripts()->get_data( $widget_asset_handle, 'before' );
	$inline_script = is_array( $inline_before ) ? implode( "\n", $inline_before ) : (string) $inline_before;
	if ( ! str_contains( $inline_script, $enabled_bot_id ) ) {
		$fail( 'Widget bootstrap data did not include the enabled bot identity.' );
	}
	foreach ( array( 'surface-secret-provider', 'surface-secret-model', 'retrieval_limit', 'vector_store', 'credential' ) as $forbidden ) {
		if ( str_contains( $inline_script, $forbidden ) ) {
			$fail( 'Widget bootstrap leaked forbidden runtime authority: ' . $forbidden );
		}
	}

	$cleanup();
	fwrite( STDOUT, "WordPress M14 floating, embedded, fullscreen, and Gutenberg widget surface smoke passed.\n" );
} catch ( Throwable $exception ) {
	$fail( 'WordPress widget surface smoke threw: ' . $exception->getMessage() );
}
