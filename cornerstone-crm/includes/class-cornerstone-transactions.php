<?php
/**
 * Data layer for the transactions table. See class-cornerstone-contacts.php
 * for the scoping conventions shared across all four data-layer classes.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Transactions {

	public static function create( array $data, int $user_id, bool $can_manage_all ): int|WP_Error {
		global $wpdb;

		$contact_id = absint( $data['contact_id'] ?? 0 );
		if ( ! $contact_id || ! Cornerstone_Contacts::exists_for_scope( $contact_id, $user_id, $can_manage_all ) ) {
			return new WP_Error( 'cornerstone_invalid_contact', __( 'That contact does not exist or is not accessible to you.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$address = sanitize_text_field( $data['property_address'] ?? '' );
		if ( '' === $address ) {
			return new WP_Error( 'cornerstone_invalid_transaction', __( 'A property address is required.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$now = current_time( 'mysql' );

		$row = [
			'contact_id'       => $contact_id,
			'user_id'          => $user_id,
			'property_address' => $address,
			'transaction_type' => Cornerstone_Validate::enum( sanitize_key( $data['transaction_type'] ?? '' ), Cornerstone_Validate::TRANSACTION_TYPES, 'buy' ),
			'status'           => Cornerstone_Validate::enum( sanitize_key( $data['status'] ?? '' ), Cornerstone_Validate::TRANSACTION_STATUSES, 'active' ),
			'price'            => Cornerstone_Validate::price( $data['price'] ?? null ),
			'key_dates'        => Cornerstone_Validate::key_dates_json( $data['key_dates'] ?? null ),
			'created_at'       => $now,
			'updated_at'       => $now,
		];

		$inserted = $wpdb->insert(
			Cornerstone_DB::transactions(),
			$row,
			[ '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not save transaction.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Transaction not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$address = array_key_exists( 'property_address', $data ) ? sanitize_text_field( $data['property_address'] ) : $existing['property_address'];
		if ( '' === $address ) {
			return new WP_Error( 'cornerstone_invalid_transaction', __( 'A property address is required.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$price = array_key_exists( 'price', $data ) ? Cornerstone_Validate::price( $data['price'] ) : ( null !== $existing['price'] ? (float) $existing['price'] : null );

		$row = [
			'property_address' => $address,
			'transaction_type' => Cornerstone_Validate::enum( sanitize_key( $data['transaction_type'] ?? $existing['transaction_type'] ), Cornerstone_Validate::TRANSACTION_TYPES, $existing['transaction_type'] ),
			'status'           => Cornerstone_Validate::enum( sanitize_key( $data['status'] ?? $existing['status'] ), Cornerstone_Validate::TRANSACTION_STATUSES, $existing['status'] ),
			'price'            => $price,
			'key_dates'        => array_key_exists( 'key_dates', $data ) ? Cornerstone_Validate::key_dates_json( $data['key_dates'] ) : $existing['key_dates'],
			'updated_at'       => current_time( 'mysql' ),
		];

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::transactions(),
			$row,
			$where,
			[ '%s', '%s', '%s', '%f', '%s', '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not update transaction.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	public static function soft_delete( int $id, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Transaction not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::transactions(),
			[ 'deleted_at' => current_time( 'mysql' ) ],
			$where,
			[ '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not delete transaction.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	public static function get( int $id, int $user_id, bool $can_manage_all ): ?array {
		global $wpdb;
		$table = Cornerstone_DB::transactions();

		if ( $can_manage_all ) {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id );
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d AND deleted_at IS NULL", $id, $user_id );
		}

		$row = $wpdb->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	/**
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
		}

		if ( ! empty( $args['contact_id'] ) ) {
			$where[]  = 'contact_id = %d';
			$params[] = absint( $args['contact_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$status = Cornerstone_Validate::enum( sanitize_key( $args['status'] ), Cornerstone_Validate::TRANSACTION_STATUSES, '' );
			if ( '' !== $status ) {
				$where[]  = 'status = %s';
				$params[] = $status;
			}
		}

		$where_sql = implode( ' AND ', $where );
		$table     = Cornerstone_DB::transactions();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, [ $per_page, $offset ] );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}
}
