<?php

declare(strict_types=1);

final class FonikAddonsPage
{
	private const UNAVAILABLE_MESSAGE = 'قابلیت افزودنی‌های CRM برای حساب شما فعال نیست. برای فعال‌سازی با پشتیبانی تماس بگیرید.';
	private const COMPANY_MISSING_MESSAGE = 'راه‌اندازی این سرویس برای حساب شما کامل نشده است. لطفاً با پشتیبانی تماس بگیرید.';
	private const SAVE_SUCCESS_MESSAGE = 'تنظیمات با موفقیت ذخیره شد.';
	private const SAVE_ERROR_MESSAGE = 'ذخیره تنظیمات انجام نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.';

	public static function requireAccess(string $permission, bool $superadminOnly = false): void
	{
		if (!self::featureEnabled()) {
			self::renderUnavailable();
		}

		if (!permission_exists($permission) || ($superadminOnly && !if_group('superadmin'))) {
			http_response_code(403);
			echo 'access denied';
			exit;
		}
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

	public static function start(string $title, string $description, string $documentationSetting, ?bool $active = null): void
	{
		$documentationUrl = self::documentationUrl($documentationSetting);
		echo '<link rel="stylesheet" href="'.PROJECT_PATH.'/app/fonik_addons/resources/css/fonik_addons.css">';
		echo '<main class="fonik-shell">';
		echo '<header class="fonik-page-header">';
		echo '<div class="fonik-page-copy"><h1>'.escape($title).'</h1>';
		if ($description !== '') {
			echo '<p>'.escape($description).'</p>';
		}
		echo '</div><div class="fonik-page-actions">';
		if ($active !== null) {
			echo '<span class="fonik-status '.($active ? 'fonik-status--active' : '').'">'.($active ? 'فعال' : 'غیرفعال').'</span>';
		}
		if ($documentationUrl !== '') {
			echo '<a class="fonik-documentation-link" href="'.escape($documentationUrl).'" target="_blank" rel="noopener noreferrer" download>دانلود مستندات</a>';
		}
		echo '</div></header>';
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
		global $token;
		echo '<form method="post" class="fonik-form">';
		echo '<input type="hidden" name="token" value="'.escape((string) $token).'">';
	}

	public static function field(string $label, string $control, string $hint = '', bool $wide = false, string $group = ''): void
	{
		$groupAttribute = $group !== '' ? ' data-fonik-group="'.escape($group).'"' : '';
		echo '<div class="fonik-field'.($wide ? ' fonik-field--wide' : '').'"'.$groupAttribute.'>';
		echo '<span class="fonik-label">'.escape($label).'</span>'.$control;
		if ($hint !== '') {
			echo '<span class="fonik-hint">'.escape($hint).'</span>';
		}
		echo '</div>';
	}

	public static function checkbox(string $name, mixed $checked): string
	{
		return '<label class="fonik-toggle"><input type="checkbox" name="'.escape($name).'" value="true"'.
			(filter_var($checked, FILTER_VALIDATE_BOOLEAN) ? ' checked="checked"' : '').
			'><span aria-hidden="true"></span></label>';
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

	private static function documentationUrl(string $setting): string
	{
		if ($setting === '') {
			return '';
		}

		$url = trim((string) ($_SESSION['fonik_addons'][$setting]['text'] ?? ''));
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return '';
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		return in_array($scheme, ['http', 'https'], true) ? $url : '';
	}
}
