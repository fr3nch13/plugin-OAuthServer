<?php
class AuthorizeHistory extends AppModel 
{
	
	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
		),
		'Client' => array(
			'className' => 'OAuthServer.Client',
			'foreignKey' => 'client_id',
		),
	);

	// define the fields that can be searched
	public $searchFields = array(
		'AuthorizeHistory.email',
		'AuthorizeHistory.ipaddress',
		'AuthorizeHistory.user_agent',
		'Client.client_name',
		'User.name',
	);
	
	public function failedAuthorizes($minutes = 5)
	{
		$minutes = '-'. $minutes. ' minutes';
		
		return $this->find('all', array(
			'recursive' => '0',
			'contain' => array('User'),
			'conditions' => array(
				'AuthorizeHistory.success' => 0,
				'AuthorizeHistory.timestamp >' => date('Y-m-d H:i:s', strtotime($minutes)),
			),
		));
	}
}