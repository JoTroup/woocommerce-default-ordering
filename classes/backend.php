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

		// Hook into parse_query to modify WooCommerce admin order list
		add_action('parse_query', [&$this, 'action_parse_query'], 10, 1);
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
		$menu1_section1 = $this->plugin->setPrefix("wdo_section1");

		// Add a section for the settings page
		add_settings_section(
			$menu1_section1,
			__("WooCommerce Default Ordering", $this->plugin->config["textDomain"]),
			function() {
				esc_html_e("Configure the default ordering for WooCommerce admin order list.", $this->plugin->config["textDomain"]);
			},
			$woo_default_order
		);

		// Add a field for the 'admin_orderby' setting
		add_settings_field(
			$this->plugin->setPrefix("admin_orderby"),
			__("Admin Order By", $this->plugin->config["textDomain"]),
			function() {
				// Get the current value or default to 'date'
				$value = $this->plugin->getOption('admin_orderby', 'date');
				?>
				<select name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_orderby]">
					<option value="date" <?php selected($value, 'date'); ?>><?php esc_html_e('Date', $this->plugin->config["textDomain"]); ?></option>
					<option value="title" <?php selected($value, 'title'); ?>><?php esc_html_e('Title', $this->plugin->config["textDomain"]); ?></option>
					<option value="ID" <?php selected($value, 'ID'); ?>><?php esc_html_e('ID', $this->plugin->config["textDomain"]); ?></option>
					<option value="modified" <?php selected($value, 'modified'); ?>><?php esc_html_e('Last Modified', $this->plugin->config["textDomain"]); ?></option>
				</select>
				<?php
			},
			$woo_default_order,
			$menu1_section1
		);

		// Register the settings
		register_setting(
			$this->plugin->setPrefix("options"),
			$this->plugin->setPrefix("options") // Add the option name here
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

	/**
	 * Modify WooCommerce admin order list query.
	 */
	public function action_parse_query($query) {
		global $pagenow;

		// Initialize
		$query_vars = &$query->query_vars;

		// Only on WooCommerce admin order list
		if (is_admin() && $query->is_main_query() && $pagenow == 'edit.php' && $query_vars['post_type'] == 'shop_order') {
			 // Get the 'orderby' value from plugin settings
			$orderby = $this->plugin->getOption('admin_orderby', 'date'); // Default to 'date' if not set

			// Set order by the retrieved value in ascending order
			$query->set('orderby', $orderby);
			$query->set('order', 'ASC');
		}
	}
	
}
