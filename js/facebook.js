window.at_facebook = function( FB, $ ){

	function loginWithFacebook( facebookResponse ) {
		Archetype.post( 'fb_login', { response: facebookResponse }, function( result ) {
			$( document ).trigger( 'Archetype_FB_Login', result );
		});
	}

	function connectWithFacebook( facebookResponse ) {
		Archetype.post( 'fb_connect', { response: facebookResponse }, function( result ) {
			$( document ).trigger( 'Archetype_FB_Connect', result );
		});
	}

	FB.Event.subscribe('auth.authResponseChange', function(response) {
		if( response.status === 'connected' && !Archetype.isUserLoggedIn() ) {
			loginWithFacebook( response );
		} else if( response.status === 'connected' ) {
			connectWithFacebook( response );
		}
	});

	$( '.at_fb_login' ).click( function() {
		var scope = $( this ) .data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	});

	$( '.at_fb_connect' ).click( function() {
		var scope = $(this).data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	});

}