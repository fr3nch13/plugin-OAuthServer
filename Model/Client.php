<?php

App::uses('OAuthServerAppModel', 'OAuthServer.Model');
App::uses('OAuthServerComponent', 'OAuthServer.Controller/Component');
App::uses('String', 'Utility');

/**
 * Client Model
 *
 * @property AccessToken $AccessToken
 * @property AuthCode $AuthCode
 * @property RefreshToken $RefreshToken
 */
class Client extends OAuthServerAppModel 
{

/**
 * Primary key field
 *
 * @var string
 */
	public $primaryKey = 'client_id';

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'client_name';

/**
 * Secret to distribute when using addClient
 *
 * @var type
 */
	protected $addClientSecret = false;

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'client_name' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
		),
		'client_id' => array(
			'isUnique' => array(
				'rule' => array('isUnique'),
			),
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
		),
		'redirect_uri' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
		),
	);

	public $actsAs = array(
		'OAuthServer.HashedField' => array(
			'fields' => array(
				'client_secret'
			),
		),
	);

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'AccessToken' => array(
			'className' => 'OAuthServer.AccessToken',
			'foreignKey' => 'client_id',
			'dependent' => false,
		),
		'AuthCode' => array(
			'className' => 'OAuthServer.AuthCode',
			'foreignKey' => 'client_id',
			'dependent' => false,
		),
		'RefreshToken' => array(
			'className' => 'OAuthServer.RefreshToken',
			'foreignKey' => 'client_id',
			'dependent' => false,
		),
		'ClientsUser' => array(
			'className' => 'ClientsUser',
			'foreignKey' => 'client_id',
			'dependent' => true,
		),
		'LoginHistory' => array(
			'className' => 'LoginHistory',
			'foreignKey' => 'client_id',
			'dependent' => true,
		),
		'AuthorizeHistory' => array(
			'className' => 'OAuthServer.AuthorizeHistory',
			'foreignKey' => 'client_id',
			'dependent' => true,
		),
	);
	
	// fields that are boolean and can be toggled
	public $toggleFields = array('active');
	
	public $clientUpdateUri = '/oauth_client/index.json';
	public $clientUpdateUserExcludeFields = ['modified', 'created', 'lastlogin', 'remote_user', 'paginate_items'];

	public function afterSave($created, $options = array()) {
		if ($this->addClientSecret) {
			$this->data['Client']['client_secret'] = $this->addClientSecret;
		}
		return true;
	}
	
	public function afterFind($results = array(), $primary = false)
	{
		foreach($results as $i => $result)
		{
			if(!isset($result[$this->alias]))
				continue;
			if(!isset($result[$this->alias][$this->primaryKey]))
				continue;
			
			$results[$i][$this->alias]['roles'] = $this->getUserRoles($result);
		}
		return parent::afterFind($results, $primary);
	}

/**
 * AddClient
 *
 * Convinience function for adding client, will create a uuid client_id and random secret
 *
 * @param mixed $data Either an array (e.g. $controller->request->data) or string redirect_uri
 * @return booleen Success of failure
 */
	public function add($data = null) {
		$this->data['Client'] = array();

		if (is_array($data) && is_array($data['Client']) && array_key_exists('redirect_uri', $data['Client'])) {
			$this->data['Client']['redirect_uri'] = $data['Client']['redirect_uri'];
		} elseif (is_string($data)) {
			$this->data['Client']['redirect_uri'] = $data;
		} else {
			return false;
		}

		/**
		 * in case you have additional fields in the clients table such as name, description etc
		 * and you are using $data['Client']['name'], etc to save
		 **/
		if (is_array($data['Client'])) {
			$this->data['Client'] = array_merge($data['Client'], $this->data['Client']);
		}

		//You may wish to change this
		$this->data['Client']['client_id'] = base64_encode(uniqid() . substr(uniqid(), 11, 2));	// e.g. NGYcZDRjODcxYzFkY2Rk (seems popular format)
		//$this->data['Client']['client_id'] = uniqid();					// e.g. 4f3d4c8602346
		//$this->data['Client']['client_id'] = str_replace('.', '', uniqid('', true));		// e.g. 4f3d4c860235a529118898
		//$this->data['Client']['client_id'] = str_replace('-', '', String::uuid());		// e.g. 4f3d4c80cb204b6a8e580a006f97281a

		$this->addClientSecret = $this->newClientSecret();
		$this->data['Client']['client_secret'] = $this->addClientSecret;

		return $this->save($this->data);
	}

/**
 * Create a new, pretty (as in moderately, not beautiful - that can't be guaranteed ;-) random client secret
 *
 * @return string
 */
	public function newClientSecret() {
		$length = 40;
		$chars = '@#!%*+/-=ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$str = '';
		$count = strlen($chars);
		while ($length--) {
			$str .= $chars[mt_rand(0, $count - 1)];
		}
		return OAuthServerComponent::hash($str);
	}
	
	public function authorizeAttempt($user_id = false, $client_id = false, $success = false, $extra = array())
	{
		
		$data = array(
			'user_agent' => env('HTTP_USER_AGENT'),
			'ipaddress' => env('REMOTE_ADDR'),
			'success' => $success,
			'user_id' => $user_id,
			'client_id' => $client_id,
			'timestamp' => date('Y-m-d H:i:s'),
		);
		$data = array_merge($data, $extra);
		
		$this->AuthorizeHistory->create();
		return $this->AuthorizeHistory->save($data);
	}
	
	public function getUserRoles($client = array())
	{
		$defaults = array('regular' => __('Regular'), 'admin' => __('Admin'));
		if(!$client)
			return $defaults;
		
		if(!isset($client[$this->alias]['redirect_uri']))
			return $defaults;
		
		if($roles = Cache::read(md5($client[$this->alias]['redirect_uri']), 'OAuthServer'))
			return $roles;
		
		$roles = @file_get_contents($client[$this->alias]['redirect_uri']. '/users/user_roles.json');
		$roles = trim($roles);
		if(!preg_match('/^\{/', $roles))
		{
			Cache::write(md5($client[$this->alias]['redirect_uri']), $defaults, 'OAuthServer');
			return $defaults;
		}
		
		$roles = json_decode($roles);
		if(isset($roles->roles))
		{
			$formattedRoles = array();
			foreach($roles->roles as $i => $role)
			{
				$formattedRoles[$role] = Inflector::humanize($role);
			}
			
			Cache::write(md5($client[$this->alias]['redirect_uri']), $formattedRoles, 'OAuthServer');
			return $formattedRoles;
		}
		
		return $defaults;
	}
	
	public function getActiveClients()
	{
		$clients = $this->find('all', [
			'conditions' => [
				$this->alias.'.active' => true,
			],
		]);
		return $clients;
	}
	
	public function getUsersForUpdate($clientId = false)
	{
		if(!$clientId)
			return [];
		
		$_users = $this->ClientsUser->find('all', [
			'contain' => ['User'],
			'conditions' => [
				'ClientsUser.client_id' => $clientId,
			],
		]);
		
		$users = [];
		foreach($_users as $user)
		{
			$_user = $user['User'];
			foreach($this->clientUpdateUserExcludeFields as $field)
			{
				if(array_key_exists($field, $_user))
				{
					unset($_user[$field]);
				}
			}
			
			// set the role to the one assigned to this user for this client
			$_user['role'] = $user['ClientsUser']['role'];
			
			// update the active state for this user in this client
			
			if($_user['active'])
				$_user['active'] = $user['ClientsUser']['active'];
			
			$users[] = ['User' => $_user];
		}
		return $users;
	}
	
	public function cron_updateClients()
	{
		$totalStart = time();
		if(Configure::read('debug') > 1)
			Configure::write('debug', 1);
		
		$this->shellOut(__('Getting a list of active clients.'));
		$clients = $this->getActiveClients();
		$this->shellOut(__('Found %s active clients.', count($clients)));
		
		App::uses('HttpSocket', 'Network/Http');
		
		$socket = new HttpSocket([
//			'ssl_allow_self_signed' => true,
			'ssl_verify_peer' => false,
			'ssl_verify_host' => false,
		]);
		
		$successCnt = $failCnt = 0;
		foreach($clients as $client)
		{
			$clientStart = time();
			$this->shellOut(__('Updating Client %s.', $client[$this->alias]['client_name']));
			$this->shellOut(__('Getting Users for %s.', $client[$this->alias]['client_name']));
			
			if(!$users = $this->getUsersForUpdate($client[$this->alias]['client_id']))
			{
				$this->shellOut(__('NO Users found for %s.', $client[$this->alias]['client_name']));
				continue;
			}
			
			$this->shellOut(__('Found %s users for %s.', count($users), $client[$this->alias]['client_name']));
			
			// always needs the client id and secret so the client can authorize this request
			$data = [
				'client_id' => $client[$this->alias]['client_id'], 
				'client_secret' => $client[$this->alias]['client_secret']
			];
			
			// the requests need to reference which model they're trying to update
			$data['requests'] = ['User' => json_encode($users)];
			$updateUri = $client[$this->alias]['redirect_uri']. $this->clientUpdateUri;
			
			$this->shellOut(__('Sending update request to %s - %s.', $client[$this->alias]['client_name'], $updateUri));
			// post the data to the client
			$responseStart = time();
			$response = $socket->post($updateUri, $data);
			
			$responseLogType = 'info';
			if($response->code == 200)
			{
				$responseLogType = 'info';
				$successCnt++;
			}
			else
			{
				$responseLogType = 'warning';
				$failCnt++; 
			}
			
			$message = false;
			if($response->body)
			{
				if($content = json_decode($response->body))
				{
					if(isset($content->message))
						$message = $content->message;
				}
			}
			$responseDiff = time() - $responseStart;
			
			$this->shellOut(__('Client %s Response is: - code: %s, reason: %s, message: %s, response diff: %s.', $client[$this->alias]['client_name'], $response->code, $response->reasonPhrase, $message, $responseDiff), 'model', $responseLogType);
			
		}

		$totalDiff = time() - $totalStart;
		$this->shellOut(__('Sent updates to %s active clients. Success: %s - Fail: %s - total time: %s ', count($clients), $successCnt, $failCnt, $totalDiff));
	}
	
	public function getServerUserRoles()
	{
		$roles = Configure::read('Routing.prefixes');
		
		$formattedRoles = array();
		foreach($roles as $i => $role)
		{
			$formattedRoles[$role] = Inflector::humanize($role);
		}
		
		return $formattedRoles;
	}
}