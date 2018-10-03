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
	 * Utility function for checking the number of recommended actions uncompleted
	 */
	public function recommended_actions_left() {

		$actions_left = 0;
		$nb_of_actions = 0;
		$plugin_helper = new Ti_About_Plugin_Helper();

		foreach( $this->config as $index => $content ) {
			if ( isset( $content['type'] ) && $content['type'] === 'recommended_actions' ) {
				$plugins = $content['plugins'];
				break;
			}
		}

		if ( ! empty( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				$nb_of_actions += 1;
				if ( $plugin_helper->check_plugin_state( $plugin['slug'] ) !== 'deactivate' ) {
					$actions_left += 1;
				}
			}
		}

		if ( $actions_left !== $nb_of_actions ) {
			$this->required_actions =  $actions_left;
			return;
		}

		$this->required_actions = 0;
		return;
	}

	/**
	 * Load css and scripts for the about page
	 */
	public function enqueue() {
		$screen = get_current_screen();
		$theme = $this->theme_args;
		$menu_name = __( 'About', 'text-domain') . ' ' . $theme['name'];
		
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
				'menu_name'           => $menu_name,
				'nr_actions_required' => $this->required_actions,
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'template_directory'  => get_template_directory_uri(),
				'activating_string'   => esc_html__( 'Activating', 'textdomain' ),
			)
		);

		wp_enqueue_script( 'ti-about-scripts' );
		Ti_About_Plugin_Helper::instance()->enqueue_scripts();

	}
}