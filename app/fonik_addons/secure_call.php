<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_secure_call_manage');
$context = FonikAddonsPage::context();
$service = ['exists' => false, 'data' => [], 'error' => ''];

if ($context['company_exists'] && $context['error'] === '') {
	$service = FonikAddonsPage::loadService($context['api'], (int) $context['company']['id'], 'private-call-service');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$payload = FonikAddonsValidator::secureCall($_POST);
	$result = FonikAddonsPage::saveService($context['api'], (int) $context['company']['id'], 'private-call-service', $payload);
	FonikAddonsPage::completeSave($result, 'secure_call.php');
}

$data = $service['data'];
$document['title'] = 'تماس امن';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'تماس امن',
	'',
	'secure_call_documentation_url',
	filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
	(string) ($context['company']['api_key'] ?? '')
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
	FonikAddonsPage::fieldsStart();
	FonikAddonsPage::field('وضعیت سرویس', FonikAddonsPage::checkbox('enabled', $data['enabled'] ?? false));
	FonikAddonsPage::field('ضبط تماس', FonikAddonsPage::checkbox('has_record', $data['has_record'] ?? false));
	FonikAddonsPage::fieldsEnd();
	FonikAddonsPage::submit();
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
