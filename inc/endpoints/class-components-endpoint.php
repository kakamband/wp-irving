<?php
/**
 * Class file for Components endpoint.
 *
 * @package WP_Irving
 */

namespace WP_Irving\REST_API;

use WP_Irving\REST_API\Endpoint;

/**
 * Components endpoint.
 */
class Components_Endpoint extends Endpoint {

	/**
	 * Path being queried.
	 *
	 * @var string
	 */
	public $path = '';

	/**
	 * Static version of the path for accessing globally.
	 *
	 * @var string
	 */
	public static $irving_path = '';

	/**
	 * Context of request.
	 *
	 * @var string
	 */
	public $context = 'page';

	/**
	 * All request parameters.
	 *
	 * @var array
	 */
	public $params = [];

	/**
	 * All non-irving request parameters.
	 *
	 * @var array
	 */
	public $custom_params = [];

	/**
	 * Query string.
	 *
	 * @var string
	 */
	public $query_string = '';

	/**
	 * Query generated by path.
	 *
	 * @var null
	 */
	public $query = null;

	/**
	 * Data for response.
	 *
	 * @var array
	 */
	public $data = [
		'defaults'       => [],
		'page'           => [],
		'providers'      => [],
		'redirectTo'     => '',
		'redirectStatus' => 0,
	];

	/**
	 * Initialize class.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( 'rest_url', [ $this, 'fix_rest_url' ] );
		add_filter( 'query_vars', [ $this, 'modify_query_vars' ] );
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::get_namespace(),
			'/components/',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_route_response' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);
	}

	/**
	 * Callback for the route.
	 *
	 * @param  WP_REST_Request $request Request object.
	 *
	 * @return array
	 */
	public function get_route_response( $request ) {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		global $wp;

		/**
		 * Action fired on the request.
		 *
		 * @param \WP_REST_Request $request  WP_REST_Request object.
		 */
		do_action( 'wp_irving_components_request', $request );

		// Remove any request parameters that haven't been allow-listed by the
		// global $wp object's $public_query_vars array.
		$this->params = array_intersect_key(
			$request->get_params(),
			array_flip( $wp->public_query_vars )
		);

		// Parse path and context.
		$this->parse_path( $this->params['path'] ?? '' );
		$this->context = $this->params['context'] ?? '';

		// Pass any extra included params.
		$this->custom_params = array_filter(
			$this->params,
			function( $key ) {
				return ! in_array( $key, [ 'path', 'context' ], true );
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->query = $this->build_query();

		// Force trailing slashes on paths.
		$this->force_trailing_slashes();

		/**
		 * Modify the output of the components route.
		 *
		 * @param array           $data     Data for response.
		 * @param WP_Query        $query    WP_Query object corresponding to this
		 *                                  request.
		 * @param string          $context  The context for this request.
		 * @param string          $path     The path for this request.
		 * @param WP_REST_Request $request  WP_REST_Request object.
		 */
		$data = (array) apply_filters(
			'wp_irving_components_route',
			$this->data,
			$this->query,
			$this->context,
			$this->path,
			$request
		);

		// Create the response object.
		$response = new \WP_REST_Response( $data );

		// Add a custom status code, and handle redirects if needed.
		if ( $this->query->is_404() ) {
			$status = 404;
		} else {
			$status = 200;
		}

		$status = apply_filters( 'wp_irving_components_route_status', $status );
		$response->set_status( $status );

		return $response;
	}

	/**
	 * Execute filters and actions for the path.
	 *
	 * @param  string $raw_path Raw path from request.
	 */
	public function parse_path( string $raw_path = '' ) {

		/**
		 * Action fired on the raw path value.
		 *
		 * @param  string $raw_path Raw path value from request.
		 */
		do_action( 'wp_irving_components_raw_path', $raw_path );

		/**
		 * Modify the output of the components route.
		 *
		 * @param  string $raw_path Raw path value from request.
		 */
		$this->path        = (string) apply_filters( 'wp_irving_components_path', $raw_path );
		self::$irving_path = $this->path;

		/**
		 * Action fired on the sanitized path value.
		 *
		 * @param  string $raw_path Raw path value from request.
		 */
		do_action( 'wp_irving_components_path', $this->path );
	}

	/**
	 * Returns a WP_Query object based on path.
	 *
	 * @return \WP_Query Resulting query.
	 */
	public function build_query() {
		global $wp_rewrite, $wp_the_query;

		// Query to execute.
		$query = '';

		// Get path, remove leading slash.
		$trimmed_path = ltrim( $this->path, '/' );

		// Loop through rewrite rules.
		$rewrites = ! empty( $wp_rewrite->wp_rewrite_rules() ) ? $wp_rewrite->wp_rewrite_rules() : [];

		// Loop through rewrites to find a match.
		// Roughly based on core's WP::parse_request().
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// @see https://github.com/WordPress/WordPress/blob/master/wp-includes/class-wp.php#L216-L243
		foreach ( $rewrites as $match => $rewrite_query ) {

			// Rewrite rule match.
			if ( preg_match( "#^$match#", $trimmed_path, $matches ) ) {

				// Handle Pages differently.
				if ( preg_match( '/pagename=\$matches\[([0-9]+)\]/', $rewrite_query, $varmatch ) ) {

					/**
					 * Allow the page type used to check a root path for a valid
					 * page to be modified.
					 *
					 * @param string $post_type Post type to use as a page check.
					 */
					$page_type = apply_filters( 'wp_irving_page_type', 'page' );

					// This is a verbose page match, let's check to be sure about it.
					$page = get_page_by_path( $matches[ $varmatch[1] ], OBJECT, $page_type );

					if ( ! $page ) {
						continue;
					}

					// Ensure that this post type is publicly queryable.
					$post_status_obj = get_post_status_object( $page->post_status );
					if (
						! $post_status_obj->public &&
						! $post_status_obj->protected &&
						! $post_status_obj->private &&
						$post_status_obj->exclude_from_search
					) {
						continue;
					}
				}

				// Prep query for use in WP_Query.
				$query = preg_replace( '!^.+\?!', '', $rewrite_query );
				$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );
				parse_str( $query, $perma_query_vars );

				// Temporarily convert the args into an array.
				$args = [];
				parse_str( $query, $args );

				// Ensure custom post types get mapped correctly. Loop through
				// all post types, ensure they're viewable, then map the
				// post_type and name appropriately.
				foreach ( get_post_types( [], 'objects' ) as $post_type_object ) {
					if ( is_post_type_viewable( $post_type_object ) && $post_type_object->query_var ) {
						if ( isset( $args[ $post_type_object->query_var ] ) ) {
							$args['post_type'] = $post_type_object->query_var;
							$args['name']      = $args[ $post_type_object->query_var ];
						}
					}
				}

				// Convert the array back into a string.
				$query = add_query_arg(
					$args,
					$query
				);

				break;
			}
		}

		if (
			! empty( $query ) ||
			! empty( $this->params['s'] ?? '' ) ||
			! empty( $this->params['preview'] ?? '' )
		) {

			// Fix an issue where `add_query_arg` doesn't work with already
			// encoded strings missing the equal.
			if ( false === strpos( $query, '=' ) ) {
				$query .= '=';
			}

			// Add irving-path to the query.
			$query = add_query_arg(
				[
					'irving-path'        => $this->path,
					'irving-path-params' => $this->custom_params,
				],
				$query
			);

			// Add any extra included params.
			foreach ( $this->custom_params as $key => $value ) {
				$query = add_query_arg( $key, $value, $query );
			}

			// add_query_arg will encode the url, which we don't want.
			$query = urldecode( $query );
		}

		/**
		 * Modify the query vars.
		 *
		 * @param string $query                Query string from path parsing.
		 * @param string $this->path           Request path.
		 * @param string $this->custom_params  Custom params.
		 * @param string $this->params         Request params.
		 */
		$query              = apply_filters( 'wp_irving_components_query_string', $query, $this->path, $this->custom_params, $this->params );
		$this->query_string = $query;

		// Execute query.
		$wp_query = new \WP_Query( $query );

		if ( '/' === $this->path && ! $wp_query->is_search() ) {
			$wp_query->is_home = true;
		}

		if ( empty( $wp_query->posts ) && ! $wp_query->is_search() && ! $wp_query->is_home() ) {
			$wp_query->set_404();
		}

		/**
		 * Modify the executed query.
		 *
		 * @param \WP_Query $query                WP_Query object corresponding
		 *                                        to this request.
		 * @param string    $this->path           Request path.
		 * @param string    $this->custom_params  Custom params.
		 * @param string    $this->params         Request params.
		 */
		$wp_query = apply_filters( 'wp_irving_components_wp_query', $wp_query, $this->path, $this->custom_params, $this->params );

		// Map to main query and set up globals.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		$wp_the_query = $wp_query;
		$this->register_globals();

		return $wp_query;
	}

	/**
	 * Permissions check.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) {

		/**
		 * Filter the permissions check.
		 *
		 * @param bool|\WP_Error   $retval  Returned value.
		 * @param \WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'wp_irving_components_route_permissions_check', true, $request );
	}

	/**
	 * Set up the WordPress Globals. Mimic Core setup.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/class-wp.php#L580
	 */
	public function register_globals() {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		global $wp_the_query, $wp_query;
		$wp_query = $wp_the_query;

		// Extract updated query vars back into global namespace.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		foreach ( (array) $wp_the_query->query_vars as $key => $value ) {
			$GLOBALS[ $key ] = $value;
		}

		$GLOBALS['query_string'] = $this->query_string;
		$GLOBALS['posts']        = & $wp_the_query->posts;
		$GLOBALS['post']         = isset( $wp_the_query->post ) ? $wp_the_query->post : null;
		$GLOBALS['request']      = $this->path;

		if ( $wp_the_query->is_single() || $wp_the_query->is_page() ) {
			$GLOBALS['more']   = 1;
			$GLOBALS['single'] = 1;
		}

		if ( $wp_the_query->is_author() && isset( $wp_the_query->post ) ) {
			$GLOBALS['authordata'] = get_userdata( $wp_the_query->post->post_author );
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	}

	/**
	 * Fix rest url. Specifically, filter it to use site_url instead of
	 * home_url, which is what it uses by default. With a decoupled FE,
	 * home_url and site_url are no longer interchangeable.
	 *
	 * @see https://github.com/WordPress/gutenberg/issues/1761
	 *
	 * @param string $url Rest URL.
	 * @return string
	 */
	public function fix_rest_url( $url ) : string {
		return str_replace( home_url(), site_url(), $url );
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Array of current query vars.
	 * @return array $vars Array of query vars.
	 */
	public function modify_query_vars( $vars ) {
		$vars[] = 'context';
		$vars[] = 'path';
		$vars[] = 'irving-path';
		$vars[] = 'irving-path-params';
		return $vars;
	}

	/**
	 * Get the WP Irving API endpoint for a specific URL.
	 *
	 * @param string $url     URL.
	 * @param string $context The context of the request, either `page` or `site`.
	 * @return string
	 */
	public static function get_wp_irving_api_url( $url, $context = 'page' ) {

		// Get the path.
		$path = str_replace( get_home_url(), '', $url );

		// Ensure context is a valid value.
		$context = ( 'site' === $context )
			? 'site'
			: 'page';

		return add_query_arg(
			[
				'context' => $context,
				'path'    => $path,
			],
			rest_url( 'irving/v1/components' )
		);
	}

	/**
	 * Force trailing slashes on all non-404, and non-file requests.
	 */
	public function force_trailing_slashes() {

		// Return if there is already a trailing slash, or no posts were
		// returned by the path's query.
		if (
			trailingslashit( $this->path ) === $this->path
			|| ! $this->query->have_posts()
		) {
			return;
		}

		// Apply a trailing slash to the path.
		$this->params['path'] = trailingslashit( $this->path );

		// Apply all params to the rest url for this endpoint.
		$request_url = add_query_arg(
			$this->params,
			rest_url( 'irving/v1/components' )
		);

		// Set headers and redirect permanently.
		header( 'Access-Control-Allow-Origin: ' . home_url() );
		header( 'Access-Control-Allow-Credentials: true' );
		wp_safe_redirect( $request_url, 301 );
		exit;
	}
}
