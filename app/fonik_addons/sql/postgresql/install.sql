-- Fonik Add-ons - scoped PostgreSQL installation for FusionPBX
-- Safe to run more than once. Only Fonik rows are inserted.

BEGIN;

DO $preflight$
BEGIN
	IF to_regclass('v_permissions') IS NULL
		OR to_regclass('v_group_permissions') IS NULL
		OR to_regclass('v_default_settings') IS NULL
		OR to_regclass('v_menu_items') IS NULL
		OR to_regclass('v_menu_languages') IS NULL
		OR to_regclass('v_menu_item_groups') IS NULL THEN
		RAISE EXCEPTION 'Required FusionPBX tables were not found in the current database/search_path';
	END IF;

	IF NOT EXISTS (
		SELECT 1 FROM v_groups
		WHERE domain_uuid IS NULL AND group_name = 'admin'
	) OR NOT EXISTS (
		SELECT 1 FROM v_groups
		WHERE domain_uuid IS NULL AND group_name = 'superadmin'
	) THEN
		RAISE EXCEPTION 'Global admin and superadmin groups must exist before installing Fonik Add-ons';
	END IF;

	IF NOT EXISTS (SELECT 1 FROM v_menus) THEN
		RAISE EXCEPTION 'No FusionPBX menus exist';
	END IF;
END
$preflight$;

-- Register only the permissions owned by this application.
WITH permission_rows(permission_name, permission_description) AS (
	VALUES
		('fonik_addons_view', 'View Fonik Add-ons for an enabled tenant'),
		('fonik_company_add', 'Create the current tenant company in Fonik'),
		('fonik_company_edit', 'Update the current tenant company in Fonik'),
		('fonik_click_to_call_manage', 'Manage the Fonik Click to Call service'),
		('fonik_popup_manage', 'Manage the Fonik Popup service'),
		('fonik_callback_manage', 'Manage the Fonik Callback service'),
		('fonik_gateway_sync', 'Synchronize the current tenant gateways with Fonik')
)
INSERT INTO v_permissions (
	permission_uuid,
	application_uuid,
	application_name,
	permission_name,
	permission_description,
	insert_date
)
SELECT
	md5('fonik-addons-permission:' || p.permission_name)::uuid,
	'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid,
	'Fonik Add-ons',
	p.permission_name,
	p.permission_description,
	CURRENT_TIMESTAMP
FROM permission_rows AS p
WHERE NOT EXISTS (
	SELECT 1
	FROM v_permissions AS existing
	WHERE existing.permission_name = p.permission_name
);

-- Grant the registered permissions to the standard global admin roles.
WITH permission_rows(permission_name) AS (
	VALUES
		('fonik_addons_view'),
		('fonik_company_add'),
		('fonik_company_edit'),
		('fonik_click_to_call_manage'),
		('fonik_popup_manage'),
		('fonik_callback_manage'),
		('fonik_gateway_sync')
), target_groups AS (
	SELECT group_uuid, group_name
	FROM v_groups
	WHERE domain_uuid IS NULL
		AND group_name IN ('admin', 'superadmin')
)
INSERT INTO v_group_permissions (
	group_permission_uuid,
	domain_uuid,
	permission_name,
	permission_protected,
	permission_assigned,
	group_name,
	group_uuid,
	insert_date
)
SELECT
	md5('fonik-addons-group-permission:' || g.group_uuid::text || ':' || p.permission_name)::uuid,
	NULL,
	p.permission_name,
	'false',
	'true',
	g.group_name,
	g.group_uuid,
	CURRENT_TIMESTAMP
FROM target_groups AS g
CROSS JOIN permission_rows AS p
WHERE NOT EXISTS (
	SELECT 1
	FROM v_group_permissions AS existing
	WHERE existing.group_uuid = g.group_uuid
		AND existing.permission_name = p.permission_name
		AND existing.domain_uuid IS NULL
);

-- Add the application defaults. Tenant access stays disabled globally.
WITH setting_rows(
	default_setting_uuid,
	default_setting_subcategory,
	default_setting_name,
	default_setting_value,
	default_setting_order,
	default_setting_description
) AS (
	VALUES
		('5b08111b-9974-4fac-91cf-f54e6657eb16'::uuid, 'enabled', 'boolean', 'false', 10, 'Enable Fonik Add-ons for the current tenant. Disabled by default.'),
		('577348ee-42b5-42fd-b6f8-ce21d29fe398'::uuid, 'api_base_url', 'text', 'http://172.22.100.20:8000/api/call/', 20, 'Fonik API base URL, including /api/call/.'),
		('ebeece37-c875-43e8-8667-68b9369f7467'::uuid, 'admin_api_key', 'text', '', 30, 'Value sent in the ADMIN-API-KEY header.'),
		('0aedf08b-07f0-4571-b86f-cf3798af7a78'::uuid, 'connect_timeout', 'numeric', '5', 40, 'Connection timeout in seconds.'),
		('6f9dc4d4-70c4-4e07-b2bd-1945c0f77092'::uuid, 'request_timeout', 'numeric', '15', 50, 'Request timeout in seconds.')
)
INSERT INTO v_default_settings (
	default_setting_uuid,
	app_uuid,
	default_setting_category,
	default_setting_subcategory,
	default_setting_name,
	default_setting_value,
	default_setting_order,
	default_setting_enabled,
	default_setting_description,
	insert_date
)
SELECT
	s.default_setting_uuid,
	'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid,
	'fonik_addons',
	s.default_setting_subcategory,
	s.default_setting_name,
	s.default_setting_value,
	s.default_setting_order,
	TRUE,
	s.default_setting_description,
	CURRENT_TIMESTAMP
FROM setting_rows AS s
WHERE NOT EXISTS (
	SELECT 1
	FROM v_default_settings AS existing
	WHERE existing.default_setting_category = 'fonik_addons'
		AND existing.default_setting_subcategory = s.default_setting_subcategory
		AND existing.default_setting_name = s.default_setting_name
);

-- Add one menu item to every configured FusionPBX menu.
INSERT INTO v_menu_items (
	menu_item_uuid,
	menu_uuid,
	menu_item_parent_uuid,
	uuid,
	menu_item_title,
	menu_item_link,
	menu_item_icon,
	menu_item_category,
	menu_item_protected,
	menu_item_order,
	menu_item_description,
	insert_date
)
SELECT
	md5('fonik-addons-menu-item:' || m.menu_uuid::text)::uuid,
	m.menu_uuid,
	NULL,
	'929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid,
	'افزودنی‌ها',
	'/app/fonik_addons/fonik_addons.php',
	'fa-puzzle-piece',
	'internal',
	'true',
	22,
	'Manage Fonik services for the active tenant.',
	CURRENT_TIMESTAMP
FROM v_menus AS m
WHERE NOT EXISTS (
	SELECT 1
	FROM v_menu_items AS existing
	WHERE existing.menu_uuid = m.menu_uuid
		AND existing.uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid
);

-- Add a title for every language already used by each menu, plus common fallbacks.
WITH menu_languages AS (
	SELECT DISTINCT menu_uuid, menu_language
	FROM v_menu_languages
	WHERE menu_language IS NOT NULL
	UNION
	SELECT menu_uuid, 'en-us' FROM v_menus
	UNION
	SELECT menu_uuid, 'fa' FROM v_menus
	UNION
	SELECT menu_uuid, 'fa-ir' FROM v_menus
), target_items AS (
	SELECT menu_item_uuid, menu_uuid
	FROM v_menu_items
	WHERE uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid
)
INSERT INTO v_menu_languages (
	menu_language_uuid,
	menu_uuid,
	menu_item_uuid,
	menu_language,
	menu_item_title,
	insert_date
)
SELECT
	md5('fonik-addons-menu-language:' || i.menu_uuid::text || ':' || l.menu_language)::uuid,
	i.menu_uuid,
	i.menu_item_uuid,
	l.menu_language,
	'افزودنی‌ها',
	CURRENT_TIMESTAMP
FROM target_items AS i
JOIN menu_languages AS l ON l.menu_uuid = i.menu_uuid
WHERE NOT EXISTS (
	SELECT 1
	FROM v_menu_languages AS existing
	WHERE existing.menu_uuid = i.menu_uuid
		AND existing.menu_item_uuid = i.menu_item_uuid
		AND existing.menu_language = l.menu_language
);

-- Allow standard admin and superadmin menu groups to receive the item.
WITH target_items AS (
	SELECT menu_item_uuid, menu_uuid
	FROM v_menu_items
	WHERE uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid
), target_groups AS (
	SELECT group_uuid, group_name
	FROM v_groups
	WHERE domain_uuid IS NULL
		AND group_name IN ('admin', 'superadmin')
)
INSERT INTO v_menu_item_groups (
	menu_item_group_uuid,
	menu_uuid,
	menu_item_uuid,
	group_name,
	group_uuid,
	insert_date
)
SELECT
	md5('fonik-addons-menu-group:' || i.menu_uuid::text || ':' || g.group_uuid::text)::uuid,
	i.menu_uuid,
	i.menu_item_uuid,
	g.group_name,
	g.group_uuid,
	CURRENT_TIMESTAMP
FROM target_items AS i
CROSS JOIN target_groups AS g
WHERE NOT EXISTS (
	SELECT 1
	FROM v_menu_item_groups AS existing
	WHERE existing.menu_uuid = i.menu_uuid
		AND existing.menu_item_uuid = i.menu_item_uuid
		AND existing.group_name = g.group_name
);

COMMIT;

-- Expected results: 7 permissions, 5 settings, and one item per menu.
SELECT COUNT(*) AS fonik_permission_count
FROM v_permissions
WHERE application_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid;

SELECT COUNT(*) AS fonik_default_setting_count
FROM v_default_settings
WHERE default_setting_category = 'fonik_addons';

SELECT menu_uuid, menu_item_uuid, menu_item_title, menu_item_link
FROM v_menu_items
WHERE uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid;
