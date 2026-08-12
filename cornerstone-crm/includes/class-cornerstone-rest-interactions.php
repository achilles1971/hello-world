<?php
/**
 * REST routes for /cornerstone-crm/v1/interactions.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_REST_Interactions extends Cornerstone_REST_Controller {

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/interactions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'index' ],
					'permission_callback' => [ __CLASS__, 'permissions_check' ],
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
			'/interactions/(?P<id>\d+)',
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

		$result = Cornerstone_Interactions::list_for_scope(
			$user_id,
			$can_manage_all,
			[
				'contact_id' => absint( $request->get_param( 'contact_id' ) ),
				'type'       => sanitize_key( (string) $request->get_param( 'type' ) ),
				'page'       => absint( $request->get_param( 'page' ) ?: 1 ),
				'per_page'   => absint( $request->get_param( 'per_page' ) ?: 20 ),
			]
		);

		return new WP_REST_Response( $result, 200 );
	}

	public static function show( WP_REST_Request $request ): WP_REST_Response {
		$row = Cornerstone_Interactions::get( absint( $request->get_param( 'id' ) ), get_current_user_id(), Cornerstone_Roles::can_manage_all() );
		if ( null === $row ) {
			return self::error_response( new WP_Error( 'cornerstone_not_found', __( 'Interaction not found.', 'cornerstone-crm' ), [ 'status' => 404 ] ) );
		}
		return new WP_REST_Response( $row, 200 );
	}

	public static function create( WP_REST_Request $request ): WP_REST_Response {
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Interactions::create( (array) $request->get_json_params() ?: $request->get_body_params(), $user_id, $can_manage_all );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		return new WP_REST_Response( Cornerstone_Interactions::get( $result, $user_id, true ), 201 );
	}

	public static function update( WP_REST_Request $request ): WP_REST_Response {
		$id             = absint( $request->get_param( 'id' ) );
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Interactions::update( $id, (array) $request->get_json_params() ?: $request->get_body_params(), $user_id, $can_manage_all );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		return new WP_REST_Response( Cornerstone_Interactions::get( $id, $user_id, $can_manage_all ), 200 );
	}

	public static function delete( WP_REST_Request $request ): WP_REST_Response {
		$id             = absint( $request->get_param( 'id' ) );
		$user_id        = get_current_user_id();
		$can_manage_all = Cornerstone_Roles::can_manage_all();

		$result = Cornerstone_Interactions::soft_delete( $id, $user_id, $can_manage_all );
		if ( is_wp_error( $result ) ) {
			return self::error_response( $result );
		}
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
