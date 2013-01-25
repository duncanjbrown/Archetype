<ul>
<?php foreach( $this->choices as $choice ) : ?>
	<?php $slug = sanitize_title( $choice ); ?>
	<li><input type="checkbox" name="<?php echo $this->slug; ?>[]" value="<?php echo $slug; ?>" id="<?php echo $this->slug; ?>" <?php checked( in_array( $slug, $opt ), true ); ?> /> <?php echo ucfirst( $choice ); ?></li>
<?php endforeach; ?>
</ul>