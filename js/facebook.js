window.at_facebook = function( FB, $ ){

	var clicked = undefined; // last clicked FB element

	function loginWithFacebook( facebookResponse ) {
		var _clicked = clicked;
		$( document ).trigger( 'ArchetypeFB_AJAXstart', _clicked );
		Archetype.post( 'fb_login', { response: facebookResponse }, function( result ) {
			FB.api( '/me', function( userinfo ) {
				$( document ).trigger( 'ArchetypeFB_AJAXstop', _clicked );
				$( document ).trigger( 'ArchetypeFB_Login', [ result, userinfo ] );				
			} );
		});
	}

	function connectWithFacebook( facebookResponse ) {
		var _clicked = clicked;
		$( document ).trigger( 'ArchetypeFB_AJAXstart', _clicked );
		Archetype.post( 'fb_connect', { response: facebookResponse }, function( result ) {
			FB.api( '/me', function( userinfo ) {
				$( document ).trigger( 'ArchetypeFB_AJAXstop', _clicked );
				$( document ).trigger( 'ArchetypeFB_Connect', [ result, userinfo ] );				
			} );
		});
	}

	FB.Event.subscribe('auth.authResponseChange', function(response) {
		if( response.status === 'connected' && !Archetype.isUserLoggedIn() ) {
			loginWithFacebook( response );
		} else if( response.status === 'connected' ) {
			connectWithFacebook( response );
		}
	});

	$( '.at_fb_login' ).click( function( e ) {
		clicked = e.target;
		var scope = $( this ) .data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	});

	$( '.at_fb_connect' ).click( function( e ) {
		clicked = e.target;
		var scope = $(this).data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	});

}