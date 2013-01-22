<div id="fb-root"></div>
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId      : '<?php echo $this->app_id; ?>', 
      channelUrl : '<?php echo site_url( "_fb_channel" ); ?>', 
      status     : false, //important 
      cookie     : true, 
      xfbml      : true  
    });

    window.AT_Facebook = new at_facebook( FB, jQuery );
  };

  (function(d, debug){
     var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
     if (d.getElementById(id)) {return;}
     js = d.createElement('script'); js.id = id; js.async = true;
     js.src = "//connect.facebook.net/en_US/all" + (debug ? "/debug" : "") + ".js";
     ref.parentNode.insertBefore(js, ref);
   }(document, /*debug*/ false));
</script>