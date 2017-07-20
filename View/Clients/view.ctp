<?php 
// File: app/View/Clients/admin_view.ctp

$page_options = array();

if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
{
	$page_options[] = $this->Form->postLink(__('Toggle Active State'),array('action' => 'toggle', 'active', $client['Client']['client_id']), array('confirm' => 'Are you sure?'));
	$page_options[] = $this->Html->link(__('Edit'), array('action' => 'edit', $client['Client']['client_id']));
	$page_options[] = $this->Form->postLink(__('Delete'),array('action' => 'delete', $client['Client']['client_id']), array('confirm' => 'Are you sure?'));
}

$details = array(
	array('name' => __('ID'), 'value' => $client['Client']['client_id']),
	array('name' => __('Default Redirect URI'), 'value' => $client['Client']['redirect_uri']),
	array('name' => __('Active'), 'value' => $this->Wrap->yesNo($client['Client']['active'])),
	array('name' => __('Created'), 'value' => $this->Wrap->niceTime($client['Client']['created'])),
	array('name' => __('Modified'), 'value' => $this->Wrap->niceTime($client['Client']['modified'])),
	
);

$stats = array();
$tabs = array();

$stats[] = array(
	'id' => 'Users',
	'name' => __('All %s', __('Users')), 
	'tip' => __('All associated %s.', __('Users')),
	'ajax_count_url' => array('plugin' => false, 'controller' => 'clients_users', 'action' => 'client', $client['Client']['client_id']),
	'tab' => array('tabs', count($tabs) + 1), // the tab to display
);

$tabs[] = array(
	'key' => 'Users',
	'title' => __('Users'),
	'url' => array('plugin' => false, 'controller' => 'clients_users', 'action' => 'client', $client['Client']['client_id']),
);

if($this->Common->roleCheck('admin') and $this->Common->isAdmin())
{
	$stats[] = array(
		'id' => 'LoginHistories',
		'name' => __('Login History'), 
		'ajax_count_url' => array('plugin' => false, 'controller' => 'login_histories', 'action' => 'client', $client['Client']['client_id']),
		'tab' => array('tabs', count($tabs)+1), // the tab to display
	);
	
	$tabs[] = array(
		'key' => 'LoginHistories',
		'title' => __('Login History'),
		'url' => array('plugin' => false, 'controller' => 'login_histories', 'action' => 'client', $client['Client']['client_id']),
	);
	
	$stats[] = array(
		'id' => 'AuthorizeHistories',
		'name' => __('Authorize History'), 
		'ajax_count_url' => array('plugin' => 'o_auth_server', 'controller' => 'authorize_histories', 'action' => 'client', $client['Client']['client_id']),
		'tab' => array('tabs', count($tabs)+1), // the tab to display
	);
	
	$tabs[] = array(
		'key' => 'AuthorizeHistories',
		'title' => __('Authorize History'),
		'url' => array('plugin' => 'o_auth_server', 'controller' => 'authorize_histories', 'action' => 'client', $client['Client']['client_id']),
	);
}

echo $this->element('Utilities.page_view', array(
	'page_title' => __('Client: %s', $client['Client']['client_name']),
	'page_options' => $page_options,
	'detailstitle' => ' ',
	'details' => $details,
	'stats' => $stats,
	'tabs_id' => 'tabs',
	'tabs' => $tabs,
));