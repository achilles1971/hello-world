<?php
/**
 * Thin wrapper around the two Google HTTP APIs this module needs: the
 * OAuth 2.0 token endpoint and the Gmail API. Uses WordPress's HTTP API
 * (wp_remote_*) rather than raw cURL, and returns WP_Error on any
 * failure so callers follow the same error-handling convention as the
 * rest of the plugin's data layer.
 *
 * Deliberately requests only the gmail.metadata scope — headers
 * (recipient, subject, date) and account profile, never message bodies.
 * The CRM only needs to prove "an email was sent to this contact," not
 * read its contents, and a narrower scope means less damage if a token
 * were ever compromised, and no Google app-verification review for
 * sensitive scopes when the OAuth consent screen is set to Internal.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Client {

	public const SCOPE = 'https://www.googleapis.com/auth/gmail.metadata';

	private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	private const API_BASE       = 'https://gmail.googleapis.com/gmail/v1/users/me';

	/** Gmail API calls under gmail.metadata still require this header. */
	private const METADATA_QUERY = '?format=metadata&metadataHeaders=To&metadataHeaders=Cc&metadataHeaders=Subject&metadataHeaders=Date';

	public static function authorize_url( string $client_id, string $redirect_uri, string $state ): string {
		return add_query_arg(
			[
				'client_id'              => $client_id,
				'redirect_uri'           => $redirect_uri,
				'response_type'          => 'code',
				'scope'                  => self::SCOPE,
				'access_type'            => 'offline',
				// Forces Google to hand back a refresh_token even if this
				// agent authorized before — without it, a reconnect after
				// a revoke would silently fail to grant one.
				'prompt'                 => 'consent',
				'include_granted_scopes' => 'true',
				'state'                  => $state,
			],
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	/**
	 * Exchanges an authorization code for tokens.
	 *
	 * @return array{access_token: string, refresh_token: string, expires_in: int}|WP_Error
	 */
	public static function exchange_code( string $client_id, string $client_secret, string $code, string $redirect_uri ): array|WP_Error {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			[
				'timeout' => 15,
				'body'    => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
				],
			]
		);

		$data = self::decode_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) || empty( $data['refresh_token'] ) ) {
			return new WP_Error( 'cornerstone_gmail_no_refresh_token', __( 'Google did not return a refresh token. Disconnect any prior authorization for this app in your Google Account and try connecting again.', 'cornerstone-crm' ) );
		}

		return [
			'access_token'  => (string) $data['access_token'],
			'refresh_token' => (string) $data['refresh_token'],
			'expires_in'    => (int) ( $data['expires_in'] ?? 3600 ),
		];
	}

	/**
	 * Exchanges a stored refresh token for a fresh short-lived access
	 * token. Called once per agent at the start of every sync run rather
	 * than caching access tokens — they're only valid ~1 hour and syncs
	 * run every 15–30 minutes, so caching would save one HTTP call at the
	 * cost of a second secret to store and encrypt.
	 *
	 * @return array{access_token: string, expires_in: int}|WP_Error
	 */
	public static function refresh_access_token( string $client_id, string $client_secret, string $refresh_token ): array|WP_Error {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			[
				'timeout' => 15,
				'body'    => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				],
			]
		);

		$data = self::decode_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['access_token'] ) ) {
			return new WP_Error( 'cornerstone_gmail_refresh_failed', __( 'Google rejected the stored refresh token. The agent needs to reconnect Gmail.', 'cornerstone-crm' ) );
		}

		return [
			'access_token' => (string) $data['access_token'],
			'expires_in'   => (int) ( $data['expires_in'] ?? 3600 ),
		];
	}

	/**
	 * @return array{emailAddress: string}|WP_Error
	 */
	public static function get_profile( string $access_token ): array|WP_Error {
		return self::get( '/profile', $access_token );
	}

	/**
	 * Lists Sent-mail message IDs newer than $after_timestamp, capped at
	 * $max_results across all pages so one sync run can't run unbounded.
	 *
	 * @return array<int, string>|WP_Error Gmail message IDs.
	 */
	public static function list_sent_message_ids( string $access_token, int $after_timestamp, int $max_results = 300 ): array|WP_Error {
		$ids         = [];
		$page_token  = '';

		do {
			$args = [
				'q'         => 'in:sent after:' . $after_timestamp,
				'maxResults' => 100,
			];
			if ( '' !== $page_token ) {
				$args['pageToken'] = $page_token;
			}

			$path   = '/messages?' . http_build_query( $args );
			$result = self::get( $path, $access_token );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( (array) ( $result['messages'] ?? [] ) as $message ) {
				if ( ! empty( $message['id'] ) ) {
					$ids[] = (string) $message['id'];
				}
			}

			$page_token = (string) ( $result['nextPageToken'] ?? '' );
		} while ( '' !== $page_token && count( $ids ) < $max_results );

		return array_slice( $ids, 0, $max_results );
	}

	/**
	 * Fetches header metadata only (To, Cc, Subject, Date) for one
	 * message — never the body, per the gmail.metadata scope this module
	 * requests.
	 *
	 * @return array{id: string, internalDate: string, payload: array}|WP_Error
	 */
	public static function get_message_metadata( string $access_token, string $message_id ): array|WP_Error {
		return self::get( '/messages/' . rawurlencode( $message_id ) . self::METADATA_QUERY, $access_token );
	}

	private static function get( string $path, string $access_token ): array|WP_Error {
		$response = wp_remote_get(
			self::API_BASE . $path,
			[
				'timeout' => 15,
				'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
			]
		);

		return self::decode_response( $response );
	}

	/**
	 * Shared response handling for every call above: network failure,
	 * non-2xx status, or unparseable JSON all become a WP_Error so
	 * callers never have to guess which shape a failure took.
	 */
	private static function decode_response( $response ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error_description'] ?? $data['error']['message'] ?? $data['error'] ?? '' ) : '';
			return new WP_Error( 'cornerstone_gmail_api_error', $message ?: sprintf( /* translators: %d: HTTP status code */ __( 'Google API returned HTTP %d.', 'cornerstone-crm' ), $code ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'cornerstone_gmail_bad_response', __( 'Unexpected response from Google.', 'cornerstone-crm' ) );
		}

		return $data;
	}
}
