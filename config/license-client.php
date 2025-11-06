<?php

return [
	// Authority base URL used for remote manifest / jwks checks
	'authority_url' => env('LICENSE_AUTHORITY_URL', 'https://hearth.master-data.ro'),

	// Whether to enforce license checks automatically (cannot be disabled by client easily)
	'enforce' => true,

	// Interval (seconds) to allow remote manifest JWKS fetch timeouts before falling back
	'remote_timeout' => 5,
];

