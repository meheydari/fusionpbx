<?php
/*
	FusionPBX
	Version: MPL 1.1
*/

//includes files
	require_once dirname(__DIR__, 2).'/resources/require.php';
	require_once 'resources/check_auth.php';

//check permissions
	if (!permission_exists('crm_connection_view')) {
		echo 'access denied';
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//helpers
	function crm_connection_bool($value) {
		return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
	}

	function crm_connection_string($value) {
		$value = trim((string) $value);
		return $value === '' ? null : $value;
	}

	function crm_connection_checkbox($name, $checked, $dependency = '') {
		$attributes = $dependency !== '' ? " data-controls='".escape($dependency)."'" : '';
		if (substr($_SESSION['theme']['input_toggle_style']['text'] ?? '', 0, 6) === 'switch') {
			return "<label class='switch'><input type='checkbox' id='".escape($name)."' name='".escape($name)."' value='true'".
				($checked ? " checked='checked'" : '').$attributes."><span class='slider'></span></label>";
		}
		return "<input type='checkbox' id='".escape($name)."' name='".escape($name)."' value='true'".
			($checked ? " checked='checked'" : '').$attributes.'>';
	}

	function crm_connection_row($label, $field, $description = '', $required = false, $dependency_group = '') {
		$group_attribute = $dependency_group !== '' ? " data-dependency-group='".escape($dependency_group)."'" : '';
		echo '<tr'.$group_attribute.">\n";
		echo "<td class='".($required ? 'vncellreq' : 'vncell')."' valign='top' align='left' nowrap='nowrap'>".escape($label)."</td>\n";
		echo "<td class='vtable' align='left'>".$field;
		if ($description !== '') {
			echo '<br><span class="description">'.escape($description).'</span>';
		}
		echo "</td>\n</tr>\n";
	}

//set defaults from the API specification
	$defaults = [
		'name' => '',
		'domain' => $_SESSION['domain_name'] ?? '',
		'ws_url' => '',
		'api_url' => '',
		'has_in_call_back' => false,
		'has_out_call_back' => false,
		'has_normal_call_back' => false,
		'callback_check_range' => 30,
		'has_popup_socket' => false,
		'has_popup_api' => false,
		'is_active' => true,
		'api_key_popup' => '',
		'has_record' => false,
		'external_number' => false,
		'internal_number' => false,
		'call_type' => false,
		'date' => false,
		'call_id' => false,
		'api_method' => 'POST',
		'api_key_popup_label' => '',
		'external_number_label' => '',
		'internal_number_label' => '',
		'date_label' => '',
		'call_id_label' => '',
		'call_type_label' => '',
		'direction' => 'INBOUND',
		'id' => '',
		'api_key' => '',
		'created_at' => '',
		'updated_at' => '',
	];

	$editable_fields = [
		'ws_url', 'api_url', 'has_in_call_back', 'has_out_call_back', 'has_normal_call_back',
		'callback_check_range', 'has_popup_socket', 'has_popup_api', 'is_active', 'api_key_popup',
		'has_record', 'external_number', 'internal_number', 'call_type', 'date', 'call_id',
		'api_method', 'api_key_popup_label', 'external_number_label', 'internal_number_label',
		'date_label', 'call_id_label', 'call_type_label', 'direction',
	];
	$boolean_fields = [
		'has_in_call_back', 'has_out_call_back', 'has_normal_call_back', 'has_popup_socket',
		'has_popup_api', 'is_active', 'has_record', 'external_number', 'internal_number',
		'call_type', 'date', 'call_id',
	];

	$domain = $defaults['domain'];
	$values = $defaults;
	$company_exists = false;
	$load_error = '';
	$api = new crm_company_api;

//load the company for the active tenant
	if ($domain === '') {
		$load_error = 'دامنه tenant جاری مشخص نیست.';
	}
	elseif (!$api->is_configured()) {
		$load_error = 'ابتدا api_url و admin_api_key را در Default Settings و دسته crm_connection تنظیم کنید.';
	}
	else {
		$result = $api->get($domain);
		if ($result['ok']) {
			$company_exists = true;
			foreach ($values as $field => $default_value) {
				if (array_key_exists($field, $result['data']) && $result['data'][$field] !== null) {
					$values[$field] = $result['data'][$field];
				}
			}
			$values['domain'] = $domain;
			foreach ($boolean_fields as $field) {
				$values[$field] = crm_connection_bool($values[$field]);
			}
			$values['callback_check_range'] = max(1, (int) $values['callback_check_range']);
			$values['api_method'] = strtoupper((string) $values['api_method']);
			$values['direction'] = strtoupper((string) $values['direction']);
		}
		elseif ($result['status'] !== 404) {
			$load_error = $result['error'];
		}
	}

//handle the form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'], 'negative');
			header('Location: crm_connection.php');
			exit;
		}

		$values['domain'] = $domain;
		$values['name'] = $company_exists ? $values['name'] : trim($_POST['name'] ?? '');
		foreach ($editable_fields as $field) {
			if (in_array($field, $boolean_fields, true)) {
				$values[$field] = crm_connection_bool($_POST[$field] ?? false);
			}
			elseif ($field === 'callback_check_range') {
				$values[$field] = (int) ($_POST[$field] ?? 30);
			}
			else {
				$values[$field] = trim((string) ($_POST[$field] ?? ''));
			}
		}

		$errors = [];
		if (!$company_exists && $values['name'] === '') {
			$errors[] = 'نام شرکت الزامی است.';
		}
		if ($domain === '') {
			$errors[] = 'دامنه tenant جاری مشخص نیست.';
		}
		if ($values['callback_check_range'] < 1) {
			$errors[] = 'بازه بررسی Callback باید بزرگ‌تر از صفر باشد.';
		}
		if (!in_array($values['api_method'], ['POST', 'GET'], true)) {
			$errors[] = 'متد API معتبر نیست.';
		}
		if (!in_array($values['direction'], ['INBOUND', 'OUTBOUND', 'BOTH'], true)) {
			$errors[] = 'جهت تماس معتبر نیست.';
		}
		if ($values['api_url'] !== '' && filter_var($values['api_url'], FILTER_VALIDATE_URL) === false) {
			$errors[] = 'آدرس API نوتیفیکیشن معتبر نیست.';
		}
		$dependent_labels = [
			'external_number' => 'external_number_label',
			'internal_number' => 'internal_number_label',
			'call_type' => 'call_type_label',
			'date' => 'date_label',
			'call_id' => 'call_id_label',
		];
		foreach ($dependent_labels as $flag => $label) {
			if ($values[$flag] && $values[$label] === '') {
				$errors[] = 'نام فیلد مربوط به '.str_replace('_', ' ', $flag).' الزامی است.';
			}
		}
		if ($values['api_key_popup'] !== '' && $values['api_key_popup_label'] === '') {
			$errors[] = 'نام فیلد کلید اختصاصی نوتیفیکیشن الزامی است.';
		}

		if (!$api->is_configured()) {
			$errors[] = 'تنظیمات اتصال به API کامل نشده است.';
		}

		if (empty($errors)) {
			$payload = [];
			foreach ($editable_fields as $field) {
				if (in_array($field, $boolean_fields, true) || $field === 'callback_check_range') {
					$payload[$field] = $values[$field];
				}
				else {
					$payload[$field] = crm_connection_string($values[$field]);
				}
			}

			if ($company_exists) {
				if (!permission_exists('crm_connection_edit')) {
					$errors[] = 'دسترسی ویرایش اتصال CRM را ندارید.';
				}
				else {
					$result = $api->update($domain, $payload);
				}
			}
			else {
				if (!permission_exists('crm_connection_add')) {
					$errors[] = 'دسترسی ایجاد اتصال CRM را ندارید.';
				}
				else {
					$payload = array_merge(['name' => $values['name'], 'domain' => $domain], $payload);
					$result = $api->create($payload);
				}
			}

			if (empty($errors) && !empty($result['ok'])) {
				message::add($company_exists ? 'اطلاعات اتصال به CRM بروزرسانی شد.' : 'شرکت با موفقیت در CRM ایجاد شد.');
				header('Location: crm_connection.php');
				exit;
			}
			if (empty($errors)) {
				$errors[] = $result['error'];
			}
		}

		foreach ($errors as $error) {
			message::add(escape($error), 'negative');
		}
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-crm_connection'];
	require_once 'resources/header.php';

echo "<form method='post' name='frm' id='frm'>\n";
echo "<div class='action_bar' id='action_bar'>\n";
echo "<div class='heading'><b>".escape($text['title-crm_connection'])."</b></div>\n";
echo "<div class='actions'>\n";
if ($load_error === '' && (($company_exists && permission_exists('crm_connection_edit')) || (!$company_exists && permission_exists('crm_connection_add')))) {
	echo button::create(['type' => 'submit', 'label' => $text['button-save'], 'icon' => $_SESSION['theme']['button_icon_save'], 'id' => 'btn_save', 'collapse' => 'hide-xs']);
}
echo "</div><div style='clear: both;'></div></div>\n";
echo '<p>'.escape($text['description-crm_connection']).'</p>';

if ($load_error !== '') {
	echo "<div class='alert alert-warning'>".escape($load_error).'</div>';
	if (permission_exists('default_setting_view')) {
		echo "<p><a href='../../core/default_settings/default_settings.php?category=crm_connection'>مشاهده تنظیمات اتصال</a></p>";
	}
}
else {
	echo "<div class='alert alert-info'>".($company_exists ? 'شرکت این tenant قبلاً ایجاد شده و اطلاعات زیر قابل ویرایش است.' : 'برای این tenant هنوز شرکتی ایجاد نشده است. فرم را تکمیل و ذخیره کنید.').'</div>';
}

echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
echo "<tr><td class='vtable' colspan='2'><h3>اطلاعات شرکت</h3></td></tr>\n";
crm_connection_row('نام شرکت', "<input class='formfld' type='text' name='name' maxlength='255' value=\"".escape($values['name'])."\"".($company_exists ? " readonly='readonly'" : " required='required'").'>', $company_exists ? 'نام شرکت طبق مستندات پس از ایجاد قابل تغییر نیست.' : '', !$company_exists);
crm_connection_row('دامنه', "<input class='formfld' type='text' value=\"".escape($domain)."\" readonly='readonly'>", 'دامنه به‌صورت خودکار از tenant جاری دریافت می‌شود.', true);
if ($company_exists) {
	crm_connection_row('شناسه شرکت', "<input class='formfld' type='text' value=\"".escape($values['id'])."\" readonly='readonly'>");
	crm_connection_row('API Key شرکت', "<input class='formfld' type='password' value=\"".escape($values['api_key'])."\" readonly='readonly' autocomplete='off'>", 'این مقدار توسط CRM تولید شده و قابل ویرایش نیست.');
	crm_connection_row('تاریخ ایجاد', "<input class='formfld' type='text' value=\"".escape($values['created_at'])."\" readonly='readonly'>");
	crm_connection_row('آخرین بروزرسانی', "<input class='formfld' type='text' value=\"".escape($values['updated_at'])."\" readonly='readonly'>");
}
crm_connection_row('شرکت فعال باشد', crm_connection_checkbox('is_active', crm_connection_bool($values['is_active'])));
crm_connection_row('قابلیت ضبط تماس', crm_connection_checkbox('has_record', crm_connection_bool($values['has_record'])), 'فعال‌سازی نهایی منوط به خرید فضای ضبط در Fonik است.');

echo "<tr><td class='vtable' colspan='2'><h3>Callback تماس‌ها</h3></td></tr>\n";
crm_connection_row('Callback تماس ورودی', crm_connection_checkbox('has_in_call_back', crm_connection_bool($values['has_in_call_back']), 'callback-settings'));
crm_connection_row('Callback تماس خروجی', crm_connection_checkbox('has_out_call_back', crm_connection_bool($values['has_out_call_back']), 'callback-settings'));
crm_connection_row('Callback تماس پاسخ‌داده‌نشده', crm_connection_checkbox('has_normal_call_back', crm_connection_bool($values['has_normal_call_back']), 'callback-settings'));
crm_connection_row('بازه بررسی Callback', "<input class='formfld' type='number' min='1' name='callback_check_range' value=\"".escape($values['callback_check_range'])."\">", 'بر حسب مقدار مورد انتظار سرویس CRM.', false, 'callback-settings');

echo "<tr><td class='vtable' colspan='2'><h3>ارسال نوتیفیکیشن تماس</h3></td></tr>\n";
crm_connection_row('ارسال از طریق WebSocket', crm_connection_checkbox('has_popup_socket', crm_connection_bool($values['has_popup_socket']), 'socket-settings'));
crm_connection_row('آدرس WebSocket', "<input class='formfld' type='text' name='ws_url' maxlength='500' value=\"".escape($values['ws_url'])."\" placeholder='127.0.0.1:9000'>", '', false, 'socket-settings');
crm_connection_row('ارسال از طریق API', crm_connection_checkbox('has_popup_api', crm_connection_bool($values['has_popup_api']), 'api-settings'));
crm_connection_row('آدرس API نوتیفیکیشن', "<input class='formfld' type='url' name='api_url' maxlength='1000' value=\"".escape($values['api_url'])."\" placeholder='https://example.com/api/'>", '', false, 'api-settings');
crm_connection_row('متد ارسال درخواست', "<select class='formfld' name='api_method'><option value='POST'".($values['api_method'] === 'POST' ? " selected='selected'" : '').">POST</option><option value='GET'".($values['api_method'] === 'GET' ? " selected='selected'" : '').'>GET</option></select>', '', false, 'api-settings');
crm_connection_row('جهت تماس', "<select class='formfld' name='direction'><option value='INBOUND'".($values['direction'] === 'INBOUND' ? " selected='selected'" : '').">Inbound</option><option value='OUTBOUND'".($values['direction'] === 'OUTBOUND' ? " selected='selected'" : '').">Outbound</option><option value='BOTH'".($values['direction'] === 'BOTH' ? " selected='selected'" : '').'>Both</option></select>');
crm_connection_row('کلید اختصاصی Notification', "<input class='formfld' type='text' name='api_key_popup' maxlength='500' value=\"".escape($values['api_key_popup'])."\">");
crm_connection_row('نام فیلد کلید Notification', "<input class='formfld' type='text' name='api_key_popup_label' maxlength='255' value=\"".escape($values['api_key_popup_label'])."\">", 'اگر کلید اختصاصی وارد شده باشد، این فیلد الزامی است.', false, 'api-key-label-settings');

echo "<tr><td class='vtable' colspan='2'><h3>فیلدهای ارسالی در نوتیفیکیشن</h3></td></tr>\n";
crm_connection_row('ارسال شماره خارجی', crm_connection_checkbox('external_number', crm_connection_bool($values['external_number']), 'external-number-settings'));
crm_connection_row('نام فیلد شماره خارجی', "<input class='formfld' type='text' name='external_number_label' maxlength='255' value=\"".escape($values['external_number_label'])."\">", '', false, 'external-number-settings');
crm_connection_row('ارسال شماره داخلی', crm_connection_checkbox('internal_number', crm_connection_bool($values['internal_number']), 'internal-number-settings'));
crm_connection_row('نام فیلد شماره داخلی', "<input class='formfld' type='text' name='internal_number_label' maxlength='255' value=\"".escape($values['internal_number_label'])."\">", '', false, 'internal-number-settings');
crm_connection_row('ارسال نوع تماس', crm_connection_checkbox('call_type', crm_connection_bool($values['call_type']), 'call-type-settings'));
crm_connection_row('نام فیلد نوع تماس', "<input class='formfld' type='text' name='call_type_label' maxlength='255' value=\"".escape($values['call_type_label'])."\">", '', false, 'call-type-settings');
crm_connection_row('ارسال تاریخ تماس', crm_connection_checkbox('date', crm_connection_bool($values['date']), 'date-settings'));
crm_connection_row('نام فیلد تاریخ تماس', "<input class='formfld' type='text' name='date_label' maxlength='255' value=\"".escape($values['date_label'])."\">", '', false, 'date-settings');
crm_connection_row('ارسال شناسه تماس', crm_connection_checkbox('call_id', crm_connection_bool($values['call_id']), 'call-id-settings'));
crm_connection_row('نام فیلد شناسه تماس', "<input class='formfld' type='text' name='call_id_label' maxlength='255' value=\"".escape($values['call_id_label'])."\">", '', false, 'call-id-settings');
echo "</table>\n";
echo "<input type='hidden' name='token' value='".escape($token)."'>\n";
echo "</form>\n";

?>
<script>
document.addEventListener('DOMContentLoaded', function () {
	function setVisible(id, visible) {
		document.querySelectorAll('[data-dependency-group="' + id + '"]').forEach(function (element) {
			element.style.display = visible ? '' : 'none';
		});
	}

	function refreshDependencies() {
		var callbackEnabled = ['has_in_call_back', 'has_out_call_back', 'has_normal_call_back'].some(function (id) {
			var input = document.getElementById(id);
			return input && input.checked;
		});
		setVisible('callback-settings', callbackEnabled);

		var socket = document.getElementById('has_popup_socket');
		setVisible('socket-settings', socket && socket.checked);
		var api = document.getElementById('has_popup_api');
		setVisible('api-settings', api && api.checked);

		[
			['external_number', 'external-number-settings'],
			['internal_number', 'internal-number-settings'],
			['call_type', 'call-type-settings'],
			['date', 'date-settings'],
			['call_id', 'call-id-settings']
		].forEach(function (dependency) {
			var input = document.getElementById(dependency[0]);
			setVisible(dependency[1], input && input.checked);
		});

		var popupKey = document.querySelector('[name="api_key_popup"]');
		setVisible('api-key-label-settings', popupKey && popupKey.value.trim() !== '');
	}

	document.querySelectorAll('input[type="checkbox"], input[name="api_key_popup"]').forEach(function (input) {
		input.addEventListener('change', refreshDependencies);
		input.addEventListener('input', refreshDependencies);
	});
	refreshDependencies();
});
</script>
<?php

require_once 'resources/footer.php';

?>
