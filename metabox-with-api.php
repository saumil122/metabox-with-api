<?php
defined('ABSPATH') or die("No script kiddies please!");
/*
Plugin Name: Metabox with API
Plugin URI: https://github.com/saumil122/metabox-with-api
Description: The plugin provide option to fetch category & product detail from api and store the data in database. The plugin generate the metabox for the post and the page. The plugin generate the short code for the selected product which can be used on any post or page.
Version: 1.0
Author: Saumil Nagariya
Author URI: https://github.com/saumil122
License:     GPL2
Custom List Table With Database Example is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Custom List Table With Database Example is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Custom List Table With Database Example. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/


//Hook to activate, deactivate & uninstatll Plugin
register_activation_hook(__FILE__, array('MetaboxAPI_Setup_Class', 'on_activation'));
register_deactivation_hook(__FILE__, array('MetaboxAPI_Setup_Class', 'on_deactivation'));
register_uninstall_hook(__FILE__, array('MetaboxAPI_Setup_Class', 'on_uninstall'));

global $wpdb;
global $table_categories;
$table_categories = $wpdb->prefix . 'metabox_categories';
global $table_products;
$table_products = $wpdb->prefix . 'metabox_products';

add_action('plugins_loaded', array('MetaboxAPI_Setup_Class', 'init'));

class MetaboxAPI_Setup_Class
{
    protected static $instance;

    public static function init()
    {
        is_null(self::$instance) and self::$instance = new self;
        return self::$instance;
    }

    public static function on_activation()
    {
        if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
        check_admin_referer("activate-plugin_{$plugin}");

        global $wpdb;
        $wpdb_collate = $wpdb->collate;
        global $table_categories;
        global $table_products;

        // create the categories table
        if ($wpdb->get_var("show tables like '$table_categories'") != $table_categories) {
            $sql = "CREATE TABLE {$table_categories} (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `category_id` varchar(10) NOT NULL,
                `category_name` varchar(50) NOT NULL,
                `category_desc` text,
                `parent_id` int(11) DEFAULT '0',
                UNIQUE KEY id (id)
      		)COLLATE {$wpdb_collate}";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // create the products table
        if ($wpdb->get_var("show tables like '$table_products'") != $table_products) {
            $sql = "CREATE TABLE {$table_products} (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` varchar(10) NOT NULL,
                `product_name` varchar(50) NOT NULL,
                `product_desc` text,
                `product_price` float(11,2) NOT NULL,
                `product_cate` varchar(50) NOT NULL,
                UNIQUE KEY id (id)
            )COLLATE {$wpdb_collate}";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public static function on_deactivation()
    {

        if (!current_user_can('activate_plugins'))
            return;
        $plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';
        check_admin_referer("deactivate-plugin_{$plugin}");

        global $wpdb;
        global $table_categories;
        global $table_products;

        $wpdb->query("DROP TABLE IF EXISTS " . $table_categories);
        $wpdb->query("DROP TABLE IF EXISTS " . $table_products);
    }

    public static function on_uninstall()
    {
        if (!current_user_can('activate_plugins'))
            return;
        check_admin_referer('bulk-plugins');

        // Important: Check if the file is the one
        // that was registered during the uninstall hook.
        if (__FILE__ != WP_UNINSTALL_PLUGIN)
            return;

        global $wpdb;
        global $table_categories;
        global $table_products;

        $wpdb->query("DROP TABLE IF EXISTS " . $table_categories);
        $wpdb->query("DROP TABLE IF EXISTS " . $table_products);
    }

    public function __construct()
    {
        if (!is_admin()) {
            //for frontend
        }

        # INIT the plugin: Hook your callbacks
        add_action('admin_menu', array($this, 'add_metaboxapi_menu'));
        add_action('admin_enqueue_scripts', array($this, 'metaboxapi_enqueue_style'));
        add_action('admin_enqueue_scripts', array($this, 'metaboxapi_enqueue_script'));

        /*add meta_box for post & page*/
        add_action('add_meta_boxes', array($this, 'add_metaboxapi_attributes'));
        add_action('save_post', array($this, 'save_metaboxapi_attributes'));

        if (isset($_POST['metaboxapi_settings_info']) && wp_verify_nonce($_POST['metaboxapi_settings_info'], 'metaboxapi_settings_info')) {
            //save program-form data
            $default=array(
                'category_api' => '',
                'product_api' => ''
            );
            $item = shortcode_atts($default, $_POST);
            $this->save_metaboxapi_settings($item);
        }

        //check for message 
        if ((isset($_GET['msg']) && $_GET['msg'] != '')) {
            add_action('admin_notices', array($this, 'metaboxapi_admin_notice'));
        }
    }

    public function metaboxapi_admin_notice()
    {
        if ($_GET['msg'] == '1') {
            echo '<div class="updated"><p>Settings Saved Successfully.</p></div>';
        }
    }

    //include custom js
    public function metaboxapi_enqueue_script($hook)
    {
        wp_enqueue_script('metaboxapi-script', plugin_dir_url(__FILE__) . 'js/metaboxapi.js');
    }

    //include custom style
    public function metaboxapi_enqueue_style()
    {
        wp_register_style('metaboxapi-style', plugin_dir_url(__FILE__) . 'css/metaboxapi.css', false, '1.0.0');
        wp_enqueue_style('metaboxapi-style');
    }

    //function to add links in Admin Menu
    public function add_metaboxapi_menu()
    {
        //add option under "Settings"
        add_submenu_page('options-general.php', 'Metabox API Settings', 'Metabox API', 'manage_options', 'metaboxapi',  array($this, 'metaboxapi_setting_page'));
    }
    public function metaboxapi_setting_page()
    {
        $lang_array = array(
            'category_api' => 'Category API',
            'product_api' => 'Product API'
        );

        $notes = array(
            'category_api' => 'Use default category data:<br/> '.plugin_dir_url( __FILE__ ).'data/category.json',
            'product_api' => 'Use default category data:<br/> '.plugin_dir_url( __FILE__ ).'data/product.json'
        );

        $lang_value_array = array();
        $option_name = 'metaboxapi_option';

        if (get_option($option_name) !== false) {
            $lang_value_array = unserialize(get_option($option_name));
        }

        $form_html = '';
        $form_html .= '<div class="wrap">';
        $form_html .= '<h2>Metabox API Settings</h2>';
        $form_html .= '<form method="post">';
        $form_html .= '<table class="form-table">';

        foreach ($lang_array as $k => $v) {
            $form_html .= '<tr>';
            $form_html .= '<th scope="row" width="20%;"><label for="' . esc_html($k) . '">' . esc_html($v) . '</label></th>';
            $form_html .= '<td width="80%;"><input type="text" name="' . esc_html($k) . '" id="' . esc_html($k) . '" value="' . esc_html($lang_value_array[$k]) . '"><br/><span class="notes">'.$notes[$k].'</span></td>';
            $form_html .= '</tr>';
        }
        /* Row for submit button */
        $form_html .= '<tr>';
        $form_html .= '<th scope="row">&nbsp;</th>';
        $form_html .= '<td><input type="submit" value="Save Changes" class="button button-primary" id="submit_settings" name="submit"></td>';
        $form_html .= '</tr>';
        if (get_option($option_name) !== false) {
            $form_html .= '<tr><th scope="row">&nbsp;</th><td><input type="button" value="Fetch Data" class="button button-primary" id="fetch_data" name="Fetch Data"></td></tr>';
        }
        $form_html .= '</table>';
        $form_html .= wp_nonce_field('metaboxapi_settings_info', 'metaboxapi_settings_info', true, true);
        $form_html .= '</form>';
        $form_html .= '</div>';
        echo $form_html;
    }

    public static function store_metaboxapi_data()
    {
        global $wpdb;
        global $table_categories;
        global $table_products;
        $result = array(
            'type' => 'success',
            'message' => 'Data fetched successfully.'
        );
        $categories_list = array();

        $lang_value_array = array();
        $option_name = 'metaboxapi_option';

        if (get_option($option_name) !== false) {
            $lang_value_array = unserialize(get_option($option_name));

            $cate_url = esc_html($lang_value_array['category_api']);
            $cate_args = array(
                'timeout'     => 120,
                'method' => 'GET',
            );
            $cate_response = wp_remote_get($cate_url, $cate_args);

            if (is_wp_error($cate_response)) {
                $error_message = $cate_response->get_error_message();
                $result['type'] = 'error';
                $result['message'] = 'Something went wrong: ' . $error_message;
            } else {
                $catedata_array = json_decode(wp_remote_retrieve_body($cate_response));

                if (!empty($catedata_array) && count($catedata_array) > 0) {
                    /* Empty category table */
                    $delete = $wpdb->query("TRUNCATE TABLE $table_categories");

                    foreach ($catedata_array as $category) {
                        $categories_list[] = $category->id;

                        $wpdb->insert(
                            $table_categories,
                            array(
                                'category_id' => sanitize_key($category->id),
                                'category_name' => sanitize_text_field($category->name),
                                'category_desc' => sanitize_text_field($category->desc)
                            ),
                            array(
                                '%s',
                                '%s',
                                '%s'
                            )
                        );

                        $cate_id = $wpdb->insert_id;
                        //subcategory
                        if (!empty($category->subConditions) && count($category->subConditions)) {
                            foreach ($category->subConditions as $subcat) {
                                $categories_list[] = $subcat->id;

                                $wpdb->insert(
                                    $table_categories,
                                    array(
                                        'category_id' => sanitize_key($subcat->id),
                                        'category_name' => sanitize_text_field($subcat->name),
                                        'category_desc' => sanitize_text_field($subcat->desc),
                                        'parent_id' => sanitize_key($cate_id)
                                    ),
                                    array(
                                        '%s',
                                        '%s',
                                        '%s',
                                        '%d'
                                    )
                                );
                            }
                        }
                    }
                }
            }

            /* product data */
            $product_url = esc_html($lang_value_array['product_api']);
            $prod_args = array(
                'timeout'     => 120,
                'method' => 'GET',
            );
            $prod_response = wp_remote_get($product_url, $prod_args);
            if (is_wp_error($prod_response)) {
                $prod_error_message = $prod_response->get_error_message();
                $result['type'] = 'error';
                $result['message'] = 'Something went wrong: ' . $prod_error_message;
            } else {
                $proddata_array = json_decode(wp_remote_retrieve_body($prod_response));

                if (!empty($proddata_array) && count($proddata_array) > 0) {
                    /* Empty product table */
                    $delete = $wpdb->query("TRUNCATE TABLE $table_products");

                    foreach ($proddata_array as $product) {
                        $wpdb->insert(
                            $table_products,
                            array(
                                'product_id' => sanitize_key($product->id),
                                'product_name' => sanitize_text_field($product->name),
                                'product_desc' => sanitize_text_field($product->desc),
                                'product_price' => sanitize_text_field($product->price),
                                'product_cate' => sanitize_key($product->conditionId)
                            ),
                            array(
                                '%s',
                                '%s',
                                '%s',
                                '%f',
                                '%s'
                            )
                        );
                    }
                }
            }
        }
        echo json_encode($result);
        wp_die();
    }

    public static function get_products_by_category()
    {
        global $wpdb;
        global $table_products;
        $result = array();

        if (isset($_POST['category']) && sanitize_key($_POST['category']) != '') {
            $query = "SELECT * FROM " . $table_products . " where product_cate='" . sanitize_key($_POST['category']) . "' ORDER BY id ASC";

            $result = array(
                'type' => 'success',
                'message' => $wpdb->get_results($query)
            );
        } else {
            $result = array(
                'type' => 'error',
                'message' => 'Something went wrong. Please try again.'
            );
        }
        echo json_encode($result);
        wp_die();
    }

    public function save_metaboxapi_settings($postdata)
    {
        $option_name = 'metaboxapi_option';
        if (get_option($option_name) !== false) {
            // The option already exists, so we just update it.
            update_option($option_name, serialize($postdata));
        } else {
            // The option hasn't been added yet. We'll add it with $autoload set to 'no'.
            $deprecated = null;
            $autoload = 'no';
            add_option($option_name, serialize($postdata), $deprecated, $autoload);
        }
        // now redirect to listing page
        $redirect_url = home_url() . '/wp-admin/options-general.php?page=metaboxapi&msg=1';
        wp_safe_redirect($redirect_url);

        // and stop php
        exit();
    }

    /**
     * Adds a box to the main column on the Post and Page edit screens.
     */
    public function add_metaboxapi_attributes()
    {

        $screens = array('post', 'page');

        foreach ($screens as $screen) {

            add_meta_box(
                'metaboxapi_sectionid',
                'Metabox API',
                array($this, 'add_metaboxapi_callback'),
                $screen,
                'side',
                'low'
            );
        }
    }

    /**
     * Prints the box content.
     * 
     * @param WP_Post $post The object for the current post/page.
     */
    public function add_metaboxapi_callback($post)
    {
        global $wpdb, $table_categories, $table_products;

        // Add an nonce field so we can check for it later.
        wp_nonce_field('metaboxapi_check', 'metaboxapi_check_nonce');

        /*
           * Use get_post_meta() to retrieve an existing value
           * from the database and use the value for the form.
           */
        //$cate_value = get_post_meta($post->ID, '_category_field', true);
        //$prod_value = get_post_meta($post->ID, '_product_field', true);

        $ddl_html = '';
        /*create dropdown for Category value*/
        $ddl_html .= '<div class="metaboxapi_field"><label for="_category_field">Category:</label>';
        $ddl_html .= '<select id="_category_field" name="_category_field">';
        $ddl_html .= '<option value="">Select Category</option>';
        $query = 'SELECT * FROM ' . $table_categories . ' ORDER BY id ASC';
        foreach ($wpdb->get_results($query) as $row) {
            $catname = $row->category_name;
            $catid = $row->category_id;
            $selected = '';
            $ddl_html .= '<option value="' . esc_html($catid) . '" ' . $selected . '>' . esc_html($catname) . '</option>';
        }
        $ddl_html .= '</select></div>';

        $ddl_html .= '<div class="metaboxapi_field"><label for="_product_field">Product:</label>';
        $ddl_html .= '<select id="_product_field" name="_product_field">';
        $ddl_html .= '<option value="">Select Product</option>';
        $ddl_html .= '</select></div>';

        $ddl_html .= '<div class="metaboxapi_field"><input type="button" id="generateCode" class="button button-primary" value="Generate Code"/></div>';

        $ddl_html .= '<div class="shortcode_preview"></div>';

        echo $ddl_html;
    }

    /**
     * When the post is saved, saves our custom data.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_metaboxapi_attributes($post_id)
    {

        /*
    	 * We need to verify this came from our screen and with proper authorization,
    	 * because the save_post action can be triggered at other times.
    	 */

        // Check if our nonce is set.
        if (!isset($_POST['metaboxapi_check_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['metaboxapi_check_nonce'], 'metaboxapi_check')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && 'page' == sanitize_text_field($_POST['post_type'])) {

            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }

        /* OK, it's safe for us to save the data now. */

        // Make sure that it is set.
        if (!isset($_POST['_category_field'])) {
            return;
        }

        // Sanitize user input.
        $category_data = sanitize_text_field($_POST['_category_field']);
        $product_data = sanitize_text_field($_POST['_product_field']);

        // Update the meta field in the database.
        update_post_meta($post_id, '_category_field', $category_data);
        update_post_meta($post_id, '_product_field', $product_data);
    }

    public static function generate_shotcode_html($atts = array())
    {
        extract(shortcode_atts(array(
            'id' => ''
        ), $atts));

        $html='';

        if (isset($atts['id']) && $atts['id'] != '') {
            global $wpdb;
            global $table_products;

            $query = "SELECT * FROM " . $table_products . " where product_id='" . $atts['id'] . "'";

            $result = $wpdb->get_row($query, ARRAY_A);
            
            $html='<input type="button" id="product_'.esc_html($result['product_id']).'" name="product_'.esc_html($result['product_id']).'" value="'.esc_html($result['product_id']).' ('.esc_html($result['product_price']).')"  class="button primary-button"/>';
        } 
        return $html;
    }
}

//fetch data request
add_action('wp_ajax_metaboxapi_action', array('MetaboxAPI_Setup_Class', 'store_metaboxapi_data'));

//get product data request
add_action('wp_ajax_metaboxapi_products_action', array('MetaboxAPI_Setup_Class', 'get_products_by_category'));


add_shortcode('CTA', array('MetaboxAPI_Setup_Class', 'generate_shotcode_html'));
