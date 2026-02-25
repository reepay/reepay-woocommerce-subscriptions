/**
 * Subscription terms checkbox for WooCommerce Blocks checkout.
 *
 * Injects the same checkbox used in the classic shortcode checkout
 * into the Blocks-based checkout page. Uses the WC Blocks validation
 * store for client-side validation and wp.apiFetch middleware to
 * pass the checkbox value through the Store API.
 */
(function( $ ) {
	'use strict';

	if ( ! window.reepayBlocksTermsData ) {
		return;
	}

	var data              = window.reepayBlocksTermsData;
	var inserted          = false;
	var validationErrorId = 'reepay_subscription_terms';

	function createCheckboxHtml() {
		var html = '<div class="billwerk-optimize-terms-and-conditions-wrapper" style="margin-bottom: 16px;">';

		if ( data.termsHtml ) {
			html += '<div class="billwerk-optimize-terms-and-conditions" style="display: none; max-height: 200px; overflow: auto; padding: 1em; box-shadow: inset 0 1px 3px rgba(0, 0, 0, .2); margin-bottom: 16px; background-color: rgba(0, 0, 0, .05);">';
			html += data.termsHtml;
			html += '</div>';
		}

		html += '<p class="form-row custom-checkbox" id="subscription_terms_field">';
		html += '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
		html += '<input type="checkbox" class="woocommerce-form__input-checkbox" name="subscription_terms" id="subscription_terms" value="1"> ';
		html += '<span class="required">*</span> ';
		html += data.label;
		html += '</label>';
		html += '</p>';

		html += '</div>';

		return html;
	}

	function setValidation( checked ) {
		if ( ! window.wp || ! wp.data ) {
			return;
		}

		try {
			if ( checked ) {
				wp.data.dispatch( 'wc/store/validation' ).clearValidationError( validationErrorId );
			} else {
				wp.data.dispatch( 'wc/store/validation' ).setValidationErrors( {
					[ validationErrorId ]: {
						message: data.errorMessage,
						hidden: true,
					},
				} );
			}
		} catch ( e ) {
			
		}
	}

	function insertCheckbox() {
		if ( inserted ) {
			return;
		}

		
		if ( document.querySelector( '.wc-block-checkout__reepay-subscription-terms' ) ) {
			return;
		}

		
		var actionsBlock = document.querySelector( '.wp-block-woocommerce-checkout-actions-block' );
		if ( ! actionsBlock ) {
			return;
		}

		var wrapper = document.createElement( 'div' );
		wrapper.innerHTML = createCheckboxHtml();
		var checkboxWrapper = wrapper.firstChild;
		actionsBlock.parentNode.insertBefore( checkboxWrapper, actionsBlock );

		inserted = true;

	
		setValidation( false );

		var checkbox = document.getElementById( 'subscription_terms' );
		if ( checkbox ) {
			checkbox.addEventListener( 'change', function() {
				setValidation( this.checked );
			} );
		}

		$( document ).on( 'click', 'a.billwerk-optimize-terms-and-conditions-link', function( e ) {
			e.preventDefault();
			var $terms = $( '.billwerk-optimize-terms-and-conditions' );
			if ( $terms.length ) {
				$terms.slideToggle( function() {
					var $link = $( '.billwerk-optimize-terms-and-conditions-link' );
					if ( $terms.is( ':visible' ) ) {
						$link.addClass( 'billwerk-optimize-terms-and-conditions-link--open' )
							.removeClass( 'billwerk-optimize-terms-and-conditions-link--closed' );
					} else {
						$link.removeClass( 'billwerk-optimize-terms-and-conditions-link--open' )
							.addClass( 'billwerk-optimize-terms-and-conditions-link--closed' );
					}
				} );
			}
		} );
	}

	
	if ( window.wp && wp.apiFetch ) {
		wp.apiFetch.use( function( options, next ) {
			if (
				options.path &&
				options.path.indexOf( '/wc/store' ) !== -1 &&
				options.path.indexOf( 'checkout' ) !== -1 &&
				options.method === 'POST'
			) {
				var checkbox = document.getElementById( 'subscription_terms' );
				if ( checkbox && options.data ) {
					if ( ! options.data.extensions ) {
						options.data.extensions = {};
					}
					options.data.extensions[ 'wc-reepay-woo-block-terms' ] = {
						reepay_subscription_terms: checkbox.checked,
					};
				}
			}
			return next( options );
		} );
	}

	
	var observer = new MutationObserver( function() {
		if ( ! inserted ) {
			insertCheckbox();
		} else {
			observer.disconnect();
		}
	} );

	if ( document.body ) {
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	
	document.addEventListener( 'DOMContentLoaded', insertCheckbox );
	window.addEventListener( 'load', insertCheckbox );

	insertCheckbox();

})( jQuery );
