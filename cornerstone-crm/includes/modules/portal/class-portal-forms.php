<?php
/**
 * Form (write) handlers for the portal. Same shape as
 * Cornerstone_Admin's admin-post handlers — nonce + capability check,
 * sanitize, call the shared data layer, redirect with a notice — just
 * under portal-specific action names and redirecting back to /crm/...
 * URLs instead of admin.php?page=... ones. No business logic lives
 * here; every write goes through the same Cornerstone_Contacts /
 * Interactions / Transactions / Tasks classes the wp-admin UI uses.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Portal_Forms {

	public static function init(): void {
		add_action( 'admin_post_cornerstone_portal_save_contact', [ __CLASS__, 'handle_save_contact' ] );
		add_action( 'admin_post_cornerstone_portal_delete_contact', [ __CLASS__, 'handle_delete_contact' ] );
		add_action( 'admin_post_cornerstone_portal_save_interaction', [ __CLASS__, 'handle_save_interaction' ] );
		add_action( 'admin_post_cornerstone_portal_delete_interaction', [ __CLASS__, 'handle_delete_interaction' ] );
		add_action( 'admin_post_cornerstone_portal_save_transaction', [ __CLASS__, 'handle_save_transaction' ] );
		add_action( 'admin_post_cornerstone_portal_delete_transaction', [ __CLASS__, 'handle_delete_transaction' ] );
		add_action( 'admin_post_cornerstone_portal_save_task', [ __CLASS__, 'handle_save_task' ] );
		add_action( 'admin_post_cornerstone_portal_delete_task', [ __CLASS__, 'handle_delete_task' ] );
	}

	private static function require_access(): void {
		if ( ! Cornerstone_Roles::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access the CRM.', 'cornerstone-crm' ), 403 );
		}
	}

	private static function scope(): array {
		return [ get_current_user_id(), Cornerstone_Roles::can_manage_all() ];
	}

	private static function redirect( string $section, string $notice ): void {
		wp_safe_redirect( add_query_arg( 'notice', $notice, Cornerstone_Portal_Router::url( $section ) ) );
		exit;
	}

	// -----------------------------------------------------------------
	// Contacts
	// -----------------------------------------------------------------

	public static function handle_save_contact(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_save_contact' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$data = [
			'first_name'      => wp_unslash( $_POST['first_name'] ?? '' ),
			'last_name'       => wp_unslash( $_POST['last_name'] ?? '' ),
			'phone'           => wp_unslash( $_POST['phone'] ?? '' ),
			'email'           => wp_unslash( $_POST['email'] ?? '' ),
			'role_tag'        => wp_unslash( $_POST['role_tag'] ?? '' ),
			'pipeline_status' => wp_unslash( $_POST['pipeline_status'] ?? '' ),
			'notes'           => wp_unslash( $_POST['notes'] ?? '' ),
		];

		$result = $id
			? Cornerstone_Contacts::update( $id, $data, $user_id, $can_manage_all )
			: Cornerstone_Contacts::create( $data, $user_id );

		self::redirect( 'contacts', is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public static function handle_delete_contact(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_delete_contact' );

		[ $user_id, $can_manage_all ] = self::scope();
		$result = Cornerstone_Contacts::soft_delete( absint( $_POST['id'] ?? 0 ), $user_id, $can_manage_all );

		self::redirect( 'contacts', is_wp_error( $result ) ? 'error' : 'deleted' );
	}

	// -----------------------------------------------------------------
	// Interactions
	// -----------------------------------------------------------------

	public static function handle_save_interaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_save_interaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$data = [
			'contact_id'  => absint( $_POST['contact_id'] ?? 0 ),
			'type'        => wp_unslash( $_POST['type'] ?? '' ),
			'note'        => wp_unslash( $_POST['note'] ?? '' ),
			'occurred_at' => wp_unslash( $_POST['occurred_at'] ?? '' ),
		];

		$result = $id
			? Cornerstone_Interactions::update( $id, $data, $user_id, $can_manage_all )
			: Cornerstone_Interactions::create( $data, $user_id, $can_manage_all );

		self::redirect( 'interactions', is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public static function handle_delete_interaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_delete_interaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$result = Cornerstone_Interactions::soft_delete( absint( $_POST['id'] ?? 0 ), $user_id, $can_manage_all );

		self::redirect( 'interactions', is_wp_error( $result ) ? 'error' : 'deleted' );
	}

	// -----------------------------------------------------------------
	// Transactions
	// -----------------------------------------------------------------

	public static function handle_save_transaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_save_transaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$data = [
			'contact_id'       => absint( $_POST['contact_id'] ?? 0 ),
			'property_address' => wp_unslash( $_POST['property_address'] ?? '' ),
			'transaction_type' => wp_unslash( $_POST['transaction_type'] ?? '' ),
			'status'           => wp_unslash( $_POST['status'] ?? '' ),
			'price'            => wp_unslash( $_POST['price'] ?? '' ),
			'key_dates'        => self::key_dates_from_post(),
		];

		$result = $id
			? Cornerstone_Transactions::update( $id, $data, $user_id, $can_manage_all )
			: Cornerstone_Transactions::create( $data, $user_id, $can_manage_all );

		self::redirect( 'transactions', is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public static function handle_delete_transaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_delete_transaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$result = Cornerstone_Transactions::soft_delete( absint( $_POST['id'] ?? 0 ), $user_id, $can_manage_all );

		self::redirect( 'transactions', is_wp_error( $result ) ? 'error' : 'deleted' );
	}

	private static function key_dates_from_post(): array {
		$labels = array_map( 'wp_unslash', (array) ( $_POST['key_date_labels'] ?? [] ) );
		$values = array_map( 'wp_unslash', (array) ( $_POST['key_date_values'] ?? [] ) );

		$key_dates = [];
		foreach ( $labels as $index => $label ) {
			$label = sanitize_text_field( (string) $label );
			$date  = sanitize_text_field( (string) ( $values[ $index ] ?? '' ) );
			if ( '' === $label || '' === $date ) {
				continue;
			}
			$key_dates[ $label ] = $date;
		}
		return $key_dates;
	}

	// -----------------------------------------------------------------
	// Tasks
	// -----------------------------------------------------------------

	public static function handle_save_task(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_save_task' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$data = [
			'contact_id'  => absint( $_POST['contact_id'] ?? 0 ),
			'description' => wp_unslash( $_POST['description'] ?? '' ),
			'due_date'    => wp_unslash( $_POST['due_date'] ?? '' ),
			'completed'   => isset( $_POST['completed'] ) ? 1 : 0,
		];

		$result = $id
			? Cornerstone_Tasks::update( $id, $data, $user_id, $can_manage_all )
			: Cornerstone_Tasks::create( $data, $user_id, $can_manage_all );

		self::redirect( 'tasks', is_wp_error( $result ) ? 'error' : 'saved' );
	}

	public static function handle_delete_task(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_portal_delete_task' );

		[ $user_id, $can_manage_all ] = self::scope();
		$result = Cornerstone_Tasks::soft_delete( absint( $_POST['id'] ?? 0 ), $user_id, $can_manage_all );

		self::redirect( 'tasks', is_wp_error( $result ) ? 'error' : 'deleted' );
	}
}
