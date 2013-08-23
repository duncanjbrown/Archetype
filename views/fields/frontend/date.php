<link rel="stylesheet" href="<?php echo AT_PLUGIN_URL; ?>js/lib/kalendae/kalendae.css" type="text/css" charset="utf-8" />
<label for="<?php echo $this->name; ?>"><?php echo $this->title; ?></label>
<input type="text" name="<?php echo $this->name; ?>" value="<?php $this->get_value(null); ?>" class="datepicker">