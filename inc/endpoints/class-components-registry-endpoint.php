<?php
/**
 * Class file for Components Registry endpoint.
 *
 * @package WP_Irving
 */

namespace WP_Irving\REST_API;

use WP_Irving\REST_API\Endpoint;
use WP_Irving\Components;

/**
 * Components Registry endpoint.
 */
class Components_Registry_Endpoint extends Endpoint {

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::get_namespace(),
			'/registered-components/',
			[
				'callback'            => function() {
					return Components\get_registry()->get_registered_components();
				},
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
			]
		);
	}
}
