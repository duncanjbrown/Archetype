window.at_facebook = function( FB, $ ){

	var clicked = undefined; // last clicked FB element
	var signup = false;

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

	function signupWithFacebook( facebookResponse ) {
		var _clicked = clicked;
		$( document ).trigger( 'ArchetypeFB_AJAXstart', _clicked );
		FB.api( '/me', function( userinfo ) {
			$( document ).trigger( 'ArchetypeFB_AJAXstop', _clicked );
			$( document ).trigger( 'ArchetypeFB_Signup', [ facebookResponse, userinfo ] );
		} );
	}

	FB.Event.subscribe('auth.authResponseChange', function(response) {
		if( response.status === 'connected' && !Archetype.isUserLoggedIn() ) {
			
			console.log( signup );
			if( signup )
				signupWithFacebook( response );
			else
				loginWithFacebook( response );
		
		} else if( response.status === 'connected' ) {
			connectWithFacebook( response );
		}
	});

	function contactFacebook( e ) {
		clicked = e.target;
		console.log( 'co' );
		var scope = $( this ) .data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	}

	$( '.at_fb_login' ).click( contactFacebook );
	$( '.at_fb_connect' ).click( contactFacebook );
	$( '.at_fb_signup' ). click( function( e ) {
		signup = true;
		contactFacebook( e );
	} );

}