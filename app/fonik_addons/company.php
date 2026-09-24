<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_page.php';

FonikAddonsPage::requireAccess('fonik_company_settings', true);
$context = FonikAddonsPage::context();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	FonikAddonsPage::validatePostToken();
	if ($context['error'] === '') {
		if ($context['company_exists']) {
			$result = $context['api']->updateCompany(
				(int) ($context['company']['id'] ?? 0),
				filter_var($_POST['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)
			);
			FonikAddonsPage::completeSave($result, 'company.php');
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
					filter_var($_POST['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)
				);
				FonikAddonsPage::completeSave($result, 'company.php');
			}
		}
	}
}

$company = $context['company'];
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
	echo '<div class="fonik-form-grid">';
	FonikAddonsPage::field(
		'نام شرکت',
		'<input class="formfld" type="text" name="name" maxlength="255" value="'.escape((string) ($company['name'] ?? '')).'"'.($context['company_exists'] ? ' readonly="readonly"' : ' required="required"').'>'
	);
	FonikAddonsPage::field('دامنه', '<input class="formfld" type="text" value="'.escape($context['domain']).'" readonly="readonly">');
	if ($context['company_exists']) {
		FonikAddonsPage::field('شناسه شرکت', '<input class="formfld" type="text" value="'.escape((string) ($company['id'] ?? '')).'" readonly="readonly">');
	}
	FonikAddonsPage::field('وضعیت شرکت', FonikAddonsPage::checkbox('is_active', $company['is_active'] ?? true));
	echo '</div>';
	FonikAddonsPage::submit($context['company_exists'] ? 'ذخیره تغییرات' : 'ثبت شرکت');
	echo '</form>';
}

FonikAddonsPage::panelEnd();
FonikAddonsPage::end();
require_once 'resources/footer.php';
