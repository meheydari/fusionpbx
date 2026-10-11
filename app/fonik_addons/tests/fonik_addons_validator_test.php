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

if (!class_exists('token')) {
	class token
	{
		/** @return array{name: string, hash: string} */
		public function create(string $key): array
		{
			return ['name' => 'csrf-name', 'hash' => 'csrf-hash'];
		}
	}
}

if (!function_exists('escape')) {
	function escape(mixed $value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

$restrictedCallback = FonikAddonsValidator::callback(
	['callback_check_range' => '30', 'has_in_call_back' => 'true'],
	['has_in_call_back'],
	['has_out_call_back' => true, 'has_normal_call_back' => true]
);
assertSameValue(true, $restrictedCallback['payload']['has_in_call_back'], 'Granted Callback option must be editable.');
assertSameValue(true, $restrictedCallback['payload']['has_out_call_back'], 'Unauthorized Callback option must retain its current value.');

$secureCall = FonikAddonsValidator::secureCall(['enabled' => 'true']);
assertSameValue(true, $secureCall['enabled'], 'Secure Call enabled must be normalized.');
assertSameValue(false, $secureCall['has_record'], 'Secure Call recording must default to false.');

$recordingNotify = FonikAddonsValidator::recordingNotify([
	'enabled' => 'true',
	'delivery_type' => 'webhook',
	'webhook_url' => 'https://example.com/recording',
	'webhook_method' => 'post',
	'call_id' => 'true',
	'call_id_label' => 'call_id',
]);
assertSameValue([], $recordingNotify['errors'], 'A valid Recording Notify payload must be accepted.');
assertSameValue('WEBHOOK', $recordingNotify['payload']['delivery_type'], 'Recording delivery type must be normalized.');

require_once dirname(__DIR__).'/resources/classes/fonik_addons_page.php';
$pageReflection = new ReflectionClass(FonikAddonsPage::class);
$accessDeniedMessage = $pageReflection->getReflectionConstant('ACCESS_DENIED_MESSAGE');
assertSameValue(
	true,
	$accessDeniedMessage !== false && str_contains((string) $accessDeniedMessage->getValue(), 'با پشتیبانی تماس بگیرید'),
	'The access denied page must direct users to support.'
);
$_SESSION['fonik_addons']['enabled']['boolean'] = 'true';
assertSameValue(true, FonikAddonsPage::featureEnabled(), 'The tenant feature flag must accept a true boolean setting.');
$_SESSION['fonik_addons']['enabled']['boolean'] = 'false';
assertSameValue(false, FonikAddonsPage::featureEnabled(), 'The tenant feature flag must reject a false boolean setting.');

$_SERVER['PHP_SELF'] = '/app/fonik_addons/company.php';
ob_start();
FonikAddonsPage::formStart();
$formStart = (string) ob_get_clean();
assertSameValue(
	true,
	str_contains($formStart, 'name="csrf-name"') && str_contains($formStart, 'value="csrf-hash"'),
	'The form must submit the dynamic CSRF token name and hash generated by FusionPBX.'
);

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
$tooltip = new ReflectionMethod($menu, 'runtime_item_tooltip');
$fonikParent = ['uuid' => '929a8d95-1ddb-4400-9a12-6d94b461ec66'];
$companyMenuItem = ['uuid' => 'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'];
$clickMenuItem = ['uuid' => 'db0eefde-f64b-408f-ae9f-789fc24fca1a'];
$popupMenuItem = ['uuid' => '94b4cce1-a6c4-4918-a631-0252bfb18957'];
$callbackMenuItem = ['uuid' => '44903cd9-a1e4-45ab-a004-f24b8bdd9061'];

$_SESSION['groups'] = [['group_name' => 'admin']];
$_SESSION['permissions'] = ['fonik_click_to_call_manage' => true];
$_SESSION['fonik_addons']['enabled']['boolean'] = false;
assertSameValue(false, $visibility->invoke($menu, $fonikParent), 'Disabled tenant must not see the Fonik parent menu.');
assertSameValue(false, $visibility->invoke($menu, $clickMenuItem), 'Disabled tenant must not see a Fonik child menu.');

$_SESSION['fonik_addons']['enabled']['boolean'] = true;
assertSameValue(true, $visibility->invoke($menu, $fonikParent), 'Enabled tenant with a module permission must see the parent menu.');
assertSameValue(true, $visibility->invoke($menu, $clickMenuItem), 'An enabled tenant must see a permitted module.');
assertSameValue(true, $visibility->invoke($menu, $popupMenuItem), 'An enabled tenant must see a module even without its permission.');
assertSameValue(true, $visibility->invoke($menu, $callbackMenuItem), 'An enabled tenant must see Callback before access is granted.');
assertSameValue(false, $visibility->invoke($menu, $companyMenuItem), 'An admin must not see company settings.');

$_SESSION['permissions']['fonik_callback_missed'] = true;
assertSameValue(true, $visibility->invoke($menu, $callbackMenuItem), 'Any Callback sub-permission must show the Callback menu.');

$_SESSION['permissions'] = [];
assertSameValue(true, $visibility->invoke($menu, $fonikParent), 'An enabled tenant without module permissions must see the parent menu.');
assertSameValue(true, $visibility->invoke($menu, $clickMenuItem), 'An enabled tenant without module permissions must see service menu items.');

$_SESSION['groups'] = [['group_name' => 'superadmin']];
$_SESSION['permissions'] = ['fonik_company_settings' => true];
assertSameValue(true, $visibility->invoke($menu, $fonikParent), 'An enabled tenant must show the parent menu to an authorized superadmin.');
assertSameValue(true, $visibility->invoke($menu, $companyMenuItem), 'Only an authorized superadmin may see company settings.');
assertSameValue('Company Settings', $tooltip->invoke($menu, $companyMenuItem), 'Company settings must have an English hover title.');
assertSameValue('Callback', $tooltip->invoke($menu, $callbackMenuItem), 'Callback must have an English hover title.');

echo "All Fonik add-ons validation tests passed.\n";
