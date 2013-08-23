window.at_facebook = function( FB, $ ){

	var clicked = undefined; // last clicked FB element
	var signup = false;
	var self = this;

	function loginWithFacebook( facebookResponse ) {
		var _clicked = clicked;
		$( document ).trigger( 'ArchetypeFB_AJAXstart', _clicked );
		Archetype.post( 'fb_login', { response: facebookResponse }, function( result ) {
			console.log('cc');
			FB.api( '/me', function( userinfo ) {
				console.log(userinfo);
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
			
			if( signup ) {
				signupWithFacebook( response );
			} else {
				loginWithFacebook( response );
			}
		
		} else if( response.status === 'connected' ) {
			connectWithFacebook( response );
		}
	});

	/**
	 * Method fires whenever fb API call is made, 
	 * to set state for receiving callback
	 */
	this.contactFacebook = function( e ) {
		clicked = e.target;
		var scope = $( this ) .data( 'scope' );
		FB.login( function() {}, { scope: scope } );
	}

	$( document ).ready( function() {
		$( '.at_fb_login' ).on( 'click', function(e) {
			self.contactFacebook(e);
		} );
		$( '.at_fb_connect' ).on( 'click', function(e) {
			self.contactFacebook(e);
		} );
		$( '.at_fb_signup' ).on( 'click', function( e ) {
			signup = true;
			self.contactFacebook( e );
		} );		
	});

}