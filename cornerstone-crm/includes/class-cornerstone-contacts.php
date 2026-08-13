<?php
/**
 * Data layer for the contacts table. Every method takes the acting user's
 * ID and manage-all flag explicitly (rather than reading current_user_can()
 * internally) so it can be unit-tested and called safely from both the
 * REST controllers and the admin screens with the same scoping guarantees.
 *
 * All queries are parameterized via $wpdb->prepare(); nothing here ever
 * concatenates raw request data into SQL.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Contacts {

	/**
	 * Sanitizes and inserts a new contact owned by $user_id.
	 *
	 * @return int|WP_Error New contact ID, or WP_Error on validation failure.
	 */
	public static function create( array $data, int $user_id ): int|WP_Error {
		global $wpdb;

		$first_name = sanitize_text_field( $data['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $data['last_name'] ?? '' );

		if ( '' === $first_name && '' === $last_name ) {
			return new WP_Error( 'cornerstone_invalid_contact', __( 'A contact needs at least a first or last name.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$now = current_time( 'mysql' );

		$row = [
			'user_id'         => $user_id,
			'first_name'      => $first_name,
			'last_name'       => $last_name,
			'phone'           => Cornerstone_Validate::phone( $data['phone'] ?? '' ),
			'email'           => Cornerstone_Validate::email( $data['email'] ?? '' ),
			'role_tag'        => Cornerstone_Validate::enum( sanitize_key( $data['role_tag'] ?? '' ), Cornerstone_Validate::ROLE_TAGS, 'sphere' ),
			'pipeline_status' => Cornerstone_Validate::enum( sanitize_key( $data['pipeline_status'] ?? '' ), Cornerstone_Validate::PIPELINE_STATUSES, 'new' ),
			'notes'           => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_at'      => $now,
			'updated_at'      => $now,
		];

		$inserted = $wpdb->insert(
			Cornerstone_DB::contacts(),
			$row,
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not save contact.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates an existing contact, scoped to the acting user unless they
	 * can manage all records.
	 */
	public static function update( int $id, array $data, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Contact not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$first_name = sanitize_text_field( $data['first_name'] ?? $existing['first_name'] );
		$last_name  = sanitize_text_field( $data['last_name'] ?? $existing['last_name'] );

		if ( '' === $first_name && '' === $last_name ) {
			return new WP_Error( 'cornerstone_invalid_contact', __( 'A contact needs at least a first or last name.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$row = [
			'first_name'      => $first_name,
			'last_name'       => $last_name,
			'phone'           => Cornerstone_Validate::phone( $data['phone'] ?? $existing['phone'] ),
			'email'           => Cornerstone_Validate::email( $data['email'] ?? $existing['email'] ),
			'role_tag'        => Cornerstone_Validate::enum( sanitize_key( $data['role_tag'] ?? $existing['role_tag'] ), Cornerstone_Validate::ROLE_TAGS, $existing['role_tag'] ),
			'pipeline_status' => Cornerstone_Validate::enum( sanitize_key( $data['pipeline_status'] ?? $existing['pipeline_status'] ), Cornerstone_Validate::PIPELINE_STATUSES, $existing['pipeline_status'] ),
			'notes'           => sanitize_textarea_field( $data['notes'] ?? $existing['notes'] ),
			'updated_at'      => current_time( 'mysql' ),
		];

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::contacts(),
			$row,
			$where,
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not update contact.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Soft-deletes a contact (sets deleted_at) rather than removing the
	 * row, per spec. Interactions/transactions/tasks referencing this
	 * contact are left untouched — they remain visible as history but
	 * exists_for_scope() will refuse new records against a deleted contact.
	 */
	public static function soft_delete( int $id, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Contact not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::contacts(),
			[ 'deleted_at' => current_time( 'mysql' ) ],
			$where,
			[ '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not delete contact.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Fetches a single non-deleted contact, scoped to the acting user
	 * unless they can manage all records. Returns null if not found or
	 * out of scope — callers must not be able to distinguish "doesn't
	 * exist" from "exists but isn't yours".
	 */
	public static function get( int $id, int $user_id, bool $can_manage_all ): ?array {
		global $wpdb;

		if ( $can_manage_all ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM " . Cornerstone_DB::contacts() . " WHERE id = %d AND deleted_at IS NULL",
				$id
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM " . Cornerstone_DB::contacts() . " WHERE id = %d AND user_id = %d AND deleted_at IS NULL",
				$id,
				$user_id
			);
		}

		$row = $wpdb->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	/**
	 * True if a non-deleted contact with this ID is visible to the acting
	 * user. Used by interactions/transactions/tasks to enforce referential
	 * integrity at the application layer (dbDelta does not manage foreign
	 * key constraints reliably, so this is the substitute).
	 */
	public static function exists_for_scope( int $id, int $user_id, bool $can_manage_all ): bool {
		return null !== self::get( $id, $user_id, $can_manage_all );
	}

	/**
	 * Lists non-deleted contacts, optionally filtered by pipeline_status,
	 * scoped to the acting user unless they can manage all records.
	 *
	 * @return array{items: array<int, array>, total: int}
	 */
	public static function list_for_scope( int $user_id, bool $can_manage_all, array $args = [] ): array {
		global $wpdb;

		$per_page = max( 1, min( 200, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [ 'deleted_at IS NULL' ];
		$params = [];

		if ( ! $can_manage_all ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		} elseif ( ! empty( $args['owner_id'] ) ) {
			// Only meaningful for the broker/admin view — an agent's own
			// list is already restricted to themselves above, so a
			// mismatched owner_id there would just (correctly) return
			// nothing rather than needing a second check.
			$where[]  = 'user_id = %d';
			$params[] = absint( $args['owner_id'] );
		}

		if ( ! empty( $args['pipeline_status'] ) ) {
			$status = Cornerstone_Validate::enum( sanitize_key( $args['pipeline_status'] ), Cornerstone_Validate::PIPELINE_STATUSES, '' );
			if ( '' !== $status ) {
				$where[]  = 'pipeline_status = %s';
				$params[] = $status;
			}
		}

		$where_sql = implode( ' AND ', $where );
		$table     = Cornerstone_DB::contacts();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		$list_sql     = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$list_params  = array_merge( $params, [ $per_page, $offset ] );
		$items        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}
}
