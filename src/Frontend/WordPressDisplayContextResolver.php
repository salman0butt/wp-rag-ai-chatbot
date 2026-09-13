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
	 *     roleMatches: list<string>,
	 *     wooArea: ?string,
	 *     siteLocale: string,
	 *     siteDirection: 'ltr'|'rtl',
	 *     siteWeekday: int,
	 *     siteMinuteOfDay: int
	 * }
	 */
	public function resolve(): array {
		$is_authenticated = is_user_logged_in();
		$post_type        = $this->normalize_token( get_post_type() );
		$roles            = array();
		$site_datetime    = current_datetime();

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
			'wooArea'         => $this->resolve_woo_area( $post_type ),
			'siteLocale'      => $this->normalize_locale( get_locale() ),
			'siteDirection'   => is_rtl() ? 'rtl' : 'ltr',
			'siteWeekday'     => (int) $site_datetime->format( 'w' ),
			'siteMinuteOfDay' => ( (int) $site_datetime->format( 'G' ) * 60 ) + (int) $site_datetime->format( 'i' ),
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

	/** Resolve a finite WooCommerce presentation-area token without requiring WooCommerce. */
	private function resolve_woo_area( ?string $post_type ): ?string {
		if ( 'product' === $post_type ) {
			return 'product';
		}

		$checks = array(
			'is_cart'         => 'cart',
			'is_checkout'     => 'checkout',
			'is_account_page' => 'account',
			'is_shop'         => 'shop',
		);

		foreach ( $checks as $predicate => $area ) {
			if ( function_exists( $predicate ) && true === $predicate() ) {
				return $area;
			}
		}

		return null;
	}

	/** Normalize WordPress locale syntax to the browser-safe M15 locale token. */
	private function normalize_locale( string $locale ): string {
		$locale = strtolower( str_replace( '_', '-', trim( $locale ) ) );
		if ( 1 === preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale ) && strlen( $locale ) <= 35 ) {
			return $locale;
		}

		return 'en';
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
