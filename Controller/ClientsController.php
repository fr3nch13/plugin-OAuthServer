<?php

App::uses('AppController', 'Controller');

class ClientsController extends AppController 
{
	public $uses = array('OAuthServer.Client');

	public function index()
	{
		$this->Prg->commonProcess();
		
		$conditions = array();
		
		$this->paginate['order'] = array('Client.client_name' => 'asc');
		$this->paginate['conditions'] = $this->Client->conditions($conditions, $this->passedArgs); 
		$this->set('clients', $this->paginate());
	}

	public function view($id = null)
	{
		if (!$client = $this->Client->read(null, $id))
		{
			throw new NotFoundException(__('Invalid %s', __('Client')));
		};
		
		$this->set('client', $client);
	}

	public function admin_index()
	{
		$this->paginate['order'] = array('Client.name' => 'asc');
		$this->Prg->commonProcess();
		$this->Client->recursive = 0;
		$this->paginate['conditions'] = $this->Client->parseCriteria($this->passedArgs);
		$this->set('clients', $this->paginate());
	}

	public function admin_view($id = null)
	{
		if (!$client = $this->Client->read(null, $id))
		{
			throw new NotFoundException(__('Invalid %s', __('Client')));
		};
		
		$this->set('client', $client);
	}

	public function admin_add()
	{
		if ($this->request->is('post'))
		{
			$this->Client->create();
			if ($this->Client->add($this->request->data))
			{
				$this->Session->setFlash(__('The %s has been saved', __('Client')));
				return $this->redirect(array('action' => 'index'));
			}
			else
			{
				$this->Session->setFlash(__('The %s could not be saved. Please, try again.', __('Client')));
			}
		}
	}

	public function admin_edit($client_id = null)
	{
		$this->Client->id = $client_id;
		if (!$client = $this->Client->read(null, $this->Client->id))
		{
			throw new NotFoundException(__('Invalid %s', __('Client')));
		}
		if ($this->request->is('post') || $this->request->is('put'))
		{
			if ($this->Client->save($this->request->data))
			{
				$this->Session->setFlash(__('The %s has been saved', __('Client')));
				return $this->redirect(array('action' => 'index'));
			}
			else
			{
				$this->Session->setFlash(__('The %s could not be saved. Please, try again.', __('Client')));
			}
		}
		else
		{
			$this->request->data = $client;
		}
	}

	public function admin_toggle($field = null, $id = null)
	{
		if ($this->Client->toggleRecord($id, $field))
		{
			$this->Session->setFlash(__('The %s has been updated.', __('Client')));
		}
		else
		{
			$this->Session->setFlash($this->Client->modelError);
		}
		
		return $this->redirect($this->referer());
	}

	public function admin_delete($id = null)
	{
		$this->Client->id = $id;
		if (!$this->Client->exists())
		{
			throw new NotFoundException(__('Invalid %s', __('Client')));
		}
		if ($this->Client->delete())
		{
			$this->Session->setFlash(__('The %s has been deleted.', __('Client')));
			return $this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('The %s was NOT deleted.', __('Client')));
		return $this->redirect(array('action' => 'index'));
	}
}