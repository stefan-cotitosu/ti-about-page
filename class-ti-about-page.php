<?php
/**
 * ThemeIsle - About page class
 *
 * @package ti-about-page
 */

/**
 * Class Ti_About_Page_Main
 *
 * @package Themeisle
 */
class Ti_About_Page {

	/**
	 * Current theme args
	 */
	private $theme_args = array();

	/**
	 * About page content that should be rendered
	 */
    private $config = array();

	/**
	 * Recommended actions uncompleted
	 */
    private $required_actions = 0;

	/**
	 * About Page instance
	 */
    private static $instance;

	/**
	 * The Main Themeisle_About_Page instance.
	 *
	 * We make sure that only one instance of Themeisle_About_Page exists in the memory at one time.
	 * @param array $config The configuration array.
	 */
	public static function init( $config ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Ti_About_Page ) ) {
			self::$instance = new Ti_About_Page();
			if ( ! empty( $config ) && is_array( $config ) ) {
				self::$instance->config = apply_filters( 'ti_about_config_filter', $config ) ;
				self::$instance->setup_config();
				self::$instance->setup_actions();
				self::$instance->set_recommended_plugins_visibility();
				self::$instance->recommended_actions_left();
			}
		}
	}

	/**
	 * Setup the class props based on current theme
	 */
	private function setup_config() {

		$theme = wp_get_theme();

		$this->theme_args['name']        = $theme->__get( 'Name' );
		$this->theme_args['version']     = $theme->__get( 'Version' );
		$this->theme_args['description'] = $theme->__get( 'Description' );
		$this->theme_args['slug']        = $theme->__get( 'stylesheet' );
	}

	/**
	 * Setup the actions used for this page.
	 */
	public function setup_actions() {

		add_action( 'admin_menu', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_update_recommended_plugins_visibility', array( $this, 'update_recommended_plugins_visibility') );
	}

	/**
	 * Register the menu page under Appearance menu.
	 */
	public function register() {
		$theme = $this->theme_args;

		if ( empty( $theme['name'] ) || empty( $theme['slug'] ) ) {
			return;
		}

		$menu_name = __( 'About', 'text-domain') . ' ' . $theme['name'];
		if ( $this->required_actions > 0 ) {
			$menu_name .= '<span class="badge-action-count">' . esc_html( $this->required_actions ) . '</span>';
		}

			add_theme_page(
				$menu_name,
				$menu_name,
				'activate_plugins',
				$theme['slug'] . '-welcome',
				array(
					$this,
					'render',
				)
			);
	}

	/**
	 * Instantiate the render class which will render all the tabs based on config
	 */
	public function render() {
		require_once 'class-ti-about-render.php';
		new TI_About_Render( $this->theme_args, $this->config );
	}

	/**
	 * Load css and scripts for the about page
	 */
	public function enqueue() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return;
		}

		if ( $screen->id !== 'appearance_page_' . $this->theme_args['slug'] . '-welcome' ) {
			return;
		}

		wp_enqueue_style( 'ti-about-style', TI_ABOUT_PAGE_URL . '/css/style.css', array(), TI_ABOUT_PAGE_VERSION );

		wp_register_script( 'ti-about-scripts', TI_ABOUT_PAGE_URL . '/js/ti_about_page_scripts.js', array( 'jquery', 'jquery-ui-tabs' ), TI_ABOUT_PAGE_VERSION, true );



		wp_localize_script(
			'ti-about-scripts',
			'tiAboutPageObject',
			array(
				'nr_actions_required' => $this->required_actions,
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'template_directory'  => get_template_directory_uri(),
				'activating_string'   => esc_html__( 'Activating', 'textdomain' ),
			)
		);

		wp_enqueue_script( 'ti-about-scripts' );
		Ti_About_Plugin_Helper::instance()->enqueue_scripts();
	}

	/**
	 * Utility function for checking the number of recommended actions uncompleted
	 */
	public function recommended_actions_left() {

		$nb_of_actions = 0;
		$actions_left = 0;
		$recommended_plugins = get_option( 'recommended_plugins' );

		if( ! empty( $recommended_plugins ) ) {
			foreach ( $recommended_plugins as $slug => $visibility ) {
				if( $recommended_plugins[$slug] === 'visible' ) {
					$nb_of_actions += 1;

					if ( Ti_About_Plugin_Helper::instance()->check_plugin_state( $slug ) !== 'deactivate' ) {
						$actions_left += 1;
					}
				}
			}
		}

		var_dump( 'total actions ' . $nb_of_actions );
		var_dump( 'actions left ' . $actions_left );
		if ( $actions_left > 0 ) {
			$this->required_actions = $actions_left;
		}
	}

	public function get_recommended_plugins() {
		foreach( $this->config as $index => $content ) {
			if ( isset( $content['type'] ) && $content['type'] === 'recommended_actions' ) {
				$plugins = $content['plugins'];
				return $plugins;
				break;
			}
		}
		return array();
	}

	public function set_recommended_plugins_visibility() {

		if ( ! empty( get_option( 'recommended_plugins' ) ) ) {
			return;
		}

		$required_plugins = $this->get_recommended_plugins();
		$required_plugins_visbility = array();
		foreach ( $required_plugins as $slug => $req_plugin ) {
			$required_plugins_visbility[$slug] = 'visible';
		}

		update_option( 'recommended_plugins', $required_plugins_visbility );
	}

	public function update_recommended_plugins_visibility() {

		$recommended_plugins = get_option( 'recommended_plugins' );

		$plugin_to_update = $_POST['slug'];
		$recommended_plugins[$plugin_to_update] = 'hidden';

		update_option( 'recommended_plugins', $recommended_plugins );

		$this->recommended_actions_left();
		$required_actions_left = array( 'required_actions', $this->required_actions );
	wp_send_json( $required_actions_left );
		die();
	}
}