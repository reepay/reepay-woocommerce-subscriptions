jQuery( function( $ ) {
    var billwerk_terms_toggle = {
        init: function() {
            $( document.body ).on( 'click', 'a.billwerk-optimize-terms-and-conditions-link', this.toggle_terms );
        },

        toggle_terms: function() {
            if ( $( '.billwerk-optimize-terms-and-conditions' ).length ) {
                $( '.billwerk-optimize-terms-and-conditions' ).slideToggle( function() {
                    var link_toggle = $( '.billwerk-optimize-terms-and-conditions-link' );

                    if ( $( '.billwerk-optimize-terms-and-conditions' ).is( ':visible' ) ) {
                        link_toggle.addClass( 'billwerk-optimize-terms-and-conditions-link--open' );
                        link_toggle.removeClass( 'billwerk-optimize-terms-and-conditions-link--closed' );
                    } else {
                        link_toggle.removeClass( 'billwerk-optimize-terms-and-conditions-link--open' );
                        link_toggle.addClass( 'billwerk-optimize-terms-and-conditions-link--closed' );
                    }
                } );

                return false;
            }
        }
    };

    billwerk_terms_toggle.init();
});