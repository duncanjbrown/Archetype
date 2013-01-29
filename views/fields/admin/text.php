<table class="form-table">

	<tr>
		<th><label for="<?php echo $this->slug; ?>"><?php echo $this->title; ?></label></th>

		<td>
			<input type="text" name="<?php echo $this->meta_key; ?>" id="<?php echo $this->slug; ?>" value="<?php echo esc_attr( get_the_author_meta( $this->meta_key , $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php echo $this->desc; ?></span>
		</td>
	</tr>

</table>