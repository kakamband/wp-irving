<?php
/**
 * WP Irving integration for Coral.
 *
 * @package WP_Irving;
 */

namespace WP_Irving\Integrations;

use WP_Irving\Singleton;

/**
 * Class to integrate Coral with Irving.
 */
class Coral {
	use Singleton;

	/**
	 * The option key for the integration.
	 *
	 * @var string
	 */
	private $option_key = 'coral';

	/**
	 * Holds the option values to be set.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Setup the singleton. Validate JWT is installed, and setup hooks.
	 */
	public function setup() {
		// Retrieve any existing integrations options.
        $this->options = get_option( 'irving_integrations' );

		// Register settings fields for integrations.
		add_action( 'admin_init', [ $this, 'register_settings_fields' ] );

		// Filter the updated option values prior to submission.
        add_filter( 'pre_update_option_irving_integrations', [ $this, 'group_and_format_options_for_storage' ] );

        $pico_sso_enabled = $this->options[ $this->option_key ]['pico_sso_enabled'] ?? false;
    
        if ( ! empty( $pico_sso_enabled ) ){
            // Expose data to the endpoint.
            add_filter(
                'wp_irving_data_endpoints',
                function ( $endpoints ) {
                    $endpoints[] = $this->get_endpoint_settings();

                    return $endpoints;
                }
            );
        }
	}

	/**
	 * Register settings fields for display.
	 */
	public function register_settings_fields() {
		// Register a new field for the Coral integration.
		add_settings_field(
			'wp_irving_ga_tracking_id',
			esc_html__( 'Coral SSO Secret', 'wp-irving' ),
			[ $this, 'render_coral_sso_secret_input' ],
			'wp_irving_integrations',
			'irving_integrations_settings'
		);
	}

	/**
	 * Render an input for the Coral SSO secret.
	 */
	public function render_coral_sso_secret_input() {
		// Check to see if there is an existing SSO secret in the option.
		$sso_secret = $this->options[ $this->option_key ]['sso_secret'] ?? '';

		?>
			<input type="text" name="irving_integrations[<?php echo esc_attr( 'sso_secret' ); ?>]" value="<?php echo esc_attr( $sso_secret ); ?>" />
		<?php
	}

	/**
	 * Loop through the updated options, group them by their integration's key,
	 * and remove any prefix set by the option's input.
	 *
	 * @param array $options The updated options.
	 * @return array The formatted options.
	 */
	public function group_and_format_options_for_storage( array $options ): array {
		$formatted_options = [];

		foreach ( $options as $key => $val ) {
			// Build the config array for Coral.
			if ( strpos( $key, 'ga_' ) !== false ) {
				$formatted_options[ $this->option_key ][ str_replace( 'ga_', '', $key ) ] = $val;
			}
		}

		return $formatted_options;
    }
    

    /**
     * Get the endpoint settings.
     *
     * @return array Endpoint settings.
     */
	public function get_endpoint_settings(): array {
		return [
			'slug'     => 'verify_pico_user',
			'callback' => [ $this, 'process_endpoint_request' ],
		];
	}

    /**
     * Get the data for the Pico user endpoint verification request.
     *
     * @param \WP_REST_Request $request The request object.
     */
	public function process_endpoint_request( \WP_REST_Request $request ) {
		// Allow access from the frontend.
		header( 'Access-Control-Allow-Origin: ' . home_url() );

		$user = $request->get_param( 'user' );

		if ( ! empty( $user ) ) {
			$credentials = [
				'jti'  => uniqid(),
				'exp'  => time() + (90 * 86400), // JWT will expire in 90 days.
				'iat'  => time(),
				'user' => [
					'id'       => '628bdc61-6616-4add-bfec-dd79156715d4', // This will be retrieved from the Pico verification response. Fix this later.
					'email'    => $user,
					'username' => explode( '@', $user ),
				],
			];

			// Build the JWT.
			$jwt = $base64_header . "." . $base64_payload . '.' . $base64_signature;

			return [
				'status' => 'success',
				'jwt'    => $jwt,
			];
		}

		return [ 'status' => 'failed' ];
	}

    /**
     * Construct a HS256-encrypted JWT for SSO authentication.
     *
     * @param array $credentials The user to be authenticated.
     * @return string The constructed JWT.
     */
	public function build_jwt( array $credentials ): string {
		// Define the JWT header and payload.
		$header     = json_encode( [ 'typ' => 'JWT', 'alg' => 'HS256' ] );
		$payload    = json_encode( $credentials );
		$secret     = $this->options[ $this->option_key ]['sso_secret'];

		// Base64 URL encode the header and payload.
		$base64_header  = $this->base64url_encode( $header );
		$base64_payload = $this->base64url_encode( $payload );

		// Generate the JWT signature.
		$signature = hash_hmac( 'sha256', $base64_header . '.' . $base64_payload, $secret, true );
		// Base64 URL encode the signature.
		$base64_signature = $this->base64url_encode( $signature );

		// Return the built JWT.
		return $base64_header . "." . $base64_payload . '.' . $base64_signature;
	}

    /**
     * Base64 URL encode a target data string.
     *
     * @param string $data The data to be encoded.
     * @return string The encoded data.
     */
	public function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
