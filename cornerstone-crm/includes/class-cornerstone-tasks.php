<?php
/**
 * Data layer for the tasks table (manual follow-up tasks tied to a
 * contact). See class-cornerstone-contacts.php for the scoping
 * conventions shared across all four data-layer classes.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Tasks {

	public static function create( array $data, int $user_id, bool $can_manage_all ): int|WP_Error {
		global $wpdb;

		$contact_id = absint( $data['contact_id'] ?? 0 );
		if ( ! $contact_id || ! Cornerstone_Contacts::exists_for_scope( $contact_id, $user_id, $can_manage_all ) ) {
			return new WP_Error( 'cornerstone_invalid_contact', __( 'That contact does not exist or is not accessible to you.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$description = sanitize_textarea_field( $data['description'] ?? '' );
		if ( '' === $description ) {
			return new WP_Error( 'cornerstone_invalid_task', __( 'A task needs a description.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$due_date = Cornerstone_Validate::date( substr( (string) ( $data['due_date'] ?? '' ), 0, 10 ) );
		$now      = current_time( 'mysql' );

		$row = [
			'contact_id'  => $contact_id,
			'user_id'     => $user_id,
			'description' => $description,
			'due_date'    => '' !== $due_date ? $due_date : null,
			'completed'   => 0,
			'created_at'  => $now,
			'updated_at'  => $now,
		];

		$inserted = $wpdb->insert(
			Cornerstone_DB::tasks(),
			$row,
			[ '%d', '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not save task.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Task not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$description = array_key_exists( 'description', $data ) ? sanitize_textarea_field( $data['description'] ) : $existing['description'];
		if ( '' === $description ) {
			return new WP_Error( 'cornerstone_invalid_task', __( 'A task needs a description.', 'cornerstone-crm' ), [ 'status' => 400 ] );
		}

		$due_date = array_key_exists( 'due_date', $data )
			? Cornerstone_Validate::date( substr( (string) $data['due_date'], 0, 10 ) )
			: (string) ( $existing['due_date'] ?? '' );

		$completed = array_key_exists( 'completed', $data ) ? (int) (bool) $data['completed'] : (int) $existing['completed'];

		$row = [
			'description' => $description,
			'due_date'    => '' !== $due_date ? $due_date : null,
			'completed'   => $completed,
			'updated_at'  => current_time( 'mysql' ),
		];

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::tasks(),
			$row,
			$where,
			[ '%s', '%s', '%d', '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not update task.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	public static function soft_delete( int $id, int $user_id, bool $can_manage_all ): true|WP_Error {
		global $wpdb;

		$existing = self::get( $id, $user_id, $can_manage_all );
		if ( null === $existing ) {
			return new WP_Error( 'cornerstone_not_found', __( 'Task not found.', 'cornerstone-crm' ), [ 'status' => 404 ] );
		}

		$where = [ 'id' => $id ];
		if ( ! $can_manage_all ) {
			$where['user_id'] = $user_id;
		}

		$updated = $wpdb->update(
			Cornerstone_DB::tasks(),
			[ 'deleted_at' => current_time( 'mysql' ) ],
			$where,
			[ '%s' ],
			array_fill( 0, count( $where ), '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cornerstone_db_error', __( 'Could not delete task.', 'cornerstone-crm' ), [ 'status' => 500 ] );
		}

		return true;
	}

	public static function get( int $id, int $user_id, bool $can_manage_all ): ?array {
		global $wpdb;
		$table = Cornerstone_DB::tasks();

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

		if ( isset( $args['completed'] ) && '' !== $args['completed'] ) {
			$where[]  = 'completed = %d';
			$params[] = (int) (bool) $args['completed'];
		}

		$where_sql = implode( ' AND ', $where );
		$table     = Cornerstone_DB::tasks();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( empty( $params ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY (due_date IS NULL), due_date ASC, updated_at DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, [ $per_page, $offset ] );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A );

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}
}
