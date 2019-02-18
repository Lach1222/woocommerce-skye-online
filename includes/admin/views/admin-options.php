<h3><?php _e( 'Skye Online', 'woocommerce-skye-online' ); ?></h3>

<div class="gateway-banner updated">
  <img src="<?php echo WC_Skye_Online()->plugin_url() . '/assets/images/logo.png'; ?>" />
  <p><?php _e( 'Allow customers to conveniently checkout directly with Skye Mastercard®.', 'woocommerce-skye-online' ); ?></p>

  <p class="main"><strong><?php _e( 'Gateway Status', 'woocommerce-skye-online' ); ?></strong></p>
  <ul>
    <li><?php echo __( 'Debug Enabled?', 'woocommerce-skye-online' ) . ' <strong>' . $this->debug . '</strong>'; ?></li>
    <li><?php echo __( 'Sandbox Enabled?', 'woocommerce-skye-online' ) . ' <strong>' . $this->sandbox . '</strong>'; ?></li>
  </ul>

  <?php if( empty( $this->secret_key ) ) { ?>
  <p><a href="https://www.skyecard.com.au/retail-partners#form-anchor target="_blank" class="button button-primary"><?php _e( 'Become a Skye Mastercard® retail partner', 'woocommerce-skye-online' ); ?></a> <a href="https://www.skyecard.com.au/retail-partners" target="_blank" class="button"><?php _e( 'Learn more', 'woocommerce-skye-online' ); ?></a></p>
  <?php } ?>
</div>

<table class="form-table">
  <?php $this->generate_settings_html(); ?>
  <script type="text/javascript">
  jQuery( '#woocommerce_skye_online_price_widget' ).change( function () {
    var widgetMode = jQuery( '#woocommerce_skye_online_price_widget_mode' ).closest( 'tr' )

    if ( jQuery( this ).is( ':checked' ) ) {
      widgetMode.show();      
    } else {
      widgetMode.hide();      
    }
  }).change();
  </script> 
</table>
