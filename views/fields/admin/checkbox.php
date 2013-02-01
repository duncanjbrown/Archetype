<table class="form-table">

	<tr>
		<th><label for="<?php echo $this->slug; ?>"><?php echo $this->title; ?></label></th>

		<td>
			<input type="checkbox" name="<?php echo $this->meta_key; ?>" value="1" id="<?php echo $this->slug; ?>" <?php checked( get_user_meta( $user->ID, $this->meta_key, true ) ); ?> /><br />
			<span class="description"><?php echo $this->desc; ?></span>
		</td>
	</tr>

</table>