;window.archetype = (function ( $ ) {

	// private
    var ajaxurl = _Archetype.ajaxurl;
    var Archetype = function () {};

    // public
    Archetype.prototype = {
        constructor: Archetype,

        // return a $.promise object
        post: function ( action, data, done ) {

        	data.action = action;
        	return $.ajax({
        		type : 'post',
        		dataType : 'json',
        		url : ajaxurl,
        		data : data
        	}).done( done );
        },

        isUserLoggedIn: function() {
            return _Archetype.isUserLoggedIn;
        }

    };

    return Archetype;

})( jQuery );

var Archetype = new archetype();
