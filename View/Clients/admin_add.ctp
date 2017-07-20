<?php ?>
<!-- File: OAuthServer/View/Clients/admin_add.ctp -->
<div class="top">
	<h1><?php echo __('Add %s', __('Client')); ?></h1>
</div>
<div class="center">
	<div class="posts form">
	<?php echo $this->Form->create('Client');?>
	    <fieldset>
	        <legend><?php echo __('Add %s', __('Client')); ?></legend>
	    	<?php
				echo $this->Form->input('client_name');
				echo $this->Form->input('redirect_uri', array(
					'label' => __('Default Redirect URI'),
				));
	    	?>
	    </fieldset>
	<?php echo $this->Form->end(__('Save %s', __('Client'))); ?>
	</div>
</div>