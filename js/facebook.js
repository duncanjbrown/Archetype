window.at_facebook = function( FB, $ ){

	var messageBox = $( '#fb_result' );

	function loginWithFacebook( facebookResponse ) {
		Archetype.post( 'fb_login', { response: facebookResponse }, function() {
			messageBox.text( 'Logging in...' );
			setTimeout( function() {
				window.location = '/';
			}, 500 );
		});
	}

	$( '.at_fb_login' ).click( function() {
		var scope = $( this ) .data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	});

	$( '.at_fb_connect' ).click( function() {
		var scope = $(this).data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	})

	FB.Event.subscribe('auth.authResponseChange', function(response) {
		if( response.status === 'connected' ) {
			loginWithFacebook( response );
		}
	});

}