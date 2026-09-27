<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

if (!FonikAddonsPage::featureEnabled()) {
	FonikAddonsPage::renderUnavailable();
}

$routes = [
	['permission' => 'fonik_company_settings', 'path' => 'company.php', 'superadmin' => true],
	['permission' => 'fonik_click_to_call_manage', 'path' => 'click_to_call.php', 'superadmin' => false],
	['permission' => 'fonik_popup_manage', 'path' => 'popup.php', 'superadmin' => false],
	['permission' => ['fonik_callback_incoming', 'fonik_callback_outgoing', 'fonik_callback_missed'], 'path' => 'callback.php', 'superadmin' => false],
	['permission' => 'fonik_gateway_sync', 'path' => 'gateway_sync.php', 'superadmin' => false],
	['permission' => 'fonik_recording_notify_manage', 'path' => 'record_notify.php', 'superadmin' => false],
	['permission' => 'fonik_secure_call_manage', 'path' => 'secure_call.php', 'superadmin' => false],
];

foreach ($routes as $route) {
	$permissions = is_array($route['permission']) ? $route['permission'] : [$route['permission']];
	$allowed = if_group('superadmin');
	foreach ($permissions as $permission) {
		$allowed = $allowed || permission_exists($permission);
	}
	if ($allowed && (!$route['superadmin'] || if_group('superadmin'))) {
		FonikAddonsPage::redirect($route['path']);
	}
}

http_response_code(403);
echo 'access denied';
