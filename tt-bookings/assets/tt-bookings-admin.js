/* Media library picker for the email logo on the settings screen. */
( function ( $ ) {
	'use strict';

	$( function () {
		var frame;
		var $input = $( '#ttb-logo' );
		var $preview = $( '.ttb-logo-preview' );

		$( '[data-ttb-logo-choose]' ).on( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: 'Choose the logo for booking emails',
					library: { type: 'image' },
					button: { text: 'Use this image' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var url = attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;

					$input.val( attachment.id );
					$preview.attr( 'src', url ).removeAttr( 'hidden' );
				} );
			}

			frame.open();
		} );

		$( '[data-ttb-logo-clear]' ).on( 'click', function ( event ) {
			event.preventDefault();
			$input.val( 0 );
			$preview.attr( 'hidden', 'hidden' ).attr( 'src', '' );
		} );
	} );
}( jQuery ) );
