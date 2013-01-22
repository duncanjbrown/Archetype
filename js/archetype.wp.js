window.archetype = (function ( $ ) {

	// private
    var ajaxurl = at_wp_js.ajaxurl;
    var Archetype = function () {};

    // public
    Archetype.prototype = {
        constructor: Archetype,

        // this returns a promise AJAX object
        post: function ( action, data, done ) {

        	data.action = action;
        	return $.ajax({
        		type : 'post',
        		dataType : 'json',
        		url : ajaxurl,
        		data : data
        	}).done( done );

        }
    };

    return Archetype;

})( jQuery );

var Archetype = new archetype();