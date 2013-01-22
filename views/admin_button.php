<table class="form-table">

	<tr class="admin_button">
		<th><label class="button" for="<?php echo $this->get_slug(); ?>"><?php echo $this->get_name(); ?></label></th>

		<td>
			<input type="checkbox" name="<?php echo $this->get_slug(); ?>" value="1" id="<?php echo $this->get_slug(); ?>" <?php checked( get_user_meta( $user->ID, $this->get_meta_key(), true ) ); ?> /><br />
			<span class="description"><?php echo $this->get_desc(); ?></span>
		</td>
	</tr>

</table>