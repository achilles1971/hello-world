<?php
/**
 * Resolves a portal request (section + view + id) to data, then hands
 * off to the matching view template. Every read here calls the exact
 * same data-layer classes and scoping rules as the wp-admin UI
 * (Cornerstone_Contacts::list_for_scope() etc.) — this is a second
 * presentation of the same data, not a second data path.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Portal_Controller {

	private const PER_PAGE = 20;

	public static function render( string $section, string $view, int $id ): void {
		if ( ! array_key_exists( $section, Cornerstone_Portal_Views::SECTIONS ) ) {
			wp_safe_redirect( Cornerstone_Portal_Router::url( 'contacts' ) );
			exit;
		}

		switch ( $section ) {
			case 'contacts':
				self::render_contacts( $view, $id );
				break;
			case 'interactions':
				self::render_interactions( $view, $id );
				break;
			case 'transactions':
				self::render_transactions( $view, $id );
				break;
			case 'tasks':
				self::render_tasks( $view, $id );
				break;
		}
	}

	private static function scope(): array {
		return [ get_current_user_id(), Cornerstone_Roles::can_manage_all() ];
	}

	private static function is_form_view( string $view, int $id ): bool {
		return 'new' === $view || ( 'edit' === $view && $id > 0 );
	}

	// -----------------------------------------------------------------
	// Contacts
	// -----------------------------------------------------------------

	private static function render_contacts( string $view, int $id ): void {
		[ $user_id, $can_manage_all ] = self::scope();
		$mode = self::is_form_view( $view, $id ) ? 'form' : 'list';

		$contact = null;
		if ( 'form' === $mode && $id ) {
			$contact = Cornerstone_Contacts::get( $id, $user_id, $can_manage_all );
			if ( null === $contact ) {
				wp_safe_redirect( add_query_arg( 'notice', 'not_found', Cornerstone_Portal_Router::url( 'contacts' ) ) );
				exit;
			}
		}

		$items = $total = $page = $status = $agent_id = $owners = $agent_choices = null;
		if ( 'list' === $mode ) {
			$status   = sanitize_key( $_GET['pipeline_status'] ?? '' );
			$agent_id = $can_manage_all ? absint( $_GET['agent_id'] ?? 0 ) : 0;
			$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );

			$result = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [
				'pipeline_status' => $status,
				'owner_id'        => $agent_id,
				'page'            => $page,
				'per_page'        => self::PER_PAGE,
			] );
			$items         = $result['items'];
			$total         = $result['total'];
			$owners        = $can_manage_all ? self::owner_names_by_id( $items ) : [];
			$agent_choices = $can_manage_all ? self::crm_user_choices() : [];
		}

		Cornerstone_Portal_Views::open( 'contacts' );
		require CORNERSTONE_CRM_PATH . 'includes/modules/portal/views/contacts.php';
		Cornerstone_Portal_Views::close();
	}

	// -----------------------------------------------------------------
	// Interactions
	// -----------------------------------------------------------------

	private static function render_interactions( string $view, int $id ): void {
		[ $user_id, $can_manage_all ] = self::scope();
		$mode = self::is_form_view( $view, $id ) ? 'form' : 'list';

		$interaction = null;
		if ( 'form' === $mode && $id ) {
			$interaction = Cornerstone_Interactions::get( $id, $user_id, $can_manage_all );
			if ( null === $interaction ) {
				wp_safe_redirect( add_query_arg( 'notice', 'not_found', Cornerstone_Portal_Router::url( 'interactions' ) ) );
				exit;
			}
		}

		$items = $total = $page = $type = $contacts = null;
		$form_contacts = $default_contact_id = null;

		if ( 'form' === $mode ) {
			$form_contacts       = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			$default_contact_id  = absint( $_GET['contact_id'] ?? 0 );
		} else {
			$type = sanitize_key( $_GET['type'] ?? '' );
			$page = max( 1, absint( $_GET['paged'] ?? 1 ) );

			$result = Cornerstone_Interactions::list_for_scope( $user_id, $can_manage_all, [
				'type'     => $type,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			] );
			$items    = $result['items'];
			$total    = $result['total'];
			$contacts = self::contact_names_by_id( $items, $user_id, $can_manage_all );
		}

		Cornerstone_Portal_Views::open( 'interactions' );
		require CORNERSTONE_CRM_PATH . 'includes/modules/portal/views/interactions.php';
		Cornerstone_Portal_Views::close();
	}

	// -----------------------------------------------------------------
	// Transactions
	// -----------------------------------------------------------------

	private static function render_transactions( string $view, int $id ): void {
		[ $user_id, $can_manage_all ] = self::scope();
		$mode = self::is_form_view( $view, $id ) ? 'form' : 'list';

		$transaction = null;
		if ( 'form' === $mode && $id ) {
			$transaction = Cornerstone_Transactions::get( $id, $user_id, $can_manage_all );
			if ( null === $transaction ) {
				wp_safe_redirect( add_query_arg( 'notice', 'not_found', Cornerstone_Portal_Router::url( 'transactions' ) ) );
				exit;
			}
		}

		$items = $total = $page = $status = $contacts = null;
		$form_contacts = $default_contact_id = null;

		if ( 'form' === $mode ) {
			$form_contacts      = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			$default_contact_id = absint( $_GET['contact_id'] ?? 0 );
		} else {
			$status = sanitize_key( $_GET['status'] ?? '' );
			$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );

			$result = Cornerstone_Transactions::list_for_scope( $user_id, $can_manage_all, [
				'status'   => $status,
				'page'     => $page,
				'per_page' => self::PER_PAGE,
			] );
			$items    = $result['items'];
			$total    = $result['total'];
			$contacts = self::contact_names_by_id( $items, $user_id, $can_manage_all );
		}

		Cornerstone_Portal_Views::open( 'transactions' );
		require CORNERSTONE_CRM_PATH . 'includes/modules/portal/views/transactions.php';
		Cornerstone_Portal_Views::close();
	}

	// -----------------------------------------------------------------
	// Tasks
	// -----------------------------------------------------------------

	private static function render_tasks( string $view, int $id ): void {
		[ $user_id, $can_manage_all ] = self::scope();
		$mode = self::is_form_view( $view, $id ) ? 'form' : 'list';

		$task = null;
		if ( 'form' === $mode && $id ) {
			$task = Cornerstone_Tasks::get( $id, $user_id, $can_manage_all );
			if ( null === $task ) {
				wp_safe_redirect( add_query_arg( 'notice', 'not_found', Cornerstone_Portal_Router::url( 'tasks' ) ) );
				exit;
			}
		}

		$items = $total = $page = $completed = $contacts = null;
		$form_contacts = $default_contact_id = null;

		if ( 'form' === $mode ) {
			$form_contacts       = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			$default_contact_id  = absint( $_GET['contact_id'] ?? 0 );
		} else {
			$completed = $_GET['completed'] ?? '';
			$completed = ( '' === $completed ) ? '' : (int) (bool) $completed;
			$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );

			$result = Cornerstone_Tasks::list_for_scope( $user_id, $can_manage_all, [
				'completed' => $completed,
				'page'      => $page,
				'per_page'  => self::PER_PAGE,
			] );
			$items    = $result['items'];
			$total    = $result['total'];
			$contacts = self::contact_names_by_id( $items, $user_id, $can_manage_all );
		}

		Cornerstone_Portal_Views::open( 'tasks' );
		require CORNERSTONE_CRM_PATH . 'includes/modules/portal/views/tasks.php';
		Cornerstone_Portal_Views::close();
	}

	// -----------------------------------------------------------------
	// Shared lookups (same conventions as Cornerstone_Admin's — kept
	// separate rather than shared across presentation layers, since each
	// is a few lines tied to what its own views need).
	// -----------------------------------------------------------------

	private static function contact_names_by_id( array $rows, int $user_id, bool $can_manage_all ): array {
		$names = [];
		foreach ( $rows as $row ) {
			$contact_id = (int) $row['contact_id'];
			if ( isset( $names[ $contact_id ] ) ) {
				continue;
			}
			$contact               = Cornerstone_Contacts::get( $contact_id, $user_id, $can_manage_all );
			$names[ $contact_id ]  = $contact ? trim( $contact['first_name'] . ' ' . $contact['last_name'] ) : __( '(unknown contact)', 'cornerstone-crm' );
		}
		return $names;
	}

	private static function owner_names_by_id( array $rows ): array {
		$names = [];
		foreach ( $rows as $row ) {
			$owner_id = (int) $row['user_id'];
			if ( isset( $names[ $owner_id ] ) ) {
				continue;
			}
			$user               = get_userdata( $owner_id );
			$names[ $owner_id ] = $user ? $user->display_name : __( '(deleted user)', 'cornerstone-crm' );
		}
		return $names;
	}

	private static function crm_user_choices(): array {
		$choices = [];
		foreach ( Cornerstone_Roles::crm_users() as $user ) {
			$choices[ (int) $user->ID ] = $user->display_name;
		}
		return $choices;
	}
}
