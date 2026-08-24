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

require_once dirname(__DIR__, 3).'/resources/classes/menu.php';
$menu = new menu();
$visibility = new ReflectionMethod($menu, 'runtime_item_visible');
$fonikMenuItem = ['uuid' => '929a8d95-1ddb-4400-9a12-6d94b461ec66'];

$_SESSION['groups'] = [['group_name' => 'admin']];
$_SESSION['fonik_addons']['enabled']['boolean'] = false;
assertSameValue(false, $visibility->invoke($menu, $fonikMenuItem), 'Disabled tenant must not see the Fonik menu.');

$_SESSION['fonik_addons']['enabled']['boolean'] = true;
assertSameValue(true, $visibility->invoke($menu, $fonikMenuItem), 'Enabled tenant must see the Fonik menu.');

$_SESSION['groups'] = [['group_name' => 'superadmin']];
$_SESSION['fonik_addons']['enabled']['boolean'] = false;
assertSameValue(true, $visibility->invoke($menu, $fonikMenuItem), 'Superadmin must always see the Fonik menu.');

echo "All Fonik add-ons validation tests passed.\n";
