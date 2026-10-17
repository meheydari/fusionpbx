<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';
require_once __DIR__.'/resources/classes/fonik_tenant_permissions.php';

FonikAddonsPage::requireAccess('fonik_company_settings', true);
$context = FonikAddonsPage::context();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	FonikAddonsPage::validatePostToken();
	if ($context['error'] === '') {
		$isActive = filter_var($_POST['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
		$result = null;
		if ($context['company_exists']) {
			$result = $context['api']->updateCompany(
				(int) ($context['company']['id'] ?? 0),
				$isActive
			);
		}
		else {
			$name = trim((string) ($_POST['name'] ?? ''));
			if ($name === '') {
				message::add('نام شرکت الزامی است.', 'negative');
			}
			else {
				$result = $context['api']->createCompany(
					$name,
					$context['domain'],
					$isActive
				);
			}
		}

		if (is_array($result) && $result['ok']) {
			try {
				$selectedPermissions = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
				FonikTenantPermissions::save((string) $_SESSION['domain_uuid'], $selectedPermissions);
				FonikTenantPermissions::setFeatureEnabled((string) $_SESSION['domain_uuid'], $isActive);
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
	$context['company_exists'] ? filter_var($company['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) : false
);
FonikAddonsPage::panelStart();

if ($context['error'] !== '') {
	FonikAddonsPage::notice($context['error'], 'error');
}
else {
	FonikAddonsPage::formStart();
	FonikAddonsPage::fieldsStart();
	FonikAddonsPage::field(
		'نام شرکت',
		'<input class="formfld" type="text" name="name" maxlength="255" value="'.escape((string) ($company['name'] ?? '')).'"'.($context['company_exists'] ? ' readonly="readonly"' : ' required="required"').'>'
	);
	FonikAddonsPage::field('دامنه', '<input class="formfld" type="text" value="'.escape($context['domain']).'" readonly="readonly">');
	if ($context['company_exists']) {
		FonikAddonsPage::field('شناسه شرکت', '<input class="formfld" type="text" value="'.escape((string) ($company['id'] ?? '')).'" readonly="readonly">');
	}
	FonikAddonsPage::field('وضعیت شرکت', FonikAddonsPage::checkbox('is_active', $company['is_active'] ?? true));
	FonikAddonsPage::fieldsHeading('دسترسی مدیر tenant');
	foreach (FonikTenantPermissions::definitions() as $permissionName => $permissionLabel) {
		FonikAddonsPage::field(
			$permissionLabel,
			FonikAddonsPage::checkbox('permissions['.$permissionName.']', $tenantPermissions[$permissionName] ?? false)
		);
	}
	FonikAddonsPage::fieldsEnd();
	FonikAddonsPage::submit($context['company_exists'] ? 'ذخیره تغییرات' : 'ثبت شرکت');
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
