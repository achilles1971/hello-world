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
}
