<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_recording_notify_manage');
$context = FonikAddonsPage::context();
$service = ['exists' => false, 'data' => [], 'error' => ''];

if ($context['company_exists'] && $context['error'] === '') {
	$service = FonikAddonsPage::loadService($context['api'], (int) $context['company']['id'], 'recording-service');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$validation = FonikAddonsValidator::recordingNotify($_POST);
	if ($validation['errors'] !== []) {
		message::add(implode(' ', $validation['errors']), 'negative');
	}
	else {
		$result = FonikAddonsPage::saveService($context['api'], (int) $context['company']['id'], 'recording-service', $validation['payload']);
		FonikAddonsPage::completeSave($result, 'record_notify.php');
	}
}

$data = $service['data'];
$fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
$deliveryType = strtoupper((string) ($data['delivery_type'] ?? 'AI'));
$webhookMethod = strtoupper((string) ($data['webhook_method'] ?? 'POST'));
$document['title'] = 'اعلان رکورد تماس';
require_once 'resources/header.php';
FonikAddonsPage::start('اعلان رکورد تماس', '', 'recording_notify_documentation_url', filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN));
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
	FonikAddonsPage::field('نوع ارسال', '<select class="formfld" name="delivery_type"><option value="AI"'.($deliveryType === 'AI' ? ' selected="selected"' : '').'>AI</option><option value="WEBHOOK"'.($deliveryType === 'WEBHOOK' ? ' selected="selected"' : '').'>Webhook</option><option value="BOTH"'.($deliveryType === 'BOTH' ? ' selected="selected"' : '').'>هر دو</option></select>');
	FonikAddonsPage::field('آدرس وب‌هوک', '<input class="formfld" type="url" name="webhook_url" maxlength="1000" value="'.escape((string) ($data['webhook_url'] ?? '')).'">');
	FonikAddonsPage::field('روش درخواست', '<select class="formfld" name="webhook_method"><option value="POST"'.($webhookMethod === 'POST' ? ' selected="selected"' : '').'>POST</option><option value="GET"'.($webhookMethod === 'GET' ? ' selected="selected"' : '').'>GET</option></select>');
	FonikAddonsPage::field('کلید وب‌هوک', '<input class="formfld" type="text" name="webhook_api_key" maxlength="500" value="'.escape((string) ($data['webhook_api_key'] ?? '')).'">');
	FonikAddonsPage::field('نام فیلد کلید وب‌هوک', '<input class="formfld" type="text" name="webhook_api_key_label" maxlength="255" value="'.escape((string) ($fields['webhook_api_key_label'] ?? '')).'">');
	FonikAddonsPage::fieldsHeading('اطلاعات ارسالی');
	$fieldDefinitions = [
		'call_id' => 'شناسه تماس',
		'external_number' => 'شماره خارجی',
		'internal_number' => 'شماره داخلی',
		'call_type' => 'نوع تماس',
		'date' => 'تاریخ تماس',
	];
	foreach ($fieldDefinitions as $fieldName => $label) {
		FonikAddonsPage::field('ارسال '.$label, FonikAddonsPage::checkbox($fieldName, $fields[$fieldName] ?? false));
		FonikAddonsPage::field('نام فیلد '.$label, '<input class="formfld" type="text" name="'.$fieldName.'_label" maxlength="255" value="'.escape((string) ($fields[$fieldName.'_label'] ?? '')).'">');
	}
	FonikAddonsPage::fieldsEnd();
	FonikAddonsPage::submit();
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
