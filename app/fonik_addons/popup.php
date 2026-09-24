<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_popup_manage');
$context = FonikAddonsPage::context();
$service = ['exists' => false, 'data' => [], 'error' => ''];

if ($context['company_exists'] && $context['error'] === '') {
	$service = FonikAddonsPage::loadService($context['api'], (int) $context['company']['id'], 'popup-service');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$validation = FonikAddonsValidator::popup($_POST);
	if ($validation['errors'] !== []) {
		message::add(implode(' ', $validation['errors']), 'negative');
	}
	else {
		$result = FonikAddonsPage::saveService($context['api'], (int) $context['company']['id'], 'popup-service', $validation['payload']);
		FonikAddonsPage::completeSave($result, 'popup.php');
	}
}

$data = $service['data'];
$fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
$popupType = strtoupper((string) ($data['popup_type'] ?? 'API'));
$direction = strtoupper((string) ($data['direction'] ?? 'BOTH'));
$method = strtoupper((string) ($data['api_method'] ?? 'POST'));

$document['title'] = 'پاپ‌آپ تماس';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'پاپ‌آپ تماس',
	'نحوه ارسال اطلاعات تماس به سامانه مقصد را تنظیم کنید.',
	'popup_documentation_url',
	filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
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
	FonikAddonsPage::field('وضعیت سرویس', FonikAddonsPage::checkbox('enabled', $data['enabled'] ?? false));
	FonikAddonsPage::field(
		'نوع ارتباط',
		'<select class="formfld" id="popup_type" name="popup_type"><option value="API"'.($popupType === 'API' ? ' selected="selected"' : '').'>API</option><option value="SOCKET"'.($popupType === 'SOCKET' ? ' selected="selected"' : '').'>WebSocket</option></select>'
	);
	FonikAddonsPage::field('آدرس API', '<input class="formfld" type="url" name="api_url" maxlength="1000" value="'.escape((string) ($data['api_url'] ?? '')).'">', '', false, 'popup-api');
	FonikAddonsPage::field('آدرس WebSocket', '<input class="formfld" type="text" name="ws_url" maxlength="1000" value="'.escape((string) ($data['ws_url'] ?? '')).'">', '', false, 'popup-socket');
	FonikAddonsPage::field('کلید احراز هویت', '<input class="formfld" type="text" name="api_key_popup" maxlength="500" required="required" value="'.escape((string) ($data['api_key_popup'] ?? '')).'">');
	FonikAddonsPage::field('نام فیلد کلید', '<input class="formfld" type="text" name="api_key_popup_label" maxlength="255" required="required" value="'.escape((string) ($fields['api_key_popup_label'] ?? '')).'">');
	FonikAddonsPage::field(
		'جهت تماس',
		'<select class="formfld" name="direction"><option value="INBOUND"'.($direction === 'INBOUND' ? ' selected="selected"' : '').'>ورودی</option><option value="OUTBOUND"'.($direction === 'OUTBOUND' ? ' selected="selected"' : '').'>خروجی</option><option value="BOTH"'.($direction === 'BOTH' ? ' selected="selected"' : '').'>هر دو</option></select>'
	);
	FonikAddonsPage::field(
		'روش درخواست',
		'<select class="formfld" name="api_method"><option value="POST"'.($method === 'POST' ? ' selected="selected"' : '').'>POST</option><option value="GET"'.($method === 'GET' ? ' selected="selected"' : '').'>GET</option></select>'
	);
	echo '<div class="fonik-fields-heading">اطلاعات ارسالی</div>';
	$fieldDefinitions = [
		'external_number' => ['شماره خارجی', 'external_number_label'],
		'internal_number' => ['شماره داخلی', 'internal_number_label'],
		'call_type' => ['نوع تماس', 'call_type_label'],
		'date' => ['تاریخ تماس', 'date_label'],
		'call_id' => ['شناسه تماس', 'call_id_label'],
	];
	foreach ($fieldDefinitions as $flag => [$label, $labelField]) {
		FonikAddonsPage::field('ارسال '.$label, FonikAddonsPage::checkbox($flag, $fields[$flag] ?? false));
		FonikAddonsPage::field('نام فیلد '.$label, '<input class="formfld" type="text" name="'.escape($labelField).'" maxlength="255" value="'.escape((string) ($fields[$labelField] ?? '')).'">');
	}
	echo '</div>';
	FonikAddonsPage::submit();
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
echo '<script src="'.PROJECT_PATH.'/app/fonik_addons/resources/js/popup.js"></script>';
require_once 'resources/footer.php';
