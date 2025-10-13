<?php
/**
 * The backend(admin)-specific functionality of the plugin.
 * - admin menus
 * - admin pages (e.g. settings)
 * - stylesheets
 * - scripts
 *
 */
class wdo_Backend {
	private $plugin;
	private $settings;
	private $isSettingsPage = false;
	
	private $pages = [];
	public function __construct($instance) {
		$this->plugin = $instance;
		
		if(current_user_can('manage_options'))
			add_action('admin_menu', [&$this, 'register_admin_menu']);
		
		add_action('admin_init', [&$this, 'register_settings']);
		
		add_action('current_screen', [$this, 'wpdocs_this_screen']);
	}
	
	public function wpdocs_this_screen() {
		$current_screen = get_current_screen();
		$this->plugin->debug('[backend] Current Screen: ' . $current_screen->id);
		
		$this->isSettingsPage = in_array($current_screen->id, $this->pages);
		$this->plugin->debug('[backend] Is settings page? ' . ($this->isSettingsPage ? 'Yes' : 'No'));
	}
	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		if(!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.', $this->plugin->config['textDomain']));
		}


		/**
		 * Add a submenu page.
		 * https://developer.wordpress.org/reference/functions/add_submenu_page/
		 *
		 * Arguments:
		 * 1. Parent Slug
		 * 2. Page Title
		 * 3. Menu Title
		 * 4. Capability
		 * 5. Menu Slug (you will overwrite that menu item if specified slug is already exists)
		 * 6. Function to display page
		 * 7. Submenu Position (larger --> move down, smaller --> move up)
		 */
		$page = add_submenu_page(
			"options-general.php",
			__("Woo Default Ordering", $this->plugin->config["textDomain"]),
			__("WooCommerce Default Ordering", $this->plugin->config["textDomain"]),
			"manage_options",
			$this->plugin->setPrefix("woo-default-order"),
			[&$this, "render_page_woo_default_order"],
			10
		);
		array_push($this->pages, $page);
	}
	
	/**
	 * Register settings page(s), sections and fields.
	 */
	public function register_settings() {
		$this->settings = (array)$this->plugin->getOption('fields'); 
		
		$woo_default_order = $this->plugin->setPrefix("woo-default-order");
		/**
		 * Add a section for the settings page
		 * https://developer.wordpress.org/reference/functions/add_settings_section/
		 *
		 * Arguments:
		 * 1. id
		 * 2. title
		 * 3. callback
		 * 4. page custom (e.g. slug defined in custom menu) or existing wp page (e.g. reading --> Settings/Reading page)
		 */
		$menu1_section1 = $this->plugin->setPrefix("menu1_section1");
		add_settings_section(
			$menu1_section1,
			__("Block 1", $this->plugin->config["textDomain"]),
			function() {
				esc_html_e("Sample description for this block.", $this->plugin->config["textDomain"]);
			},
//			[&$this, "render_menu1_section1"],
			$woo_default_order
		);


	}
	/**
	 * Display the settings page for the menu(s) that have created.
	 */
	public function render_page_woo_default_order() {
		?>
		<div id="wrap">
			<form action="options.php" method="post">
				<?php
				// Render all sections of the page
				do_settings_sections($this->plugin->setPrefix("woo-default-order"));
	

				// Render fields
				settings_fields($this->plugin->setPrefix("options"));
	

				submit_button();
				?>
			</form>
		</div>
		<?php
	}


	
	/**
	 * Display sections
	 */
	public function render_menu1_section1() {
		esc_html_e("Section content", $this->plugin->config["textDomain"]); 
	}

	
}
