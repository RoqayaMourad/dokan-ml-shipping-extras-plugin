<?php
/**
 * @package MultiLanguage and Shipping Managment for Doakn 
 * @version 1.7.2
 */
/*
Plugin Name: MultiLanguage and Shipping Managment for Doakn
Plugin URI: https://hiroqaya.com/
Description: Add fields for product translations
Author: Roqaya Mourad
Version: 1.0.0
Author URI: https://hiroqaya.com/
 */

function add_shipping_class_for_admin()
{
    function add_shipping_state_column($columns)
    {
        $columns['wc-shipping-class-state'] = __('Shipping State', 'woocommerce');
        return $columns;
    }
    function add_shipping_state($class)
    {
        $countries_obj = new WC_Countries();
        $states = $countries_obj->get_states("EG");
        $tt = $class;
        ?>
        <div class="view">{{ data.state }}</div>
        <div class="edit">
            <fieldset>
                <legend class="screen-reader-text"><span>State</span></legend>
                <select class="select " name="state[{{ data.state }}]"
                data-attribute="state" value="{{ data.slug }}" placeholder="<?php esc_attr_e('State', 'woocommerce');?>">
                <option value="">Don't Set/Update</option>
                <?php foreach ($states as $key => $value): ?>
                    <option value="<?=$key?>"><?=$value?></option>
                <?php endforeach;?>
                </select>
            </fieldset>
        </div>
        <!-- <div class="view">{{ data.state }}</div>
        <div class="edit"><input type="text" name="state[{{ data.state }}]" data-attribute="state" value="{{ data.state }}" placeholder="<?php esc_attr_e('State', 'woocommerce');?>" /></div> -->
        <?php
return $class;
    }
    function shipping_classes_save_class($term_id, $data)
    {
        if ($data['state']) {
            $data['slug'] = $data['state']; // . "--" . $data['slug'];
            wp_update_term($term_id, 'product_shipping_class', $data);
        }
        return $term_id;
    }

    add_action('woocommerce_shipping_classes_save_class', 'shipping_classes_save_class', 11, 2);
    add_action('woocommerce_shipping_classes_columns', 'add_shipping_state_column');
    add_action('woocommerce_shipping_classes_column_wc-shipping-class-state', 'add_shipping_state');
}
add_shipping_class_for_admin();

// dokan modifications

// === mycode
function is_response_error($response)
{
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    if (is_int($response)) {
        return true;
    } else {
        wp_send_json_error(__('Something wrong, please try again later', 'dokan-lite'));
    }
    return false;
}

function get_ar_postdata_from_postdata($postdata)
{
    $post_data_ar = $postdata;
    $post_data_ar['post_title'] = $postdata['post_title'];
    $post_data_ar['post_excerpt'] = $postdata['post_excerpt'];

    unset($post_data_ar['post_title_en']);
    unset($post_data_ar['post_excerpt_en']);
    return $post_data_ar;
}

function get_en_postdata_from_postdata($postdata)
{
    $post_data_en = $postdata;

    $post_data_en['post_title'] = $postdata['post_title_en'];
    $post_data_en['post_excerpt'] = $postdata['post_excerpt_en'];

    unset($post_data_en['post_title_en']);
    unset($post_data_en['post_excerpt_en']);
    return $post_data_en;
}
/**
 * Create product from popup submission
 *
 * @since  2.5.0
 *
 * @return void
 */
function my_create_product()
{
    // echo "this is my function";
    // return "OK";
    check_ajax_referer('dokan_reviews');

    if (!current_user_can('dokan_add_product')) {
        wp_send_json_error(__('You have no permission to do this action', 'dokan-lite'));
    }

    $submited_data = isset($_POST['postdata']) ? wp_unslash($_POST['postdata']) : ''; //phpcs:ignore

    parse_str($submited_data, $postdata);

    /* steps to translate
    1- switch to ar
    2- save product
    3- switch to en
    4- save product
    5- switch back to original visitor language
     */
    global $sitepress;
    $current_lang = $sitepress->get_current_language();

    $sitepress->switch_lang('ar', true);
    // get shipping classes
    $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));

    // determine correct shipping class based on vendor location
    $profile_info = dokan_get_store_info(dokan_get_current_user_id());
    $profile_state = strtolower($profile_info['address']['state']);

    $shipping_class_for_this_vendor = false;
    foreach ($shipping_classes as $shipping_class) {
        if ($shipping_class->slug == $profile_state) {
            $shipping_class_for_this_vendor = $shipping_class;
            break;
        }
    }

    $post_data_ar = get_ar_postdata_from_postdata($postdata);
    $response_ar = dokan_save_product($post_data_ar);
    if ($shipping_class_for_this_vendor) {
        $product = wc_get_product($response_ar); // Get an instance of the WC_Product Object
        $product->set_shipping_class_id($shipping_class_for_this_vendor->term_id); // Set the shipping class ID
        $product->save(); // Save the product data to database
    }

    $sitepress->switch_lang('en', true);
    // get shipping classes
    $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));

    // determine correct shipping class based on vendor location
    $profile_info = dokan_get_store_info(dokan_get_current_user_id());
    $profile_state = strtolower($profile_info['address']['state']);

    $shipping_class_for_this_vendor = false;
    foreach ($shipping_classes as $shipping_class) {
        if ($shipping_class->slug == $profile_state) {
            $shipping_class_for_this_vendor = $shipping_class;
            break;
        }
    }

    $post_data_en = get_en_postdata_from_postdata($postdata);
    $response_en = dokan_save_product($post_data_en);
    if ($shipping_class_for_this_vendor) {
        $product = wc_get_product($response_en); // Get an instance of the WC_Product Object
        $product->set_shipping_class_id($shipping_class_for_this_vendor->term_id); // Set the shipping class ID
        $product->save(); // Save the product data to database
    }
    $sitepress->switch_lang($current_lang, true);

    if (is_response_error($response_ar)) {
        $redirect = dokan_get_navigation_url('products');
        wp_send_json_success($redirect);
    }
}


/// === Removing dokan's ajax action
if( ! function_exists( 'remove_class_filter' ) ){

    function remove_class_filter( $tag, $class_name = '', $method_name = '', $priority = 10 ) {
        global $wp_filter;
        // Check that filter actually exists first
        if ( ! isset( $wp_filter[ $tag ] ) ) {
            return FALSE;
        }
        if ( is_object( $wp_filter[ $tag ] ) && isset( $wp_filter[ $tag ]->callbacks ) ) {
            // Create $fob object from filter tag, to use below
            $fob       = $wp_filter[ $tag ];
            $callbacks = &$wp_filter[ $tag ]->callbacks;
        } else {
            $callbacks = &$wp_filter[ $tag ];
        }
        // Exit if there aren't any callbacks for specified priority
        if ( ! isset( $callbacks[ $priority ] ) || empty( $callbacks[ $priority ] ) ) {
            return FALSE;
        }
        foreach ( (array) $callbacks[ $priority ] as $filter_id => $filter ) {
            if ( ! isset( $filter['function'] ) || ! is_array( $filter['function'] ) ) {
                continue;
            }
            // If first value in array is not an object, it can't be a class
            if ( ! is_object( $filter['function'][0] ) ) {
                continue;
            }
            if ( $filter['function'][1] !== $method_name ) {
                continue;
            }
            if ( get_class( $filter['function'][0] ) === $class_name ) {
                if ( isset( $fob ) ) {
                    $fob->remove_filter( $tag, $filter['function'], $priority );
                } else {
                    unset( $callbacks[ $priority ][ $filter_id ] );
                    if ( empty( $callbacks[ $priority ] ) ) {
                        unset( $callbacks[ $priority ] );
                    }
                    if ( empty( $callbacks ) ) {
                        $callbacks = array();
                    }
                    unset( $GLOBALS['merged_filters'][ $tag ] );
                }
                return TRUE;
            }
        }
        return FALSE;
    }
}
if( ! function_exists( 'remove_class_action') ){

    function remove_class_action( $tag, $class_name = '', $method_name = '', $priority = 10 ) {
        remove_class_filter( $tag, $class_name, $method_name, $priority );
    }
}

add_action( 'init', 'init_remove_my_class_action', 999 );
function init_remove_my_class_action() {
    // echo "we are here";
    remove_class_action("wp_ajax_dokan_create_new_product","WeDevs\Dokan\Ajax","create_product",10);
}
add_action( 'wp_ajax_dokan_create_new_product','my_create_product',9999999 );

function add_fields_scripts(){
    ob_start();
    ?>
    <input type="text" class="dokan-form-control" name="post_title_en" placeholder="<?php esc_attr_e( 'Product name English..', 'dokan-lite' ); ?>">
    <?php
    $name_field = ob_get_clean();
    ob_start();
    ?><textarea name="post_excerpt_en" id="" class="dokan-form-control" rows="5" placeholder="<?php esc_attr_e( 'Enter some english short description about this product...' , 'dokan-lite' ) ?>"></textarea><?php
    $desc_field = ob_get_clean();
    ?>
    <script type="text/javascript">
        jQuery(".dokan-add-product-link").on("click",function (){
            setTimeout(() => {
                // Insert decp div
                let desc_div = document.createElement("div");
                desc_div.className= "dokan-form-group";
                desc_div.innerHTML = `<?=$desc_field?>`
                let dec_group = document.querySelector(".dokan-form-group>textarea[name=\"post_excerpt\"].dokan-form-control").parentElement.parentElement
                jQuery(dec_group).after(desc_div);
                // Insert name div
                let name_div = document.createElement("div");
                name_div.className= "dokan-form-group";
                name_div.innerHTML = `<?=$name_field?>`
                jQuery(".dokan-form-group>input[name=\"post_title\"].dokan-form-control").parent().after(name_div);
            }, 50);
        })

    </script>
    <?php
}

add_action("dokan_after_listing_product","add_fields_scripts");