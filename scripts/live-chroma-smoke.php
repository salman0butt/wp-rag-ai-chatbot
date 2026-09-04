<?php
/**
 * Opt-in live Chroma health smoke executed inside WP-CLI.
 *
 * This file is intentionally never run by normal CI. The shell wrapper validates
 * explicit opt-in and required scope before invoking it.
 *
 * @package WpRagAiChatbot
 */
declare(strict_types=1);

use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\WordPressHttpTransport;
use WpRagAiChatbot\VectorStore\Chroma\ChromaConfig;
use WpRagAiChatbot\VectorStore\Chroma\ChromaVectorStore;

$endpoint = getenv( 'CHROMA_ENDPOINT' );
$tenant   = getenv( 'CHROMA_TENANT' );
$database = getenv( 'CHROMA_DATABASE' );
$token    = getenv( 'CHROMA_TOKEN' );

if ( ! is_string( $endpoint ) || '' === trim( $endpoint ) ) {
	fwrite( STDERR, "Live Chroma smoke requires CHROMA_ENDPOINT.\n" );
	exit( 2 );
}

if ( ! is_string( $tenant ) || '' === trim( $tenant ) ) {
	fwrite( STDERR, "Live Chroma smoke requires CHROMA_TENANT.\n" );
	exit( 2 );
}

if ( ! is_string( $database ) || '' === trim( $database ) ) {
	fwrite( STDERR, "Live Chroma smoke requires CHROMA_DATABASE.\n" );
	exit( 2 );
}

$token = is_string( $token ) && '' !== trim( $token ) ? $token : null;
$profile = new VectorIndexProfile(
	new EmbeddingProfile( 'live-chroma', 'health-check', 1, NormalizationMode::NONE ),
	DistanceMetric::COSINE
);
$store = new ChromaVectorStore(
	new ChromaConfig( $endpoint, $tenant, $database, $token ),
	$profile,
	new WordPressHttpTransport()
);
$health = $store->health();

if ( ! $health->healthy ) {
	fwrite( STDERR, "Live Chroma health check failed.\n" );
	exit( 1 );
}

fwrite( STDOUT, "Live Chroma health check passed.\n" );
