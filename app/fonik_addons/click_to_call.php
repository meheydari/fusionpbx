<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_click_to_call_manage');
$context = FonikAddonsPage::context();
$service = ['exists' => false, 'data' => [], 'error' => ''];

if ($context['company_exists'] && $context['error'] === '') {
	$service = FonikAddonsPage::loadService($context['api'], (int) $context['company']['id'], 'click-to-call-service');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$payload = FonikAddonsValidator::clickToCall($_POST);
	$result = FonikAddonsPage::saveService($context['api'], (int) $context['company']['id'], 'click-to-call-service', $payload);
	FonikAddonsPage::completeSave($result, 'click_to_call.php');
}

$document['title'] = 'کلیک برای تماس';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'کلیک برای تماس',
	'تنظیمات سرویس کلیک برای تماس را مدیریت کنید.',
	'click_to_call_documentation_url',
	filter_var($service['data']['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
);
FonikAddonsPage::panelStart();

if ($context['error'] !== '') {
	FonikAddonsPage::notice($context['error'], 'error');
}
elseif (!$context['company_exists']) {
	FonikAddonsPage::companyMissing();
}
elseif ($service['error'] !== '') {
	FonikAddonsPage::notice($service['error'], 'error');
}
else {
	FonikAddonsPage::formStart();
	echo '<div class="fonik-form-grid">';
	FonikAddonsPage::field('وضعیت سرویس', FonikAddonsPage::checkbox('enabled', $service['data']['enabled'] ?? false));
	FonikAddonsPage::field('ضبط تماس', FonikAddonsPage::checkbox('has_record', $service['data']['has_record'] ?? false));
	echo '</div>';
	FonikAddonsPage::submit();
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
