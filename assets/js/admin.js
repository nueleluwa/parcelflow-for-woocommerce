/* ParcelFlow admin: confirmation prompts for state-changing actions. No secrets handled here. */
( function () {
	'use strict';
	var t = window.parcelflowAdmin || {};

	document.addEventListener( 'submit', function ( e ) {
		var key = e.target && e.target.getAttribute && e.target.getAttribute( 'data-pf-confirm' );
		if ( key && t[ key ] && ! window.confirm( t[ key ] ) ) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest ? e.target.closest( 'a[data-pf-confirm]' ) : null;
		if ( a ) {
			var key = a.getAttribute( 'data-pf-confirm' );
			if ( t[ key ] && ! window.confirm( t[ key ] ) ) {
				e.preventDefault();
			}
		}
	} );
}() );
