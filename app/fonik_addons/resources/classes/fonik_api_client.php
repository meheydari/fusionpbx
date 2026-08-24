<?php

declare(strict_types=1);

final class FonikApiClient
{
	private string $baseUrl;
	private string $apiKey;
	private int $connectTimeout;
	private int $requestTimeout;

	public function __construct(?array $settings = null)
	{
		$settings ??= $_SESSION['fonik_addons'] ?? [];
		$this->baseUrl = $this->normalizeBaseUrl((string) ($settings['api_base_url']['text'] ?? ''));
		$this->apiKey = trim((string) ($settings['admin_api_key']['text'] ?? ''));
		$this->connectTimeout = max(1, (int) ($settings['connect_timeout']['numeric'] ?? 5));
		$this->requestTimeout = max($this->connectTimeout, (int) ($settings['request_timeout']['numeric'] ?? 15));
	}

	public function isConfigured(): bool
	{
		return filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false
			&& $this->apiKey !== ''
			&& preg_match('/[\r\n]/', $this->apiKey) !== 1;
	}

	/** @return array{ok: bool, status: int, data: array<mixed>, error: string} */
	public function findCompanyByDomain(string $domain): array
	{
		$result = $this->request('GET', 'company/');
		if (!$result['ok']) {
			return $result;
		}

		foreach ($result['data'] as $company) {
			if (is_array($company) && isset($company['domain']) && (string) $company['domain'] === $domain) {
				return ['ok' => true, 'status' => 200, 'data' => $company, 'error' => ''];
			}
		}

		return ['ok' => false, 'status' => 404, 'data' => [], 'error' => 'شرکت این tenant هنوز در Fonik ایجاد نشده است.'];
	}

	/** @return array{ok: bool, status: int, data: array<mixed>, error: string} */
	public function createCompany(string $name, string $domain, bool $isActive): array
	{
		return $this->request('POST', 'company/', [
			'name' => $name,
			'domain' => $domain,
			'is_active' => $isActive,
		]);
	}

	/** @return array{ok: bool, status: int, data: array<mixed>, error: string} */
	public function updateCompany(int $companyId, bool $isActive): array
	{
		return $this->request('PUT', 'company/'.$companyId.'/', ['is_active' => $isActive]);
	}

	/** @return array{ok: bool, status: int, data: array<mixed>, error: string} */
	public function getService(int $companyId, string $service): array
	{
		return $this->request('GET', $this->servicePath($companyId, $service));
	}

	/** @param array<string, mixed> $payload
	 *  @return array{ok: bool, status: int, data: array<mixed>, error: string}
	 */
	public function saveService(int $companyId, string $service, array $payload, bool $exists): array
	{
		return $this->request($exists ? 'PUT' : 'POST', $this->servicePath($companyId, $service), $payload);
	}

	/** @return array{ok: bool, status: int, data: array<mixed>, error: string} */
	public function syncGateways(int $companyId): array
	{
		return $this->request('PUT', 'company/'.$companyId.'/sync-gateways/');
	}

	private function servicePath(int $companyId, string $service): string
	{
		$allowed = ['click-to-call-service', 'popup-service', 'callback-service'];
		if (!in_array($service, $allowed, true)) {
			throw new InvalidArgumentException('Unsupported Fonik service.');
		}

		return 'company/'.$companyId.'/'.$service.'/';
	}

	private function normalizeBaseUrl(string $url): string
	{
		$url = rtrim(trim($url), '/');
		if (str_ends_with($url, '/company')) {
			$url = substr($url, 0, -strlen('/company'));
		}

		return $url === '' ? '' : $url.'/';
	}

	/** @param array<string, mixed>|null $payload
	 *  @return array{ok: bool, status: int, data: array<mixed>, error: string}
	 */
	private function request(string $method, string $path, ?array $payload = null): array
	{
		if (!$this->isConfigured()) {
			return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'تنظیمات اتصال Fonik کامل نشده است.'];
		}

		$url = $this->baseUrl.ltrim($path, '/');
		$curl = curl_init($url);
		if ($curl === false) {
			return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'امکان شروع ارتباط با Fonik وجود ندارد.'];
		}

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Content-Type: application/json',
				'ADMIN-API-KEY: '.$this->apiKey,
			],
			CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
			CURLOPT_TIMEOUT => $this->requestTimeout,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS => 0,
		]);

		if ($payload !== null) {
			$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($json === false) {
				curl_close($curl);
				return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'امکان تبدیل اطلاعات به JSON وجود ندارد.'];
			}
			curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		}

		$body = curl_exec($curl);
		$curlError = curl_error($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($body === false) {
			error_log('[fonik_addons] API request failed: '.$curlError);
			return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'ارتباط با سرویس Fonik برقرار نشد.'];
		}

		$decoded = $body === '' ? [] : json_decode($body, true);
		if ($body !== '' && !is_array($decoded)) {
			error_log('[fonik_addons] Invalid JSON response with HTTP status '.$status);
			return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'پاسخ سرویس Fonik معتبر نیست.'];
		}

		$ok = $status >= 200 && $status < 300;
		return [
			'ok' => $ok,
			'status' => $status,
			'data' => $decoded,
			'error' => $ok ? '' : $this->errorMessage($decoded, $status),
		];
	}

	/** @param array<mixed> $data */
	private function errorMessage(array $data, int $status): string
	{
		foreach (['error', 'detail'] as $key) {
			if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
				return $data[$key];
			}
		}

		$messages = [];
		foreach ($data as $field => $errors) {
			if (is_array($errors)) {
				$messages[] = (string) $field.': '.implode(', ', array_map('strval', $errors));
			}
		}

		return $messages !== []
			? implode(' | ', $messages)
			: 'سرویس Fonik درخواست را نپذیرفت (HTTP '.$status.').';
	}
}
