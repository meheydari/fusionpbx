<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_callback_manage');
$context = FonikAddonsPage::context();
$service = ['exists' => false, 'data' => [], 'error' => ''];

if ($context['company_exists'] && $context['error'] === '') {
	$service = FonikAddonsPage::loadService($context['api'], (int) $context['company']['id'], 'callback-service');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $context['company_exists'] && $context['error'] === '') {
	FonikAddonsPage::validatePostToken();
	$validation = FonikAddonsValidator::callback($_POST);
	if ($validation['errors'] !== []) {
		message::add(implode(' ', $validation['errors']), 'negative');
	}
	else {
		$result = FonikAddonsPage::saveService($context['api'], (int) $context['company']['id'], 'callback-service', $validation['payload']);
		FonikAddonsPage::completeSave($result, 'callback.php');
	}
}

$data = $service['data'];
$range = (int) ($data['callback_check_range'] ?? 30);
$rangeOptions = [30 => '۳۰ دقیقه', 60 => '۶۰ دقیقه', 90 => '۹۰ دقیقه', 2880 => 'دو روز'];
$rangeSelect = '<select class="formfld" name="callback_check_range">';
foreach ($rangeOptions as $value => $label) {
	$rangeSelect .= '<option value="'.$value.'"'.($range === $value ? ' selected="selected"' : '').'>'.escape($label).'</option>';
}
$rangeSelect .= '</select>';

$document['title'] = 'تماس بازگشتی';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'تماس بازگشتی',
	'شرایط و بازه زمانی ایجاد تماس بازگشتی را تنظیم کنید.',
	'callback_documentation_url',
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
	FonikAddonsPage::field('تماس‌های ورودی', FonikAddonsPage::checkbox('has_in_call_back', $data['has_in_call_back'] ?? false));
	FonikAddonsPage::field('تماس‌های خروجی', FonikAddonsPage::checkbox('has_out_call_back', $data['has_out_call_back'] ?? false));
	FonikAddonsPage::field('تماس‌های بی‌پاسخ', FonikAddonsPage::checkbox('has_normal_call_back', $data['has_normal_call_back'] ?? false));
	FonikAddonsPage::field('بازه بررسی', $rangeSelect);
	echo '</div>';
	FonikAddonsPage::submit();
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
