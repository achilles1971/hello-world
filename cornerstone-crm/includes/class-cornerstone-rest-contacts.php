<?php
/**
 * REST routes for /cornerstone-crm/v1/contacts.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_REST_Contacts extends Cornerstone_REST_Controller {

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/contacts',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'index' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
					'args'                => [
						'pipeline_status' => [ 'type' => 'string', 'required' => false ],
						'page'            => [ 'type' => 'integer', 'required' => false ],
						'per_page'        => [ 'type' => 'integer', 'required' => false ],
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'create' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/contacts/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'show' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ __CLASS__, 'update' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ __CLASS__, 'delete' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
				],
			]
		);
	}

	public static function index( WP_REST_Request $request ): WP_REST_Response {
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Contacts::list_for_scope(
			$user_id,
			$can_manage_all,
			[
				'pipeline_status' => sanitize_key( (string) $request->get_param( 'pipeline_status' ) ),
				'page'            => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page'        => absint( $request->get_param( 'per_page' ) ?: 20 ),
			]
		);

		return new WP_REST_Response( $result, 200 );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$contact = Cornerstone_Contacts::get( absint( $request->get_param( 'id' ) ), get_current_user_id(), Cornerstone_Roles::can_manage_all() );
		if ( null === $contact ) {
			return self::error_response( new WP_Error( 'cornerstone_not_found', __( 'Contact not found.', 'cornerstone-crm' ), [ 'status' => 404 ] ) );
		}
		return new WP_REST_Response( $contact, 200 );
	}

	public static function create( WP_REST_Request $request ): WP_REST_Response {
		$result = Cornerstone_Contacts::create( (array) $request->get_json_params() ?: $request->get_body_params(), get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		$contact = Cornerstone_Contacts::get( $result, get_current_user_id(), true );
		return new WP_REST_Response( $contact, 201 );
	}

	public static function update( WP_REST_Request $request ): WP_REST_Response {
		$id             = absint( $request->get_param( 'id' ) );
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Contacts::update( $id, (array) $request->get_json_params() ?: $request->get_body_params(), $user_id, $can_manage_all );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		return new WP_REST_Response( Cornerstone_Contacts::get( $id, $user_id, $can_manage_all ), 200 );
	}

	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		$id             = absint( $request->get_param( 'id' ) );
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Contacts::soft_delete( $id, $user_id, $can_manage_all );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
