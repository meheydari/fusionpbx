<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/resources/classes/fonik_addons_validator.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
	if ($expected !== $actual) {
		fwrite(STDERR, "FAIL: {$message}\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
		exit(1);
	}
}

if (!function_exists('if_group')) {
	function if_group(string $group): bool
	{
		foreach ($_SESSION['groups'] ?? [] as $assignedGroup) {
			if (($assignedGroup['group_name'] ?? '') === $group) {
				return true;
			}
		}

		return false;
	}
}

if (!function_exists('permission_exists')) {
	function permission_exists(string $permission): bool
	{
		return ($_SESSION['permissions'][$permission] ?? false) === true;
	}
}

$click = FonikAddonsValidator::clickToCall(['enabled' => 'true']);
assertSameValue(true, $click['enabled'], 'Click to Call enabled must be normalized to boolean.');
assertSameValue(false, $click['has_record'], 'Missing has_record must be false.');

$validPopup = FonikAddonsValidator::popup([
	'enabled' => 'true',
	'popup_type' => 'api',
	'api_url' => 'https://example.com/popup',
	'api_key_popup' => 'tenant-key',
	'api_key_popup_label' => 'apikey',
	'direction' => 'both',
	'api_method' => 'post',
	'external_number' => 'true',
	'external_number_label' => 'number',
	'internal_number' => 'true',
	'internal_number_label' => 'extension',
]);
assertSameValue([], $validPopup['errors'], 'A complete Popup payload must be accepted.');
assertSameValue('API', $validPopup['payload']['popup_type'], 'Popup type must be normalized.');
assertSameValue(null, $validPopup['payload']['ws_url'], 'Unused Popup URL must be null.');

$invalidPopup = FonikAddonsValidator::popup([
	'popup_type' => 'SOCKET',
	'api_key_popup' => '',
	'api_key_popup_label' => '',
	'external_number' => 'true',
]);
assertSameValue(true, count($invalidPopup['errors']) >= 4, 'Popup validation must enforce URL, key, labels, and two selected fields.');

$validCallback = FonikAddonsValidator::callback(['callback_check_range' => '2880']);
assertSameValue([], $validCallback['errors'], 'Documented callback range must be accepted.');
assertSameValue(2880, $validCallback['payload']['callback_check_range'], 'Callback range must be an integer.');

$invalidCallback = FonikAddonsValidator::callback(['callback_check_range' => '120']);
assertSameValue(1, count($invalidCallback['errors']), 'Undocumented callback range 120 must be rejected.');

require_once dirname(__DIR__).'/resources/classes/fonik_addons_page.php';
$_SESSION['fonik_addons']['enabled']['boolean'] = 'true';
assertSameValue(true, FonikAddonsPage::featureEnabled(), 'The tenant feature flag must accept a true boolean setting.');
$_SESSION['fonik_addons']['enabled']['boolean'] = 'false';
assertSameValue(false, FonikAddonsPage::featureEnabled(), 'The tenant feature flag must reject a false boolean setting.');

$documentationUrl = new ReflectionMethod(FonikAddonsPage::class, 'documentationUrl');
$_SESSION['fonik_addons']['popup_documentation_url']['text'] = 'https://docs.example.com/popup.pdf';
assertSameValue(
	'https://docs.example.com/popup.pdf',
	$documentationUrl->invoke(null, 'popup_documentation_url'),
	'A valid HTTPS documentation URL must be accepted.'
);
$_SESSION['fonik_addons']['popup_documentation_url']['text'] = 'javascript:alert(1)';
assertSameValue('', $documentationUrl->invoke(null, 'popup_documentation_url'), 'Unsafe documentation URL schemes must be rejected.');

require_once dirname(__DIR__, 3).'/resources/classes/menu.php';
$menu = new menu();
$visibility = new ReflectionMethod($menu, 'runtime_item_visible');
$fonikParent = ['uuid' => '929a8d95-1ddb-4400-9a12-6d94b461ec66'];
$companyMenuItem = ['uuid' => 'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'];
$clickMenuItem = ['uuid' => 'db0eefde-f64b-408f-ae9f-789fc24fca1a'];
$popupMenuItem = ['uuid' => '94b4cce1-a6c4-4918-a631-0252bfb18957'];

$_SESSION['groups'] = [['group_name' => 'admin']];
$_SESSION['permissions'] = ['fonik_click_to_call_manage' => true];
$_SESSION['fonik_addons']['enabled']['boolean'] = false;
assertSameValue(false, $visibility->invoke($menu, $fonikParent), 'Disabled tenant must not see the Fonik parent menu.');
assertSameValue(false, $visibility->invoke($menu, $clickMenuItem), 'Disabled tenant must not see a Fonik child menu.');

$_SESSION['fonik_addons']['enabled']['boolean'] = true;
assertSameValue(true, $visibility->invoke($menu, $fonikParent), 'Enabled tenant with a module permission must see the parent menu.');
assertSameValue(true, $visibility->invoke($menu, $clickMenuItem), 'A tenant must see its permitted module.');
assertSameValue(false, $visibility->invoke($menu, $popupMenuItem), 'A tenant must not see a module without its permission.');
assertSameValue(false, $visibility->invoke($menu, $companyMenuItem), 'An admin must not see company settings.');

$_SESSION['permissions'] = [];
assertSameValue(false, $visibility->invoke($menu, $fonikParent), 'A tenant without module permissions must not see the parent menu.');

$_SESSION['groups'] = [['group_name' => 'superadmin']];
$_SESSION['permissions'] = ['fonik_company_settings' => true];
assertSameValue(true, $visibility->invoke($menu, $fonikParent), 'An enabled tenant must show the parent menu to an authorized superadmin.');
assertSameValue(true, $visibility->invoke($menu, $companyMenuItem), 'Only an authorized superadmin may see company settings.');

echo "All Fonik add-ons validation tests passed.\n";
