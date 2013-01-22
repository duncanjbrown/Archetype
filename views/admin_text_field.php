<table class="form-table">

	<tr>
		<th><label for="<?php echo $this->get_slug(); ?>"><?php echo $this->get_name(); ?></label></th>

		<td>
			<input type="text" name="<?php echo $this->get_meta_key(); ?>" id="<?php echo $this->get_slug(); ?>" value="<?php echo esc_attr( get_the_author_meta( $this->get_meta_key() , $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php echo $this->get_desc(); ?></span>
		</td>
	</tr>

</table>