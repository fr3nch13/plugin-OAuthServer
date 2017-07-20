<?php

class CronShell extends AppShell
{
	// the models to use
	public $uses = [
		'User', 'OAuthServer.Client'
	];
	
	public function startup() 
	{
		$this->clear();
		$this->out('Cron Shell');
		$this->hr();
		return parent::startup();
	}
	
	public function getOptionParser()
	{
		$parser = parent::getOptionParser();
		
		$parser->description(__d('cake_console', 'The Cron Shell runs all needed cron jobs'));
		
		$parser->addSubcommand('update_clients', array(
			'help' => __d('cake_console', 'Calls each active client and sends them updated information. Used for things like Users, etc.'),
		));
		
		return $parser;
	}
	
	public function update_clients()
	{
		$this->Client->cron_updateClients();
	}
	
	
/** Below are just left here for reference, then can be deleted if needed/wanted. **/
	
	public function change_log()
	{
	/*
	 * Sends an email when a change is made
	 * Send an email to whomever is involved with the change
	 */
		$minutes = '5';
		if(isset($this->args[0]))
		{
			$minutes = $this->args[0];
		}
		
		/////////// get the list of changes
		$logs = $this->Dblog->latest($minutes);
		if(!$logs)
		{
			$this->out(__('No logged changes'));
			return false;
		}
		
		$this->out(__('Found %s logged changes.', count($logs)), 1, Shell::QUIET);
		
		// build a cache of users
		$user_cache = array();
		$user_ids = array();
		
		
		/////////// add the user_info to the user_cache
		foreach($logs as $log)
		{
			// only email changes when a media or custody chain is affected
			if(!in_array($log['Dblog']['model'], array('Rule'))) continue;
			if(isset($log['Rule']))
			{
				$user_ids[$log['Rule']['added_user_id']] = $log['Rule']['added_user_id'];
				$user_ids[$log['Rule']['modified_user_id']] = $log['Rule']['modified_user_id'];
				$user_ids[$log['Rule']['reviewed_user_id']] = $log['Rule']['reviewed_user_id'];
				
			}
		}
		
		$user_cache = $this->User->changeLogList($user_ids);
		
		/////////// sort the logs into 1 of 2 different types: created, updated
		$logs_created = array();
		$logs_updated = array();
		foreach($logs as $log)
		{	
			// only email changes when a media or custody chain is affected
			if(!in_array($log['Dblog']['model'], array('Rule'))) continue;			
			
			// create a log key
			$log_key = $log['Dblog']['model']. '-'. $log['Dblog']['model_id']. '-'. $log['Dblog']['id'];
			
			// attach the RuleAddedUser
			if(!isset($log['RuleAddedUser']))
			{
				$log['RuleAddedUser'] = array();
				
				if(isset($log['Rule']['added_user_id']) and $log['Rule']['added_user_id'] > 0)
				{
					if(!isset($user_cache[$log['Rule']['added_user_id']]))
					{
						$user_cache[$log['Rule']['added_user_id']] = array();
						$user = $this->User->find('first', array(
							'recursive' => 0,
							'conditions' => array(
								'User.id' => $log['Rule']['added_user_id'],
							),
						));
						if($user) $user_cache[$log['Rule']['added_user_id']] = $user;
					}
					$log['RuleAddedUser'] = $user_cache[$log['Rule']['added_user_id']]['User'];
				}
			}
			
			// attach the RuleReviewedUser
			if(!isset($log['RuleReviewedUser']))
			{
				$log['RuleReviewedUser'] = array();
				
				if(isset($log['Rule']['reviewed_user_id']) and $log['Rule']['reviewed_user_id'] > 0)
				{
					if(!isset($user_cache[$log['Rule']['reviewed_user_id']]))
					{
						$user_cache[$log['Rule']['reviewed_user_id']] = array();
						$user = $this->User->find('first', array(
							'recursive' => 0,
							'conditions' => array(
								'User.id' => $log['Rule']['reviewed_user_id'],
							),
						));
						if($user) $user_cache[$log['Rule']['reviewed_user_id']] = $user;
					}
					$log['RuleReviewedUser'] = $user_cache[$log['Rule']['reviewed_user_id']]['User'];
				}
			}
			
			// attach the RuleModifiedUser
			if(!isset($log['RuleModifiedUser']))
			{
				$log['RuleModifiedUser'] = array();
				
				if(isset($log['Rule']['modified_user_id']) and $log['Rule']['modified_user_id'] > 0)
				{
					if(!isset($user_cache[$log['Rule']['modified_user_id']]))
					{
						$user_cache[$log['Rule']['modified_user_id']] = array();
						$user = $this->User->find('first', array(
							'recursive' => 0,
							'conditions' => array(
								'User.id' => $log['Rule']['modified_user_id'],
							),
						));
						if($user) $user_cache[$log['Rule']['modified_user_id']] = $user;
					}
					$log['RuleModifiedUser'] = $user_cache[$log['Rule']['modified_user_id']]['User'];
				}
			}
			
			// track all user_ids for this entry
			$log['user_ids'] = array();
			foreach($log as $modelName => $modelValues)
			{
				// if email is set, and an id is set, it's a user
				if(isset($modelValues['email']) and isset($modelValues['id']))
				{
					$log['user_ids'][$modelValues['id']] = $modelValues['id'];
				}
			}
			
			// map the fields
			$log = $this->Dblog->mapFields($log);
			
			// new entries
			if($log['Dblog']['new'] == 1)
			{
				$logs_created[$log_key] = $log;
				continue;
			}
			
			// closed entries
			$changes = unserialize($log['Dblog']['changes']);
			
			// updated entries
			$logs_updated[$log_key] = $log;
		}
		
		/////////// seperate the users into groups based on their settings
		// list of users that want emails all of the time when created
		$users_created_all = array();
		// list of users that want emails only when mentioned
		$users_created_mentioned = array();
		// list of users that want emails all of the time when updated
		$users_updated_all = array();
		// list of users that want emails only when mentioned
		$users_updated_mentioned = array();
		
		foreach($user_cache as $user_id => $user)
		{
			if($user['UserSetting']['email_new'] == 2) $users_created_all[$user_id] = $user['User'];
			if($user['UserSetting']['email_new'] == 1) $users_created_mentioned[$user_id] = $user['User'];
			if($user['UserSetting']['email_updated'] == 2) $users_updated_all[$user_id] = $user['User'];
			if($user['UserSetting']['email_updated'] == 1) $users_updated_mentioned[$user_id] = $user['User'];
		}
		
		// build the emails
		$emails = array();
		
		// map the created log to the users that want an email when one is created
		foreach($logs_created as $log_id => $log)
		{
			$rule_id = 0;
			if(isset($log['Rule']['id']))
			{
				$rule_id = $log['Rule']['id'];
			}
			else
			{
				$changes = unserialize($log['Dblog']['changes']);
				if(isset($changes['rule_id'])) $rule_id = $changes['rule_id'];
			}
		
			// build one email for each user that want all
			foreach($users_created_all as $user_id => $user_created_all)
			{
				// make sure there is an entry into the email array
				$email_address = $user_created_all['email'];
				if(!isset($emails[$email_address]))
				{
					$emails[$email_address] = array(
						'email' => $email_address,
						'name' => $user_created_all['name'],
						'log_count_updated' => 0,
						'log_count_new' => 0,
						'log_count_deleted' => 0,
						'logs' => array(),
					);
				}
				
				($log['Dblog']['deleted']?$emails[$email_address]['log_count_deleted']++:$emails[$email_address]['log_count_new']++);
				
				// add this log to the email
				$emails[$email_address]['logs'][$log_id] = array(
					'model' => $log['Dblog']['model'],
					'model_id' => $log['Dblog']['model_id'],
					'user_id' => $log['Dblog']['user_id'],
					'user' => (isset($log['DblogUser']['email'])?$log['DblogUser']['email']:''),
					'rule_id' => $rule_id,
					'status' => ($log['Dblog']['deleted']?4:1),
					'timestamp' => $log['Dblog']['created'],
					'message' => ($log['Dblog']['mapped_readable']?$log['Dblog']['mapped_readable']:str_replace(';;;', "\n", $log['Dblog']['readable'])),
				);
			}
			
			// build one email for each user that want all
			foreach($users_created_mentioned as $user_id => $user_created_mentioned)
			{
				if(!in_array($user_id, $log['user_ids'])) continue;
				
				// make sure there is an entry into the email array
				$email_address = $user_created_mentioned['email'];
				if(!isset($emails[$email_address]))
				{
					$emails[$email_address] = array(
						'email' => $email_address,
						'name' => $user_created_mentioned['name'],
						'log_count_updated' => 0,
						'log_count_new' => 0,
						'log_count_deleted' => 0,
						'logs' => array(),
					);
				}
				
				($log['Dblog']['deleted']?$emails[$email_address]['log_count_deleted']++:$emails[$email_address]['log_count_new']++);
				
				// add this log to the email
				$emails[$email_address]['logs'][$log_id] = array(
					'model' => $log['Dblog']['model'],
					'model_id' => $log['Dblog']['model_id'],
					'user_id' => $log['Dblog']['user_id'],
					'user' => (isset($log['DblogUser']['email'])?$log['DblogUser']['email']:''),
					'rule_id' => $rule_id,
					'status' => ($log['Dblog']['deleted']?4:1),
					'timestamp' => $log['Dblog']['created'],
					'message' => ($log['Dblog']['mapped_readable']?$log['Dblog']['mapped_readable']:str_replace(';;;', "\n", $log['Dblog']['readable'])),
				);
			}
		}
		
		// map the updated log to the users that want an email when one is updated
		foreach($logs_updated as $log_id => $log)
		{
			$rule_id = 0;
			if(isset($log['Rule']['id']))
			{
				$rule_id = $log['Rule']['id'];
			}
			else
			{
				$changes = unserialize($log['Dblog']['changes']);
				if(isset($changes['rule_id'])) $rule_id = $changes['rule_id'];
			}
			
			// build one email for each user that want all
			foreach($users_updated_all as $user_id => $user_updated_all)
			{
				// make sure there is an entry into the email array
				$email_address = $user_updated_all['email'];
				if(!isset($emails[$email_address]))
				{
					$emails[$email_address] = array(
						'email' => $email_address,
						'name' => $user_updated_all['name'],
						'log_count_updated' => 0,
						'log_count_new' => 0,
						'log_count_deleted' => 0,
						'logs' => array(),
					);
				}
				
				($log['Dblog']['deleted']?$emails[$email_address]['log_count_deleted']++:$emails[$email_address]['log_count_updated']++);
				
				// add this log to the email
				$emails[$email_address]['logs'][$log_id] = array(
					'model' => $log['Dblog']['model'],
					'model_id' => $log['Dblog']['model_id'],
					'user_id' => $log['Dblog']['user_id'],
					'user' => (isset($log['DblogUser']['email'])?$log['DblogUser']['email']:''),
					'rule_id' => $rule_id,
					'status' => ($log['Dblog']['deleted']?4:2),
					'timestamp' => $log['Dblog']['created'],
					'message' => ($log['Dblog']['mapped_readable']?$log['Dblog']['mapped_readable']:str_replace(';;;', "\n", $log['Dblog']['readable'])),
				);
			}
			
			// build one email for each user that want all
			foreach($users_updated_mentioned as $user_id => $user_updated_mentioned)
			{
				if(!in_array($user_id, $log['user_ids'])) continue;
				
				// make sure there is an entry into the email array
				$email_address = $user_updated_mentioned['email'];
				if(!isset($emails[$email_address]))
				{
					$emails[$email_address] = array(
						'email' => $email_address,
						'name' => $user_updated_mentioned['name'],
						'log_count_updated' => 0,
						'log_count_new' => 0,
						'log_count_deleted' => 0,
						'logs' => array(),
					);
				}
				
				($log['Dblog']['deleted']?$emails[$email_address]['log_count_deleted']++:$emails[$email_address]['log_count_updated']++);
				
				// add this log to the email
				$emails[$email_address]['logs'][$log_id] = array(
					'model' => $log['Dblog']['model'],
					'model_id' => $log['Dblog']['model_id'],
					'user_id' => $log['Dblog']['user_id'],
					'user' => (isset($log['DblogUser']['email'])?$log['DblogUser']['email']:''),
					'rule_id' => $rule_id,
					'status' => ($log['Dblog']['deleted']?4:2),
					'timestamp' => $log['Dblog']['created'],
					'message' => ($log['Dblog']['mapped_readable']?$log['Dblog']['mapped_readable']:str_replace(';;;', "\n", $log['Dblog']['readable'])),
				);
			}
		}
		
		// this keeps the logs in the proper order by object, then log order (uses key)
		foreach($emails as $email_address => $email_info)
		{
			if(isset($emails[$email_address]['logs']))
			{
				ksort($emails[$email_address]['logs']);
			}
		}
		
		// load ability to create an html link
		App::uses('View', 'View');
		$View = new View();
		App::uses('HtmlHelper', 'View/Helper');
		$HtmlHelper = new HtmlHelper($View);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		
		$signature = array();
		
		$message_status_template = "%s - %s";
		$log_status_map = array(
			1 => __('Added'),
			2 => __('Updated'),
			4 => __('Deleted'),
		);
		
		foreach($emails as $email_address => $email_info)
		{
			$Email->set('to', array($email_info['email'] => $email_info['name']));
			$subject = __('Changes made. New: %s, Updated: %s, Deleted: %s',
				$email_info['log_count_new'],
				$email_info['log_count_updated'],
				$email_info['log_count_deleted']
			);
			$Email->set('subject', $subject); 
		
			$body = array();
			
			$message_items = array();
			foreach($email_info['logs'] as $log)
			{
				$link = Configure::read('Site.base_url'). $HtmlHelper->url(array('controller' => 'rule', 'action' => 'view', $log['rule_id']));
				$message = array(
					__($message_status_template, $log['model'], $log_status_map[$log['status']]),
				);
				$message[] = __('Timestamp: %s', $log['timestamp']);
				$message[] = __('Log Generated by: %s', $log['user']);
				$message[] = __('Details: %s', $log['message']);
				$message[] = __('Link: %s', $link);
				$message_items[] = implode("\n", $message);
			}
			
			$message_items = implode("\n------------------------------\n", $message_items);
			$body[] = $message_items;
			
			// signature
			$body[] = ' ';
			$body[] = '------------------------------';
			$body[] = __('To change your email settings for this notification, please visit the below url, and select the "Email Settings" tab.');
			$body[] = Configure::read('Site.base_url'). $HtmlHelper->url(array('controller' => 'users', 'action' => 'edit'));
			
			$body = implode("\n", $body);
			
			$Email->set('body', $body);
			$Email->execute();
			$this->out(__('Email sent to: %s - Subject: %s', $email_info['email'], $subject), 1, Shell::QUIET);
		}
	}
	
	public function review_state_emails()
	{
	/*
	 * Sends a digest email of Rules that are assigned to a Review State.
	 * Review states are chosen based on their email settings in the admin.
	 */
		$hour = date('H');
		$day = strtolower(date('D'));
		
		$this->out(__('Finding %s that needs to have notifications sent. Day: %s - Hour: %s', __('Rules'), $day , $hour), 1, Shell::QUIET);
		
		/////////// get the list of changes
		$review_states = $this->ReviewState->find('all', array(
			'conditions' => array(
				'ReviewState.sendemail' => true,
				'ReviewState.'.$day => true,
				'ReviewState.notify_time' => $hour,
			),
		));
		if(!$review_states)
		{
			$this->out(__('No %s marked for notification at %s.', __('Review States'), date('g a')), 1, Shell::QUIET);
			return false;
		}
		
		$this->out(__('Found %s %s%s to send at %s.', count($review_states), __('Review State'), (count($review_states)>1?'s':''), date('g a')), 1, Shell::QUIET);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		$Email->set('template', 'review_state_emails');
		
		// Each one gets an email to be sent out
		foreach($review_states as $review_state)
		{
			// if no email is sent, make a note, than move on
			if(!$review_state['ReviewState']['notify_email'])
			{
				$this->out(__('The %s "%s" doesn\'t have an email address associated.', __('Review State'), $review_state['ReviewState']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$rules = $this->Rule->find('all', array(
				'recursive' => 0,
				'conditions' => array(
					'Rule.review_state_id' => $review_state['ReviewState']['id'],
				),
				'order' => array('Rule.created' => 'asc'),
			));
			
			if(!$rules)
			{
				$this->out(__('No %s was found with the %s of "%s".', __('Rule'), __('Review State'), $review_state['ReviewState']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$this->out(__('Found %s %s with the %s "%s".', count($rules), __('Rule'), __('Review State'), $review_state['ReviewState']['name']), 1, Shell::QUIET);
			
			// set the variables so we can use view templates
			$viewVars = array(
				'instructions' => trim($review_state['ReviewState']['instructions']),
				'review_state' => $review_state,
				'rules' => $rules,
			);
			
			//set the email parts
			$Email->set('to', $review_state['ReviewState']['notify_email']);
			$Email->set('subject', __('%s Status: %s - %s Count: %s', __('Review State'), $review_state['ReviewState']['name'], __('Rule'), count($rules)));
			$Email->set('viewVars', $viewVars);
			
			
			// send the email
			if(!$results = $Email->executeFull())
			{
				$this->out(__('Error sending notification email for %s "%s".', __('Review State'), $review_state['ReviewState']['name']), 1, Shell::QUIET);
			}
			
			$this->out(__('Sent notification email for %s "%s".', __('Review State'), $review_state['ReviewState']['name']), 1, Shell::QUIET);
		}
	}
	
	public function fisma_type_emails()
	{
	/*
	 * Sends a digest email of FISMA Inventories that are assigned to a FISMA Type.
	 * Review states are chosen based on their email settings in the admin.
	 */
		$hour = date('H');
		$day = strtolower(date('D'));
		
		$this->out(__('Finding %s that needs to have notifications sent. Day: %s - Hour: %s', __('FISMA Types'), $day , $hour), 1, Shell::QUIET);
		
		/////////// get the list of changes
		$fisma_types = $this->FismaType->find('all', array(
			'conditions' => array(
				'FismaType.sendemail' => true,
				'FismaType.'.$day => true,
				'FismaType.notify_time' => $hour,
			),
		));
		if(!$fisma_types)
		{
			$this->out(__('No %s marked for notification at %s.', __('FISMA Types'), date('g a')), 1, Shell::QUIET);
			return false;
		}
		
		$this->out(__('Found %s %s%s to send at %s.', count($fisma_types), __('FISMA Type'), (count($fisma_types)>1?'s':''), date('g a')), 1, Shell::QUIET);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		$Email->set('template', 'fisma_type_emails');
		
		// Each one gets an email to be sent out
		foreach($fisma_types as $fisma_type)
		{
			// if no email is sent, make a note, than move on
			if(!$fisma_type['FismaType']['notify_email'])
			{
				$this->out(__('The %s "%s" doesn\'t have an email address associated.', __('FISMA Type'), $fisma_type['FismaType']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$fisma_inventories = $this->FismaInventory->find('all', array(
				'recursive' => 0,
				'conditions' => array(
					'FismaInventory.fisma_type_id' => $fisma_type['FismaType']['id'],
				),
				'order' => array('FismaInventory.created' => 'asc'),
			));
			
			if(!$fisma_inventories)
			{
				$this->out(__('No %s was found with the %s of "%s".', __('FISMA Inventories'), __('FISMA Type'), $fisma_type['FismaType']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$this->out(__('Found %s %s with the %s "%s".', count($fisma_inventories), __('FISMA Inventories'), __('FISMA Type'), $fisma_type['FismaType']['name']), 1, Shell::QUIET);
			
			// set the variables so we can use view templates
			$viewVars = array(
				'instructions' => trim($fisma_type['FismaType']['instructions']),
				'fisma_type' => $fisma_type,
				'fisma_inventories' => $fisma_inventories,
			);
			
			//set the email parts
			$Email->set('to', $fisma_type['FismaType']['notify_email']);
			$Email->set('subject', __('%s: %s - %s Count: %s', __('FISMA Type'), $fisma_type['FismaType']['name'], __('FISMA Inventory'), count($fisma_inventories)));
			$Email->set('viewVars', $viewVars);
			
			
			// send the email
			if(!$results = $Email->executeFull())
			{
				$this->out(__('Error sending notification email for %s "%s".', __('FISMA Type'), $fisma_type['FismaType']['name']), 1, Shell::QUIET);
			}
			
			$this->out(__('Sent notification email for %s "%s".', __('FISMA Type'), $fisma_type['FismaType']['name']), 1, Shell::QUIET);
		}
	}
	
	public function fisma_status_emails()
	{
	/*
	 * Sends a digest email of FISMA Inventories that are assigned to a FISMA Status.
	 * Review states are chosen based on their email settings in the admin.
	 */
		$hour = date('H');
		$day = strtolower(date('D'));
		
		$this->out(__('Finding %s that needs to have notifications sent. Day: %s - Hour: %s', __('FISMA Statuses'), $day , $hour), 1, Shell::QUIET);
		
		/////////// get the list of changes
		$fisma_statuses = $this->FismaStatus->find('all', array(
			'conditions' => array(
				'FismaStatus.sendemail' => true,
				'FismaStatus.'.$day => true,
				'FismaStatus.notify_time' => $hour,
			),
		));
		
		if(!$fisma_statuses)
		{
			$this->out(__('No %s marked for notification at %s.', __('FISMA Statuses'), date('g a')), 1, Shell::QUIET);
			return false;
		}
		
		$this->out(__('Found %s %s%s to send at %s.', count($fisma_statuses), __('FISMA Status'), (count($fisma_statuses)>1?'s':''), date('g a')), 1, Shell::QUIET);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		$Email->set('template', 'fisma_status_emails');
		
		// Each one gets an email to be sent out
		foreach($fisma_statuses as $fisma_status)
		{
			// if no email is sent, make a note, than move on
			if(!$fisma_status['FismaStatus']['notify_email'])
			{
				$this->out(__('The %s "%s" doesn\'t have an email address associated.', __('FISMA Status'), $fisma_status['FismaStatus']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$fisma_inventories = $this->FismaInventory->find('all', array(
				'recursive' => 0,
				'conditions' => array(
					'FismaInventory.fisma_status_id' => $fisma_status['FismaStatus']['id'],
				),
				'order' => array('FismaInventory.created' => 'asc'),
			));
			
			if(!$fisma_inventories)
			{
				$this->out(__('No %s was found with the %s of "%s".', __('FISMA Inventories'), __('FISMA Status'), $fisma_status['FismaStatus']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$this->out(__('Found %s %s with the %s "%s".', count($fisma_inventories), __('FISMA Inventories'), __('FISMA Status'), $fisma_status['FismaStatus']['name']), 1, Shell::QUIET);
			
			// set the variables so we can use view templates
			$viewVars = array(
				'instructions' => trim($fisma_status['FismaStatus']['instructions']),
				'fisma_status' => $fisma_status,
				'fisma_inventories' => $fisma_inventories,
			);
			
			//set the email parts
			$Email->set('to', $fisma_status['FismaStatus']['notify_email']);
			$Email->set('subject', __('%s: %s - %s Count: %s', __('FISMA Status'), $fisma_status['FismaStatus']['name'], __('FISMA Inventory'), count($fisma_inventories)));
			$Email->set('viewVars', $viewVars);
			
			
			// send the email
			if(!$results = $Email->executeFull())
			{
				$this->out(__('Error sending notification email for %s "%s".', __('FISMA Status'), $fisma_status['FismaStatus']['name']), 1, Shell::QUIET);
			}
			
			$this->out(__('Sent notification email for %s "%s".', __('FISMA Status'), $fisma_status['FismaStatus']['name']), 1, Shell::QUIET);
		}
	}
	
	public function fisma_source_emails()
	{
	/*
	 * Sends a digest email of FISMA Inventories that are assigned to a FISMA Source.
	 * Review states are chosen based on their email settings in the admin.
	 */
		$hour = date('H');
		$day = strtolower(date('D'));
		
		$this->out(__('Finding %s that needs to have notifications sent. Day: %s - Hour: %s', __('FISMA Sources'), $day , $hour), 1, Shell::QUIET);
		
		/////////// get the list of changes
		$fisma_sources = $this->FismaSource->find('all', array(
			'conditions' => array(
				'FismaSource.sendemail' => true,
				'FismaSource.'.$day => true,
				'FismaSource.notify_time' => $hour,
			),
		));
		if(!$fisma_sources)
		{
			$this->out(__('No %s marked for notification at %s.', __('FISMA Sources'), date('g a')), 1, Shell::QUIET);
			return false;
		}
		
		$this->out(__('Found %s %s%s to send at %s.', count($fisma_sources), __('FISMA Source'), (count($fisma_sources)>1?'s':''), date('g a')), 1, Shell::QUIET);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		$Email->set('template', 'fisma_source_emails');
		
		// Each one gets an email to be sent out
		foreach($fisma_sources as $fisma_source)
		{
			// if no email is sent, make a note, than move on
			if(!$fisma_source['FismaSource']['notify_email'])
			{
				$this->out(__('The %s "%s" doesn\'t have an email address associated.', __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$fisma_inventories = $this->FismaInventory->find('all', array(
				'recursive' => 0,
				'conditions' => array(
					'FismaInventory.fisma_source_id' => $fisma_source['FismaSource']['id'],
				),
				'order' => array('FismaInventory.created' => 'asc'),
			));
			
			if(!$fisma_inventories)
			{
				$this->out(__('No %s was found with the %s of "%s".', __('FISMA Inventories'), __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
				continue;
			}
			
			$this->out(__('Found %s %s with the %s "%s".', count($fisma_inventories), __('FISMA Inventories'), __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
			
			// set the variables so we can use view templates
			$viewVars = array(
				'instructions' => trim($fisma_source['FismaSource']['instructions']),
				'fisma_source' => $fisma_source,
				'fisma_inventories' => $fisma_inventories,
			);
			
			//set the email parts
			$Email->set('to', $fisma_source['FismaSource']['notify_email']);
			$Email->set('subject', __('%s: %s - %s Count: %s', __('FISMA Source'), $fisma_source['FismaSource']['name'], __('FISMA Inventory'), count($fisma_inventories)));
			$Email->set('viewVars', $viewVars);
			
			
			// send the email
			if(!$results = $Email->executeFull())
			{
				$this->out(__('Error sending notification email for %s "%s".', __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
			}
			
			$this->out(__('Sent notification email for %s "%s".', __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
		}
	}
	
	public function fisma_expire_emails()
	{
		
		$hour = date('H');
		$day = strtolower(date('w'));
		$config = Configure::read('FismaSystem');
		
		// see if we need to send an email
		$continue = false;
		if(isset($config['expire_notification_time']) and $config['expire_notification_time'] == $hour)
		{
			if(isset($config['expire_notification_days']) and in_array($day, $config['expire_notification_days']))
			{
				$continue = true;
			}
		}
		if(!$continue) return false;
		
		$this->out(__('Finding %s that expire in %s', __('FISMA Systems'), $config['expire_notification_timespan']), 1, Shell::QUIET);
		
		$expiration_start_threshold = date('Y-m-d 00:00:00', strtotime($config['expire_notification_timespan']));
		$expiration_end_threshold = date('Y-m-d 23:59:59', strtotime($config['expire_notification_timespan']));
		
		$conditions = array();
		$conditions['FismaSystem.ato_expiration >'] = $expiration_start_threshold = date('Y-m-d 00:00:01');
		$conditions['FismaSystem.ato_expiration <'] = $expiration_end_threshold;
		
		/////////// get the list of changes
		$fisma_systems = $this->FismaSystem->find('all', array(
			'recursive' => 0,
			'conditions' => $conditions,
			'order' => array('FismaSystem.ato_expiration' => 'asc'),
		));
		
		if(!$fisma_systems)
		{
			$this->out(__('No %s will expire in %s.', __('FISMA Systems'), $config['expire_notification_timespan']), 1, Shell::QUIET);
			return false;
		}
		
		$this->out(__('Found %s %s%s to send Expiration Notification.', count($fisma_systems), __('FISMA System'), (count($fisma_systems)>1?'s':'')), 1, Shell::QUIET);
		
		// load the email task
		$Email = $this->Tasks->load('Utilities.Email');
		$Email->set('template', 'fisma_expire_emails');
		
		// Each one gets an email to be sent out
		foreach($fisma_systems as $fisma_system)
		{
			////// find all of the poc contacts
			$emails = array();
			
/*
// No longer used, these were replaced by the contacts plugin
			// owner
			if($fisma_system['FismaSystem']['owner_email'])
			{
				$email_address = $fisma_system['FismaSystem']['owner_email'];
				$email_name = $fisma_system['FismaSystem']['owner_email'];
				if($fisma_system['FismaSystem']['owner_name'])
					$email_name = $fisma_system['FismaSystem']['owner_name'];
				$emails[$email_address] = $email_name;
			}
			// tech
			if($fisma_system['FismaSystem']['tech_email'])
			{
				$email_address = $fisma_system['FismaSystem']['tech_email'];
				$email_name = $fisma_system['FismaSystem']['tech_email'];
				if($fisma_system['FismaSystem']['tech_name'])
					$email_name = $fisma_system['FismaSystem']['tech_name'];
				$emails[$email_address] = $email_name;
			}
*/
			
			// other associated users
			foreach($fisma_system as $user_type => $possible_user)
			{
				if(!isset($possible_user['email'])) continue;
				if(!$possible_user['email']) continue;
				
				$email_address = $possible_user['email'];
				$email_name = $possible_user['email'];
				if(isset($possible_user['name']) and $possible_user['name'])
					$email_name = $possible_user['name'];
				$emails[$email_address] = $email_name;
			}
			
			/// no email addresses for POCs were found
			if(!count($emails)) continue;
			
			$subject = __('%s: The %s "%s" expires on %s', 
				__('ATO Expiration'), 
				__('FISMA System'), 
				$fisma_system['FismaSystem']['name'], 
				date('D, M jS Y', strtotime($fisma_system['FismaSystem']['ato_expiration']))
			);
			$viewVars = array(
				'instructions' => $config['expire_notification_text'],
				'fisma_system' => $fisma_system,
			);
			
			if(isset($config['from_email']) and $config['from_email'])
			{
				$Email->set('from', $config['from_email']);
				$Email->set('replyTo', $config['from_email']);
			}
			
			$Email->set('to', $emails);
			$Email->set('subject',  $subject);
			$Email->set('viewVars', $viewVars);
			
			// send the email
			if(!$results = $Email->executeFull())
			{
				$this->out(__('Error sending notification email for %s "%s".', __('FISMA Source'), $fisma_source['FismaSource']['name']), 1, Shell::QUIET);
			}
		}
		
		return true;
	}
	
	public function results_status_change()
	{	
		$this->ReportsStatus->resultsChangeEmail();
	}
	
	public function results_remediation_change()
	{	
		$this->ReportsRemediation->resultsChangeEmail();
	}
	
	public function copy_to_contacts()
	{
		$this->FismaSystem->cron_copyToContacts();
	}
	
	public function update_to_contacts()
	{
		$this->FismaSystem->cron_updateToContacts();
		$this->FismaSystem->cron_removefromContacts();
	}
}