<?php 
/**
 * Expects RI_User $user, options hash $options
 */ ?>

<table class="form-table">

	<tr>
		<th><label for="<?php echo $this->get_slug(); ?>"><?php echo $this->title; ?></label></th>

		<td>
			<select name="<?php echo $this->meta_key; ?>" id="<?php echo $this->slug; ?>">
			    <?php $selected = get_user_meta( $user->ID, $this->meta_key, true );
			    foreach( $options as $code => $text ) : ?>
			        <option value="<?php echo $code; ?>" <?php selected( $code, $selected ); ?>><?php echo $text; ?></option>
			    <?php endforeach; ?>
			</select>
			<span class="description"><?php echo $this->desc; ?></span>
		</td>
	</tr>

</table>