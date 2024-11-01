<?php
/*
 * Add custom fields to checkout, admin and my account page
*/

/*
 * Add the VAT number field in my account and checkout
*/
add_filter('woocommerce_billing_fields', 'tdata_add_billing_field_piva');

function tdata_add_billing_field_piva($fields)
{
    $fields['billing_piva'] = array(
        'label' => __('VAT number', 'tdata') ,
        'placeholder' => _x('VAT number', 'placeholder', 'tdata') ,
        'required' => false,
        'class' => array(
            'form-row-wide'
        ) ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the VAT number field in admin area
*/
add_filter('woocommerce_admin_billing_fields', 'tdata_add_admin_field_piva');

function tdata_add_admin_field_piva($fields)
{
    $fields['piva'] = array(
        'label' => __('VAT number', 'tdata') ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the PEC field in my account and checkout
*/
add_filter('woocommerce_billing_fields', 'tdata_add_billing_field_pec');

function tdata_add_billing_field_pec($fields)
{
    $fields['billing_pec'] = array(
        'label' => __('PEC', 'tdata') ,
        'placeholder' => _x('PEC', 'placeholder', 'tdata') ,
        'required' => false,
        'class' => array(
            'form-row-wide'
        ) ,
        'show' => true
    );
    return $fields;
}

/*
 *Add the PEC field in admin area
*/
add_filter('woocommerce_admin_billing_fields', 'tdata_add_admin_field_pec');

function tdata_add_admin_field_pec($fields)
{
    $fields['pec'] = array(
        'label' => __('PEC', 'tdata') ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the SDI field in my account and checkout
*/
add_filter('woocommerce_billing_fields', 'tdata_add_billing_field_sdi');

function tdata_add_billing_field_sdi($fields)
{
    $fields['billing_sdi'] = array(
        'label' => __('SDI', 'tdata') ,
        'placeholder' => _x('SDI', 'placeholder', 'tdata') ,
        'required' => false,
        'class' => array(
            'form-row-wide'
        ) ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the SDI field in admin area
*/
add_filter('woocommerce_admin_billing_fields', 'tdata_add_admin_field_sdi');

function tdata_add_admin_field_sdi($fields)
{
    $fields['sdi'] = array(
        'label' => __('SDI', 'tdata') ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the CF field in my account and checkout
*/
add_filter('woocommerce_billing_fields', 'tdata_add_billing_field_cf');

function tdata_add_billing_field_cf($fields)
{
    $fields['billing_cf'] = array(
        'label' => __('Fiscal code', 'tdata') ,
        'placeholder' => _x('Fiscal code', 'placeholder', 'tdata') ,
        'required' => false,
        'class' => array(
            'form-row-wide'
        ) ,
        'show' => true
    );
    return $fields;
}

/*
 * Add the CF field in admin area
*/
add_filter('woocommerce_admin_billing_fields', 'tdata_add_admin_field_cf');

function tdata_add_admin_field_cf($fields)
{
    $fields['cf'] = array(
        'label' => __('Fiscal code', 'tdata') ,
        'show' => true
    );
    return $fields;
}

// Aggiungere il campo CF in my account e checkout
add_filter('woocommerce_billing_fields', 'tdata_add_billing_field_checkbox');

function tdata_add_billing_field_checkbox($fields)
{
    $fields['billing_invoice_checkbox'] = array(
    	'type' => 'checkbox',
        'label' => _x('Invoice required', 'tdata') ,
        'required' => false,
        'show' => true,
        'priority' => 1
    );
    return $fields;
}

// Show / Hide billing fields
add_action( 'woocommerce_after_checkout_form', 'tdata_conditionally_hide_show_new_field', 9999 );

function tdata_conditionally_hide_show_new_field() {
    
  wc_enqueue_js( "
      jQuery('input#billing_invoice_checkbox').change(function(){
           
         if (! this.checked) {
            // HIDE IF NOT CHECKED
            jQuery('#billing_company_field').fadeOut();
            jQuery('#billing_company_field input').val(''); 
            jQuery('#billing_piva_field').fadeOut();
            jQuery('#billing_piva_field input').val(''); 
            jQuery('#billing_pec_field').fadeOut();
            jQuery('#billing_pec_field input').val(''); 
            jQuery('#billing_sdi_field').fadeOut();
            jQuery('#billing_sdi_field input').val(''); 
            jQuery('#billing_cf_field').fadeOut();
            jQuery('#billing_cf_field input').val('');         
         } else {
            // SHOW IF CHECKED
            jQuery('#billing_company_field').fadeIn();
            jQuery('#billing_piva_field').fadeIn();
            jQuery('#billing_pec_field').fadeIn();
            jQuery('#billing_sdi_field').fadeIn();
            jQuery('#billing_cf_field').fadeIn();
         }
           
      }).change();
  ");
       
}

/*
 * Set as cancelled on-hold orders
*/

add_action('restrict_manage_posts', 'tdata_cancel_unpaid_orders');

function tdata_cancel_unpaid_orders()
{
    global $pagenow, $post_type;

    // Enable the process to be executed daily when browsing Admin order list
    if ('shop_order' === $post_type && 'edit.php' === $pagenow && get_option('unpaid_orders_daily_process') < time()):

        $start = date('Y-m-d H:i:s');
    	$today = strtotime('-1 hour', strtotime($start));

        // Get unpaid orders (5 days old)
        $unpaid_orders = (array)wc_get_orders(array(
            'limit' => - 1,
            'status' => 'on-hold',
            'date_created' => '<' . ($today) ,
        ));

        if (sizeof($unpaid_orders) > 0)
        {
            $cancelled_text = __("The order was cancelled due to no payment from customer.", "woocommerce");

            // Loop through orders
            foreach ($unpaid_orders as $unpaid_order)
            {
                $unpaid_order->update_status('cancelled', $cancelled_text);
            }
        }
        // Schedule the process to the next day (executed once restriction)
        update_option('unpaid_orders_daily_process', $today + $one_day);

    endif;
}

?>
