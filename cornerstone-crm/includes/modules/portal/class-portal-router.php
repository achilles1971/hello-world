<?php
/**
 * URL routing for the standalone branded portal at /crm/. Uses
 * WordPress's rewrite API rather than a query-string-only URL, so it
 * reads as a real page rather than "the WordPress home page with
 * parameters." Self-flushes its rewrite rules once after being added to
 * an already-active site — this module can land on a site where the
 * plugin was activated long before it existed, so it can't rely solely
 * on the one-time flush_rewrite_rules() call in the plugin's own
 * activation hook.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Portal_Router {

	public const BASE_SLUG = 'crm';

	private const REWRITE_VERSION_OPTION = 'cornerstone_portal_rewrite_version';
	/** Bump this if the rewrite rules below ever change shape. */
	private const REWRITE_VERSION = '1';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_rewrites' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'dispatch' ] );
	}

	public static function register_rewrites(): void {
		add_rewrite_tag( '%cornerstone_portal%', '1' );
		add_rewrite_tag( '%cornerstone_portal_section%', '([^&]+)' );
		add_rewrite_tag( '%cornerstone_portal_view%', '([^&]+)' );
		add_rewrite_tag( '%cornerstone_portal_id%', '([0-9]+)' );

		$base = self::BASE_SLUG;

		add_rewrite_rule(
			'^' . $base . '/([^/]+)/(new)/?$',
			'index.php?cornerstone_portal=1&cornerstone_portal_section=$matches[1]&cornerstone_portal_view=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/([^/]+)/(edit)/([0-9]+)/?$',
			'index.php?cornerstone_portal=1&cornerstone_portal_section=$matches[1]&cornerstone_portal_view=$matches[2]&cornerstone_portal_id=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/([^/]+)/?$',
			'index.php?cornerstone_portal=1&cornerstone_portal_section=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/?$',
			'index.php?cornerstone_portal=1&cornerstone_portal_section=contacts',
			'top'
		);

		self::maybe_flush();
	}

	private static function maybe_flush(): void {
		if ( get_option( self::REWRITE_VERSION_OPTION ) !== self::REWRITE_VERSION ) {
			flush_rewrite_rules();
			update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION );
		}
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = 'cornerstone_portal';
		$vars[] = 'cornerstone_portal_section';
		$vars[] = 'cornerstone_portal_view';
		$vars[] = 'cornerstone_portal_id';
		return $vars;
	}

	/**
	 * Intercepts the request entirely — no theme template ever loads for
	 * a portal URL. Auth gate lives here: anonymous visitors bounce to
	 * WordPress's own login screen (no custom login form to secure and
	 * maintain), and logged-in users without CRM access get a branded
	 * 403 rather than silently landing on a broken page.
	 */
	public static function dispatch(): void {
		if ( '1' !== (string) get_query_var( 'cornerstone_portal' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::current_url() ) );
			exit;
		}

		if ( ! Cornerstone_Roles::can_access() ) {
			status_header( 403 );
			Cornerstone_Portal_Views::render_forbidden();
			exit;
		}

		$section = sanitize_key( (string) get_query_var( 'cornerstone_portal_section' ) );
		$view    = sanitize_key( (string) get_query_var( 'cornerstone_portal_view' ) );
		$id      = absint( get_query_var( 'cornerstone_portal_id' ) );

		Cornerstone_Portal_Controller::render( $section, $view, $id );
		exit;
	}

	/**
	 * Builds a /crm/... URL. This is the only place path shape is
	 * decided, so every link and redirect in the module stays in sync
	 * with the rewrite rules above.
	 */
	public static function url( string $section, string $view = '', int $id = 0 ): string {
		$path = home_url( '/' . self::BASE_SLUG . '/' . rawurlencode( $section ) . '/' );

		if ( 'new' === $view ) {
			$path .= 'new/';
		} elseif ( 'edit' === $view && $id > 0 ) {
			$path .= 'edit/' . $id . '/';
		}

		return $path;
	}

	private static function current_url(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' . self::BASE_SLUG . '/';
		return home_url( $path );
	}
}
