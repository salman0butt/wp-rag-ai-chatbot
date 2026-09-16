<?php
/**
 * Real WordPress end-to-end modern knowledge publish-flow smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Admin\Rest\AdminRestBootstrap;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\Repository\WpdbDocumentRepository;
use WpRagAiChatbot\Database\Repository\WpdbKnowledgeSourceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Frontend\PublicChatRestBootstrap;
use WpRagAiChatbot\Jobs\WordPressJobCron;
use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;

$source_id       = null;
$bot_id          = null;
$disabled_bot_id = null;
$unbound_bot_id  = null;
$conversation_id = null;
$job_keys        = array();
$http_calls      = array();
$credential_set  = false;
$original_remote = $_SERVER['REMOTE_ADDR'] ?? null;
$fake_secret     = 'task9-fake-gemini-secret';

$cleanup = static function () use (
    &$source_id,
    &$bot_id,
    &$disabled_bot_id,
    &$unbound_bot_id,
    &$conversation_id,
    &$job_keys,
    &$credential_set,
    $original_remote
): void {
    try {
        global $wpdb;
        $connection = new WpdbConnection( $wpdb );
        $tables     = new TableNames( $connection->prefix() );

        foreach ( array( $bot_id, $disabled_bot_id, $unbound_bot_id ) as $id ) {
            if ( is_string( $id ) ) {
                ( new WpdbBotRepository( $connection, $tables ) )->delete( new BotId( $id ) );
            }
        }

        if ( is_string( $conversation_id ) ) {
            $message_ids = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE conversation_id = %s',
                    $tables->messages(),
                    $conversation_id
                )
            );
            foreach ( $message_ids as $message_id ) {
                $wpdb->delete( $tables->message_citations(), array( 'message_id' => (int) $message_id ), array( '%d' ) );
            }
            $wpdb->delete( $tables->messages(), array( 'conversation_id' => $conversation_id ), array( '%s' ) );
            $wpdb->delete( $tables->conversations(), array( 'conversation_id' => $conversation_id ), array( '%s' ) );
        }

        foreach ( $job_keys as $job_key ) {
            $wpdb->delete( $tables->jobs(), array( 'job_key' => $job_key ), array( '%s' ) );
        }

        if ( is_int( $source_id ) ) {
            $wpdb->delete( $tables->chunk_search(), array( 'source_id' => $source_id ), array( '%d' ) );
            $wpdb->delete( $tables->documents(), array( 'source_id' => $source_id ), array( '%d' ) );
            $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM %i WHERE collection_key = %s AND metadata_json LIKE %s',
                    $tables->vectors(),
                    'wp-rag-default',
                    '%"source_id":' . $source_id . '%'
                )
            );
            ( new WpdbKnowledgeSourceRepository( $connection, $tables ) )->delete( $source_id );
        }

        if ( $credential_set ) {
            ( new WordPressCredentialStore( new AuthenticatedCredentialCipher( new RuntimeCryptoCapabilities() ) ) )->delete( 'gemini_direct' );
        }
    } catch ( Throwable ) {
        // Cleanup must not hide the assertion that caused the smoke to fail.
    }

    wp_set_current_user( 0 );
    if ( null === $original_remote ) {
        unset( $_SERVER['REMOTE_ADDR'] );
    } else {
        $_SERVER['REMOTE_ADDR'] = $original_remote;
    }
};

$fail = static function ( string $message ) use ( $cleanup ): void {
    $cleanup();
    fwrite( STDERR, $message . PHP_EOL );
    exit( 1 );
};

$assert = static function ( bool $condition, string $message ) use ( $fail ): void {
    if ( ! $condition ) {
        $fail( $message );
    }
};

$request = static function ( string $method, string $route, ?array $body = null ): WP_REST_Response {
    $rest_request = new WP_REST_Request( $method, $route );
    if ( null !== $body ) {
        $rest_request->set_header( 'content-type', 'application/json' );
        $rest_request->set_body( wp_json_encode( $body ) );
    }

    return rest_do_request( $rest_request );
};

$list_jobs = static function () use ( $request, $fail ): array {
    $response = $request( 'GET', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/jobs' );
    if ( 200 !== $response->get_status() ) {
        $fail( 'Modern setup smoke could not inspect the persisted job queue.' );
    }

    $data = $response->get_data();
    if ( ! is_array( $data ) || ! is_array( $data['items'] ?? null ) ) {
        $fail( 'Modern setup smoke received an invalid job projection.' );
    }

    return $data['items'];
};

$find_job = static function ( array $jobs, string $job_key ): ?array {
    foreach ( $jobs as $job ) {
        if ( is_array( $job ) && $job_key === ( $job['job_key'] ?? null ) ) {
            return $job;
        }
    }

    return null;
};

$fake_http = static function ( mixed $preempt, array $args, string $url ) use ( &$http_calls ): mixed {
    unset( $preempt );
    $http_calls[] = $url;

    if ( str_contains( $url, '/embeddings' ) ) {
        $body   = json_decode( (string) ( $args['body'] ?? '{}' ), true );
        $inputs = is_array( $body['input'] ?? null ) ? array_values( $body['input'] ) : array( '' );
        $data   = array();
        foreach ( $inputs as $index => $input ) {
            unset( $input );
            $data[] = array(
                'index'     => $index,
                'embedding' => array_fill( 0, 3072, 0.001 ),
            );
        }

        return array(
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'headers'  => array( 'content-type' => 'application/json' ),
            'body'     => wp_json_encode(
                array(
                    'object' => 'list',
                    'data'   => $data,
                    'model'  => 'gemini-embedding-001',
                    'usage'  => array( 'prompt_tokens' => 1, 'total_tokens' => 1 ),
                )
            ),
        );
    }

    if ( str_contains( $url, '/chat/completions' ) ) {
        return array(
            'response' => array( 'code' => 200, 'message' => 'OK' ),
            'headers'  => array( 'content-type' => 'application/json' ),
            'body'     => wp_json_encode(
                array(
                    'id'      => 'task9-smoke-completion',
                    'model'   => 'gemini-2.5-flash',
                    'choices' => array(
                        array(
                            'index'         => 0,
                            'message'       => array( 'role' => 'assistant', 'content' => 'Smoke answer [C1]: queue-backed knowledge is available.' ),
                            'finish_reason' => 'stop',
                        ),
                    ),
                    'usage' => array( 'prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2 ),
                )
            ),
        );
    }

    return false;
};

try {
    global $wpdb;

    $administrator_ids = get_users(
        array(
            'role'   => 'administrator',
            'number' => 1,
            'fields' => 'ids',
        )
    );
    $assert( ! empty( $administrator_ids ), 'Modern setup smoke requires an administrator user.' );
    wp_set_current_user( (int) $administrator_ids[0] );

    $server = rest_get_server();
    do_action( 'rest_api_init', $server );
    $routes = $server->get_routes();
    $assert( isset( $routes['/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources'] ), 'Knowledge source REST route is not registered.' );
    $assert( isset( $routes['/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE] ), 'Public chat REST route is not registered.' );

    $credential = $request(
        'PUT',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/providers/gemini_direct/credential',
        array( 'credential' => $fake_secret )
    );
    $assert( 200 === $credential->get_status() && true === ( $credential->get_data()['managed'] ?? false ), 'Gemini smoke credential could not be configured through the admin boundary.' );
    $credential_set = true;
    add_filter( 'pre_http_request', $fake_http, 10, 3 );

    $token = substr( md5( uniqid( '', true ) ), 0, 8 );
    $source_response = $request(
        'POST',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources',
        array(
            'source_type' => 'manual_text',
            'title'       => 'Task 9 modern setup smoke ' . $token,
            'config'      => array(
                'text'       => 'Modern publish flow smoke fact: queue-backed knowledge is available.',
                'language'   => 'en',
                'visibility' => 'public',
            ),
        )
    );
    $source_data = $source_response->get_data();
    $assert( 200 === $source_response->get_status() && is_array( $source_data['source'] ?? null ) && is_array( $source_data['job'] ?? null ), 'Manual source creation did not return source and job projections.' );
    $source_id = $source_data['source']['id'] ?? null;
    $source_job = $source_data['job'];
    $assert( is_int( $source_id ) && $source_id > 0, 'Manual source creation did not return a persisted source ID.' );
    $assert( 'sync.source' === ( $source_job['type'] ?? null ) && 'queued' === ( $source_job['status'] ?? null ), 'Manual source creation did not enqueue sync.source.' );
    $job_keys[] = $source_job['job_key'];

    $queued_jobs = $list_jobs();
    $observed_source_job = $find_job( $queued_jobs, $source_job['job_key'] );
    $assert( is_array( $observed_source_job ) && 'sync.source' === $observed_source_job['type'] && 'queued' === $observed_source_job['status'], 'Persisted source-sync job was not observable through the admin queue projection.' );

    do_action( WordPressJobCron::HOOK, $source_job['job_key'] );

    $completed_jobs = $list_jobs();
    $completed_source_job = $find_job( $completed_jobs, $source_job['job_key'] );
    $assert( is_array( $completed_source_job ) && 'succeeded' === $completed_source_job['status'], 'Queued sync.source work did not complete through the existing worker.' );

    $documents_response = $request( 'GET', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources/' . $source_id . '/documents' );
    $documents_data     = $documents_response->get_data();
    $assert( 200 === $documents_response->get_status() && 1 === ( $documents_data['total'] ?? 0 ) && ! empty( $documents_data['items'][0] ), 'Source synchronization did not persist a canonical document.' );
    $document_key = $documents_data['items'][0]['document_key'] ?? null;
    $assert( is_string( $document_key ) && '' !== $document_key, 'Canonical document projection omitted document_key.' );

    $child_job = null;
    foreach ( $completed_jobs as $job ) {
        if ( is_array( $job ) && 'index.document' === ( $job['type'] ?? null ) && 'succeeded' === ( $job['status'] ?? null ) ) {
            $payload = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT payload_json FROM %i WHERE job_key = %s',
                    ( new TableNames( $wpdb->prefix ) )->jobs(),
                    $job['job_key']
                )
            );
            if ( is_string( $payload ) && str_contains( $payload, $document_key ) ) {
                $child_job = $job;
                $job_keys[] = $job['job_key'];
                break;
            }
        }
    }
    $assert( is_array( $child_job ), 'Source synchronization did not produce a completed child index.document job.' );

    $connection        = new WpdbConnection( $wpdb );
    $tables            = new TableNames( $connection->prefix() );
    $document          = ( new WpdbDocumentRepository( $connection, $tables ) )->findByKey( $document_key );
    $assert( null !== $document && str_contains( $document->content, 'queue-backed knowledge' ), 'Canonical document content was not persisted.' );

    $chunks_response = $request( 'GET', '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/knowledge/sources/' . $source_id . '/documents/' . rawurlencode( $document_key ) . '/chunks' );
    $chunks_data     = $chunks_response->get_data();
    $assert( 200 === $chunks_response->get_status() && ( $chunks_data['total'] ?? 0 ) > 0, 'Document indexing did not persist chunk projections.' );
    $assert( str_contains( (string) ( $chunks_data['items'][0]['content'] ?? '' ), 'queue-backed knowledge' ), 'Chunk projection did not preserve the manual source content.' );

    $vector_rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT vector_json, metadata_json FROM %i WHERE collection_key = %s',
            $tables->vectors(),
            'wp-rag-default'
        ),
        ARRAY_A
    );
    $matching_vectors = array_values(
        array_filter(
            is_array( $vector_rows ) ? $vector_rows : array(),
            static function ( mixed $row ) use ( $source_id ): bool {
                return is_array( $row ) && str_contains( (string) ( $row['metadata_json'] ?? '' ), '"source_id":' . $source_id );
            }
        )
    );
    $assert( ! empty( $matching_vectors ), 'Document indexing did not persist a local vector.' );
    $vector = json_decode( (string) $matching_vectors[0]['vector_json'], true );
    $assert( is_array( $vector ) && 3072 === count( $vector ), 'Persisted vector dimensions do not match the Gemini indexing profile.' );

    $bot_response = $request(
        'POST',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/bots',
        array(
            'name'        => 'Task 9 Gemini smoke bot',
            'enabled'     => true,
            'provider_id' => 'gemini_direct',
            'model_id'    => 'gemini-2.5-flash',
        )
    );
    $bot_data = $bot_response->get_data();
    $bot_id   = $bot_data['bot']['id'] ?? null;
    $assert( 200 === $bot_response->get_status() && is_string( $bot_id ) && 32 === strlen( $bot_id ), 'Gemini bot creation did not persist a canonical bot.' );

    $binding_response = $request(
        'PUT',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/bots/' . $bot_id . '/retrieval',
        array( 'source_id' => $source_id )
    );
    $binding_data = $binding_response->get_data();
    $assert( 200 === $binding_response->get_status() && true === ( $binding_data['retrieval']['configured'] ?? false ) && true === ( $binding_data['retrieval']['collection_ready'] ?? false ), 'Gemini bot retrieval binding did not persist against the indexed source.' );

    $playground = $request(
        'POST',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/debug/playground',
        array(
            'bot_id'        => $bot_id,
            'source_id'     => $source_id,
            'collection_id' => 'wp-rag-default',
            'question'      => 'What does the smoke source say?',
        )
    );
    $playground_data = $playground->get_data();
    $assert( 200 === $playground->get_status() && true === ( $playground_data['ok'] ?? false ) && ! empty( $playground_data['citations'] ), 'Playground did not execute the production grounded chat path.' );

    $widget_asset_handle = 'wp-rag-ai-chatbot-widget';
    $surfaces            = array(
        'floating'   => '[wp_rag_ai_chatbot bot="' . esc_attr( $bot_id ) . '"]',
        'embedded'   => '[wp_rag_ai_chatbot_embed bot="' . esc_attr( $bot_id ) . '"]',
        'fullscreen' => '[wp_rag_ai_chatbot_fullscreen bot="' . esc_attr( $bot_id ) . '"]',
    );
    foreach ( $surfaces as $surface => $shortcode ) {
        $html = do_shortcode( $shortcode );
        $assert( str_contains( $html, 'class="wp-rag-ai-chatbot-widget"' ) && str_contains( $html, 'data-wp-rag-ai-chatbot-bot="' . esc_attr( $bot_id ) . '"' ) && str_contains( $html, 'data-wp-rag-ai-chatbot-surface="' . $surface . '"' ), 'Public ' . $surface . ' shortcode did not render its shared widget mount.' );
    }
    $block_html = do_blocks( '<!-- wp:wp-rag-ai-chatbot/chatbot ' . wp_json_encode( array( 'bot' => $bot_id ) ) . ' /-->' );
    $assert( str_contains( $block_html, 'data-wp-rag-ai-chatbot-surface="embedded"' ), 'Dynamic Gutenberg chatbot block did not render the embedded widget surface.' );
    $assert( wp_script_is( $widget_asset_handle, 'enqueued' ) && wp_style_is( $widget_asset_handle, 'enqueued' ), 'Public widget assets were not enqueued for a valid published bot.' );

    $inline_before = wp_scripts()->get_data( $widget_asset_handle, 'before' );
    $inline_script = is_array( $inline_before ) ? implode( "\n", $inline_before ) : (string) $inline_before;
    $assert( str_contains( $inline_script, $bot_id ) && str_contains( $inline_script, 'wp-rag-ai-chatbot/v1' ), 'Public widget bootstrap omitted the bot identity or REST base.' );
    foreach ( array( $fake_secret, 'wp_rag_ai_gemini_api_key', 'ciphertext', 'authorization', 'api_key', 'ABSPATH', 'WP_CONTENT_DIR', '/var/www', '/srv/', '/home/' ) as $forbidden ) {
        $assert( ! str_contains( strtolower( $inline_script ), strtolower( $forbidden ) ), 'Public widget bootstrap exposed forbidden credential or server-path data: ' . $forbidden );
    }

    $_SERVER['REMOTE_ADDR'] = '198.51.100.91';
    $public_chat = $request(
        'POST',
        '/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE,
        array(
            'bot_id'   => $bot_id,
            'question' => 'What does the smoke source say?',
        )
    );
    $public_data = $public_chat->get_data();
    $assert( 200 === $public_chat->get_status() && true === ( $public_data['ok'] ?? false ) && ! empty( $public_data['citations'] ), 'Public chat did not execute the published production chat path.' );
    $public_serialized = wp_json_encode( $public_data );
    $assert( is_string( $public_serialized ) && ! str_contains( $public_serialized, $fake_secret ), 'Public chat response exposed the smoke credential.' );

    $disabled_response = $request(
        'POST',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/bots',
        array(
            'name'        => 'Task 9 disabled smoke bot',
            'enabled'     => false,
            'provider_id' => 'gemini_direct',
            'model_id'    => 'gemini-2.5-flash',
        )
    );
    $disabled_bot_id = $disabled_response->get_data()['bot']['id'] ?? null;
    $assert( is_string( $disabled_bot_id ), 'Disabled Gemini smoke bot could not be created.' );

    $_SERVER['REMOTE_ADDR'] = '198.51.100.92';
    $disabled_chat = $request(
        'POST',
        '/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE,
        array( 'bot_id' => $disabled_bot_id, 'question' => 'Disabled bots must fail closed.' )
    );
    $assert( 'chat_unavailable' === ( $disabled_chat->get_data()['error']['code'] ?? null ), 'Disabled public bot did not fail closed.' );
    $assert( '' === do_shortcode( '[wp_rag_ai_chatbot bot="' . esc_attr( $disabled_bot_id ) . '"]' ), 'Disabled public widget did not fail closed.' );

    $unbound_response = $request(
        'POST',
        '/' . AdminRestBootstrap::REST_NAMESPACE . '/admin/bots',
        array(
            'name'        => 'Task 9 unbound smoke bot',
            'enabled'     => true,
            'provider_id' => 'gemini_direct',
            'model_id'    => 'gemini-2.5-flash',
        )
    );
    $unbound_bot_id = $unbound_response->get_data()['bot']['id'] ?? null;
    $assert( is_string( $unbound_bot_id ), 'Unbound Gemini smoke bot could not be created.' );

    $_SERVER['REMOTE_ADDR'] = '198.51.100.93';
    $unbound_chat = $request(
        'POST',
        '/' . PublicChatRestBootstrap::REST_NAMESPACE . PublicChatRestBootstrap::REST_ROUTE,
        array( 'bot_id' => $unbound_bot_id, 'question' => 'Unbound bots must fail closed.' )
    );
    $assert( 'chat_unavailable' === ( $unbound_chat->get_data()['error']['code'] ?? null ), 'Enabled but unbound public bot did not fail closed.' );
    $assert( '' === do_shortcode( '[wp_rag_ai_chatbot bot="' . esc_attr( $unbound_bot_id ) . '"]' ), 'Unbound public widget did not fail closed.' );

    remove_filter( 'pre_http_request', $fake_http, 10 );
    $cleanup();
    fwrite( STDOUT, "WordPress modern knowledge publish-flow smoke passed.\n" );
} catch ( Throwable $exception ) {
    remove_filter( 'pre_http_request', $fake_http, 10 );
    $fail( 'Modern knowledge publish-flow smoke threw: ' . $exception->getMessage() );
}
