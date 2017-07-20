<?php
App::uses('AppController', 'Controller');

class AuthorizeHistoriesController extends AppController 
{
	
	public function admin_index() 
	{
		$this->Prg->commonProcess();
		
		$conditions = array();
		
		$this->AuthorizeHistory->recursive = 0;
		$this->paginate['order'] = array('AuthorizeHistory.timestamp' => 'desc');
		$this->paginate['conditions'] = $this->AuthorizeHistory->conditions($conditions, $this->passedArgs); 
		$this->set('loginHistories', $this->paginate());
	}
	
	public function admin_user($user_id = false) 
	{
		$this->AuthorizeHistory->User->id = $user_id;
		if (!$user = $this->AuthorizeHistory->User->read(null, $user_id))
		{
			throw new NotFoundException(__('Invalid %s', __('User')));
		};
		$this->set('user', $user);
		
		$this->Prg->commonProcess();
		
		$conditions = array(
			'AuthorizeHistory.user_id' => $user_id
		);
		
		$this->AuthorizeHistory->recursive = 0;
		$this->paginate['order'] = array('AuthorizeHistory.timestamp' => 'desc');
		$this->paginate['conditions'] = $this->AuthorizeHistory->conditions($conditions, $this->passedArgs); 
		$this->set('loginHistories', $this->paginate());
	}
	
	public function admin_client($client_id = false) 
	{
		$this->AuthorizeHistory->Client->id = $client_id;
		if (!$client = $this->AuthorizeHistory->Client->read(null, $client_id))
		{
			throw new NotFoundException(__('Invalid %s', __('Client')));
		};
		$this->set('client', $client);
		
		$this->Prg->commonProcess();
		
		$conditions = array(
			'AuthorizeHistory.client_id' => $client_id
		);
		
		$this->AuthorizeHistory->recursive = 0;
		$this->paginate['order'] = array('AuthorizeHistory.timestamp' => 'desc');
		$this->paginate['conditions'] = $this->AuthorizeHistory->conditions($conditions, $this->passedArgs); 
		$this->set('loginHistories', $this->paginate());
	}
	
	public function admin_delete($id = null) 
	{
		if (!$this->request->is('post')) 
		{
			throw new MethodNotAllowedException();
		}
		$this->AuthorizeHistory->id = $id;
		if (!$this->AuthorizeHistory->exists()) 
		{
			throw new NotFoundException(__('Invalid login history'));
		}
		if ($this->AuthorizeHistory->delete()) 
		{
			$this->Session->setFlash(__('Authorize history deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Authorize history was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
