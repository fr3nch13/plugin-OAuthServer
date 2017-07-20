<?php

App::uses('OAuthServerAppModel', 'OAuthServer.Model');

/**
 * AccessToken Model
 *
 * @property Client $Client
 * @property User $User
 */
class AccessToken extends OAuthServerAppModel {

/**
 * Primary key field
 *
 * @var string
 */
	public $primaryKey = 'OAuthServer_token';

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'OAuthServer_token';

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'OAuthServer_token' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
			'isUnique' => array(
				'rule' => array('isUnique'),
			)
		),
		'client_id' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
		),
		'user_id' => array(
			'notBlank' => array(
				'rule' => array('notBlank'),
			),
		),
		'expires' => array(
			'numeric' => array(
				'rule' => array('numeric'),
			),
		),
	);

	public $actsAs = array(
		'OAuthServer.HashedField' => array(
			'fields' => 'OAuthServer_token',
		),
	);

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Client' => array(
			'className' => 'OAuthServer.Client',
			'foreignKey' => 'client_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

}
