<?php if( !$this->opts['hidden'] ) : ?>
	<label for="<?php echo $this->slug; ?>"><?php echo $this->name; ?></label>
<?php endif; ?>
<input id='at_text_field' data-bind="value: <?php echo $this->slug; ?>" name='<?php echo $this->slug; ?>' size='40' type='<?php echo $this->opts['hidden'] ? "hidden" : "text"; ?>' value='<?php echo $opt; ?>' />