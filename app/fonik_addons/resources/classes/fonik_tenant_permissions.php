<?php

declare(strict_types=1);

final class FonikTenantPermissions
{
	/** @return array<string, string> */
	public static function definitions(): array
	{
		return [
			'fonik_click_to_call_manage' => 'کلیک برای تماس',
			'fonik_popup_manage' => 'پاپ‌آپ تماس',
			'fonik_callback_incoming' => 'تماس بازگشتی - تماس‌های ورودی',
			'fonik_callback_outgoing' => 'تماس بازگشتی - تماس‌های خروجی',
			'fonik_callback_missed' => 'تماس بازگشتی - تماس‌های بی‌پاسخ',
			'fonik_gateway_sync' => 'همگام‌سازی درگاه‌ها',
			'fonik_recording_notify_manage' => 'اعلان رکورد تماس',
			'fonik_secure_call_manage' => 'تماس امن',
		];
	}

	/** @return array<string, bool> */
	public static function get(string $domainUuid, string $groupName = 'admin'): array
	{
		$result = array_fill_keys(array_keys(self::definitions()), false);
		$sql = "select permission_name, permission_assigned from v_group_permissions ";
		$sql .= "where domain_uuid = :domain_uuid and group_name = :group_name ";
		$sql .= "and permission_name in ('".implode("','", array_keys(self::definitions()))."')";
		$database = new database();
		$rows = $database->select($sql, ['domain_uuid' => $domainUuid, 'group_name' => $groupName], 'all');
		foreach (is_array($rows) ? $rows : [] as $row) {
			$result[(string) $row['permission_name']] = filter_var($row['permission_assigned'] ?? false, FILTER_VALIDATE_BOOLEAN);
		}

		return $result;
	}

	/** @param array<string, mixed> $selected */
	public static function save(string $domainUuid, array $selected, string $groupName = 'admin'): void
	{
		$sql = "select group_uuid from v_groups where domain_uuid is null and group_name = :group_name";
		$database = new database();
		$group = $database->select($sql, ['group_name' => $groupName], 'row');
		if (!is_array($group) || empty($group['group_uuid'])) {
			throw new RuntimeException('گروه مدیر سیستم پیدا نشد.');
		}

		foreach (self::definitions() as $permissionName => $label) {
			$assigned = isset($selected[$permissionName]) ? 'true' : 'false';
			$parameters = [
				'domain_uuid' => $domainUuid,
				'group_uuid' => $group['group_uuid'],
				'group_name' => $groupName,
				'permission_name' => $permissionName,
				'permission_assigned' => $assigned,
			];
			$database->execute(
				"update v_group_permissions set permission_assigned = :permission_assigned, update_date = current_timestamp ".
				"where domain_uuid = :domain_uuid and group_uuid = :group_uuid and permission_name = :permission_name",
				$parameters
			);
			$parameters['group_permission_uuid'] = uuid();
			$parameters['check_domain_uuid'] = $domainUuid;
			$parameters['check_group_uuid'] = $group['group_uuid'];
			$parameters['check_permission_name'] = $permissionName;
			$database->execute(
				"insert into v_group_permissions (group_permission_uuid, domain_uuid, permission_name, permission_protected, permission_assigned, group_name, group_uuid, insert_date) ".
				"select :group_permission_uuid, :domain_uuid, :permission_name, 'false', :permission_assigned, :group_name, :group_uuid, current_timestamp ".
				"where not exists (select 1 from v_group_permissions where domain_uuid = :check_domain_uuid and group_uuid = :check_group_uuid and permission_name = :check_permission_name)",
				$parameters
			);
		}

		unset($_SESSION['menu']['array']);
	}
}
