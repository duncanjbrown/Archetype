<?php do_action( 'at_signup_form_top' ); ?>
<form action="" method="POST" id="at_signup_form">
	<?php wp_nonce_field( AT_USER_NONCE ); ?>
	<label for="at_email">Email</label>
	<input type="text" name="at_email" /><br />
	<label for="at_password">Password</label>
	<input type="text" name="at_password" /><br />
	<?php do_action( 'at_show_signup_fields' ); ?>
	<input type="submit" value="Go" />
</form>