<label for="<?php echo $this->name; ?>"><?php echo $this->title; ?></label>
<select name="<?php echo $this->name; ?>">
    <?php $selected = $this->get_value( null );
    foreach( $this->get_choices() as $code => $text ) : ?>
        <option value="<?php echo $code; ?>" <?php selected( $code, $selected ); ?>><?php echo $text; ?></option>
    <?php endforeach; ?>
</select>