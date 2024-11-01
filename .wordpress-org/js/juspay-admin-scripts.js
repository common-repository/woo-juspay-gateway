jQuery( function( $ ) {
	'use strict';
	
	/**
	 * Object to handle Juspay admin functions.
	 */
	var wc_juspay_admin = {
		getEnvironment: function() {
			return $( '#woocommerce_juspay_environment' ).val();
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_juspay_environment', function() {
				var environment = $( '#woocommerce_juspay_environment' ).val(),
					staging_api_key = $( '#woocommerce_juspay_staging_api_key' ).parents( 'tr' ).eq( 0 ),
					production_api_key = $( '#woocommerce_juspay_production_api_key' ).parents( 'tr' ).eq( 0 ),
					axis_api_key = $( '#woocommerce_juspay_axis_api_key' ).parents( 'tr' ).eq( 0 );

				if ( 'production' === environment ) {
					production_api_key.show();
					axis_api_key.hide();
					staging_api_key.hide();
				}
				else if ( 'axis' === environment ) {
					production_api_key.hide();
					axis_api_key.show();
					staging_api_key.hide();
				}
				else {
					production_api_key.hide();
					axis_api_key.hide();
					staging_api_key.show();
				}
			});

			$( '#woocommerce_juspay_environment' ).change();
		}
	};

	wc_juspay_admin.init();
});
