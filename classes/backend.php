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
	private $isOrderPage = false;
	private $isSettingsPage = false;
	
	private $pages = [];
	public function __construct($instance) {
		$this->plugin = $instance;
		
		if(current_user_can('manage_options'))
			add_action('admin_menu', [&$this, 'register_admin_menu']);
		
		add_action('admin_init', [&$this, 'register_settings']);
		
		add_action('current_screen', [$this, 'wpdocs_this_screen']);


		add_filter('woocommerce_orders_table_query_clauses',[$this, 'action_parse_query'], 25);

		add_action('woocommerce_order_list_table_prepare_items_query_args', [$this, 'hide_orders_by_status_for_role'], 25);

		add_action( 'current_screen', function( $screen ) {

			$this->plugin->debug('[action_parse_query] Screen ID ' . $screen->id);

			if ( $screen->id === 'woocommerce_page_wc-orders' ) {
				$this->plugin->debug('[action_parse_query] Function triggered.');
				$this->isOrderPage = true;

				// You're on the WooCommerce Orders admin page
				// You can now hook into parse_query or modify filters
			} else {
				$this->plugin->debug('[action_parse_query] bypass triggered.');
				$this->isOrderPage = false;
			}
		});
		
		

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
				$options = get_option($this->plugin->setPrefix("options"), []);
				$value = isset($options['admin_orderby']) ? $options['admin_orderby'] : 'date';
				$custom_value = isset($options['admin_orderby_custom']) ? $options['admin_orderby_custom'] : '';
				?>
				<select name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_orderby]" id="admin_orderby">
					<option value="date" <?php selected($value, 'date'); ?>><?php esc_html_e('Date', $this->plugin->config["textDomain"]); ?></option>
					<option value="title" <?php selected($value, 'title'); ?>><?php esc_html_e('Title', $this->plugin->config["textDomain"]); ?></option>
					<option value="ID" <?php selected($value, 'ID'); ?>><?php esc_html_e('ID', $this->plugin->config["textDomain"]); ?></option>
					<option value="modified" <?php selected($value, 'modified'); ?>><?php esc_html_e('Last Modified', $this->plugin->config["textDomain"]); ?></option>
					<option value="custom" <?php selected($value, 'custom'); ?>><?php esc_html_e('Custom', $this->plugin->config["textDomain"]); ?></option>
				</select>
				<div id="custom_orderby_field" style="margin-top: 10px; <?php echo $value === 'custom' ? '' : 'display: none;'; ?>">
					<label for="admin_orderby_custom"><?php esc_html_e('Custom Order By:', $this->plugin->config["textDomain"]); ?></label>
					<input type="text" name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_orderby_custom]" id="admin_orderby_custom" value="<?php echo esc_attr($custom_value); ?>" />
				</div>
				<script>
					(function() {
						const selectField = document.getElementById('admin_orderby');
						const customField = document.getElementById('custom_orderby_field');
						selectField.addEventListener('change', function() {
							if (this.value === 'custom') {
								customField.style.display = 'block';
							} else {
								customField.style.display = 'none';
							}
						});
					})();
				</script>
				<?php
			},
			$woo_default_order,
			$menu1_section1
		);

		// Add a field for the 'admin_filterStatus' setting
		add_settings_field(
			$this->plugin->setPrefix("admin_filterStatus"),
			__("Exclude Order Statuses", $this->plugin->config["textDomain"]),
			function() {
				$options = get_option($this->plugin->setPrefix("options"), []);
				$selected_statuses = isset($options['admin_filterStatus']) ? (array) $options['admin_filterStatus'] : [];
				$statuses = wc_get_order_statuses(); // Get all WooCommerce order statuses

				?>
				<select name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_filterStatus][]" id="admin_filterStatus" multiple style="width: 100%; height: auto;">
					<?php foreach ($statuses as $status_key => $status_label): ?>
						<option value="<?php echo esc_attr($status_key); ?>" <?php echo in_array($status_key, $selected_statuses) ? 'selected' : ''; ?>>
							<?php echo esc_html($status_label); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e('Select the order statuses to exclude from the WooCommerce order view.', $this->plugin->config["textDomain"]); ?></p>
				<?php
			},
			$woo_default_order,
			$menu1_section1
		);

		// Register the settings
		register_setting(
			$this->plugin->setPrefix("options"),
			$this->plugin->setPrefix("options"), // Ensure the correct option key is registered
			[
				'default' => [
					'admin_orderby' => 'date', // Set default value for admin_orderby
				],
			]
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
	public function action_parse_query ($clauses) {

		global $wpdb;

		// Ensure this is the main query and for the WooCommerce orders page
		$this->plugin->debug('[action_parse_query] Function triggered.');

		$this->plugin->debug('[action_parse_query] Is Order Page? ' . ($this->isOrderPage ? 'Yes' : 'No'));
		$this->plugin->debug('[action_parse_query] $clauses: ' . print_r($clauses, true));
		$clauses['order_by'] = "{$wpdb->prefix}wc_orders.id ASC";

		return $clauses;
	}

	/**
	 * Hide orders by status for specific roles.
	 */
	public function hide_orders_by_status_for_role($query_args) {
		// Modify the query to order by order ID



		$options = get_option($this->plugin->setPrefix("options"), []);
		$orderby = isset($options['admin_orderby']) ? $options['admin_orderby'] : 'ID';
		$query_args['orderby'] = $orderby;
		$query_args['order'] = 'ASC';

		// Filter orders by excluded statuses
		if (!empty($options['admin_filterStatus'])) {
			$excluded_statuses = (array) $options['admin_filterStatus'];
			$all_statuses = array_keys(wc_get_order_statuses()); // Get only the keys (status slugs)
			$query_args['status'] = array_diff($all_statuses, $excluded_statuses);
		}

		return $query_args;
	}


	
}



