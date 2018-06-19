<?php
/**
 * Class file for Components endpoint.
 *
 * @package WP_Irving
 */

namespace WP_Irving\REST_API;

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
	 * Context of request.
	 *
	 * @var string
	 */
	public $context = 'page';

	/**
	 * Query generated by path.
	 *
	 * @var null
	 */
	public $query = null;

	/**
	 * Response of request.
	 *
	 * @var array
	 */
	public $response = [
		'defaults' => [],
		'page'     => [],
	];

	/**
	 * Initialize class.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'post_row_actions', [ $this, 'add_api_link' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_api_link' ], 10, 2 );
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/components/',
			[
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'get_route_response' ],
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
		$this->path    = $request->get_param( 'path' ) ?? '';
		$this->context = $request->get_param( 'context' ) ?? '';
		$this->query   = $this->get_query_by_path( $this->path );

		/**
		 * Modify the output of the components route.
		 *
		 * @param Array           $response The response of this request.
		 * @param WP_Query        $query    WP_Query object corresponding to this
		 *                                  request.
		 * @param string          $context  The context for this request.
		 * @param string          $path     The path for this request.
		 * @param WP_REST_Request $request  WP_REST_Request object.
		 */
		return (array) apply_filters(
			'wp_irving_components_route',
			$this->response,
			$this->query,
			$this->context,
			$this->path,
			$request
		);
	}

	/**
	 * Returns a WP_Query object based on path.
	 *
	 * @param  string $path Path of request.
	 * @return WP_Query Resulting query.
	 */
	public function get_query_by_path( $path ) {
		global $wp_rewrite;

		// Query to execute.
		$query = '';

		// Get path, remove leading slash.
		$path = ltrim( $path, '/' );

		// Loop through rewrite rules.
		$rewrites = $wp_rewrite->wp_rewrite_rules();
		foreach ( $rewrites as $match => $query ) {

			// Rewrite rule match.
			if ( preg_match( "#^$match#", $path, $matches ) ) {

				// Prep query for use in WP_Query.
				$query = preg_replace( '!^.+\?!', '', $query );
				$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );
				parse_str( $query, $perma_query_vars );
				break;
			}
		}

		return new \WP_Query( $query );
	}

	/**
	 * Add API endpoint link to post row actions.
	 *
	 * @param  array    $actions Action links.
	 * @param  \WP_Post $post    WP_Post object.
	 * @return array Updated action links.
	 */
	public function add_api_link( array $actions, \WP_Post $post ) : array {

		// Get post permalink.
		$permalink = get_permalink( $post );

		// Extract path.
		$path = wp_parse_url( $permalink, PHP_URL_PATH );

		// Apply path to rest URL for Irving components endpoint.
		$path_url = add_query_arg(
			'path',
			$path,
			rest_url( 'irving/v1/components' )
		);

		// Add new link.
		$actions['api'] = sprintf(
			'<a href="%1$s">API</a>',
			esc_url( $path_url )
		);

		return $actions;
	}
}

new Components_Endpoint();
