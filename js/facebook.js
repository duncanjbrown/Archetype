window.at_facebook = function( FB, $ ){

	var messageBox = $( '#fb_result' );

	function loginWithFacebook( facebookResponse ) {
		Archetype.post( 'fb_login', { response: facebookResponse }, function() {
			messageBox.text( 'Logging in...' );
			setTimeout( function() {
				window.location = window.location;
			}, 500 );
		});
	}

	function connectWithFacebook( facebookResponse ) {
		Archetype.post( 'fb_connect', { response: facebookResponse }, function() {
			messageBox.text( 'Connecting to Facebook...' );
			setTimeout( function() {
				window.location = window.location;
			}, 500 );
		});
	}

	FB.Event.subscribe('auth.authResponseChange', function(response) {
		console.log( response );
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