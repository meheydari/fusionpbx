<?php

declare(strict_types=1);

require_once __DIR__.'/fonik_tenant_permissions.php';

final class FonikAddonsPage
{
	private const UNAVAILABLE_MESSAGE = 'قابلیت افزودنی‌های CRM برای حساب شما فعال نیست. برای فعال‌سازی با پشتیبانی تماس بگیرید.';
	private const ACCESS_DENIED_MESSAGE = 'دسترسی شما به این بخش فعال نیست. برای دریافت دسترسی با پشتیبانی تماس بگیرید.';
	private const COMPANY_MISSING_MESSAGE = 'راه‌اندازی این سرویس برای حساب شما کامل نشده است. لطفاً با پشتیبانی تماس بگیرید.';
	private const SAVE_SUCCESS_MESSAGE = 'تنظیمات با موفقیت ذخیره شد.';
	private const SAVE_ERROR_MESSAGE = 'ذخیره تنظیمات انجام نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.';

	/** @param string|list<string> $permission */
	public static function requireAccess(string|array $permission, bool $superadminOnly = false): void
	{
		$isSuperadmin = if_group('superadmin');
		if ($superadminOnly) {
			if (!$isSuperadmin) {
				self::renderAccessDenied();
			}

			return;
		}

		if (!self::featureEnabled()) {
			self::renderUnavailable();
		}

		if (!self::hasServiceAccess($permission)) {
			self::renderAccessDenied();
		}
	}

	/** @param string|list<string> $permission */
	public static function hasServiceAccess(string|array $permission): bool
	{
		return self::grantedServicePermissions(is_array($permission) ? $permission : [$permission]) !== [];
	}

	/**
	 * Superadmins manage a tenant but are not members of its admin group. Their service access must
	 * therefore be evaluated against the tenant's configured admin permissions instead of their own session.
	 *
	 * @param list<string> $permissions
	 * @return list<string>
	 */
	public static function grantedServicePermissions(array $permissions): array
	{
		$granted = [];
		if (if_group('superadmin')) {
			$domainUuid = trim((string) ($_SESSION['domain_uuid'] ?? ''));
			if ($domainUuid === '') {
				return [];
			}
			$tenantPermissions = FonikTenantPermissions::get($domainUuid);
			foreach ($permissions as $permissionName) {
				if ($tenantPermissions[$permissionName] ?? false) {
					$granted[] = $permissionName;
				}
			}

			return $granted;
		}

		foreach ($permissions as $permissionName) {
			if (permission_exists($permissionName)) {
				$granted[] = $permissionName;
			}
		}

		return $granted;
	}

	public static function tenantAvailableToCurrentUser(): bool
	{
		return if_group('superadmin') || self::featureEnabled();
	}

	public static function featureEnabled(): bool
	{
		return filter_var(
			$_SESSION['fonik_addons']['enabled']['boolean'] ?? false,
			FILTER_VALIDATE_BOOLEAN
		);
	}

	public static function renderUnavailable(): never
	{
		global $document;
		http_response_code(403);
		$document['title'] = 'افزودنی‌ها';
		require 'resources/header.php';
		self::start('افزودنی‌ها', '', '');
		echo '<section class="fonik-notice fonik-notice--warning" role="status">'.escape(self::UNAVAILABLE_MESSAGE).'</section>';
		self::end();
		require 'resources/footer.php';
		exit;
	}

	public static function renderAccessDenied(): never
	{
		global $document;
		http_response_code(403);
		$document['title'] = 'افزودنی‌ها';
		require 'resources/header.php';
		self::start('افزودنی‌ها', '', '');
		echo '<section class="fonik-notice fonik-notice--warning" role="alert">'.escape(self::ACCESS_DENIED_MESSAGE).'</section>';
		self::end();
		require 'resources/footer.php';
		exit;
	}

	/** @return array{api: FonikApiClient, domain: string, company: array<mixed>, company_exists: bool, error: string} */
	public static function context(): array
	{
		$api = new FonikApiClient();
		$domain = trim((string) ($_SESSION['domain_name'] ?? ''));
		$company = [];
		$companyExists = false;
		$error = '';

		if ($domain === '') {
			$error = 'دامنه حساب جاری مشخص نیست.';
		}
		elseif (!$api->isConfigured()) {
			$error = 'ابتدا api_base_url و admin_api_key را در Default Settings و دسته fonik_addons تنظیم کنید.';
		}
		else {
			$result = $api->findCompanyByDomain($domain);
			if ($result['ok']) {
				$company = $result['data'];
				$companyExists = true;
			}
			elseif ($result['status'] !== 404) {
				$error = $result['error'];
			}
		}

		return [
			'api' => $api,
			'domain' => $domain,
			'company' => $company,
			'company_exists' => $companyExists,
			'error' => $error,
		];
	}

	public static function validatePostToken(): void
	{
		$tokenObject = new token();
		if (!$tokenObject->validate($_SERVER['PHP_SELF'])) {
			$language = new text();
			$text = $language->get();
			message::add($text['message-invalid_token'], 'negative');
			self::redirect(basename((string) $_SERVER['PHP_SELF']));
		}
	}

	public static function redirect(string $path): never
	{
		header('Location: '.$path);
		exit;
	}

	/** @param array{ok: bool, status: int, data: array<mixed>, error: string} $result */
	public static function completeSave(array $result, string $redirect): void
	{
		if ($result['ok']) {
			message::add(self::SAVE_SUCCESS_MESSAGE);
			self::redirect($redirect);
		}

		error_log('[fonik_addons] Save failed with HTTP status '.(string) $result['status'].': '.$result['error']);
		message::add(self::SAVE_ERROR_MESSAGE, 'negative');
	}

	/** @param array<mixed> $payload */
	public static function saveService(FonikApiClient $api, int $companyId, string $service, array $payload): array
	{
		$current = $api->getService($companyId, $service);
		if (!$current['ok'] && $current['status'] !== 404) {
			return $current;
		}

		return $api->saveService($companyId, $service, $payload, $current['ok']);
	}

	/** @return array{exists: bool, data: array<mixed>, error: string} */
	public static function loadService(FonikApiClient $api, int $companyId, string $service): array
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

	public static function start(string $title, string $description, string $documentationSetting, ?bool $active = null, ?string $companyApiKey = null): void
	{
		$documentationUrl = self::documentationUrl($documentationSetting);
		$companyApiKey = trim((string) $companyApiKey);
		echo '<link rel="stylesheet" href="'.PROJECT_PATH.'/app/fonik_addons/resources/css/fonik_addons.css">';
		echo '<main class="fonik-shell">';
		echo '<div class="action_bar fonik-page-header"><div class="heading"><b>'.escape($title).'</b></div><div class="actions">';
		if ($active !== null) {
			echo '<span class="fonik-status '.($active ? 'fonik-status--active' : '').'">'.($active ? 'فعال' : 'غیرفعال').'</span>';
		}
		if ($documentationUrl !== '') {
			echo '<a href="'.escape($documentationUrl).'" target="_blank" rel="noopener noreferrer" download>'.button::create([
				'type' => 'button',
				'label' => 'دانلود مستندات',
				'icon' => 'download',
			]).'</a>';
		}
		if ($companyApiKey !== '') {
			echo button::create([
				'type' => 'button',
				'label' => 'دریافت API Key',
				'icon' => 'key',
				'id' => 'fonik-api-key-open',
				'collapse' => 'hide-xs',
			]);
		}
		echo '</div><div style="clear: both;"></div></div>';
		if ($companyApiKey !== '') {
			self::apiKeyDialog($companyApiKey);
		}
		if ($description !== '') {
			echo '<div class="fonik-description">'.escape($description).'</div>';
		}
	}

	public static function end(): void
	{
		echo '</main>';
	}

	public static function panelStart(string $title = ''): void
	{
		echo '<section class="fonik-panel">';
		if ($title !== '') {
			echo '<div class="fonik-panel-heading"><h2>'.escape($title).'</h2></div>';
		}
		echo '<div class="fonik-panel-body">';
	}

	public static function panelEnd(): void
	{
		echo '</div></section>';
	}

	public static function notice(string $message, string $type = 'warning'): void
	{
		$type = in_array($type, ['warning', 'error', 'info'], true) ? $type : 'warning';
		echo '<div class="fonik-notice fonik-notice--'.escape($type).'" role="status">'.escape($message).'</div>';
	}

	public static function companyMissing(): void
	{
		self::notice(self::COMPANY_MISSING_MESSAGE, 'warning');
	}

	public static function formStart(): void
	{
		$tokenObject = new token();
		$formToken = $tokenObject->create($_SERVER['PHP_SELF']);
		echo '<form method="post" class="fonik-form">';
		echo '<input type="hidden" name="'.escape((string) $formToken['name']).'" value="'.escape((string) $formToken['hash']).'">';
	}

	public static function fieldsStart(): void
	{
		echo '<table class="fonik-fields" width="100%" border="0" cellpadding="0" cellspacing="0">';
	}

	public static function fieldsEnd(): void
	{
		echo '</table>';
	}

	public static function fieldsHeading(string $title): void
	{
		echo '<tr><td class="vtable fonik-fields-heading" colspan="2">'.escape($title).'</td></tr>';
	}

	public static function field(string $label, string $control, string $hint = '', bool $wide = false, string $group = ''): void
	{
		$groupAttribute = $group !== '' ? ' data-fonik-group="'.escape($group).'"' : '';
		echo '<tr class="fonik-field"'.$groupAttribute.'>';
		echo '<td class="vncell" valign="top" nowrap="nowrap" width="30%">'.escape($label).'</td><td class="vtable">'.$control;
		if ($hint !== '') {
			echo '<div class="fonik-hint">'.escape($hint).'</div>';
		}
		echo '</td></tr>';
	}

	public static function checkbox(string $name, mixed $checked): string
	{
		return '<label class="switch"><input type="checkbox" name="'.escape($name).'" value="true"'.
			(filter_var($checked, FILTER_VALIDATE_BOOLEAN) ? ' checked="checked"' : '').
			'><span class="slider"></span></label>';
	}

	public static function submit(string $label = 'ذخیره تنظیمات'): void
	{
		echo '<div class="fonik-form-actions">'.button::create([
			'type' => 'submit',
			'label' => $label,
			'icon' => $_SESSION['theme']['button_icon_save'] ?? 'save',
			'collapse' => 'hide-xs',
		]).'</div>';
	}

	private static function apiKeyDialog(string $apiKey): void
	{
		echo '<dialog class="fonik-api-key-dialog" id="fonik-api-key-dialog" aria-labelledby="fonik-api-key-title">';
		echo '<div class="fonik-api-key-dialog__header"><b id="fonik-api-key-title">API Key شرکت</b>';
		echo '<button type="button" class="fonik-api-key-dialog__close" id="fonik-api-key-close" aria-label="بستن">&times;</button></div>';
		echo '<div class="fonik-api-key-dialog__body">';
		echo '<p>این کلید را فقط در سامانه‌های مورد اعتماد استفاده کنید.</p>';
		echo '<div class="fonik-api-key-value"><input class="formfld" id="fonik-api-key-value" type="text" readonly="readonly" dir="ltr" value="'.escape($apiKey).'">';
		echo button::create([
			'type' => 'button',
			'label' => 'کپی کلید',
			'icon' => 'copy',
			'id' => 'fonik-api-key-copy',
			'collapse' => false,
		]).'</div>';
		echo '<div class="fonik-api-key-feedback" id="fonik-api-key-feedback" role="status" aria-live="polite"></div>';
		echo '</div></dialog>';
		echo '<script src="'.PROJECT_PATH.'/app/fonik_addons/resources/js/api_key.js"></script>';
	}

	private static function documentationUrl(string $setting): string
	{
		if ($setting === '') {
			return '';
		}

		$defaults = [
			'popup_documentation_url' => 'https://{domain}/docs/Pop-up.pdf',
			'recording_notify_documentation_url' => 'https://{domain}/docs/record-notify.pdf',
			'secure_call_documentation_url' => 'https://{domain}/docs/security-call.pdf',
		];
		$url = trim((string) ($_SESSION['fonik_addons'][$setting]['text'] ?? ''));
		if ($url === '') {
			$url = $defaults[$setting] ?? '';
		}

		$domain = trim((string) ($_SESSION['domain_name'] ?? ''));
		if ($domain === '' || preg_match('/[\s\/?#]/', $domain) === 1) {
			return '';
		}
		$url = str_replace('{domain}', $domain, $url);
		if (strtolower((string) parse_url($url, PHP_URL_HOST)) === 'etehad.fonik.cloud') {
			$scheme = (string) parse_url($url, PHP_URL_SCHEME);
			$path = (string) parse_url($url, PHP_URL_PATH);
			$query = parse_url($url, PHP_URL_QUERY);
			$url = $scheme.'://'.$domain.$path.($query !== null && $query !== false ? '?'.$query : '');
		}
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return '';
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		return in_array($scheme, ['http', 'https'], true) ? $url : '';
	}
}
