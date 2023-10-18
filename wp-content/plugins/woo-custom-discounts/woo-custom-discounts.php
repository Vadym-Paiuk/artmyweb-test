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
				'depth'              => 0,
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
				'depth'              => 0,
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
	
	add_action( 'woocommerce_cart_contents', 'add_free_product_selection_row' );
	function add_free_product_selection_row(){
		$free_cat = get_option( 'free_cat' );
		$free_cat = get_term($free_cat, 'product_cat');
		$args = [
			'category' => [$free_cat->slug],
			'limit' => -1,
			'status' => 'publish'
		];
		$products = wc_get_products( $args );
		
		echo '<tr><td></td><td>PRODUCT FREE</td><td><select name="free_product">';
		echo '<option value="0">Select Product</option>';
		foreach ($products as $product) {
			echo '<option value="' . $product->get_id() . '">' . $product->get_name() . '</option>';
		}
		echo '</select></td></tr>';
	}
	
	add_action('woocommerce_before_calculate_totals', 'custom_cart_recalculate');
	function custom_cart_recalculate() {
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}
		
		$cart = WC()->cart;
		$discount_cat = get_option( 'discount_cat' );
		$numbers_input = get_option( 'numbers_input' );
		$count_product_in_discount_cat = 0;
		$has_free_product = false;
		
		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			$term_list_ids = wp_get_post_terms( $cart_item['product_id'], 'product_cat', ['fields' => 'ids'] );
			
			if (in_array( $discount_cat, $term_list_ids )){
				$count_product_in_discount_cat += $cart_item['quantity'];
			}
			
			if( isset( $cart_item['free_product'] ) && $cart_item['free_product'] === true ){
				$has_free_product = true;
				$free_product = $cart_item['data'];
				$free_product_key = $cart_item_key;
				$free_product->set_price(0);
			}
		}
		
		if ( $numbers_input > $count_product_in_discount_cat && $has_free_product ){
			$cart->remove_cart_item($free_product_key);
			$has_free_product = false;
		}
		
		if ( $numbers_input > $count_product_in_discount_cat ){
			remove_action( 'woocommerce_cart_contents', 'add_free_product_selection_row' );
		}
		
		if( $has_free_product ){
			remove_action( 'woocommerce_cart_contents', 'add_free_product_selection_row' );
		}
	}
	
	add_action('woocommerce_update_cart_action_cart_updated', 'custom_update_cart');
	function custom_update_cart(){
		if( empty( $_POST['free_product'] ) ){
			return;
		}
		
		$product_id = (int)$_POST['free_product'];
		$quantity = 1;
		$cart_item_data = [
			'free_product' => true
		];

		if (WC()->cart) {
			WC()->cart->add_to_cart($product_id, $quantity, 0, [] ,$cart_item_data);
		}
	}
	
	add_filter('woocommerce_cart_item_quantity', 'remove_ability_change_quantity', 10, 2);
	function remove_ability_change_quantity($item_quantity, $cart_item_key) {
		$cart = WC()->cart;
		$cart_item = $cart->get_cart_item($cart_item_key);
		
		if( isset( $cart_item['free_product'] ) && $cart_item['free_product'] === true ){
			$cart->set_quantity($cart_item_key);
			return $cart_item['quantity'];
		}
		
		return $item_quantity;
	}
	
	add_action('wp_enqueue_scripts', 'add_custom_inline_script');
	function add_custom_inline_script() {
		$script ='
            $(document).on("change", "[name=free_product]", function(){
				$( ".woocommerce-cart-form" ).find( ":input[name=update_cart]" ).prop( "disabled", false );
            });
        ';
		
		wp_add_inline_script('jquery', 'jQuery(document).ready(function($) { ' . $script . ' });');
	}
