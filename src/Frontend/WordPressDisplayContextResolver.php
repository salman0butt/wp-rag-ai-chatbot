<?php
/**
 * Public-safe WordPress display context projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

/**
 * Resolves the minimum trusted WordPress facts needed for display-rule evaluation.
 */
final class WordPressDisplayContextResolver {
	private const MAX_ROLE_MATCHES = 16;

	/**
	 * Resolve browser-safe presentation facts for the current request.
	 *
	 * @return array{
	 *     path: string,
	 *     isAuthenticated: bool,
	 *     postType: ?string,
	 *     roleMatches: list<string>
	 * }
	 */
	public function resolve(): array {
		$is_authenticated = is_user_logged_in();
		$post_type        = $this->normalize_token( get_post_type() );
		$roles            = array();

		if ( $is_authenticated ) {
			$user = wp_get_current_user();

			foreach ( $user->roles as $role ) {
				$normalized = $this->normalize_token( $role );
				if ( null === $normalized || in_array( $normalized, $roles, true ) ) {
					continue;
				}

				$roles[] = $normalized;
				if ( self::MAX_ROLE_MATCHES === count( $roles ) ) {
					break;
				}
			}
		}

		return array(
			'path'            => $this->resolve_path(),
			'isAuthenticated' => $is_authenticated,
			'postType'        => $post_type,
			'roleMatches'     => $roles,
		);
	}

	/** Resolve and normalize the current request path without query or fragment data. */
	private function resolve_path(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the URL path is extracted and normalized below; query/fragment data is discarded.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		$path = strtolower( $path );

		return str_starts_with( $path, '/' ) ? $path : '/' . $path;
	}

	/**
	 * Normalize a bounded WordPress presentation token.
	 *
	 * @param mixed $value Candidate token.
	 */
	private function normalize_token( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = strtolower( trim( $value ) );
		if ( '' === $value || 1 !== preg_match( '/^[a-z0-9_-]{1,64}$/', $value ) ) {
			return null;
		}

		return $value;
	}
}
