<?php
/**
 * Admin UI: menu registration and admin-post.php form handlers.
 *
 * Deliberately built as classic server-rendered admin screens (full page
 * reloads, no JS/AJAX) rather than a JS app talking to the REST API. This
 * keeps the write path to one form of authentication (WordPress admin
 * session + nonce) instead of two, and keeps the admin screens usable
 * with JS disabled. The REST API (class-cornerstone-rest-*.php) exists
 * independently for future programmatic/JS use and is not used by these
 * screens.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Admin {

	public const SLUG_CONTACTS     = 'cornerstone-crm';
	public const SLUG_INTERACTIONS = 'cornerstone-crm-interactions';
	public const SLUG_TRANSACTIONS = 'cornerstone-crm-transactions';
	public const SLUG_TASKS        = 'cornerstone-crm-tasks';

	public static function init(): void {
		// Runs before any Cornerstone screen renders on this request; see
		// the docblock on Cornerstone_Roles::maybe_heal_administrator_access()
		// for why this self-heal exists.
		add_action( 'admin_init', [ 'Cornerstone_Roles', 'maybe_heal_administrator_access' ] );

		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		add_action( 'admin_post_cornerstone_save_contact', [ __CLASS__, 'handle_save_contact' ] );
		add_action( 'admin_post_cornerstone_delete_contact', [ __CLASS__, 'handle_delete_contact' ] );
		add_action( 'admin_post_cornerstone_save_interaction', [ __CLASS__, 'handle_save_interaction' ] );
		add_action( 'admin_post_cornerstone_delete_interaction', [ __CLASS__, 'handle_delete_interaction' ] );
		add_action( 'admin_post_cornerstone_save_transaction', [ __CLASS__, 'handle_save_transaction' ] );
		add_action( 'admin_post_cornerstone_delete_transaction', [ __CLASS__, 'handle_delete_transaction' ] );
		add_action( 'admin_post_cornerstone_save_task', [ __CLASS__, 'handle_save_task' ] );
		add_action( 'admin_post_cornerstone_delete_task', [ __CLASS__, 'handle_delete_task' ] );
	}

	// -----------------------------------------------------------------
	// Menu
	// -----------------------------------------------------------------

	public static function register_menu(): void {
		add_menu_page(
			__( 'Cornerstone CRM', 'cornerstone-crm' ),
			__( 'Cornerstone CRM', 'cornerstone-crm' ),
			Cornerstone_Roles::CAP_ACCESS,
			self::SLUG_CONTACTS,
			[ __CLASS__, 'render_contacts_page' ],
			'dashicons-groups',
			26
		);

		add_submenu_page( self::SLUG_CONTACTS, __( 'Contacts', 'cornerstone-crm' ), __( 'Contacts', 'cornerstone-crm' ), Cornerstone_Roles::CAP_ACCESS, self::SLUG_CONTACTS, [ __CLASS__, 'render_contacts_page' ] );
		add_submenu_page( self::SLUG_CONTACTS, __( 'Interactions', 'cornerstone-crm' ), __( 'Interactions', 'cornerstone-crm' ), Cornerstone_Roles::CAP_ACCESS, self::SLUG_INTERACTIONS, [ __CLASS__, 'render_interactions_page' ] );
		add_submenu_page( self::SLUG_CONTACTS, __( 'Transactions', 'cornerstone-crm' ), __( 'Transactions', 'cornerstone-crm' ), Cornerstone_Roles::CAP_ACCESS, self::SLUG_TRANSACTIONS, [ __CLASS__, 'render_transactions_page' ] );
		add_submenu_page( self::SLUG_CONTACTS, __( 'Tasks', 'cornerstone-crm' ), __( 'Tasks', 'cornerstone-crm' ), Cornerstone_Roles::CAP_ACCESS, self::SLUG_TASKS, [ __CLASS__, 'render_tasks_page' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( $screen->id, 'cornerstone-crm' ) ) {
			return;
		}
		wp_enqueue_style( 'cornerstone-crm-admin', CORNERSTONE_CRM_URL . 'admin/css/admin.css', [], CORNERSTONE_CRM_VERSION );
	}

	// -----------------------------------------------------------------
	// Shared helpers
	// -----------------------------------------------------------------

	/**
	 * Hard-stops the request unless the current user has CRM access.
	 * Belt-and-suspenders: add_menu_page()'s capability already prevents
	 * the page from being registered for unauthorized users, but form
	 * handlers (admin-post.php) are reachable by URL directly and must
	 * re-check.
	 */
	private static function require_access(): void {
		if ( ! Cornerstone_Roles::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access the CRM.', 'cornerstone-crm' ), 403 );
		}
	}

	private static function scope(): array {
		return [ get_current_user_id(), Cornerstone_Roles::can_manage_all() ];
	}

	private static function render_view( string $view, array $vars = [] ): void {
		extract( $vars, EXTR_SKIP );
		require CORNERSTONE_CRM_PATH . 'admin/views/' . $view . '.php';
	}

	/**
	 * Small fixed vocabulary of notice messages. Only ever fed a
	 * sanitize_key()'d value read back from our own redirect URLs, and
	 * only known keys render — this can never reflect arbitrary request
	 * input as text.
	 */
	public static function notice_text( string $key ): string {
		$messages = [
			'saved'          => __( 'Saved.', 'cornerstone-crm' ),
			'deleted'        => __( 'Deleted.', 'cornerstone-crm' ),
			'error'          => __( 'Something went wrong. Please try again.', 'cornerstone-crm' ),
			'not_found'      => __( 'Record not found.', 'cornerstone-crm' ),
		];
		return $messages[ $key ] ?? '';
	}

	// -----------------------------------------------------------------
	// Contacts screen
	// -----------------------------------------------------------------

	public static function render_contacts_page(): void {
		self::require_access();
		[ $user_id, $can_manage_all ] = self::scope();

		$view = sanitize_key( $_GET['view'] ?? 'list' );
		$id   = absint( $_GET['id'] ?? 0 );

		if ( 'new' === $view || ( 'edit' === $view && $id ) ) {
			$contact = $id ? Cornerstone_Contacts::get( $id, $user_id, $can_manage_all ) : null;
			if ( $id && null === $contact ) {
				self::render_view( 'contacts-list', [ 'notice' => 'not_found' ] + self::contacts_list_data( $user_id, $can_manage_all ) );
				return;
			}
			self::render_view( 'contacts-form', [ 'contact' => $contact ] );
			return;
		}

		self::render_view( 'contacts-list', self::contacts_list_data( $user_id, $can_manage_all ) );
	}

	private static function contacts_list_data( int $user_id, bool $can_manage_all ): array {
		$status   = sanitize_key( $_GET['pipeline_status'] ?? '' );
		$agent_id = $can_manage_all ? absint( $_GET['agent_id'] ?? 0 ) : 0;
		$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$result = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [
			'pipeline_status' => $status,
			'owner_id'        => $agent_id,
			'page'            => $page,
			'per_page'        => 20,
		] );

		return [
			'items'          => $result['items'],
			'total'          => $result['total'],
			'page'           => $page,
			'status'         => $status,
			'agent_id'       => $agent_id,
			'notice'         => sanitize_key( $_GET['notice'] ?? '' ),
			'can_manage_all' => $can_manage_all,
			// Only resolved for the broker/admin view — an agent's own
			// list is entirely their own records, so the column/filter
			// would be redundant and this skips the extra lookups for them.
			'owners'         => $can_manage_all ? self::owner_names_by_id( $result['items'] ) : [],
			'agent_choices'  => $can_manage_all ? self::crm_user_choices() : [],
		];
	}

	/**
	 * All WordPress users holding any Cornerstone capability (broker,
	 * agent, or an administrator who inherited access), for the broker's
	 * "Agent" filter dropdown. Ordered by display name.
	 */
	private static function crm_user_choices(): array {
		$users = get_users( [
			'role__in' => [ Cornerstone_Roles::ROLE_BROKER, Cornerstone_Roles::ROLE_AGENT, 'administrator' ],
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'fields'   => [ 'ID', 'display_name' ],
		] );

		$choices = [];
		foreach ( $users as $user ) {
			$choices[ (int) $user->ID ] = $user->display_name;
		}
		return $choices;
	}

	public static function handle_save_contact(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_save_contact' );

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

		$notice = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_CONTACTS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_delete_contact(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_delete_contact' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$result = Cornerstone_Contacts::soft_delete( $id, $user_id, $can_manage_all );

		$notice = is_wp_error( $result ) ? 'error' : 'deleted';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_CONTACTS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -----------------------------------------------------------------
	// Interactions screen
	// -----------------------------------------------------------------

	public static function render_interactions_page(): void {
		self::require_access();
		[ $user_id, $can_manage_all ] = self::scope();

		$view       = sanitize_key( $_GET['view'] ?? 'list' );
		$id         = absint( $_GET['id'] ?? 0 );
		$contact_id = absint( $_GET['contact_id'] ?? 0 );

		if ( 'new' === $view || ( 'edit' === $view && $id ) ) {
			$interaction = $id ? Cornerstone_Interactions::get( $id, $user_id, $can_manage_all ) : null;
			if ( $id && null === $interaction ) {
				self::render_view( 'interactions-list', [ 'notice' => 'not_found' ] + self::interactions_list_data( $user_id, $can_manage_all ) );
				return;
			}
			$contacts = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			self::render_view( 'interactions-form', [ 'interaction' => $interaction, 'contacts' => $contacts, 'default_contact_id' => $contact_id ] );
			return;
		}

		self::render_view( 'interactions-list', self::interactions_list_data( $user_id, $can_manage_all ) );
	}

	private static function interactions_list_data( int $user_id, bool $can_manage_all ): array {
		$type = sanitize_key( $_GET['type'] ?? '' );
		$page = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$result = Cornerstone_Interactions::list_for_scope( $user_id, $can_manage_all, [
			'type'     => $type,
			'page'     => $page,
			'per_page' => 20,
		] );

		$contacts = self::contact_names_by_id( $result['items'], $user_id, $can_manage_all );

		return [
			'items'    => $result['items'],
			'total'    => $result['total'],
			'page'     => $page,
			'type'     => $type,
			'contacts' => $contacts,
			'notice'   => sanitize_key( $_GET['notice'] ?? '' ),
		];
	}

	public static function handle_save_interaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_save_interaction' );

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

		$notice = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_INTERACTIONS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_delete_interaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_delete_interaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$result = Cornerstone_Interactions::soft_delete( $id, $user_id, $can_manage_all );

		$notice = is_wp_error( $result ) ? 'error' : 'deleted';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_INTERACTIONS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -----------------------------------------------------------------
	// Transactions screen
	// -----------------------------------------------------------------

	public static function render_transactions_page(): void {
		self::require_access();
		[ $user_id, $can_manage_all ] = self::scope();

		$view       = sanitize_key( $_GET['view'] ?? 'list' );
		$id         = absint( $_GET['id'] ?? 0 );
		$contact_id = absint( $_GET['contact_id'] ?? 0 );

		if ( 'new' === $view || ( 'edit' === $view && $id ) ) {
			$transaction = $id ? Cornerstone_Transactions::get( $id, $user_id, $can_manage_all ) : null;
			if ( $id && null === $transaction ) {
				self::render_view( 'transactions-list', [ 'notice' => 'not_found' ] + self::transactions_list_data( $user_id, $can_manage_all ) );
				return;
			}
			$contacts = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			self::render_view( 'transactions-form', [ 'transaction' => $transaction, 'contacts' => $contacts, 'default_contact_id' => $contact_id ] );
			return;
		}

		self::render_view( 'transactions-list', self::transactions_list_data( $user_id, $can_manage_all ) );
	}

	private static function transactions_list_data( int $user_id, bool $can_manage_all ): array {
		$status = sanitize_key( $_GET['status'] ?? '' );
		$page   = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$result = Cornerstone_Transactions::list_for_scope( $user_id, $can_manage_all, [
			'status'   => $status,
			'page'     => $page,
			'per_page' => 20,
		] );

		$contacts = self::contact_names_by_id( $result['items'], $user_id, $can_manage_all );

		return [
			'items'    => $result['items'],
			'total'    => $result['total'],
			'page'     => $page,
			'status'   => $status,
			'contacts' => $contacts,
			'notice'   => sanitize_key( $_GET['notice'] ?? '' ),
		];
	}

	public static function handle_save_transaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_save_transaction' );

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

		$notice = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_TRANSACTIONS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_delete_transaction(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_delete_transaction' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$result = Cornerstone_Transactions::soft_delete( $id, $user_id, $can_manage_all );

		$notice = is_wp_error( $result ) ? 'error' : 'deleted';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_TRANSACTIONS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -----------------------------------------------------------------
	// Tasks screen
	// -----------------------------------------------------------------

	public static function render_tasks_page(): void {
		self::require_access();
		[ $user_id, $can_manage_all ] = self::scope();

		$view       = sanitize_key( $_GET['view'] ?? 'list' );
		$id         = absint( $_GET['id'] ?? 0 );
		$contact_id = absint( $_GET['contact_id'] ?? 0 );

		if ( 'new' === $view || ( 'edit' === $view && $id ) ) {
			$task = $id ? Cornerstone_Tasks::get( $id, $user_id, $can_manage_all ) : null;
			if ( $id && null === $task ) {
				self::render_view( 'tasks-list', [ 'notice' => 'not_found' ] + self::tasks_list_data( $user_id, $can_manage_all ) );
				return;
			}
			$contacts = Cornerstone_Contacts::list_for_scope( $user_id, $can_manage_all, [ 'per_page' => 200 ] )['items'];
			self::render_view( 'tasks-form', [ 'task' => $task, 'contacts' => $contacts, 'default_contact_id' => $contact_id ] );
			return;
		}

		self::render_view( 'tasks-list', self::tasks_list_data( $user_id, $can_manage_all ) );
	}

	private static function tasks_list_data( int $user_id, bool $can_manage_all ): array {
		$completed = $_GET['completed'] ?? '';
		$completed = ( '' === $completed ) ? '' : (int) (bool) $completed;
		$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );

		$result = Cornerstone_Tasks::list_for_scope( $user_id, $can_manage_all, [
			'completed' => $completed,
			'page'      => $page,
			'per_page'  => 20,
		] );

		$contacts = self::contact_names_by_id( $result['items'], $user_id, $can_manage_all );

		return [
			'items'     => $result['items'],
			'total'     => $result['total'],
			'page'      => $page,
			'completed' => $completed,
			'contacts'  => $contacts,
			'notice'    => sanitize_key( $_GET['notice'] ?? '' ),
		];
	}

	public static function handle_save_task(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_save_task' );

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

		$notice = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_TASKS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_delete_task(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_delete_task' );

		[ $user_id, $can_manage_all ] = self::scope();
		$id = absint( $_POST['id'] ?? 0 );

		$result = Cornerstone_Tasks::soft_delete( $id, $user_id, $can_manage_all );

		$notice = is_wp_error( $result ) ? 'error' : 'deleted';
		wp_safe_redirect( add_query_arg( [ 'page' => self::SLUG_TASKS, 'notice' => $notice ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Rebuilds the key_dates associative array from the parallel
	 * key_date_labels[]/key_date_values[] inputs rendered by
	 * transactions-form.php, discarding any row missing a label or date.
	 * Cornerstone_Validate::key_dates_json() re-sanitizes every key and
	 * value again before storage.
	 */
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
	// Shared lookups
	// -----------------------------------------------------------------

	/**
	 * Renders the four-tab sub-nav shared by every screen. Escapes every
	 * value; $active is always one of the four SLUG_* constants passed by
	 * our own code, never request input.
	 */
	public static function nav_html( string $active ): void {
		$tabs = [
			self::SLUG_CONTACTS     => __( 'Contacts', 'cornerstone-crm' ),
			self::SLUG_INTERACTIONS => __( 'Interactions', 'cornerstone-crm' ),
			self::SLUG_TRANSACTIONS => __( 'Transactions', 'cornerstone-crm' ),
			self::SLUG_TASKS        => __( 'Tasks', 'cornerstone-crm' ),
		];
		echo '<h2 class="nav-tab-wrapper cornerstone-crm-nav">';
		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( admin_url( 'admin.php?page=' . $slug ) ),
				esc_attr( $class ),
				esc_html( $label )
			);
		}
		echo '</h2>';
	}

	/**
	 * Renders a fixed admin notice banner from the small vocabulary in
	 * notice_text(). No-ops for unknown/empty keys.
	 */
	public static function notice_html( string $notice ): void {
		$text = self::notice_text( $notice );
		if ( '' === $text ) {
			return;
		}
		$class = in_array( $notice, [ 'error', 'not_found' ], true ) ? 'notice-error' : 'notice-success';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
	}

	/**
	 * Simple prev/next pagination for a 20-per-page list.
	 */
	public static function pagination_html( int $page, int $total, int $per_page, string $page_slug, array $extra_args = [] ): void {
		$last_page = max( 1, (int) ceil( $total / $per_page ) );
		if ( $last_page <= 1 ) {
			return;
		}
		echo '<div class="tablenav-pages cornerstone-crm-pagination">';
		if ( $page > 1 ) {
			$prev_url = add_query_arg( array_merge( $extra_args, [ 'page' => $page_slug, 'paged' => $page - 1 ] ), admin_url( 'admin.php' ) );
			printf( '<a class="button" href="%s">&laquo; %s</a> ', esc_url( $prev_url ), esc_html__( 'Previous', 'cornerstone-crm' ) );
		}
		printf( '<span class="cornerstone-crm-page-info">%s</span> ', esc_html( sprintf(
			/* translators: 1: current page 2: total pages */
			__( 'Page %1$d of %2$d', 'cornerstone-crm' ),
			$page,
			$last_page
		) ) );
		if ( $page < $last_page ) {
			$next_url = add_query_arg( array_merge( $extra_args, [ 'page' => $page_slug, 'paged' => $page + 1 ] ), admin_url( 'admin.php' ) );
			printf( '<a class="button" href="%s">%s &raquo;</a>', esc_url( $next_url ), esc_html__( 'Next', 'cornerstone-crm' ) );
		}
		echo '</div>';
	}

	/**
	 * Resolves display names for the small set of contact_ids present in
	 * a list result, scoped the same way as the list itself, so an
	 * agent viewing their own interactions/transactions/tasks never
	 * sees another agent's contact name leak through.
	 */
	private static function contact_names_by_id( array $rows, int $user_id, bool $can_manage_all ): array {
		$names = [];
		foreach ( $rows as $row ) {
			$contact_id = (int) $row['contact_id'];
			if ( isset( $names[ $contact_id ] ) ) {
				continue;
			}
			$contact = Cornerstone_Contacts::get( $contact_id, $user_id, $can_manage_all );
			$names[ $contact_id ] = $contact ? trim( $contact['first_name'] . ' ' . $contact['last_name'] ) : __( '(unknown contact)', 'cornerstone-crm' );
		}
		return $names;
	}

	/**
	 * Resolves the owning agent's display name for each row's user_id.
	 * Only called for the broker/admin view (see contacts_list_data() and
	 * friends) — WordPress's own user cache makes get_userdata() cheap
	 * for the same small set of agents repeated across a page of rows.
	 */
	private static function owner_names_by_id( array $rows ): array {
		$names = [];
		foreach ( $rows as $row ) {
			$owner_id = (int) $row['user_id'];
			if ( isset( $names[ $owner_id ] ) ) {
				continue;
			}
			$user = get_userdata( $owner_id );
			$names[ $owner_id ] = $user ? $user->display_name : __( '(deleted user)', 'cornerstone-crm' );
		}
		return $names;
	}
}
