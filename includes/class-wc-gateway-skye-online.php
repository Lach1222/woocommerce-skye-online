<?php
if( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

/**
 * WooCommerce Skye Online.
 *
 * @class   WC_Gateway_Skye_Online
 * @extends WC_Payment_Gateway
 * @version 1.0.0
 * @package WooCommerce Skye Online/Includes
 * @author  Allan.Nudas@Flexigroup.com.au
 */
class WC_Gateway_Skye_Online extends WC_Payment_Gateway {

  /**
   * Constructor for the gateway.
   *
   * @access public
   * @return void
   */
  public function __construct() {
    $this->id                 = 'skye_online';
    $this->icon               = apply_filters( 'woocommerce_skye_online_icon', plugins_url( '/assets/images/logo.png', dirname( __FILE__ ) ) );
    $this->has_fields         = false;
    $this->credit_fields      = false;

    $this->order_button_text  = __( 'Pay with Skye Mastercard®', 'woocommerce-skye-online' );

    $this->method_title       = __( 'Skye Online', 'woocommerce-skye-online' );
    $this->method_description = __( 'Take payments via Skye Online.', 'woocommerce-skye-online' );    
    
    $this->notify_url         = WC()->api_request_url( 'WC_Gateway_Skye_Online' );    

    $this->supports           = array(      
      'products',
      'default_credit_card_form',
      'refunds',
      'pre-orders'
    );

    // Load the form fields.
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();

    
    // Get setting values.
    $this->enabled        = $this->get_option( 'enabled' );

    $this->title          = $this->get_option( 'title' );
    $this->skye_minimum   = $this->get_option( 'skye_minimum' )?  $this->get_option( 'skye_minimum' ) : "0";
    $this->skye_maximum   = $this->get_option( 'skye_maximum' )?  $this->get_option( 'skye_maximum' ) : "999999";
    $this->skye_promo_term = $this->get_option( 'skye_promo_term' )?  $this->get_option( 'skye_promo_term' ) : "6";
    //$this->description    = $this->get_option( 'description' );
    $checkout_total = (WC()->cart)? WC()->cart->get_totals()['total'] : "0";
    $this->description    = __('<style>.payment_method_skye_online a#skye-tag p { margin: 1.5em; } .payment_method_skye_online a#skye-tag p img { float: none; display: inline;}</style><div id="checkout_method_skye_online"></div><script id="skye-widget" data-min="'.$this->skye_minimum.'" data-max="'.$this->skye_maximum.'" src="https://d1y94doel0eh42.cloudfront.net/content/scripts/skye-widget.js?id='.$this->get_option( 'merchant_id' ).'&used_in=checkout&productPrice='.$checkout_total.'&element=%23checkout_method_skye_online&mode='.$this->get_option('price_widget_mode').'&term='.$this->get_option('skye_promo_term').'"></script>', 'woocommerce-skye-online');
    $this->instructions   = $this->get_option( 'instructions' );

    $this->sandbox        = $this->get_option( 'sandbox' );
    $this->secret_key     = $this->get_option( 'secret_key' );        

    $this->debug          = $this->get_option( 'debug' );   
    $this->price_widget   = $this->get_option( 'price_widget' ); 

    // Logs.
    if( $this->debug == 'yes' ) {
      if( class_exists( 'WC_Logger' ) ) {
        $this->log = new WC_Logger();
      }
      else {
        $this->log = $woocommerce->logger();
      }
    }

    if( $this->sandbox == 'yes' ) {
      $this->wsdl_url = 'https://captureuat.onceonline.com.au/IPL_service/ipltransaction.asmx?wsdl';
      $this->gateway_url = 'https://cxskyeuat.flexicards.com.au/PromotionSelector?';
    }
    else {
      $this->wsdl_url = 'https://apply.flexicards.com.au/IPL_service/ipltransaction.asmx?wsdl';
      $this->gateway_url = 'https://apply.flexicards.com.au/PromotionSelector?';
    }    

    // Hooks.
    if( is_admin() ) {
      add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
      add_action( 'admin_notices', array( $this, 'checks' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
    add_action('woocommerce_single_product_summary', array($this, 'add_price_widget'));
    add_action('woocommerce_cart_totals_after_order_total', array($this, 'add_checkout_price_widget'));
    add_filter('woocommerce_thankyou_order_id', array($this,'payment_finalisation' ));
    add_filter('the_title', array( $this,'order_received_title'), 11 );   
    add_action('woocommerce_before_checkout_form', array($this, 'display_min_max_notice'));
    add_action('woocommerce_before_cart', array($this, 'display_min_max_notice'));
    add_filter('woocommerce_available_payment_gateways', array($this,'display_min_max_filter'));
    add_filter('woocommerce_thankyou_order_received_text', array($this, 'thankyou_page'));

    // Customer Emails.
    add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
  }

  public function add_price_widget(){    
    global $product;
    if(isset($this->settings['price_widget']) && $this->price_widget =='yes'){            
      $minimum = $this->get_min_price();
      $maximum = $this->get_max_price();
      $price = wc_get_price_to_display($product);
      if(($minimum == 0 || $price >= $minimum) && ($maximum == 0 || $price <= $maximum)) {
        echo '<style>#skye-tag p img { display: inline; } #skye-tag p {margin: 1em 0px 1em 0px;}</style><div id="skye-price-anchor"></div><script id="skye-widget" data-min="'.$this->skye_minimum.'" data-max="'.$this->skye_maximum.'" src="https://d1y94doel0eh42.cloudfront.net/content/scripts/skye-widget.js?id='.$this->settings['merchant_id'].'&productPrice='.$price.'&element=%23skye-price-anchor&term='.$this->settings['skye_promo_term'].'&mode='.$this->settings['price_widget_mode'].'"></script>';
      }
    }
  }

  public function add_checkout_price_widget(){
    if(isset($this->settings['price_widget']) && $this->price_widget =='yes'){   
      $minimum = $this->get_min_price();
      $maximum = $this->get_max_price();
      $price = WC()->cart->total;
      if(($minimum == 0 || $price >= $minimum) && ($maximum == 0 || $price <= $maximum)) {
        echo '<style>#skye-tag p img { display: inline; } #skye-tag p {margin: 1em 0px 1em 0px;}</style><div id="skye-price-anchor"></div><script id="skye-widget" data-min="'.$this->skye_minimum.'" data-max="'.$this->skye_maximum.'" src="https://d1y94doel0eh42.cloudfront.net/content/scripts/skye-widget.js?id='.$this->settings['merchant_id'].'&productPrice='.$price.'&element=tr.order-total%20span.woocommerce-Price-amount.amount&term='.$this->settings['skye_promo_term'].'&mode='.$this->settings['price_widget_mode'].'"></script>';
      }
    }
  }
  /**
  * Display message to customer if the total amount meets the min or max amount required to use Skye as an option
  */
  function display_min_max_notice(){
    $minimum = $this->get_min_price();
    $maximum = $this->get_max_price();

    if ( $minimum != 0 && WC()->cart->total < $minimum ){
      if(is_checkout()){
        wc_print_notice(
          sprintf("You must have an order with a minimum of %s to use %s. Your current order total is %s.",
                  wc_price($minimum),
                  $this->title,
                  wc_price(WC()->cart->total)
          ), 'notice'
        );
      }
    } elseif ( $maximum !=0 && WC()->cart->total > $maximum ){
      if(is_checkout()){
        wc_print_notice(
          sprintf("You must have an order with a maximum of %s to use %s. Your current order total is %s.",
            wc_price($maximum),
            $this->title,
            wc_price(WC()->cart->total)
          ), 'notice'
        );
      }
    }
  }

  protected function get_min_price(){    

    return isset($this->settings['skye_minimum'])? $this->settings['skye_minimum']:0;
  }

  protected function get_max_price()
  {
    return isset($this->settings['skye_maximum'])? $this->settings['skye_maximum']:0;
  }

  function display_min_max_filter($available_gateways){
    $this->log->add( $this->id, 'Avaialable gateways: ' . $available_gateways[0] . ')' );
    $minimum = $this->get_min_price();
    $maximum = $this->get_max_price();
    if ( ( $minimum != 0 && WC()->cart->total < $minimum) || ($maximum != 0 && WC()->cart->total > $maximum) ){
      if(isset($available_gateways[$this->id])){
        unset($available_gateways[$this->id]);
      }
    }
    return $available_gateways;
  }

  /**
   * Admin Panel Options
   * - Options for bits like 'title' and availability on a country-by-country basis
   *
   * @access public
   * @return void
   */
  public function admin_options() {
    include_once( WC_Skye_Online()->plugin_path() . '/includes/admin/views/admin-options.php' );
  }

  /**
   * Check if SSL is enabled and notify the user.
   *
   * @TODO:  Use only what you need.
   * @access public
   */
  public function checks() {
    if( $this->enabled == 'no' ) {
      return;
    }

    // PHP Version.
    if( version_compare( phpversion(), '5.3', '<' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Skye Online Error: Skye Online requires PHP 5.3 and above. You are using version %s.', 'woocommerce-skye-online' ), phpversion() ) . '</p></div>';
    }

    // Check required fields.
    else if( !$this->secret_key ) {
      echo '<div class="error"><p>' . __( 'Skye Online Error: Please enter your merchant secret key', 'woocommerce-skye-online' ) . '</p></div>';
    }

    // Show message if enabled and FORCE SSL is disabled and WordPress HTTPS plugin is not detected.
    else if( 'no' == get_option( 'woocommerce_force_ssl_checkout' ) && !class_exists( 'WordPressHTTPS' ) ) {
      echo '<div class="error"><p>' . sprintf( __( 'Skye Online is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Skye Online will only work in sandbox mode.', 'woocommerce-skye-online'), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
    }
  }

  /**
   * Check if this gateway is enabled.
   *
   * @access public
   */
  public function is_available() {
    if( $this->enabled == 'no' ) {
      return false;
    }

    if( !is_ssl() && 'yes' != $this->sandbox ) {
      return false;
    }

    if( !$this->secret_key ) {
      return false;
    }

    return true;
  }

  /**
   * Initialise Gateway Settings Form Fields
   *
   * The standard gateway options have already been applied. 
   * Change the fields to match what the payment gateway your building requires.
   *
   * @access public
   */
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'       => __( 'Enable/Disable', 'woocommerce-skye-online' ),
        'label'       => __( 'Enable Skye Online', 'woocommerce-skye-online' ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
      ),
      'title' => array(
        'title'       => __( 'Title', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-skye-online' ),
        'default'     => __( 'Skye Online', 'woocommerce-skye-online' ),
        'desc_tip'    => true
      ),
      'description' => array(
        'title'       => __( 'Description', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-skye-online' ),
        'default'     => 'Pay with Skye Mastercard®.',
        'desc_tip'    => true
      ),
      'instructions' => array(
        'title'       => __( 'Instructions', 'woocommerce-skye-online' ),
        'type'        => 'textarea',
        'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce-skye-online' ),
        'default'     => '',
        'desc_tip'    => true,
      ),
      'debug' => array(
        'title'       => __( 'Debug Log', 'woocommerce-skye-online' ),
        'type'        => 'checkbox',
        'label'       => __( 'Enable logging', 'woocommerce-skye-online' ),
        'default'     => 'no',
        'description' => sprintf( __( 'Log Gateway name events inside <code>%s</code>', 'woocommerce-skye-online' ), wc_get_log_file_path( $this->id ) )
      ),
      'sandbox' => array(
        'title'       => __( 'Sandbox', 'woocommerce-skye-online' ),
        'label'       => __( 'Enable Sandbox Mode', 'woocommerce-skye-online' ),
        'type'        => 'checkbox',
        'description' => __( 'Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woocommerce-skye-online' ),
        'default'     => 'yes'
      ),
      'secret_key' => array(
        'title'       => __( 'Secret Key', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Your Skye Online merchant secret key would have been supplied during sign up. Contact us if you cannot find it.', 'woocommerce-skye-online' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'price_widget' => array(
        'title'     => __( 'Price Widget', 'woocommerce' ),
        'type'      => 'checkbox',
        'label'     => __( 'Enable the Skye Online Price Widget', 'woocommerce-skye-online' ),
        'default'     => 'yes',
        'description' => 'Display a price widget in each product page.',
        'desc_tip'    => true
      ),  
       'price_widget_mode' => array(
        'title'     => __( 'Price Widget Modes', 'woocommerce' ),
        'type'      => 'select',
        'options'   => array(
           '' => 'Monthly',
           'weekly' => 'Weekly'
        ),
        'label'     => __( 'Price widget mode', 'woocommerce-skye-online' ),
        'default'     => '',
        'description' => 'Display price widget in monthly or weekly terms.',
        'desc_tip'    => true
      ),      
      'merchant_id' => array(
        'title'       => __( 'Merchant ID', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Your Skye Online merchant Id would have been supplied during sign up. Contact us if you cannot find it.', 'woocommerce-skye-online' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'operator_id' => array(
        'title'       => __( 'Operator ID', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Your Skye Online operator Id would have been supplied during sign up. Contact us if you cannot find it.', 'woocommerce-skye-online' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'operator_pwd' => array(
        'title'       => __( 'Operator Password', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Your Skye Online operator password would have been supplied during sign up. Contact us if you cannot find it.', 'woocommerce-skye-online' ),
        'default'     => '',
        'desc_tip'    => true
      ),
      'skye_promo_code' => array(
        'title'       => __( 'Default Interest term code', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Interest term code you want to offer your customers by default. Please contact us if unsure.', 'woocommerce-skye-online' ),
        'default'     => '31404',
        'desc_tip'    => true
      ),
      'skye_promo_term' => array(
        'title'       => __( 'Default Interest term (months)', 'woocommerce-skye-online' ),
        'type'        => 'text',
        'description' => __( 'Interest term you want to offer your customers by default. The term should match the promo code. Please contact us if unsure.', 'woocommerce-skye-online' ),
        'default'     => '6',
        'desc_tip'    => true
      ),
      'skye_minimum'    => array(
        'id'        => 'skye_minimum',
        'title'       => __( 'Minimum Order Total', 'woocommerce' ),
        'type'        => 'text',
        'default'     => '0',
        'description' => 'Minimum order total to use Skye Mastercard®. Empty for unlimited',
        'desc_tip'    => true,
      ),
      'skye_maximum'    => array(
        'id'        => 'skye_maximum',
        'title'       => __( 'Maximum Order Total', 'woocommerce' ),
        'type'        => 'text',
        'default'     => '999999',
        'description' => 'Maximum order total to use Skye Mastercard®. Empty for unlimited',
        'desc_tip'    => true,
      )
    );
  }

  /**
   * Output for the order received page.
   *
   * @access public
   * @return void
   */
  public function receipt_page( $order ) {
    echo '<p>' . __( 'Thank you - your order is now pending payment.', 'woocommerce-skye-online' ) . '</p>';

    // TODO: 
  }

  /**
   * Payment form on checkout page.
   *
   * @TODO:  Use this function to add credit card 
   *         and custom fields on the checkout page.
   * @access public
   */
  public function payment_fields() {
    $description = $this->get_description();

    if( $this->sandbox == 'yes' ) {
      if (empty( $description ))
      {
        $description .= ' ' . __( 'TEST MODE ENABLED.' );
      }
    }

    if( !empty( $description ) ) {
      echo wpautop( wptexturize( trim( $description ) ) );
    }

    // If credit fields are enabled, then the credit card fields are provided automatically.
    if( $this->credit_fields ) {
      $this->credit_card_form(
        array( 
          'fields_have_names' => false
        )
      );
    }

    // This includes your custom payment fields.
    include_once( WC_Skye_Online()->plugin_path() . '/includes/views/html-payment-fields.php' );

  }

  /**
   * Outputs scripts used for the payment gateway.
   *
   * @access public
   */
  public function payment_scripts() {
    if( !is_checkout() || !$this->is_available() ) {
      return;
    }

    $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

    // TODO: Enqueue your wp_enqueue_script's here.

  }
  /**
  * This is a filter setup to receive the results from the flexi services to show the required
  * outcome for the order based on the 'x_result' property
  * @param $order_id
  * @return mixed
  */
  function payment_finalisation($order_id)
  {
    $order = wc_get_order($order_id);
    $cart = WC()->cart;

    $scheme = 'http';
    if (!empty($_SERVER['HTTPS'])) {
      $scheme = 'https';
    }

    $full_url = sprintf(
      '%s://%s%s',
      $scheme,
      $_SERVER['HTTP_HOST'],
      $_SERVER['REQUEST_URI']
    );
    $parts = parse_url($full_url, PHP_URL_QUERY);
    parse_str($parts, $params);

    $this->log->add( $this->id, 'Processing orderId: %s  (ID: ' . $params[0] . ')' );
    // we need order information in order to complete the order
    if (empty($order)) {
      $this->log->add( $this->id, 'unable to get order information for orderId: %s  (ID: ' . $order_id . ')' );
      return $order_id;
    }

    // make sure we have an flexi order
    if ($order->get_data()['payment_method'] !== $this->id) {
      // we don't care about it because it's not an flexi order
      // only log in debug mode      
      $this->log->add( $this->id, 'No action required orderId: $order_id is not an'.$this->id.' order ' );
      return $order_id;
    }

    
     $this->log->add( $this->id, 'Processing orderId: %s  (ID: ' . $params['transaction'] . ')' );
    // Get the status of the order from XPay and handle accordingly
    $flexi_result_note = '';                
    $flexi_result_note = __( 'Payment approved using ' . $this->id . '. Reference #' . $params['transaction'], 'woocommerce');
    $ipl_transaction_result = $this->get_ipl_transaction($params['transaction']);
    $this->log->add( $this->id, 'result:'.$ipl_transaction_result);
    if( 'ACCEPTED' == $ipl_transaction_result ) {

      $commited_transaction = $this->commit_ipl_transaction($params['transaction']);
      if ($commited_transaction)
      {
        // Payment complete.
        $order->payment_complete();

        // Store the transaction ID for WC 2.2 or later.
        add_post_meta( $order->id, '_transaction_id', $params['transaction'], true );

        // Add order note.
        $order->add_order_note( sprintf( __( 'Skye online payment approved (ID: %s)', 'woocommerce-skye-online' ), $params['transaction'] ) );

        if( $this->debug == 'yes' ) {
          $this->log->add( $this->id, 'Skye online payment approved (ID: ' . $params['transaction'] . ')' );
        }

        // Reduce stock levels.
        $order->reduce_order_stock();

        if( $this->debug == 'yes' ) {
          $this->log->add( $this->id, 'Stocked reduced.' );
        }

        // Remove items from cart.
        WC()->cart->empty_cart();

        if( $this->debug == 'yes' ) {
          $this->log->add( $this->id, 'Cart emptied.' );
        }  
      } else {
        // Add order note.
        $order->add_order_note( __( 'Skye online payment not committed: '. $params['transaction'], 'woocommerce-skye-online' ) );
        // Return message to customer.
        return array(
          'result'   => 'failure',
          'message'  => 'Skye commit failed',
          'redirect' => ''
        );
      }

    }else {
      // Add order note.
      $order->add_order_note( __( 'Skye online payment '. $ipl_transaction_result, 'woocommerce-skye-online' ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Skye Online payment '. $ipl_transaction_result .' (ID: ' . $params['transaction'] . ')' );
      }

      // Return message to customer.
      return array(
        'result'   => 'failure',
        'message'  => '',
        'redirect' => ''
      );
    }
    return $order_id;
  }  

  /**
   * Output for the order received page.
   *
   * @access public
   */
  public function thankyou_page( $order_id ) {
    if( !empty( $this->instructions ) ) {
      echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
    }

    $this->extra_details( $order_id );
  }

  /**
  * This is a filter setup to override the title on the order received page
  * in the case where the payment has failed
  * @param $title
  * @return string
  */
  function order_received_title( $title ) {
    global $wp_query;
    //copying woocommerce logic from wc_page_endpoint_title() in wc-page-functions.php
    if ( ! is_null( $wp_query ) && ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_wc_endpoint_url() ) {
      //make sure we are on the Order Received page and have the payment result available
      $endpoint = WC()->query->get_current_endpoint();
      if( $endpoint == 'order-received' && ! empty( $_GET['transaction'] ) ){
        //look at the transaction query var. Ideally we'd load the order and look at the status, but this has not been updated when this filter runs
        if( $_GET['transaction'] == '' ){
          $title = 'Payment Failed';
        }
      }
      //copying woocommerce code- the filter only needs to run once
      remove_filter( 'the_title', array( $this, 'order_received_title' ), 11 );
    }
    return $title;
  }

  /**
   * Add content to the WC emails.
   *
   * @access public
   * @param  WC_Order $order
   * @param  bool $sent_to_admin
   * @param  bool $plain_text
   */
  public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
    if( !$sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
      if( !empty( $this->instructions ) ) {
        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
      }

      $this->extra_details( $order->id );
    }
  }

  /**
   * Gets the extra details you set here to be 
   * displayed on the 'Thank you' page.
   *
   * @access private
   */
  private function extra_details( $order_id = '' ) {
    echo '<h2>' . __( 'Extra Details', 'woocommerce-skye-online' ) . '</h2>' . PHP_EOL;

    // TODO: Place what ever instructions or details the payment gateway needs to display here.
  }

  /**
   * Process the payment and return the result.
   *
   * @access public
   * @param  int $order_id
   * @return array
   */
  public function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );

    $ipl_transacion_id = $this->begin_ipl_transaction($order);

    if( $this->debug == 'yes' ) {
      $this->log->add( $this->id, 'Skye Online payment response: ' . print_r( $ipl_transacion_id, true ) . ')' );
    }        

    if ( $ipl_transacion_id != '')
    {
      return array(
        'result'  =>  'success',
        'redirect'  =>  $this->gateway_url.'seller='.$this->settings['merchant_id'].'&ifol=true&transactionId='.$ipl_transacion_id
      );
    } else {
      return array(
        'result'   => 'failure',
        'message'  => 'Skye BeginIPLTransaction failed',
        'redirect' => ''
      );
    }
    
  }
  /**
   * Begin IPL Transaction
   * Call BeginIPLTransaction SKYE IFOL service to initiaite transaction
   *
   * @access private   
   * @param  WC_Order $order
   * @return string $transaction_id
   */
  private function begin_ipl_transaction($order) {

    $this->log->add( $this->id, 'Address Shipping->' . $order->get_shipping_address_1() . ' ' .$order->get_shipping_address_2() );
    $this->log->add( $this->id, 'Address Billing->' . $order->get_billing_address_1() . ' ' .$order->get_billing_address_2() );

    $format_shipping_address = $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2();
    $format_shipping_address_group = $this->format_address($format_shipping_address, $order);

    $format_billing_address = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();    
    $format_billing_address_group = $this->format_address($format_billing_address, $order);


    $transaction_information = array (
      'MerchantId' => str_replace(PHP_EOL, ' ', $this->settings['merchant_id']),          
      'OperatorId' => str_replace(PHP_EOL, ' ', $this->settings['operator_id']),
      'Password' => str_replace(PHP_EOL, ' ', $this->settings['operator_pwd']),
      'EncPassword' => '',            
      'Offer' => str_replace(PHP_EOL, ' ',$this->settings['skye_promo_code']),
      'CreditProduct'=> str_replace(PHP_EOL, ' ', 'MyBuy_Online'),
      'NoApps' => '',
      'OrderNumber' => $order->get_id(),            
      'ApplicationId' => '',
      'Description' => '',
      'Amount' => $order->get_total(),            
      'ExistingCustomer' => '0',
      'Title' => '', 
      'FirstName' => $order->get_billing_first_name(),             
      'MiddleName' => '', 
      'Surname' => $order->get_billing_last_name(),           
      'Gender' => '', 
      'BillingAddress' => $format_billing_address_group, 
      'DeliveryAddress' => $format_shipping_address_group,
      'WorkPhoneArea' => '',
      'WorkPhoneNumber' => '',
      'HomePhoneArea' => '',
      'HomePhoneNumber' => '',            
      'MobilePhoneNumber' => preg_replace('/\D+/', '', $order->get_billing_phone()),            
      'EmailAddress' => str_replace(PHP_EOL, ' ', $order->get_billing_email()),
      'Status' => '',
      'ReturnApprovedUrl' => str_replace(PHP_EOL, ' ', $this->get_return_url( $order ).'&transaction=[TRANSACTIONID]'),
      'ReturnDeclineUrl' => str_replace(PHP_EOL, ' ', $order->get_cancel_order_url_raw().'&transaction=[TRANSACTIONID]'),
      'ReturnWithdrawUrl' => str_replace(PHP_EOL, ' ', $order->get_cancel_order_url_raw().'&transaction=[TRANSACTIONID]'),
      'ReturnReferUrl' => str_replace(PHP_EOL, ' ', $order->get_cancel_order_url_raw().'&transaction=[TRANSACTIONID]'),
      'SuccessPurch' => '',
      'SuccessAmt' => '',
      'DateLastPurch' => '',
      'PayLastPurch' => '',
      'DateFirstPurch' => '',
      'AcctOpen' => '',
      'CCDets' => '',
      'CCDeclines' => '',
      'CCDeclineNum' => '',
      'DeliveryAddressVal' => '',
      'Fraud' => '',
      'EmailVal' => '',
      'MobileVal' => '',
      'PhoneVal' => '',
      'TransType' => '',
      'UserField1' => '',
      'UserField2' => '',
      'UserField3' => '',
      'UserField4' => '',
      'SMSCustLink' => '',
      'EmailCustLink' => '',
      'SMSCustTemplate' => '',
      'EmailCustTemplate' => '',
      'SMSCustTemplate' => '',
      'EmailDealerTemplate' => '',
      'EmailDealerSubject' => '',
      'EmailCustSubject' => '',
      'DealerEmail' => '',
      'DealerSMS' => '',
      'CreditLimit' => ''
   );

    $params = array(                
      'TransactionInformation' => $transaction_information, 
      'SecretKey' => $this->settings['secret_key']     
    );   
    $transaction_id = '';
    $soap_client = new SoapClient($this->wsdl_url, ['trace' => true, 'exceptions' => true]);
    try{        
      $response = $soap_client->__soapCall('BeginIPLTransaction',[$params]);             
      $transaction_id = $response->BeginIPLTransactionResult;                                
    }catch(Exception $ex){            
      $this->log->add( $this->id, 'Exception: response->' . $transaction_id . ' ' . $ex->getMessage() );      
      $this->log->add( $this->id, 'Exception: request->' . $soap_client->__getLastRequest() . ' ' . $ex->getMessage() );
      $this->log->add( $this->id, 'Exception: request->' . $soap_client->__getLastResponse() . ' ' . $ex->getMessage() );                  
    }    
    return $transaction_id;
  }

  /**
   * Get IPL Transaction Status
   * Get IPL transaction status
   *
   * @access private
   * @param  string $address_parts   
   * $param  WC_Order $order
   */
  private function get_ipl_transaction ($transaction_id){
    $get_ipl_transaction = array (
      'TransactionID' => str_replace(PHP_EOL, ' ',$transaction_id),
      'MerchantId' => str_replace(PHP_EOL, ' ',$this->settings['merchant_id'])
    );      

    $soapclient = new SoapClient($this->wsdl_url, ['trace' => true, 'exceptions' => true]);   
    try{                  
      $response = $soapclient->__soapCall('GetIPLTransactionStatus',[$get_ipl_transaction]);
      $ipl_transaction_result = $response->GetIPLTransactionStatusResult;         
    }catch(Exception $ex){   
      $this->log->add( $this->id, 'An exception was encountered in get_ipl_transaction:' . $transaction_id . ' ' . $ex->getMessage() ); 
      $this->log->add( $this->id, 'An exception was encountered in get_ipl_transaction response->:' . $soapclient->__getLastRequest() . ' ' . $ex->getMessage() );         
    }
    return $ipl_transaction_result;  
  }

  /**
   * Commit IPL Transaction
   * Commit transaction in Skye
   *
   * @access private
   * @param  string $transaction_id      
   */
  private function commit_ipl_transaction($transaction_id) {
    $commit_ipl_transaction = array (
      'TransactionID' => str_replace(PHP_EOL, ' ',$transaction_id),
      'MerchantId' => str_replace(PHP_EOL, ' ',$this->settings['merchant_id'])
    );
    $commit_ipl_transaction_result = false;
    $soapclient = new SoapClient($this->wsdl_url, ['trace' => true, 'exceptions' => true]);
    try{                        
      $response = $soapclient->__soapCall('CommitIPLTransaction',[$commit_ipl_transaction]);
      $commit_ipl_transaction_result = $response->CommitIPLTransactionResult;       
    }catch(Exception $ex){            
      $this->log->add( $this->id, 'An exception was encountered in commit_ipl_transaction:' . $transaction_id . ' ' . $ex->getMessage() ); 
      $this->log->add( $this->id, 'An exception was encountered in commit_ipl_transaction response->:' . $soapclient->__getLastRequest() . ' ' . $ex->getMessage() );            
    }               
    return $commit_ipl_transaction_result;
  }

  /**
   * Format address
   * Parse address to populate IFOL address fields correctly
   *
   * @access private
   * @param  string $address_parts   
   * @param  WC_Order $order
   * @return array $formatted_address
   */
  private function format_address($address_parts, $order) {        
    $order_data = $order->get_data();
    $address_street0 = explode(' ', $address_parts);  
    $address_street_count = count($address_street0);
    $this->log->add( $this->id, 'Address street 0->' . $address_parts . ' ' . $address_street_count);
    foreach ($address_street0 as $address_value0) {
      
      if (is_numeric($address_value0)) 
      { 
        $address_no_str = $address_value0;
      }                
    }

    if ($address_street_count == 4)
    {                
      $address_name_str = $address_street0[$address_street_count - 3];                                       
      $address_type_str = $address_street0[$address_street_count - 2];                
    }                         
            
    $this->log->add( $this->id, 'Address street ->' . $address_no_str . ' ' . $address_name_str . ' ' . $address_type_str);
    $formatted_address = array(           
      'AddressType' => 'Residential', 
      'UnitNumber' => '', 
      'StreetNumber' => $address_no_str? $address_no_str : '',
      'StreetName' => $address_name_str? $address_name_str : '',
      'StreetType' => $address_type_str? $address_type_str : '',
      'Suburb' => str_replace(PHP_EOL, ' ', $order_data['billing']['city']), 
      'City' => str_replace(PHP_EOL, ' ', $order_data['billing']['city']),
      'State' => str_replace(PHP_EOL, ' ', $order_data['billing']['state']),             
      'Postcode' => str_replace(PHP_EOL, ' ', $order_data['billing']['postcode']),
      'DPID' => ''
    );   
        
    return $formatted_address;
  }
  /**
   * Process refunds.
   * WooCommerce 2.2 or later
   *
   * @access public
   * @param  int $order_id
   * @param  float $amount
   * @param  string $reason
   * @return bool|WP_Error
   */
  public function process_refund( $order_id, $amount = null, $reason = '' ) {

    $payment_id = get_post_meta( $order_id, '_transaction_id', true );
    $response = ''; // TODO: Use this variable to fetch a response from your payment gateway, if any.

    if( is_wp_error( $response ) ) {
      return $response;
    }

    if( 'APPROVED' == $refund['status'] ) {

      // Mark order as refunded
      $order->update_status( 'refunded', __( 'Payment refunded via Skye Online.', 'woocommerce-skye-online' ) );

      $order->add_order_note( sprintf( __( 'Refunded %s - Refund ID: %s', 'woocommerce-skye-online' ), $refunded_cost, $refund_transaction_id ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Skye online order #' . $order_id . ' refunded successfully!' );
      }
      return true;
    }
    else {

      $order->add_order_note( __( 'Error in refunding the order.', 'woocommerce-skye-online' ) );

      if( $this->debug == 'yes' ) {
        $this->log->add( $this->id, 'Error in refunding the order #' . $order_id . '. Skye Online response: ' . print_r( $response, true ) );
      }

      return true;
    }

  }

  /**
   * Get the transaction URL.
   *
   * @TODO   Replace both 'view_transaction_url'\'s. 
   *         One for sandbox/testmode and one for live.
   * @param  WC_Order $order
   * @return string
   */
  public function get_transaction_url( $order ) {
    if( $this->sandbox == 'yes' ) {
      $this->view_transaction_url = 'https://cxskyecsp.flexicards.com.au/PromotionSelector?';
    }
    else {
      $this->view_transaction_url = 'https://apply.flexicards.com.au/PromotionSelector?';
    }

    return parent::get_transaction_url( $order );
  }  

} // end class.

?>