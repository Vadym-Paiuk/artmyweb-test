<?php
	/*
	Plugin Name: WooCommerce Custom Discounts
	Description: WooCommerce Custom Discounts
	Version: 1.0
	Author: Vadym Paiuk
	*/
	global $plugin_settings_page;
	global $plugin_settings_group;
	$plugin_settings_page = 'my-plugin-settings';
	$plugin_settings_group = 'plugin-settings-group';
	
	add_action('admin_menu', 'add_custom_settings_page');
	function add_custom_settings_page() {
		global $plugin_settings_page;
		global $plugin_settings_group;
		$plugin_settings_section = 'plugin-settings-section';
		
		add_menu_page(
			'Discount Settings',
			'Discount Settings',
			'manage_options',
			$plugin_settings_page,
			'render_plugin_settings_page'
		);
		
		add_settings_section(
			$plugin_settings_section,
			'General Settings',
			'',
			$plugin_settings_page
		);
		
		$option_name = 'discount_cat';
		register_setting($plugin_settings_group, $option_name);
		add_settings_field(
			$option_name,
			'Discount Category',
			'select_callback',
			$plugin_settings_page,
			$plugin_settings_section,
			[
				'name' => $option_name,
				'show_option_all'    => 'All Categories',
				'taxonomy'           => 'product_cat',
				'orderby'            => 'name',
				'hierarchical'       => 1,
				'depth'              => 1,
				'show_count'         => 0,
				'hide_empty'         => 0,
				'selected'           => get_option( $option_name )
			]
		);
		
		$option_name = 'numbers_input';
		register_setting($plugin_settings_group, $option_name);
		add_settings_field(
			$option_name,
			'Number of products for this category',
			'input_callback',
			$plugin_settings_page,
			$plugin_settings_section,
			[
				'type' => 'number',
				'name' => $option_name
			]
		);
		
		$option_name = 'free_cat';
		register_setting($plugin_settings_group, $option_name);
		add_settings_field(
			$option_name,
			'Free product category',
			'select_callback',
			$plugin_settings_page,
			$plugin_settings_section,
			[
				'name' => $option_name,
				'show_option_all'    => 'All Categories',
				'taxonomy'           => 'product_cat',
				'orderby'            => 'name',
				'hierarchical'       => 1,
				'depth'              => 1,
				'show_count'         => 0,
				'hide_empty'         => 0,
				'selected'           => get_option( $option_name )
			]
		);
	}
	function render_plugin_settings_page() {
		global $plugin_settings_page;
		global $plugin_settings_group;
		?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			
			<form method="post" action="options.php">
				<?php settings_fields($plugin_settings_group); ?>
				<?php do_settings_sections($plugin_settings_page); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
	
	function input_callback( $args ){
		printf(
			'<input type="%1$s" id="%2$s" name="%2$s" value="%3$s" />',
			$args[ 'type' ],
			$args[ 'name' ],
			sanitize_text_field( esc_attr( get_option( $args[ 'name' ] ) ) )
		);
	}
	
	function select_callback( $args ){
		wp_dropdown_categories( $args );
	}