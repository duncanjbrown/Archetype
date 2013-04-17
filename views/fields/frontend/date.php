<link rel="stylesheet" href="<?php echo AT_PLUGIN_URL; ?>js/lib/kalendae/kalendae.css" type="text/css" charset="utf-8">
<script src="<?php echo AT_PLUGIN_URL; ?>js/lib/kalendae/kalendae.standalone.js" type="text/javascript" charset="utf-8"></script>
<label for="<?php echo $this->name; ?>"><?php echo $this->title; ?></label>
<input type="text" name="<?php echo $this->name; ?>" value="<?php $this->get_value(null); ?>" class="datepicker"></div>