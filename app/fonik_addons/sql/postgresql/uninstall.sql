-- Scoped rollback for the Fonik Add-ons database registration.
-- Application files and the core menu visibility patch are not removed by SQL.

BEGIN;

DELETE FROM v_menu_item_groups
WHERE menu_item_uuid IN (
	SELECT menu_item_uuid
	FROM v_menu_items
	WHERE uuid IN (
		'929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid,
		'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid,
		'db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid,
		'94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid,
		'44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid,
		'161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid
	)
);

DELETE FROM v_menu_languages
WHERE menu_item_uuid IN (
	SELECT menu_item_uuid
	FROM v_menu_items
	WHERE uuid IN (
		'929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid,
		'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid,
		'db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid,
		'94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid,
		'44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid,
		'161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid
	)
);

DELETE FROM v_menu_items
WHERE uuid IN (
	'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid,
	'db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid,
	'94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid,
	'44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid,
	'161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid
);

DELETE FROM v_menu_items
WHERE uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid;

DELETE FROM v_group_permissions
WHERE permission_name IN (
	'fonik_addons_view',
	'fonik_company_add',
	'fonik_company_edit',
	'fonik_company_settings',
	'fonik_click_to_call_manage',
	'fonik_popup_manage',
	'fonik_callback_manage',
	'fonik_gateway_sync'
);

DELETE FROM v_permissions
WHERE application_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid;

DELETE FROM v_domain_settings
WHERE app_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid
	AND domain_setting_category = 'fonik_addons';

DELETE FROM v_default_settings
WHERE app_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid
	AND default_setting_category = 'fonik_addons';

COMMIT;
