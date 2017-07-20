<?php 
// File: OAuthServer/View/Clients/index.ctp

$page_options = array();

if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
{
	$page_options[] = $this->Html->link(__('Add %s', __('Client')), array('action' => 'add'));
}

// content
$th = array();
$th['Client.client_name'] = array('content' => __('Name'), 'options' => array('sort' => 'Client.client_name'));
$th['Client.client_id'] = array('content' => __('ID'), 'options' => array('sort' => 'Client.client_id'));
$th['Client.redirect_uri'] = array('content' => __('Default Redirect URI'), 'options' => array('sort' => 'Client.redirect_uri'));
if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
	$th['Client.client_secret'] = array('content' => __('Secret'), 'options' => array('sort' => 'Client.client_secret'));
$th['Client.active'] = array('content' => __('Active'), 'options' => array('sort' => 'Client.active'));
$th['Client.actions'] = array('content' => __('Actions'), 'options' => array('class' => 'actions'));

$td = array();
foreach ($clients as $i => $client)
{
	$actions = array(
		$this->Html->link(__('View'), array('action' => 'view', $client['Client']['client_id'])),
	);
	$active = $this->Wrap->yesNo($client['Client']['active']);
	if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
	{
		$actions[] = $this->Html->link(__('Edit'), array('action' => 'edit', $client['Client']['client_id']));
		$actions[] = $this->Form->postLink(__('Delete'),array('action' => 'delete', $client['Client']['client_id']), array('confirm' => 'Are you sure?'));
		$active = array(
			$this->Form->postLink($active, array('action' => 'toggle', 'active', $client['Client']['client_id']), array('confirm' => 'Are you sure?')), 
			array('class' => 'actions'),
		);
	}
	
	$td[$i] = array();
	$td[$i][] = $this->Html->link($client['Client']['client_name'], array('action' => 'view', $client['Client']['client_id']));
	$td[$i][] = $client['Client']['client_id'];
	$td[$i][] = $client['Client']['redirect_uri'];
	if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
		$td[$i][] = $client['Client']['client_secret'];
	$td[$i][] = $active;
	$td[$i][] = array(
		implode('', $actions), 
		array('class' => 'actions'),
	);
}

echo $this->element('Utilities.page_index', array(
	'page_title' => __('Manage %s', __('Clients')),
	'page_options' => $page_options,
	'th' => $th,
	'td' => $td,
));