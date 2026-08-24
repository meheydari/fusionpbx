<?php

declare(strict_types=1);

final class FonikAddonsValidator
{
	/** @param array<string, mixed> $input
	 *  @return array<string, mixed>
	 */
	public static function clickToCall(array $input): array
	{
		return [
			'enabled' => self::boolean($input['enabled'] ?? false),
			'has_record' => self::boolean($input['has_record'] ?? false),
		];
	}

	/** @param array<string, mixed> $input
	 *  @return array{payload: array<string, mixed>, errors: list<string>}
	 */
	public static function popup(array $input): array
	{
		$popupType = strtoupper(trim((string) ($input['popup_type'] ?? 'API')));
		$apiMethod = strtoupper(trim((string) ($input['api_method'] ?? 'POST')));
		$direction = strtoupper(trim((string) ($input['direction'] ?? 'BOTH')));
		$fields = [];
		$errors = [];

		$labels = [
			'external_number' => 'external_number_label',
			'internal_number' => 'internal_number_label',
			'call_type' => 'call_type_label',
			'date' => 'date_label',
			'call_id' => 'call_id_label',
		];
		$selected = 0;
		foreach ($labels as $flag => $label) {
			$fields[$flag] = self::boolean($input[$flag] ?? false);
			$fields[$label] = self::nullableString($input[$label] ?? null);
			if ($fields[$flag]) {
				$selected++;
				if ($fields[$label] === null) {
					$errors[] = 'نام فیلد مربوط به '.str_replace('_', ' ', $flag).' الزامی است.';
				}
			}
		}
		$fields['api_key_popup_label'] = self::nullableString($input['api_key_popup_label'] ?? null);

		if (!in_array($popupType, ['API', 'SOCKET'], true)) {
			$errors[] = 'نوع Popup باید API یا SOCKET باشد.';
		}
		if (!in_array($apiMethod, ['GET', 'POST'], true)) {
			$errors[] = 'متد ارسال Popup باید GET یا POST باشد.';
		}
		if (!in_array($direction, ['INBOUND', 'OUTBOUND', 'BOTH'], true)) {
			$errors[] = 'جهت تماس Popup معتبر نیست.';
		}
		if ($selected < 2) {
			$errors[] = 'حداقل دو فیلد Popup باید فعال باشند.';
		}
		if ($fields['api_key_popup_label'] === null) {
			$errors[] = 'نام فیلد کلید اختصاصی Popup الزامی است.';
		}
		if (trim((string) ($input['api_key_popup'] ?? '')) === '') {
			$errors[] = 'کلید اختصاصی Popup الزامی است.';
		}

		$apiUrl = self::nullableString($input['api_url'] ?? null);
		$wsUrl = self::nullableString($input['ws_url'] ?? null);
		if ($popupType === 'API' && ($apiUrl === null || filter_var($apiUrl, FILTER_VALIDATE_URL) === false)) {
			$errors[] = 'برای Popup نوع API یک api_url معتبر الزامی است.';
		}
		if ($popupType === 'SOCKET' && $wsUrl === null) {
			$errors[] = 'برای Popup نوع SOCKET مقدار ws_url الزامی است.';
		}

		return [
			'payload' => [
				'enabled' => self::boolean($input['enabled'] ?? false),
				'popup_type' => $popupType,
				'api_key_popup' => trim((string) ($input['api_key_popup'] ?? '')),
				'ws_url' => $popupType === 'SOCKET' ? $wsUrl : null,
				'api_url' => $popupType === 'API' ? $apiUrl : null,
				'direction' => $direction,
				'api_method' => $apiMethod,
				'fields' => $fields,
			],
			'errors' => $errors,
		];
	}

	/** @param array<string, mixed> $input
	 *  @return array{payload: array<string, mixed>, errors: list<string>}
	 */
	public static function callback(array $input): array
	{
		$range = (int) ($input['callback_check_range'] ?? 30);
		$errors = in_array($range, [30, 60, 90, 2880], true)
			? []
			: ['بازه Callback باید یکی از مقادیر ۳۰، ۶۰، ۹۰ یا ۲۸۸۰ دقیقه باشد.'];

		return [
			'payload' => [
				'enabled' => self::boolean($input['enabled'] ?? false),
				'has_in_call_back' => self::boolean($input['has_in_call_back'] ?? false),
				'has_out_call_back' => self::boolean($input['has_out_call_back'] ?? false),
				'has_normal_call_back' => self::boolean($input['has_normal_call_back'] ?? false),
				'callback_check_range' => $range,
			],
			'errors' => $errors,
		];
	}

	private static function boolean(mixed $value): bool
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	private static function nullableString(mixed $value): ?string
	{
		$value = trim((string) $value);
		return $value === '' ? null : $value;
	}
}
