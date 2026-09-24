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
	['permission' => 'fonik_callback_manage', 'path' => 'callback.php', 'superadmin' => false],
	['permission' => 'fonik_gateway_sync', 'path' => 'gateway_sync.php', 'superadmin' => false],
];

foreach ($routes as $route) {
	if (permission_exists($route['permission']) && (!$route['superadmin'] || if_group('superadmin'))) {
		FonikAddonsPage::redirect($route['path']);
	}
}

http_response_code(403);
echo 'access denied';
