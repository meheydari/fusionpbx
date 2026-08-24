<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/resources/require.php';
require_once 'resources/check_auth.php';
require_once __DIR__.'/resources/classes/fonik_api_client.php';
require_once __DIR__.'/resources/classes/fonik_addons_validator.php';

if (!permission_exists('fonik_addons_view')) {
	header('HTTP/1.1 403 Forbidden');
	echo 'access denied';
	exit;
}

$fonikAddonsEnabled = filter_var(
	$_SESSION['fonik_addons']['enabled']['boolean'] ?? false,
	FILTER_VALIDATE_BOOLEAN
);
if (!if_group('superadmin') && !$fonikAddonsEnabled) {
	header('HTTP/1.1 403 Forbidden');
	echo 'Fonik add-ons are not enabled for this tenant.';
	exit;
}

$language = new text();
$text = $language->get();

function fonik_checked(mixed $value): bool
{
	return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function fonik_checkbox(string $name, mixed $checked): string
{
	if (substr((string) ($_SESSION['theme']['input_toggle_style']['text'] ?? ''), 0, 6) === 'switch') {
		return "<label class='switch'><input type='checkbox' id='".escape($name)."' name='".escape($name)."' value='true'".
			(fonik_checked($checked) ? " checked='checked'" : '')."><span class='slider'></span></label>";
	}

	return "<input type='checkbox' id='".escape($name)."' name='".escape($name)."' value='true'".
		(fonik_checked($checked) ? " checked='checked'" : '').'>';
}

function fonik_row(string $label, string $field, string $description = '', bool $required = false, string $group = ''): void
{
	$groupAttribute = $group !== '' ? " data-fonik-group='".escape($group)."'" : '';
	echo '<tr'.$groupAttribute.'>';
	echo "<td class='".($required ? 'vncellreq' : 'vncell')."' valign='top' nowrap='nowrap'>".escape($label).'</td>';
	echo "<td class='vtable'>".$field;
	if ($description !== '') {
		echo '<br><span class="description">'.escape($description).'</span>';
	}
	echo '</td></tr>';
}

function fonik_form_start(string $action): void
{
	global $token;
	echo "<form method='post' class='fonik-form'>";
	echo "<input type='hidden' name='action' value='".escape($action)."'>";
	echo "<input type='hidden' name='token' value='".escape($token)."'>";
}

function fonik_save_button(string $label = 'ذخیره'): void
{
	echo "<div class='fonik-actions'>";
	echo button::create([
		'type' => 'submit',
		'label' => $label,
		'icon' => $_SESSION['theme']['button_icon_save'] ?? 'save',
		'collapse' => 'hide-xs',
	]);
	echo '</div>';
}

/** @return array{exists: bool, data: array<mixed>, error: string} */
function fonik_load_service(FonikApiClient $api, int $companyId, string $service): array
{
	$result = $api->getService($companyId, $service);
	if ($result['ok']) {
		return ['exists' => true, 'data' => $result['data'], 'error' => ''];
	}
	if ($result['status'] === 404) {
		return ['exists' => false, 'data' => [], 'error' => ''];
	}

	return ['exists' => false, 'data' => [], 'error' => $result['error']];
}

$api = new FonikApiClient();
$domain = trim((string) ($_SESSION['domain_name'] ?? ''));
$configurationError = '';
$company = [];
$companyExists = false;

if ($domain === '') {
	$configurationError = 'دامنه tenant جاری مشخص نیست.';
}
elseif (!$api->isConfigured()) {
	$configurationError = 'ابتدا api_base_url و admin_api_key را در Default Settings و دسته fonik_addons تنظیم کنید.';
}
else {
	$companyResult = $api->findCompanyByDomain($domain);
	if ($companyResult['ok']) {
		$company = $companyResult['data'];
		$companyExists = true;
	}
	elseif ($companyResult['status'] !== 404) {
		$configurationError = $companyResult['error'];
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$tokenObject = new token();
	if (!$tokenObject->validate($_SERVER['PHP_SELF'])) {
		message::add($text['message-invalid_token'], 'negative');
		header('Location: fonik_addons.php');
		exit;
	}

	$action = (string) ($_POST['action'] ?? '');
	$errors = [];
	$result = null;
	$companyId = (int) ($company['id'] ?? 0);

	if ($configurationError !== '') {
		$errors[] = $configurationError;
	}
	elseif ($action === 'company_create') {
		if ($companyExists) {
			$errors[] = 'برای این tenant قبلاً شرکت ایجاد شده است.';
		}
		if (!permission_exists('fonik_company_add')) {
			$errors[] = 'دسترسی ایجاد شرکت Fonik را ندارید.';
		}
		$name = trim((string) ($_POST['name'] ?? ''));
		if ($name === '') {
			$errors[] = 'نام شرکت الزامی است.';
		}
		if ($errors === []) {
			$result = $api->createCompany($name, $domain, fonik_checked($_POST['is_active'] ?? false));
		}
	}
	elseif ($action === 'company_update') {
		if (!$companyExists || $companyId < 1) {
			$errors[] = 'ابتدا شرکت tenant را ایجاد کنید.';
		}
		if (!permission_exists('fonik_company_edit')) {
			$errors[] = 'دسترسی ویرایش شرکت Fonik را ندارید.';
		}
		if ($errors === []) {
			$result = $api->updateCompany($companyId, fonik_checked($_POST['is_active'] ?? false));
		}
	}
	elseif (in_array($action, ['click_save', 'popup_save', 'callback_save'], true)) {
		if (!$companyExists || $companyId < 1) {
			$errors[] = 'ابتدا شرکت tenant را ایجاد کنید.';
		}

		$definitions = [
			'click_save' => ['permission' => 'fonik_click_to_call_manage', 'service' => 'click-to-call-service'],
			'popup_save' => ['permission' => 'fonik_popup_manage', 'service' => 'popup-service'],
			'callback_save' => ['permission' => 'fonik_callback_manage', 'service' => 'callback-service'],
		];
		$definition = $definitions[$action];
		if (!permission_exists($definition['permission'])) {
			$errors[] = 'دسترسی مدیریت این سرویس Fonik را ندارید.';
		}

		$validation = ['payload' => [], 'errors' => []];
		if ($action === 'click_save') {
			$validation['payload'] = FonikAddonsValidator::clickToCall($_POST);
		}
		elseif ($action === 'popup_save') {
			$validation = FonikAddonsValidator::popup($_POST);
		}
		else {
			$validation = FonikAddonsValidator::callback($_POST);
		}
		$errors = array_merge($errors, $validation['errors']);

		if ($errors === []) {
			$current = $api->getService($companyId, $definition['service']);
			$exists = $current['ok'];
			if (!$exists && $current['status'] !== 404) {
				$errors[] = $current['error'];
			}
			else {
				$result = $api->saveService($companyId, $definition['service'], $validation['payload'], $exists);
			}
		}
	}
	elseif ($action === 'gateway_sync') {
		if (!$companyExists || $companyId < 1) {
			$errors[] = 'ابتدا شرکت tenant را ایجاد کنید.';
		}
		if (!permission_exists('fonik_gateway_sync')) {
			$errors[] = 'دسترسی همگام‌سازی Gatewayها را ندارید.';
		}
		if ($errors === []) {
			$result = $api->syncGateways($companyId);
		}
	}
	else {
		$errors[] = 'عملیات درخواست‌شده معتبر نیست.';
	}

	if ($result !== null && $result['ok']) {
		$message = 'تغییرات با موفقیت در Fonik ذخیره شد.';
		if ($action === 'gateway_sync') {
			$gateways = $result['data']['gateways'] ?? [];
			$message = 'Gatewayها همگام شدند.';
			if (is_array($gateways) && $gateways !== []) {
				$message .= ' شماره‌های فعال: '.implode('، ', array_map('strval', $gateways));
			}
		}
		message::add(escape($message));
		header('Location: fonik_addons.php');
		exit;
	}
	if ($result !== null && !$result['ok']) {
		$errors[] = $result['error'];
	}
	foreach (array_unique($errors) as $error) {
		message::add(escape((string) $error), 'negative');
	}
}

$tokenObject = new token();
$token = $tokenObject->create($_SERVER['PHP_SELF']);

$click = ['exists' => false, 'data' => ['enabled' => false, 'has_record' => false], 'error' => ''];
$popup = [
	'exists' => false,
	'data' => [
		'enabled' => false, 'popup_type' => 'API', 'api_key_popup' => '', 'ws_url' => '', 'api_url' => '',
		'direction' => 'BOTH', 'api_method' => 'POST', 'fields' => [],
	],
	'error' => '',
];
$callback = [
	'exists' => false,
	'data' => [
		'enabled' => false, 'has_in_call_back' => false, 'has_out_call_back' => false,
		'has_normal_call_back' => false, 'callback_check_range' => 30,
	],
	'error' => '',
];

if ($companyExists && $configurationError === '') {
	$companyId = (int) $company['id'];
	$click = array_replace_recursive($click, fonik_load_service($api, $companyId, 'click-to-call-service'));
	$popup = array_replace_recursive($popup, fonik_load_service($api, $companyId, 'popup-service'));
	$callback = array_replace_recursive($callback, fonik_load_service($api, $companyId, 'callback-service'));
}

$document['title'] = $text['title-fonik_addons'];
require_once 'resources/header.php';

?>
<style>
.fonik-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:18px; align-items:start; }
.fonik-card { border:1px solid var(--border-color, #d6d6d6); border-radius:8px; background:var(--card-background, #fff); overflow:hidden; }
.fonik-card-header { padding:14px 16px; border-bottom:1px solid var(--border-color, #ddd); display:flex; justify-content:space-between; align-items:center; }
.fonik-card-header h3 { margin:0; }
.fonik-card-body { padding:10px 14px 16px; }
.fonik-card table { width:100%; }
.fonik-actions { padding-top:14px; text-align:left; }
.fonik-status { font-size:12px; padding:3px 9px; border-radius:20px; background:#eceff1; }
.fonik-status.active { background:#dff3e4; color:#17692c; }
.fonik-warning { margin:12px 0; padding:12px; border-radius:6px; background:#fff3cd; color:#664d03; }
@media (max-width: 680px) { .fonik-grid { grid-template-columns:1fr; } }
</style>

<div class="action_bar" id="action_bar">
	<div class="heading"><b><?= escape($text['title-fonik_addons']) ?></b></div>
	<div class="actions"></div><div style="clear:both"></div>
</div>
<p><?= escape($text['description-fonik_addons']) ?></p>

<?php if (if_group('superadmin') && !$fonikAddonsEnabled): ?>
	<div class="fonik-warning">افزودنی‌ها برای tenant جاری غیرفعال است. تا زمانی که <code>fonik_addons.enabled</code> را در Domain Settings روی true قرار ندهید، کاربران این tenant منو و صفحه را نمی‌بینند.</div>
<?php endif; ?>

<?php if ($configurationError !== ''): ?>
	<div class="fonik-warning"><?= escape($configurationError) ?></div>
	<?php if (permission_exists('default_setting_view')): ?>
		<p><a href="../../core/default_settings/default_settings.php?category=fonik_addons">تنظیمات اتصال Fonik</a></p>
	<?php endif; ?>
<?php endif; ?>

<div class="fonik-grid">
	<section class="fonik-card">
		<div class="fonik-card-header">
			<h3>شرکت</h3>
			<span class="fonik-status <?= $companyExists ? 'active' : '' ?>"><?= $companyExists ? 'ایجاد شده' : 'ایجاد نشده' ?></span>
		</div>
		<div class="fonik-card-body">
			<?php fonik_form_start($companyExists ? 'company_update' : 'company_create'); ?>
			<table cellpadding="0" cellspacing="0">
				<?php
				fonik_row('نام شرکت', "<input class='formfld' type='text' name='name' maxlength='255' value=\"".escape((string) ($company['name'] ?? ''))."\"".($companyExists ? " readonly='readonly'" : " required='required'").'>', $companyExists ? 'نام شرکت بعد از ایجاد قابل تغییر نیست.' : '', !$companyExists);
				fonik_row('دامنه tenant', "<input class='formfld' type='text' value=\"".escape($domain)."\" readonly='readonly'>", 'دامنه از سشن tenant جاری گرفته می‌شود.', true);
				if ($companyExists) {
					fonik_row('شناسه شرکت', "<input class='formfld' type='text' value=\"".escape((string) $company['id'])."\" readonly='readonly'>");
				}
				fonik_row('فعال باشد', fonik_checkbox('is_active', $company['is_active'] ?? true));
				?>
			</table>
			<?php
			$canSaveCompany = $configurationError === '' && (
				($companyExists && permission_exists('fonik_company_edit')) ||
				(!$companyExists && permission_exists('fonik_company_add'))
			);
			if ($canSaveCompany) {
				fonik_save_button($companyExists ? 'بروزرسانی شرکت' : 'ایجاد شرکت');
			}
			?>
			</form>
		</div>
	</section>

	<?php if (!$companyExists): ?>
		<section class="fonik-card">
			<div class="fonik-card-header"><h3>سرویس‌ها</h3></div>
			<div class="fonik-card-body"><div class="fonik-warning">برای مدیریت سرویس‌ها، ابتدا شرکت این tenant را ایجاد کنید.</div></div>
		</section>
	<?php else: ?>
		<section class="fonik-card">
			<div class="fonik-card-header"><h3>Click to Call</h3><span class="fonik-status <?= $click['exists'] ? 'active' : '' ?>"><?= $click['exists'] ? 'ایجاد شده' : 'ایجاد نشده' ?></span></div>
			<div class="fonik-card-body">
				<?php if ($click['error'] !== ''): ?><div class="fonik-warning"><?= escape($click['error']) ?></div><?php endif; ?>
				<?php fonik_form_start('click_save'); ?>
				<table cellpadding="0" cellspacing="0">
					<?php
					fonik_row('سرویس فعال باشد', fonik_checkbox('enabled', $click['data']['enabled'] ?? false));
					fonik_row('ضبط تماس فعال باشد', fonik_checkbox('has_record', $click['data']['has_record'] ?? false), 'فعال‌سازی ضبط نیازمند مجوز Call Recording است.');
					?>
				</table>
				<?php if (permission_exists('fonik_click_to_call_manage')) { fonik_save_button(); } ?>
				</form>
			</div>
		</section>

		<section class="fonik-card">
			<div class="fonik-card-header"><h3>Callback</h3><span class="fonik-status <?= $callback['exists'] ? 'active' : '' ?>"><?= $callback['exists'] ? 'ایجاد شده' : 'ایجاد نشده' ?></span></div>
			<div class="fonik-card-body">
				<?php if ($callback['error'] !== ''): ?><div class="fonik-warning"><?= escape($callback['error']) ?></div><?php endif; ?>
				<?php fonik_form_start('callback_save'); ?>
				<table cellpadding="0" cellspacing="0">
					<?php
					fonik_row('سرویس فعال باشد', fonik_checkbox('enabled', $callback['data']['enabled'] ?? false));
					fonik_row('تماس ورودی', fonik_checkbox('has_in_call_back', $callback['data']['has_in_call_back'] ?? false));
					fonik_row('تماس خروجی', fonik_checkbox('has_out_call_back', $callback['data']['has_out_call_back'] ?? false));
					fonik_row('تماس بدون پاسخ', fonik_checkbox('has_normal_call_back', $callback['data']['has_normal_call_back'] ?? false));
					$range = (int) ($callback['data']['callback_check_range'] ?? 30);
					$options = [30 => '۳۰ دقیقه', 60 => '۶۰ دقیقه', 90 => '۹۰ دقیقه', 2880 => 'دو روز'];
					$select = "<select class='formfld' name='callback_check_range'>";
					foreach ($options as $value => $label) {
						$select .= "<option value='".$value."'".($range === $value ? " selected='selected'" : '').'>'.$label.'</option>';
					}
					$select .= '</select>';
					fonik_row('بازه بررسی', $select);
					?>
				</table>
				<?php if (permission_exists('fonik_callback_manage')) { fonik_save_button(); } ?>
				</form>
			</div>
		</section>

		<?php
		$popupData = $popup['data'];
		$popupFields = is_array($popupData['fields'] ?? null) ? $popupData['fields'] : [];
		$popupType = strtoupper((string) ($popupData['popup_type'] ?? 'API'));
		?>
		<section class="fonik-card">
			<div class="fonik-card-header"><h3>Popup</h3><span class="fonik-status <?= $popup['exists'] ? 'active' : '' ?>"><?= $popup['exists'] ? 'ایجاد شده' : 'ایجاد نشده' ?></span></div>
			<div class="fonik-card-body">
				<?php if ($popup['error'] !== ''): ?><div class="fonik-warning"><?= escape($popup['error']) ?></div><?php endif; ?>
				<?php fonik_form_start('popup_save'); ?>
				<table cellpadding="0" cellspacing="0">
					<?php
					fonik_row('سرویس فعال باشد', fonik_checkbox('enabled', $popupData['enabled'] ?? false));
					fonik_row('نوع Popup', "<select class='formfld' id='popup_type' name='popup_type'><option value='API'".($popupType === 'API' ? " selected='selected'" : '').">API</option><option value='SOCKET'".($popupType === 'SOCKET' ? " selected='selected'" : '').'>SOCKET</option></select>');
					fonik_row('آدرس API', "<input class='formfld' type='url' name='api_url' maxlength='1000' value=\"".escape((string) ($popupData['api_url'] ?? ''))."\">", '', false, 'popup-api');
					fonik_row('آدرس WebSocket', "<input class='formfld' type='text' name='ws_url' maxlength='1000' value=\"".escape((string) ($popupData['ws_url'] ?? ''))."\">", '', false, 'popup-socket');
					fonik_row('کلید اختصاصی Popup', "<input class='formfld' type='text' name='api_key_popup' maxlength='500' required='required' value=\"".escape((string) ($popupData['api_key_popup'] ?? ''))."\">", '', true);
					fonik_row('نام فیلد کلید Popup', "<input class='formfld' type='text' name='api_key_popup_label' maxlength='255' required='required' value=\"".escape((string) ($popupFields['api_key_popup_label'] ?? ''))."\">", '', true);
					$direction = strtoupper((string) ($popupData['direction'] ?? 'BOTH'));
					fonik_row('جهت تماس', "<select class='formfld' name='direction'><option value='INBOUND'".($direction === 'INBOUND' ? " selected='selected'" : '').">ورودی</option><option value='OUTBOUND'".($direction === 'OUTBOUND' ? " selected='selected'" : '').">خروجی</option><option value='BOTH'".($direction === 'BOTH' ? " selected='selected'" : '').'>هر دو</option></select>');
					$method = strtoupper((string) ($popupData['api_method'] ?? 'POST'));
					fonik_row('متد API', "<select class='formfld' name='api_method'><option value='POST'".($method === 'POST' ? " selected='selected'" : '').">POST</option><option value='GET'".($method === 'GET' ? " selected='selected'" : '').'>GET</option></select>');
					?>
					<tr><td class="vtable" colspan="2"><strong>فیلدهای Notification (حداقل دو مورد)</strong></td></tr>
					<?php
					$fieldDefinitions = [
						'external_number' => ['شماره خارجی', 'external_number_label'],
						'internal_number' => ['شماره داخلی', 'internal_number_label'],
						'call_type' => ['نوع تماس', 'call_type_label'],
						'date' => ['تاریخ تماس', 'date_label'],
						'call_id' => ['شناسه تماس', 'call_id_label'],
					];
					foreach ($fieldDefinitions as $flag => [$label, $labelField]) {
						fonik_row('ارسال '.$label, fonik_checkbox($flag, $popupFields[$flag] ?? false));
						fonik_row('نام فیلد '.$label, "<input class='formfld' type='text' name='".escape($labelField)."' maxlength='255' value=\"".escape((string) ($popupFields[$labelField] ?? ''))."\">");
					}
					?>
				</table>
				<?php if (permission_exists('fonik_popup_manage')) { fonik_save_button(); } ?>
				</form>
			</div>
		</section>

		<section class="fonik-card">
			<div class="fonik-card-header"><h3>همگام‌سازی Gateway</h3></div>
			<div class="fonik-card-body">
				<p>Gatewayهای tenant از سیستم مرکزی دریافت و با Fonik همگام می‌شوند.</p>
				<?php if (permission_exists('fonik_gateway_sync')): ?>
					<?php fonik_form_start('gateway_sync'); ?>
					<?php fonik_save_button('همگام‌سازی Gatewayها'); ?>
					</form>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var popupType = document.getElementById('popup_type');
	function refreshPopupType() {
		if (!popupType) return;
		document.querySelectorAll('[data-fonik-group="popup-api"]').forEach(function (row) {
			row.style.display = popupType.value === 'API' ? '' : 'none';
		});
		document.querySelectorAll('[data-fonik-group="popup-socket"]').forEach(function (row) {
			row.style.display = popupType.value === 'SOCKET' ? '' : 'none';
		});
	}
	if (popupType) popupType.addEventListener('change', refreshPopupType);
	refreshPopupType();
});
</script>

<?php require_once 'resources/footer.php'; ?>
