<?php
/**
 * The portal's document shell — its own <!DOCTYPE html> through <body>,
 * deliberately not calling wp_head()/wp_footer(). That means no theme
 * CSS, no unrelated plugin's frontend scripts, and no WordPress admin
 * bar competing with the brand styling; it also means the portal owns
 * responsibility for its own security headers/escaping, same as any
 * other server-rendered page in this plugin.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Portal_Views {

	public const SECTIONS = [
		'contacts'     => 'Contacts',
		'interactions' => 'Interactions',
		'transactions' => 'Transactions',
		'tasks'        => 'Tasks',
	];

	/**
	 * Outputs everything from <!DOCTYPE html> through the opening of
	 * <main>, including the site header/nav and any notice banner.
	 */
	public static function open( string $active_section ): void {
		$user = wp_get_current_user();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( self::page_title( $active_section ) ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo esc_url( CORNERSTONE_CRM_URL . 'includes/modules/portal/assets/portal.css?ver=' . CORNERSTONE_CRM_VERSION ); ?>">
</head>
<body class="cornerstone-portal">
<div class="cs-portal">
	<header class="cs-header">
		<div class="cs-header__bar">
			<div class="cs-brand">
				<span class="cs-brand__name"><?php esc_html_e( 'Aaron Atkinson Realty', 'cornerstone-crm' ); ?></span>
				<span class="cs-brand__product"><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></span>
			</div>
			<div class="cs-header__user">
				<span><?php echo esc_html( $user->display_name ); ?></span>
				<?php if ( Cornerstone_Roles::can_manage_all() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS ) ); ?>"><?php esc_html_e( 'wp-admin ↗', 'cornerstone-crm' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Main site', 'cornerstone-crm' ); ?></a>
				<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'cornerstone-crm' ); ?></a>
			</div>
		</div>
		<nav class="cs-nav">
			<div class="cs-nav__bar">
				<?php foreach ( self::SECTIONS as $slug => $label ) : ?>
					<a href="<?php echo esc_url( Cornerstone_Portal_Router::url( $slug ) ); ?>" class="cs-nav__link<?php echo $slug === $active_section ? ' is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
		</nav>
	</header>
	<main class="cs-main">
		<?php self::render_notice(); ?>
		<?php
	}

	/**
	 * Closes what open() started.
	 */
	public static function close(): void {
		?>
	</main>
</div>
</body>
</html>
		<?php
	}

	public static function render_forbidden(): void {
		self::open( '' );
		?>
		<div class="cs-forbidden cs-card">
			<h1><?php esc_html_e( "You don't have access", 'cornerstone-crm' ); ?></h1>
			<p><?php esc_html_e( 'Your account is not set up with Cornerstone CRM access. Ask your broker to grant it.', 'cornerstone-crm' ); ?></p>
			<p><a class="cs-btn cs-btn--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the main site', 'cornerstone-crm' ); ?></a></p>
		</div>
		<?php
		self::close();
	}

	private static function page_title( string $section ): string {
		$label = self::SECTIONS[ $section ] ?? __( 'Portal', 'cornerstone-crm' );
		return sprintf( '%s — Cornerstone CRM', $label );
	}

	/**
	 * Small fixed vocabulary, same convention as the wp-admin notices —
	 * only ever fed a sanitize_key()'d value read back from our own
	 * redirect URLs.
	 */
	public static function notice_text( string $key ): string {
		$messages = [
			'saved'     => __( 'Saved.', 'cornerstone-crm' ),
			'deleted'   => __( 'Deleted.', 'cornerstone-crm' ),
			'error'     => __( 'Something went wrong. Please try again.', 'cornerstone-crm' ),
			'not_found' => __( 'Record not found.', 'cornerstone-crm' ),
		];
		return $messages[ $key ] ?? '';
	}

	private static function render_notice(): void {
		$notice = sanitize_key( $_GET['notice'] ?? '' );
		$text   = self::notice_text( $notice );
		if ( '' === $text ) {
			return;
		}
		$class = in_array( $notice, [ 'error', 'not_found' ], true ) ? 'cs-notice--error' : 'cs-notice--success';
		printf( '<div class="cs-notice %1$s">%2$s</div>', esc_attr( $class ), esc_html( $text ) );
	}

	/**
	 * Shared "Title Case With Spaces" formatter used across every
	 * section's enum-like columns (role_tag, pipeline_status, etc.).
	 */
	public static function label( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	/**
	 * Hooked to cornerstone_crm_admin_nav_after (see
	 * Cornerstone_Admin::nav_html()) so the wp-admin CRM screens link out
	 * to the branded portal without core having to know this module
	 * exists.
	 */
	public static function print_admin_nav_link( string $active_admin_slug ): void {
		$section_map = [
			Cornerstone_Admin::SLUG_CONTACTS     => 'contacts',
			Cornerstone_Admin::SLUG_INTERACTIONS => 'interactions',
			Cornerstone_Admin::SLUG_TRANSACTIONS => 'transactions',
			Cornerstone_Admin::SLUG_TASKS        => 'tasks',
		];
		$section = $section_map[ $active_admin_slug ] ?? 'contacts';

		printf(
			'<p class="description" style="margin:0.75em 0 0;"><a href="%1$s">%2$s</a></p>',
			esc_url( Cornerstone_Portal_Router::url( $section ) ),
			esc_html__( 'Open the branded portal ↗', 'cornerstone-crm' )
		);
	}

	/**
	 * Simple prev/next pagination, same shape as the wp-admin version but
	 * linking to /crm/{section}/ URLs.
	 */
	public static function pagination( int $page, int $total, int $per_page, string $section, array $extra_args = [] ): void {
		$last_page = max( 1, (int) ceil( $total / $per_page ) );
		if ( $last_page <= 1 ) {
			return;
		}
		echo '<div class="cs-pagination">';
		if ( $page > 1 ) {
			$prev_url = add_query_arg( array_merge( $extra_args, [ 'paged' => $page - 1 ] ), Cornerstone_Portal_Router::url( $section ) );
			printf( '<a class="cs-btn cs-btn--secondary cs-btn--small" href="%s">&laquo; %s</a>', esc_url( $prev_url ), esc_html__( 'Previous', 'cornerstone-crm' ) );
		}
		printf( '<span>%s</span>', esc_html( sprintf(
			/* translators: 1: current page 2: total pages */
			__( 'Page %1$d of %2$d', 'cornerstone-crm' ),
			$page,
			$last_page
		) ) );
		if ( $page < $last_page ) {
			$next_url = add_query_arg( array_merge( $extra_args, [ 'paged' => $page + 1 ] ), Cornerstone_Portal_Router::url( $section ) );
			printf( '<a class="cs-btn cs-btn--secondary cs-btn--small" href="%s">%s &raquo;</a>', esc_url( $next_url ), esc_html__( 'Next', 'cornerstone-crm' ) );
		}
		echo '</div>';
	}
}
