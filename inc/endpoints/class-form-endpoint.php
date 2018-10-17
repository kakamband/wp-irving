<?php
/**
 * Class file for form endpoints.
 *
 * @package WP_Irving
 */

namespace WP_Irving\REST_API;

/**
 * Form Endpoint.
 */
class Form_Endpoint extends Endpoint {
	/**
	 * Attach to required hooks for form endpoint
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		/**
		 * Modify the output of the components route.
		 *
		 * @param array $form_endpoints {
		 *     Form endpoint slugs and callback functions.
		 *
		 *     @type string $slug The slug for the form endpoint.
		 *     @type string $callback response callback to use when the endpoint is called.
		 * }
		 */
		$form_endpoints = (array) apply_filters( 'wp_irving_form_endpoints', [] );

		if ( empty( $form_endpoints ) ) {
			return;
		}

		foreach ( $form_endpoints as $idx => $endpoint ) {
			register_rest_route(
				$this->namespace,
				'/form/' . $endpoint['slug'],
				[
					'methods'  => \WP_REST_Server::CREATABLE,
					'callback' => $endpoint['callback'],
				]
			);
		}
	}
}

new Form_Endpoint();