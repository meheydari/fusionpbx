<?php

if (!class_exists('crm_company_api')) {
	class crm_company_api {

		private $api_url;
		private $api_key;
		private $connect_timeout;
		private $request_timeout;

		public function __construct() {
			$this->api_url = trim($_SESSION['crm_connection']['api_url']['text'] ?? '');
			if ($this->api_url !== '') {
				$this->api_url = rtrim($this->api_url, '/').'/';
			}
			$this->api_key = trim($_SESSION['crm_connection']['admin_api_key']['text'] ?? '');
			$this->connect_timeout = max(1, (int) ($_SESSION['crm_connection']['connect_timeout']['numeric'] ?? 5));
			$this->request_timeout = max($this->connect_timeout, (int) ($_SESSION['crm_connection']['request_timeout']['numeric'] ?? 15));
		}

		public function is_configured() {
			return filter_var($this->api_url, FILTER_VALIDATE_URL) !== false
				&& $this->api_key !== ''
				&& !preg_match('/[\r\n]/', $this->api_key);
		}

		public function get($domain) {
			return $this->request('GET', ['domain' => $domain]);
		}

		public function create(array $data) {
			return $this->request('POST', [], $data);
		}

		public function update($domain, array $data) {
			return $this->request('PUT', ['company_domain' => $domain], $data);
		}

		private function request($method, array $query = [], ?array $data = null) {
			if (!$this->is_configured()) {
				return [
					'ok' => false,
					'status' => 0,
					'data' => null,
					'error' => 'تنظیمات api_url و admin_api_key برای crm_connection کامل نشده است.',
				];
			}

			$url = $this->api_url;
			if (!empty($query)) {
				$url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
			}

			$headers = [
				'Accept: application/json',
				'Content-Type: application/json',
				'ADMIN-API-KEY: '.$this->api_key,
			];

			$curl = curl_init($url);
			curl_setopt_array($curl, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
				CURLOPT_TIMEOUT => $this->request_timeout,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_MAXREDIRS => 0,
			]);

			if ($data !== null) {
				$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				if ($json === false) {
					unset($curl);
					return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'امکان تبدیل اطلاعات فرم به JSON وجود ندارد.'];
				}
				curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
			}

			$body = curl_exec($curl);
			$curl_error = curl_error($curl);
			$status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
			unset($curl);

			if ($body === false) {
				error_log('[crm_connection] Company API request failed: '.$curl_error);
				return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'ارتباط با سرویس CRM برقرار نشد.'];
			}

			$decoded = json_decode($body, true);
			if ($body !== '' && json_last_error() !== JSON_ERROR_NONE) {
				error_log('[crm_connection] Invalid JSON response with HTTP status '.$status);
				return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'پاسخ سرویس CRM معتبر نیست.'];
			}

			$ok = $status >= 200 && $status < 300;
			return [
				'ok' => $ok,
				'status' => $status,
				'data' => is_array($decoded) ? $decoded : [],
				'error' => $ok ? '' : $this->error_message($decoded, $status),
			];
		}

		private function error_message($data, $status) {
			if (is_array($data)) {
				if (!empty($data['error']) && is_string($data['error'])) {
					return $data['error'];
				}
				if (!empty($data['detail']) && is_string($data['detail'])) {
					return $data['detail'];
				}
				$messages = [];
				foreach ($data as $field => $errors) {
					if (is_array($errors)) {
						$messages[] = $field.': '.implode(', ', array_map('strval', $errors));
					}
				}
				if (!empty($messages)) {
					return implode(' | ', $messages);
				}
			}
			return 'سرویس CRM درخواست را نپذیرفت (HTTP '.$status.').';
		}
	}
}

?>
