<?php

/**
 * Internal defaults merged by the package (not publishable).
 * Authority URL is not configurable — use Package::authorityUrl() only.
 */
return [
    'global_enforce' => \Hearth\LicenseClient\Package::globalEnforce(),
    'private_key_path' => storage_path('keys/private.pem'),
];
