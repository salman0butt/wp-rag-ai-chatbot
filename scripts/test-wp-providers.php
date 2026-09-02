<?php
/**
 * Real WordPress provider credential and bootstrap smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Providers\Credentials\AuthenticatedCredentialCipher;
use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSource;
use WpRagAiChatbot\Providers\Credentials\DirectProviderCredentialConfig;
use WpRagAiChatbot\Providers\Credentials\RuntimeCredentialSourceReader;
use WpRagAiChatbot\Providers\Credentials\RuntimeCryptoCapabilities;
use WpRagAiChatbot\Providers\Credentials\WordPressCredentialStore;
use WpRagAiChatbot\Providers\ProviderBootstrap;
use WpRagAiChatbot\Providers\ProviderIds;

global $wpdb;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$fake_secret = 'm03-real-wp-fake-openai-key-not-live';
$config      = DirectProviderCredentialConfig::for_provider( ProviderIds::OPENAI_DIRECT );
$cipher      = new AuthenticatedCredentialCipher( new RuntimeCryptoCapabilities() );
$store       = new WordPressCredentialStore( $cipher );
$reader      = new RuntimeCredentialSourceReader();
$resolver    = new CredentialResolver( $reader, $store );

$store->delete( ProviderIds::OPENAI_DIRECT );
$store->save( ProviderIds::OPENAI_DIRECT, $fake_secret );

$envelope = get_option( $config->option_name, null );
if ( ! is_string( $envelope ) || '' === $envelope ) {
	$fail( 'Encrypted OpenAI credential option was not stored.' );
}
if ( str_contains( $envelope, $fake_secret ) ) {
	$fail( 'Stored OpenAI credential contains plaintext.' );
}

$decoded = json_decode( $envelope, true );
if ( ! is_array( $decoded ) || 1 !== ( $decoded['v'] ?? null ) ) {
	$fail( 'Credential envelope version is invalid.' );
}
if ( ! in_array( $decoded['alg'] ?? null, array( 'xchacha20poly1305', 'aes-256-gcm' ), true ) ) {
	$fail( 'Credential envelope algorithm is not approved.' );
}
foreach ( array( 'nonce', 'ciphertext' ) as $field ) {
	if ( ! isset( $decoded[ $field ] ) || ! is_string( $decoded[ $field ] ) || false === base64_decode( $decoded[ $field ], true ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validating binary crypto envelope encoding.
		$fail( 'Credential envelope contains invalid base64 data.' );
	}
}
if ( 'aes-256-gcm' === $decoded['alg'] && ( ! isset( $decoded['tag'] ) || ! is_string( $decoded['tag'] ) || false === base64_decode( $decoded['tag'], true ) ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validating binary crypto envelope encoding.
	$fail( 'AES-GCM credential envelope tag is invalid.' );
}

$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		$config->option_name
	)
);
if ( ! is_string( $autoload ) || in_array( strtolower( $autoload ), array( 'yes', 'on', 'auto-on' ), true ) ) {
	$fail( 'Provider credential option is autoloaded.' );
}

$round_trip = null;
$loaded     = $store->load( ProviderIds::OPENAI_DIRECT );
if ( null === $loaded ) {
	$fail( 'Encrypted provider credential did not load.' );
}
$loaded->with_value(
	static function ( string $value ) use ( &$round_trip ): void {
		$round_trip = $value;
	}
);
if ( $fake_secret !== $round_trip ) {
	$fail( 'Credential plaintext did not round trip only through Secret::with_value().' );
}

putenv( 'OPENAI_API_KEY=m03-env-precedence-value' );
$resolved = $resolver->resolve( ProviderIds::OPENAI_DIRECT );
if ( null === $resolved || CredentialSource::ENVIRONMENT !== $resolved->source ) {
	$fail( 'Environment credential did not take precedence over stored option.' );
}
$environment_value = null;
$resolved->secret->with_value(
	static function ( string $value ) use ( &$environment_value ): void {
		$environment_value = $value;
	}
);
if ( 'm03-env-precedence-value' !== $environment_value ) {
	$fail( 'Environment credential value did not resolve exactly.' );
}

putenv( 'OPENAI_API_KEY=   ' );
$resolved = $resolver->resolve( ProviderIds::OPENAI_DIRECT );
if ( null === $resolved || CredentialSource::OPTION !== $resolved->source ) {
	$fail( 'Blank environment credential did not fall through to encrypted option.' );
}
putenv( 'OPENAI_API_KEY' );

ProviderBootstrap::register();
$registry = ProviderBootstrap::registry();
if ( array( ProviderIds::OPENAI_DIRECT, ProviderIds::OPENROUTER_DIRECT, ProviderIds::WORDPRESS_AI_CLIENT ) !== $registry->ids() ) {
	$fail( 'Provider bootstrap registry does not contain the expected stable IDs.' );
}

$core_available = function_exists( 'wp_ai_client_prompt' )
	&& ( ! function_exists( 'wp_supports_ai' ) || wp_supports_ai() );
if ( $core_available !== $registry->generation( ProviderIds::WORDPRESS_AI_CLIENT )->available() ) {
	$fail( 'WordPress AI Client feature detection does not match the runtime.' );
}

$descriptors = ProviderBootstrap::configuration()->all();
$serialized  = wp_json_encode( $descriptors );
if ( ! is_string( $serialized ) ) {
	$fail( 'Provider descriptors could not be serialized.' );
}
foreach ( array( $fake_secret, 'm03-env-precedence-value', 'ciphertext', 'authorization', 'kdf', 'salt' ) as $forbidden ) {
	if ( str_contains( strtolower( $serialized ), strtolower( $forbidden ) ) ) {
		$fail( 'Provider descriptor serialization contains forbidden secret-bearing data.' );
	}
}

$store->delete( ProviderIds::OPENAI_DIRECT );
if ( false !== get_option( $config->option_name, false ) ) {
	$fail( 'Provider credential deletion did not remove the option.' );
}

fwrite( STDOUT, "Provider WordPress smoke passed.\n" );
