<?php
/**
 * Role and capability system for Cornerstone CRM.
 *
 * Deliberately built on WordPress's native roles/capabilities API rather
 * than a bespoke permissions table. Two custom roles are added:
 *
 *   - cornerstone_broker : full visibility, sees every agent's records.
 *   - cornerstone_agent  : sees and manages only their own records.
 *
 * Two custom capabilities gate everything:
 *
 *   - cornerstone_crm_access     : minimum requirement to open the CRM
 *                                  admin screens or call any REST route.
 *                                  Granted to both roles and to
 *                                  administrators.
 *   - cornerstone_crm_manage_all : when present, scoping is skipped and
 *                                  the user may read/write any record
 *                                  regardless of who owns it. Granted to
 *                                  the broker role and to administrators.
 *
 * Administrators are granted both capabilities on activation so the
 * existing site owner (currently an Administrator, not yet assigned a
 * CRM role) has working access without a manual role change.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Roles {

	public const CAP_ACCESS     = 'cornerstone_crm_access';
	public const CAP_MANAGE_ALL = 'cornerstone_crm_manage_all';

	public const ROLE_BROKER = 'cornerstone_broker';
	public const ROLE_AGENT  = 'cornerstone_agent';

	/**
	 * Creates the custom roles and grants capabilities. Safe to call
	 * repeatedly (add_role/add_cap are both idempotent no-ops if already
	 * present).
	 */
	public static function register(): void {
		add_role(
			self::ROLE_BROKER,
			__( 'Broker', 'cornerstone-crm' ),
			[
				'read'                    => true,
				self::CAP_ACCESS          => true,
				self::CAP_MANAGE_ALL      => true,
			]
		);

		add_role(
			self::ROLE_AGENT,
			__( 'Agent', 'cornerstone-crm' ),
			[
				'read'           => true,
				self::CAP_ACCESS => true,
			]
		);

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_ACCESS );
			$administrator->add_cap( self::CAP_MANAGE_ALL );
		}
	}

	/**
	 * Defensive self-heal, run on every wp-admin request (see admin_init
	 * hook in Cornerstone_Admin::init()). If a WordPress Administrator
	 * (core 'manage_options' capability) is missing either Cornerstone
	 * capability, grant it immediately rather than requiring a
	 * deactivate/reactivate cycle.
	 *
	 * This exists because the one-time grant in register() can fail to
	 * "stick" for reasons outside this plugin's control — most commonly a
	 * persistent object cache (e.g. SiteGround's SG Optimizer /
	 * Memcached/Redis) serving a stale copy of the roles option from the
	 * moment activation ran, or activation being interrupted before it
	 * reached this step. Cheap in the common case: the two
	 * current_user_can() checks short-circuit to a no-op once the
	 * capability is actually present, so this only ever touches the
	 * database on the (rare, self-correcting) request where it's missing.
	 */
	public static function maybe_heal_administrator_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( current_user_can( self::CAP_ACCESS ) && current_user_can( self::CAP_MANAGE_ALL ) ) {
			return;
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_ACCESS );
			$administrator->add_cap( self::CAP_MANAGE_ALL );
		}
	}

	/**
	 * Whether the given (or current) user may open the CRM at all.
	 */
	public static function can_access( ?int $user_id = null ): bool {
		return self::user_has_cap( self::CAP_ACCESS, $user_id );
	}

	/**
	 * Whether the given (or current) user may see/manage every user's
	 * records (broker view) rather than only their own (agent view).
	 */
	public static function can_manage_all( ?int $user_id = null ): bool {
		return self::user_has_cap( self::CAP_MANAGE_ALL, $user_id );
	}

	private static function user_has_cap( string $cap, ?int $user_id ): bool {
		if ( null === $user_id ) {
			return current_user_can( $cap );
		}
		return user_can( $user_id, $cap );
	}

	/**
	 * Every WordPress user holding any Cornerstone capability (broker,
	 * agent, or an administrator with inherited access), ordered by
	 * display name. Shared by both the wp-admin UI and the portal for
	 * their "Agent" filter/owner lookups, so the two presentation layers
	 * can't drift on who counts as a CRM user.
	 *
	 * @return WP_User[]
	 */
	public static function crm_users(): array {
		return get_users( [
			'role__in' => [ self::ROLE_BROKER, self::ROLE_AGENT, 'administrator' ],
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		] );
	}
}
