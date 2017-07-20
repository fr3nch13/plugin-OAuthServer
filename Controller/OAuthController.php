<?php

/**
 * CakePHP OAuthServer Server Plugin
 *
 * This is an example controller providing the necessary endpoints
 *
 * @author Thom Seddon <thom@seddonmedia.co.uk>
 * @see https://github.com/thomseddon/cakephp-OAuthServer-server
 *
 */

App::uses('OAuthServerAppController', 'OAuthServer.Controller');

/**
 * OAuthServerController
 *
 */
class OAuthController extends OAuthServerAppController 
{

	public $components = array('OAuthServer.OAuthServer', 'Security');

	public $uses = array('Users', 'Client');

	public $helpers = array('Form');

	private $blackHoled = false;

/**
 * beforeFilter
 *
 */
	public function beforeFilter() 
	{
		parent::beforeFilter();
		$this->OAuthServer->authenticate = array('fields' => array('username' => 'email'));
		$this->Auth->allow($this->OAuthServer->allowedActions);
		$this->Security->blackHoleCallback = 'blackHole';
	}

/**
 * Example Authorize Endpoint
 *
 * Send users here first for authorization_code grant mechanism
 *
 * Required params (GET or POST):
 *	- response_type = code
 *	- client_id
 *	- redirect_url
 *
 */
	public function authorize() 
	{
		if (!$this->Auth->loggedIn()) 
		{
			// instead of sending them to the login below, send them to the login method in the users controller
			//$this->redirect(array('action' => 'login', '?' => $this->request->query));
			$this->redirect(array('controller' => 'users', 'action' => 'login', '?' => $this->request->query, 'plugin' => false));
		}
		
		$this->validateRequest();
		
		if ($this->Session->check('OAuthServer.logout')) 
		{
			$this->Auth->logout();
			$this->Session->delete('OAuthServer.logout');
		}

		// Clickjacking prevention (supported by IE8+, FF3.6.9+, Opera10.5+, Safari4+, Chrome 4.1.249.1042+)
		$this->response->header('X-Frame-Options: DENY');

		if ($this->Session->check('OAuthServer.params')) 
		{
			$OAuthServerParams = $this->Session->read('OAuthServer.params');
			$this->Session->delete('OAuthServer.params');
		} 
		else 
		{
			try 
			{
				$OAuthServerParams = $this->OAuthServer->getAuthorizeParams();
			} 
			catch (Exception $e)
			{
				$e->sendHttpResponse();
			}
		}
		
		// at this point the user is logged in, and the client exists
		
		$user = $this->Auth->user();
		
		// make sure the client is active
		$client = $this->Client->read(null, $OAuthServerParams['client_id']);
		if(!$client['Client']['active'])
		{
			$fail_reason = __('The Portal "%s" is inactive.', $client['Client']['client_name']);
			$this->Client->authorizeAttempt($user['id'], $client['Client']['client_id'], false, array('fail_reason' => $fail_reason));
//			return $this->OAuthServer->throwAclException($fail_reason);
			return $this->redirect(array('plugin' => false, 'controller' => 'users', 'action' => 'help', 'client_id' => $client['Client']['client_id'], 'fail_reason' => base64_encode($fail_reason)));
		}
		
		// make sure the user is assigned to this client
		$xref = $this->Client->ClientsUser->find('first', array(
			'conditions' => array(
				'ClientsUser.user_id' => $user['id'],
				'ClientsUser.client_id' => $OAuthServerParams['client_id'],
			),
		));
		
		if(!$xref)
		{
			$fail_reason = __('You don\'t have access to the Portal "%s".', $client['Client']['client_name']);
			$this->Client->authorizeAttempt($user['id'], $client['Client']['client_id'], false, array('fail_reason' => $fail_reason));
//			return $this->OAuthServer->throwAclException($fail_reason);
			return $this->redirect(array('plugin' => false, 'controller' => 'users', 'action' => 'help', 'client_id' => $client['Client']['client_id'], 'fail_reason' => base64_encode($fail_reason)));
		}
		
		// make sure this user is active for this client
		
		if(!$xref['ClientsUser']['active'])
		{
			$fail_reason = __('Your access to the Portal "%s" has been marked inactive.', $client['Client']['client_name']);
			$this->Client->authorizeAttempt($user['id'], $client['Client']['client_id'], false, array('fail_reason' => $fail_reason));
//			return $this->OAuthServer->throwAclException($fail_reason);
			return $this->redirect(array('plugin' => false, 'controller' => 'users', 'action' => 'help', 'client_id' => $client['Client']['client_id'], 'fail_reason' => base64_encode($fail_reason)));
		}
		
		try 
		{
			$fail_reason = __('If a fail immediatly follows this with a %s, this is not successful.', 'OAuth2RedirectException');
			$this->Client->authorizeAttempt($user['id'], $client['Client']['client_id'], true, array('fail_reason' => $fail_reason));
			$this->OAuthServer->finishClientAuthorization(true, $user['id'], $OAuthServerParams);
		} 
		catch (OAuth2RedirectException $e) 
		{
			$fail_reason = 'OAuth2RedirectException';
			$this->Client->authorizeAttempt($user['id'], $client['Client']['client_id'], false, array('fail_reason' => $fail_reason));
			$e->sendHttpResponse();
		}
		
		$this->set(compact('OAuthServerParams'));
	}
/*
 * The original function.
 * This always asks if the user wants access. 
 * This access is being moved to another place so admins can define which clients the users can have access to.

	public function authorize() 
	{
		if (!$this->Auth->loggedIn()) 
		{
			// instead of sending them to the login below, send them to the login method in the users controller
			//$this->redirect(array('action' => 'login', '?' => $this->request->query));
			$this->redirect(array('controller' => 'users', 'action' => 'login', '?' => $this->request->query, 'plugin' => false));
		}

		if ($this->request->is('post')) 
		{
			$this->validateRequest();

			$userId = $this->Auth->user('id');

			if ($this->Session->check('OAuthServer.logout')) 
			{
				$this->Auth->logout();
				$this->Session->delete('OAuthServer.logout');
			}

			//Did they accept the form? Adjust accordingly
			$accepted = $this->request->data['accept'] == 'Yep';
			try 
			{
				$this->OAuthServer->finishClientAuthorization($accepted, $userId, $this->request->data['Authorize']);
			} 
			catch (OAuth2RedirectException $e) 
			{
				$e->sendHttpResponse();
			}
		}

		// Clickjacking prevention (supported by IE8+, FF3.6.9+, Opera10.5+, Safari4+, Chrome 4.1.249.1042+)
		$this->response->header('X-Frame-Options: DENY');

		if ($this->Session->check('OAuthServer.params')) 
		{
			$OAuthServerParams = $this->Session->read('OAuthServer.params');
			$this->Session->delete('OAuthServer.params');
		} 
		else 
		{
			try 
			{
				$OAuthServerParams = $this->OAuthServer->getAuthorizeParams();
			} 
			catch (Exception $e)
			{
				$e->sendHttpResponse();
			}
		}
		$this->set(compact('OAuthServerParams'));
	}
*/

/**
 * Example Login Action
 *
 * Users must authorize themselves before granting the app authorization
 * Allows login state to be maintained after authorization
 *
 */
	public function login () {
		$OAuthServerParams = $this->OAuthServer->getAuthorizeParams();
		if ($this->request->is('post')) {
			$this->validateRequest();

			//Attempted login
			if ($this->Auth->login()) {
				//Write this to session so we can log them out after authenticating
				// allow them to login
				//$this->Session->write('OAuthServer.logout', true);

				//Write the auth params to the session for later
				$this->Session->write('OAuthServer.params', $OAuthServerParams);

				//Off we go
				$this->redirect(array('action' => 'authorize'));
			} else {
				$this->Session->setFlash(__('Username or password is incorrect'), 'default', array(), 'auth');
			}
		}
		$this->set(compact('OAuthServerParams'));
	}

/**
 * Example Token Endpoint - this is where clients can retrieve an access token
 *
 * Grant types and parameters:
 * 1) authorization_code - exchange code for token
 *	- code
 *	- client_id
 *	- client_secret
 *
 * 2) refresh_token - exchange refresh_token for token
 *	- refresh_token
 *	- client_id
 *	- client_secret
 *
 * 3) password - exchange raw details for token
 *	- username
 *	- password
 *	- client_id
 *	- client_secret
 *
 */
	public function token() {
		$this->autoRender = false;
		try {
			$this->OAuthServer->grantAccessToken();
		} catch (OAuth2ServerException $e) {
			$e->sendHttpResponse();
		}
	}

/**
 * Quick and dirty example implementation for protecetd resource
 *
 * User accesible via $this->OAuthServer->user();
 * Single fields avaliable via $this->OAuthServer->user("id");
 *
 */
	public function userinfo() {
		$this->layout = null;
		$userData = $this->OAuthServer->user();
		
		$user = $userData['User'];
		
		if(isset($user['id']))
			unset($user['id']);
		if(isset($user['password']))
			unset($user['password']);
		if(isset($user['created']))
			unset($user['created']);
		if(isset($user['modified']))
			unset($user['modified']);
		if(isset($user['lastlogin']))
			unset($user['lastlogin']);
		if(isset($user['photo']))
			unset($user['photo']);
		if(isset($user['photo']))
			unset($user['photo']);
		
		// get the xref record
		$xref = $this->Client->ClientsUser->find('first', array(
			'conditions' => array(
				'ClientsUser.user_id' => $userData['User']['id'],
				'ClientsUser.client_id' => $userData['Client']['client_id'],
			),
		));
		
		// of the org groups are available for this server, send them as well to update the portal
		if(isset($this->Client->ClientsUser->OrgGroup))
		{
			$user['orgGroups'] = $this->Client->ClientsUser->OrgGroup->find('all');
		}
		
		// remove some of the fields
		unset($xref['ClientsUser']['id'], $xref['ClientsUser']['user_id'], $xref['ClientsUser']['client_id']);
		
		$user = array_merge($user, $xref['ClientsUser']);
			
		$this->set(compact('user'));
	}

/**
 * Blackhold callback
 *
 * OAuthServer requests will fail postValidation, so rather than disabling it completely
 * if the request does fail this check we store it in $this->blackHoled and then
 * when handling our forms we can use $this->validateRequest() to check if there
 * were any errors and handle them with an exception.
 * Requests that fail for reasons other than postValidation are handled here immediately
 * using the best guess for if it was a form or OAuthServer
 *
 * @param string $type
 */
	public function blackHole($type) {
		$this->blackHoled = $type;

		if ($type != 'auth') {
			if (isset($this->request->data['_Token'])) {
				//Probably our form
				$this->validateRequest();
			} else {
				//Probably OAuthServer
				$e = new OAuth2ServerException(OAuth2::HTTP_BAD_REQUEST, OAuth2::ERROR_INVALID_REQUEST, 'Request Invalid.');
				$e->sendHttpResponse();
			}
		}
	}

/**
 * Check for any Security blackhole errors
 *
 * @throws BadRequestException
 */
	private function validateRequest() {
		if ($this->blackHoled) {
			//Has been blackholed before - naughty
			throw new BadRequestException(__d('OAuthServer', 'The request has been black-holed'));
		}
	}

}
