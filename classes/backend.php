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

		if (current_user_can('manage_options')) {
			add_action('admin_menu', [&$this, 'register_admin_menu']);
			add_action('admin_enqueue_scripts', [&$this, 'enqueue_admin_scripts']); // Enqueue scripts
		}

		add_action('admin_init', [&$this, 'register_settings']);
		add_action('current_screen', [$this, 'wpdocs_this_screen']);
		add_filter('woocommerce_orders_table_query_clauses', [$this, 'action_parse_query'], 25);
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
	
	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts($hook) {
		// Only enqueue scripts on the plugin's settings page
		if (strpos($hook, $this->plugin->setPrefix("woo-default-order")) === false) {
			return;
		}

		// Enqueue jQuery UI sortable
		wp_enqueue_script('jquery-ui-sortable');

		// Enqueue jQuery UI styles (optional, for better visuals)
		wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
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
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.', $this->plugin->config['textDomain']));
		}

		/**
		 * Add a submenu page under WooCommerce.
		 * https://developer.wordpress.org/reference/functions/add_submenu_page/
		 *
		 * Arguments:
		 * 1. Parent Slug
		 * 2. Page Title
		 * 3. Menu Title
		 * 4. Capability
		 * 5. Menu Slug
		 * 6. Function to display page
		 */
		$page = add_submenu_page(
			"woocommerce", // Parent slug for WooCommerce
			__("Woo Default Ordering", $this->plugin->config["textDomain"]),
			__("Default Ordering", $this->plugin->config["textDomain"]),
			"manage_options",
			$this->plugin->setPrefix("woo-default-order"),
			[&$this, "render_page_woo_default_order"]
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

				// Default WooCommerce order fields
				$fields = [
					'custom'    => __('Custom Field', $this->plugin->config["textDomain"]),
					'ID'        => __('Order ID', $this->plugin->config["textDomain"]),
					'date'      => __('Date Created', $this->plugin->config["textDomain"]),
					'modified'  => __('Last Modified', $this->plugin->config["textDomain"]),
					'title'     => __('Title', $this->plugin->config["textDomain"]),
					// Add more default fields if needed
				];
			
				// Get custom meta fields (added by plugins)
				global $wpdb;
				$meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order') LIMIT 50");
				if ($meta_keys) {
					foreach ($meta_keys as $meta_key) {
						if (!empty($meta_key) && !isset($fields[$meta_key])) {
							$fields[$meta_key] = $meta_key;
						}
					}
				}
				?>
				<select name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_orderby]" id="admin_orderby">
					<?php foreach ($fields as $field_key => $field_label): ?>
						<option value="<?php echo esc_attr($field_key); ?>" <?php selected($value, $field_key); ?>>
							<?php echo esc_html($field_label); ?>
						</option>
					<?php endforeach; ?>
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
				<script>
					(function() {
						const selectField = document.getElementById('admin_orderby');
						const customField = document.getElementById('custom_orderby_field');
						const customInput = document.getElementById('admin_orderby_custom');
						selectField.addEventListener('change', function() {
							if (this.value === 'custom') {
								customField.style.display = 'block';
							} else {
								customField.style.display = 'none';
								customInput.value = ''; // Reset custom field when not selected
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
			__("Filter Status", $this->plugin->config["textDomain"]),
			function() {
				$options = get_option($this->plugin->setPrefix("options"), []);
				$excluded_statuses = [];

				// Decode or cast admin_filterStatus to ensure it's a valid array
				if (isset($options['admin_filterStatus'])) {
					if (is_string($options['admin_filterStatus'])) {
						$excluded_statuses = json_decode($options['admin_filterStatus'], true);
					} elseif (is_array($options['admin_filterStatus'])) {
						$excluded_statuses = $options['admin_filterStatus'];
					}
				}

				// Ensure excluded_statuses is a valid array
				if (!is_array($excluded_statuses)) {
					$excluded_statuses = [];
				}

				$statuses = wc_get_order_statuses(); // Get all WooCommerce order statuses
				$included_statuses = array_diff(array_keys($statuses), $excluded_statuses); // Calculate included statuses
				$this->plugin->debug('[admin_filterStatus] Included Statuses: ' . print_r($included_statuses, true));
				$this->plugin->debug('[admin_filterStatus] Excluded Statuses: ' . print_r($excluded_statuses, true));

				?>
				<style>
					#included_statuses, #excluded_statuses {
						border: 1px solid #ccc;
						padding: 10px;
						min-height: 100px;
						width: 45%;
						display: inline-block;
						vertical-align: top;
					}
					#included_statuses li, #excluded_statuses li {
						list-style: none;
						margin: 5px 0;
						padding: 5px;
						background: #f1f1f1;
						cursor: move;
					}
				</style>
				<div>
					<p><?php esc_html_e('Included Statuses', $this->plugin->config["textDomain"]); ?></p>
					<ul id="included_statuses" class="connectedSortable">
						<?php foreach ($included_statuses as $status): ?>
							<li data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html($statuses[$status]); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div>
					<p><?php esc_html_e('Excluded Statuses', $this->plugin->config["textDomain"]); ?></p>
					<ul id="excluded_statuses" class="connectedSortable">
						<?php foreach ($excluded_statuses as $status): ?>
							<?php if (isset($statuses[$status])): ?>
								<li data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html($statuses[$status]); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>
				<input type="hidden" name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_filterStatus]" id="admin_filterStatus" value="<?php echo esc_attr(json_encode($excluded_statuses)); ?>" />
				<script>
					(function($) {
						$(document).ready(function() {
							// Ensure sortable functionality is initialized
							$('#included_statuses, #excluded_statuses').sortable({
								connectWith: '.connectedSortable',
								placeholder: 'ui-state-highlight',
								update: function() {
									const excluded = [];
									$('#excluded_statuses li').each(function() {
										excluded.push($(this).data('status'));
									});
									$('#admin_filterStatus').val(JSON.stringify(excluded));
								}
							}).addClass('connectedSortable');
						});
					})(jQuery);
				</script>
				<?php
			},
			$woo_default_order,
			$menu1_section1
		);

		add_settings_field(
			$this->plugin->setPrefix("admin_appliedtorole"),
			__("Role Applied To", $this->plugin->config["textDomain"]),
			function() {
				// Get the current value or default to 'date'
				$options = get_option($this->plugin->setPrefix("options"), []);
				$value = isset($options['admin_appliedtorole']) ? $options['admin_appliedtorole'] : '';



				// Get all roles from WordPress
				global $wp_roles;
				$roles = $wp_roles->roles;

				// Display roles as a dropdown
				?>
				<select name="<?php echo esc_attr($this->plugin->setPrefix("options")); ?>[admin_appliedtorole]" id="admin_appliedtorole">
					<?php foreach ($roles as $role_key => $role_data): ?>
						<option value="<?php echo esc_attr($role_key); ?>" <?php selected($value, $role_key); ?>>
							<?php echo esc_html($role_data['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php
			},
			$woo_default_order,
			$menu1_section1
		);

		// Register the settings with proper sanitization
		register_setting(
			$this->plugin->setPrefix("options"),
			$this->plugin->setPrefix("options"),
			[
				'default' => [
					'admin_orderby' => 'date', // Set default value for admin_orderby
				],
				'sanitize_callback' => function($input) {
					// Ensure admin_filterStatus is saved as a JSON string
					if (isset($input['admin_filterStatus']) && is_array($input['admin_filterStatus'])) {
						$input['admin_filterStatus'] = json_encode($input['admin_filterStatus']);
					}
					return $input;
				}
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

		////gello

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
		// Only run if ?status=all is present in the URL
		$is_wc_orders_page = isset($_GET['page']) && $_GET['page'] === 'wc-orders';
		$is_status_all_or_unset = !isset($_GET['status']) || $_GET['status'] === 'all';
		if (!$is_wc_orders_page || !$is_status_all_or_unset) {
			return $query_args;
		}

		$options = get_option($this->plugin->setPrefix("options"), []);
		if (isset($options['admin_orderby_custom'])) {
			$orderby = $options['admin_orderby_custom'];
		} elseif (isset($options['admin_orderby'])) {
			$orderby = $options['admin_orderby'];
		} else {
			$orderby = 'ID';
		}
		$query_args['orderby'] = $orderby;
		$query_args['order'] = 'ASC';


		// Check if 'admin_appliedtorole' is set and apply filter for the selected role
		if (!empty($options['admin_appliedtorole'])) {
			$current_user = wp_get_current_user();
			$applied_role = $options['admin_appliedtorole'];

			// Check if the current user has the selected role
			if (!in_array($applied_role, $current_user->roles, true)) {
				return $query_args; // Skip applying the filter if the role doesn't match
			}
		}


		// Filter orders by excluded statuses
		if (!empty($options['admin_filterStatus'])) {
			// Decode JSON or cast to array to ensure valid data
			$excluded_statuses = is_string($options['admin_filterStatus']) 
				? json_decode($options['admin_filterStatus'], true) 
				: (array) $options['admin_filterStatus'];

			if (is_array($excluded_statuses)) {
				$all_statuses = array_keys(wc_get_order_statuses()); // Get only the keys (status slugs)
				$query_args['status'] = array_diff($all_statuses, $excluded_statuses); // Ensure valid keys
			}
		}

		return $query_args;
	}


	
}



