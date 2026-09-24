<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_gateway_sync');
$context = FonikAddonsPage::context();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$result = $context['api']->syncGateways((int) $context['company']['id']);
	FonikAddonsPage::completeSave($result, 'gateway_sync.php');
}

$document['title'] = 'همگام‌سازی درگاه‌ها';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'همگام‌سازی درگاه‌ها',
	'درگاه‌های فعال این حساب را با سامانه Fonik همگام کنید.',
	'gateway_sync_documentation_url'
);
FonikAddonsPage::panelStart();

if ($context['error'] !== '') {
	FonikAddonsPage::notice($context['error'], 'error');
}
elseif (!$context['company_exists']) {
	FonikAddonsPage::companyMissing();
}
else {
	FonikAddonsPage::formStart();
	FonikAddonsPage::notice('درگاه‌های فعال حساب جاری دریافت و با سامانه Fonik همگام می‌شوند.', 'info');
	FonikAddonsPage::submit('شروع همگام‌سازی');
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
