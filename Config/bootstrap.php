<?php

Cache::config('OAuthServer', [
	'engine' => 'File',
	'duration'=> 3600,
	'prefix' => 'OAuthServer_',
	'mask' => 0666,
]);