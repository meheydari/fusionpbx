<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';
require_once __DIR__.'/resources/classes/fonik_tenant_permissions.php';

FonikAddonsPage::requireAccess('fonik_company_settings', true);
$context = FonikAddonsPage::context();

if ($context['company_exists'] && !FonikAddonsPage::featureEnabled()) {
	try {
		FonikTenantPermissions::setFeatureEnabled((string) $_SESSION['domain_uuid'], true);
	}
	catch (Throwable $exception) {
		error_log('[fonik_addons] Existing company activation sync failed: '.$exception->getMessage());
		message::add('شرکت در Fonik فعال است اما همگام‌سازی دسترسی tenant انجام نشد.', 'negative');
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	FonikAddonsPage::validatePostToken();
	if ($context['error'] === '') {
		$result = ['ok' => true, 'status' => 200, 'data' => $context['company'], 'error' => ''];
		if (!$context['company_exists']) {
			$name = trim((string) ($_POST['name'] ?? ''));
			if ($name === '') {
				message::add('نام شرکت الزامی است.', 'negative');
			}
			else {
				$result = $context['api']->createCompany(
					$name,
					$context['domain'],
					true
				);
			}
		}

		if (is_array($result) && $result['ok']) {
			try {
				$selectedPermissions = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
				FonikTenantPermissions::save((string) $_SESSION['domain_uuid'], $selectedPermissions);
				FonikTenantPermissions::setFeatureEnabled((string) $_SESSION['domain_uuid'], true);
				message::add('تنظیمات با موفقیت ذخیره شد.');
				FonikAddonsPage::redirect('company.php');
			}
			catch (Throwable $exception) {
				error_log('[fonik_addons] Tenant access save failed: '.$exception->getMessage());
				message::add('شرکت ذخیره شد اما فعال‌سازی دسترسی‌های tenant انجام نشد.', 'negative');
			}
		}
		elseif (is_array($result)) {
			FonikAddonsPage::completeSave($result, 'company.php');
		}
	}
}

$company = $context['company'];
$tenantPermissions = FonikTenantPermissions::get((string) $_SESSION['domain_uuid']);
$document['title'] = 'تنظیمات شرکت';
require_once 'resources/header.php';
FonikAddonsPage::start(
	'تنظیمات شرکت',
	'اطلاعات شرکت متصل به حساب Fonik را مدیریت کنید.',
	'company_documentation_url',
	$context['company_exists']
);
FonikAddonsPage::panelStart();

if ($context['error'] !== '') {
	FonikAddonsPage::notice($context['error'], 'error');
}
else {
	FonikAddonsPage::formStart();
	FonikAddonsPage::fieldsStart();
	if ($context['company_exists']) {
		FonikAddonsPage::field('نام شرکت', '<span class="fonik-readonly-value">'.escape((string) ($company['name'] ?? '')).'</span>');
		FonikAddonsPage::field('دامنه', '<span class="fonik-readonly-value" dir="ltr">'.escape($context['domain']).'</span>');
		FonikAddonsPage::field('شناسه شرکت', '<span class="fonik-readonly-value" dir="ltr">'.escape((string) ($company['id'] ?? '')).'</span>');
	}
	else {
		FonikAddonsPage::field(
			'نام شرکت',
			'<input class="formfld" type="text" name="name" maxlength="255" required="required">'
		);
		FonikAddonsPage::field('دامنه', '<span class="fonik-readonly-value" dir="ltr">'.escape($context['domain']).'</span>');
	}
	FonikAddonsPage::fieldsHeading('دسترسی مدیر tenant');
	foreach (FonikTenantPermissions::definitions() as $permissionName => $permissionLabel) {
		FonikAddonsPage::field(
			$permissionLabel,
			FonikAddonsPage::checkbox('permissions['.$permissionName.']', $tenantPermissions[$permissionName] ?? false)
		);
	}
	FonikAddonsPage::fieldsEnd();
	FonikAddonsPage::submit($context['company_exists'] ? 'ذخیره دسترسی‌ها' : 'ثبت شرکت');
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
