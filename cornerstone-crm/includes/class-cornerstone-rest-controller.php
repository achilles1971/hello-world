<?php
/**
 * Shared base for every Cornerstone REST controller.
 *
 * WordPress's REST API already validates the X-WP-Nonce header against
 * cookie authentication before routes run (rest_cookie_check_errors()),
 * but that only rejects a *bad* nonce — a request presenting no nonce at
 * all still resolves to a logged-out/unauthenticated context. We enforce
 * that explicitly here: every route requires a logged-in user, a valid
 * 'wp_rest' nonce, and the cornerstone_crm_access capability. Nothing in
 * this plugin's REST namespace is reachable by an unauthenticated request.
 */

defined( 'ABSPATH' ) || exit;

abstract class Cornerstone_REST_Controller {

	public const NAMESPACE_V1 = 'cornerstone-crm/v1';

	/**
	 * permission_callback for every route in this plugin. Registering a
	 * route without a permission_callback is how CRM data leaks to the
	 * public; every register_rest_route() call in this codebase must pass
	 * this method (or something stricter) explicitly.
	 */
	public static function permissions_check( WP_REST_Request $request ): true|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'cornerstone_rest_unauthorized', __( 'You must be logged in.', 'cornerstone-crm' ), [ 'status' => 401 ] );
		}

		$nonce      = $request->get_header( 'X-WP-Nonce' );
		$nonce_good = $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
		if ( ! $nonce_good ) {
			return new WP_Error( 'cornerstone_rest_bad_nonce', __( 'Invalid or missing nonce.', 'cornerstone-crm' ), [ 'status' => 403 ] );
		}

		if ( ! Cornerstone_Roles::can_access() ) {
			return new WP_Error( 'cornerstone_rest_forbidden', __( 'You do not have access to the CRM.', 'cornerstone-crm' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Converts a WP_Error into the matching WP_REST_Response so data-layer
	 * errors surface with their intended HTTP status instead of a generic
	 * 500.
	 */
	protected static function error_response( WP_Error $error ): WP_REST_Response {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		return new WP_REST_Response(
			[
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			],
			$status
		);
	}
}
