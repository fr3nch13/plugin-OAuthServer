<?php 
// File: app/View/AuthorizeHistories/admin_index.ctp

$page_options = array(
);

// content
$th = array(
	'User.name' => array('content' => __('User'), 'options' => array('sort' => 'User.name')),
	'Client.client_name' => array('content' => __('Client'), 'options' => array('sort' => 'Client.client_name')),
	'AuthorizeHistory.ipaddress' => array('content' => __('Ip Address'), 'options' => array('sort' => 'AuthorizeHistory.ipaddress')),
	'AuthorizeHistory.user_agent' => array('content' => __('User Agent'), 'options' => array('sort' => 'AuthorizeHistory.user_agent')),
	'AuthorizeHistory.success' => array('content' => __('Successful'), 'options' => array('sort' => 'AuthorizeHistory.success')),
	'details' => array('content' => __('Reason')),
	'AuthorizeHistory.timestamp' => array('content' => __('Time'), 'options' => array('sort' => 'AuthorizeHistory.timestamp')),
	'actions' => array('content' => __('Actions'), 'options' => array('class' => 'actions')),
);

$td = array();
foreach ($loginHistories as $i => $login_history)
{
	$user = '&nbsp';
	if($login_history['AuthorizeHistory']['user_id'] > 0)
	{
		$tmp = array('User' => $login_history['User']);
		$user = $this->Html->link($tmp['User']['name'], array('plugin' => false, 'controller' => 'users', 'action' => 'view', $tmp['User']['id']));
	}
	$td[$i] = array(
		$user,
		$this->Html->link($login_history['Client']['client_name'], array('plugin' => 'o_auth_server', 'controller' => 'clients', 'action' => 'view', $login_history['Client']['client_id'])),
		$login_history['AuthorizeHistory']['ipaddress'],
		$login_history['AuthorizeHistory']['user_agent'],
		$this->Wrap->yesNo($login_history['AuthorizeHistory']['success']),
		$login_history['AuthorizeHistory']['fail_reason'],
		$this->Wrap->niceTime($login_history['AuthorizeHistory']['timestamp']),
		array(
			$this->Form->postLink(__('Delete'),array('action' => 'delete', $login_history['AuthorizeHistory']['id']),array('confirm' => 'Are you sure?')), 
			array('class' => 'actions'),
		),
	);
}

echo $this->element('Utilities.page_index', array(
	'page_title' => __('Authorize History'),
	'search_placeholder' => __('login history'),
	'page_options' => $page_options,
	'th' => $th,
	'td' => $td,
));