-- Fonik Add-ons - scoped PostgreSQL installation for FusionPBX
-- Safe to run more than once. It never restores or replaces unrelated defaults.

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
		SELECT 1 FROM v_groups WHERE domain_uuid IS NULL AND group_name = 'admin'
	) OR NOT EXISTS (
		SELECT 1 FROM v_groups WHERE domain_uuid IS NULL AND group_name = 'superadmin'
	) THEN
		RAISE EXCEPTION 'Global admin and superadmin groups must exist before installing Fonik Add-ons';
	END IF;
END
$preflight$;

-- Remove obsolete permissions owned by version 1 of this application.
DELETE FROM v_group_permissions
WHERE permission_name IN ('fonik_addons_view', 'fonik_company_add', 'fonik_company_edit');

DELETE FROM v_permissions
WHERE application_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid
	AND permission_name IN ('fonik_addons_view', 'fonik_company_add', 'fonik_company_edit');

-- Service permissions must be granted per tenant, never globally.
DELETE FROM v_group_permissions
WHERE domain_uuid IS NULL
	AND permission_name IN (
		'fonik_click_to_call_manage',
		'fonik_popup_manage',
		'fonik_callback_manage',
		'fonik_gateway_sync'
	);

WITH permission_rows(permission_name, permission_description) AS (
	VALUES
		('fonik_company_settings', 'Manage the active tenant company in Fonik'),
		('fonik_click_to_call_manage', 'Manage Click to Call for the active tenant'),
		('fonik_popup_manage', 'Manage Popup for the active tenant'),
		('fonik_callback_manage', 'Manage Callback for the active tenant'),
		('fonik_gateway_sync', 'Synchronize gateways for the active tenant')
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
	SELECT 1 FROM v_permissions AS existing WHERE existing.permission_name = p.permission_name
);

-- Company settings are always restricted to superadmins.
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
	md5('fonik-addons-group-permission:' || g.group_uuid::text || ':fonik_company_settings')::uuid,
	NULL,
	'fonik_company_settings',
	'false',
	'true',
	g.group_name,
	g.group_uuid,
	CURRENT_TIMESTAMP
FROM v_groups AS g
WHERE g.domain_uuid IS NULL
	AND g.group_name = 'superadmin'
	AND NOT EXISTS (
		SELECT 1
		FROM v_group_permissions AS existing
		WHERE existing.group_uuid = g.group_uuid
			AND existing.permission_name = 'fonik_company_settings'
			AND existing.domain_uuid IS NULL
	);

UPDATE v_group_permissions
SET permission_assigned = 'true'
WHERE domain_uuid IS NULL
	AND group_name = 'superadmin'
	AND permission_name = 'fonik_company_settings';

WITH setting_rows(
	default_setting_uuid,
	default_setting_subcategory,
	default_setting_name,
	default_setting_value,
	default_setting_order,
	default_setting_description
) AS (
	VALUES
		('5b08111b-9974-4fac-91cf-f54e6657eb16'::uuid, 'enabled', 'boolean', 'false', 10, 'Enable Fonik Add-ons for the active tenant.'),
		('577348ee-42b5-42fd-b6f8-ce21d29fe398'::uuid, 'api_base_url', 'text', 'http://172.22.100.20:8000/api/call/', 20, 'Fonik API base URL.'),
		('ebeece37-c875-43e8-8667-68b9369f7467'::uuid, 'admin_api_key', 'text', '', 30, 'Value sent in the ADMIN-API-KEY header.'),
		('0aedf08b-07f0-4571-b86f-cf3798af7a78'::uuid, 'connect_timeout', 'numeric', '5', 40, 'Connection timeout in seconds.'),
		('6f9dc4d4-70c4-4e07-b2bd-1945c0f77092'::uuid, 'request_timeout', 'numeric', '15', 50, 'Request timeout in seconds.'),
		('1fd717f4-363d-4d5f-b160-dbec78180100'::uuid, 'company_documentation_url', 'text', '', 60, 'Company settings documentation URL.'),
		('c0cca4ad-cba0-4dfa-816f-cc76079ba017'::uuid, 'click_to_call_documentation_url', 'text', '', 70, 'Click to Call documentation URL.'),
		('2b586bec-7313-4229-b7c7-86f41ee6f227'::uuid, 'popup_documentation_url', 'text', '', 80, 'Popup documentation URL.'),
		('cfda0b41-2cf6-489a-ae98-4a9891f6f89e'::uuid, 'callback_documentation_url', 'text', '', 90, 'Callback documentation URL.'),
		('e6f39d49-d422-40fd-89b1-fea72f411fb8'::uuid, 'gateway_sync_documentation_url', 'text', '', 100, 'Gateway synchronization documentation URL.')
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

-- Convert the existing item into a parent menu.
UPDATE v_menu_items
SET menu_item_parent_uuid = NULL,
	menu_item_title = 'افزودنی‌ها',
	menu_item_link = '',
	menu_item_icon = 'fa-puzzle-piece',
	menu_item_order = 22,
	menu_item_description = 'Fonik add-on services.'
WHERE uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid;

WITH menu_rows(item_uuid, parent_uuid, title, link, icon, item_order) AS (
	VALUES
		('929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, NULL::uuid, 'افزودنی‌ها', '', 'fa-puzzle-piece', 22),
		('b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid, '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'تنظیمات شرکت', '/app/fonik_addons/company.php', 'fa-building', 10),
		('db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid, '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'کلیک برای تماس', '/app/fonik_addons/click_to_call.php', 'fa-phone', 20),
		('94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid, '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'پاپ‌آپ تماس', '/app/fonik_addons/popup.php', 'fa-bell', 30),
		('44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid, '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'تماس بازگشتی', '/app/fonik_addons/callback.php', 'fa-reply', 40),
		('161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid, '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'همگام‌سازی درگاه‌ها', '/app/fonik_addons/gateway_sync.php', 'fa-refresh', 50)
), targets AS (
	SELECT
		m.menu_uuid,
		r.*,
		parent.menu_item_uuid AS resolved_parent_uuid
	FROM v_menus AS m
	CROSS JOIN menu_rows AS r
	LEFT JOIN v_menu_items AS parent
		ON parent.menu_uuid = m.menu_uuid
		AND parent.uuid = r.parent_uuid
)
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
	md5('fonik-addons-menu-item:' || t.menu_uuid::text || ':' || t.item_uuid::text)::uuid,
	t.menu_uuid,
	t.resolved_parent_uuid,
	t.item_uuid,
	t.title,
	t.link,
	t.icon,
	'internal',
	'true',
	t.item_order,
	'Fonik Add-ons',
	CURRENT_TIMESTAMP
FROM targets AS t
WHERE NOT EXISTS (
	SELECT 1 FROM v_menu_items AS existing
	WHERE existing.menu_uuid = t.menu_uuid AND existing.uuid = t.item_uuid
);

-- Keep child menu metadata current on repeated runs.
WITH menu_rows(item_uuid, title, link, icon, item_order) AS (
	VALUES
		('b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid, 'تنظیمات شرکت', '/app/fonik_addons/company.php', 'fa-building', 10),
		('db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid, 'کلیک برای تماس', '/app/fonik_addons/click_to_call.php', 'fa-phone', 20),
		('94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid, 'پاپ‌آپ تماس', '/app/fonik_addons/popup.php', 'fa-bell', 30),
		('44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid, 'تماس بازگشتی', '/app/fonik_addons/callback.php', 'fa-reply', 40),
		('161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid, 'همگام‌سازی درگاه‌ها', '/app/fonik_addons/gateway_sync.php', 'fa-refresh', 50)
)
UPDATE v_menu_items AS item
SET menu_item_parent_uuid = parent.menu_item_uuid,
	menu_item_title = r.title,
	menu_item_link = r.link,
	menu_item_icon = r.icon,
	menu_item_order = r.item_order
FROM menu_rows AS r, v_menu_items AS parent
WHERE item.uuid = r.item_uuid
	AND parent.menu_uuid = item.menu_uuid
	AND parent.uuid = '929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid;

WITH menu_languages AS (
	SELECT DISTINCT menu_uuid, menu_language
	FROM v_menu_languages
	WHERE menu_language IS NOT NULL
	UNION SELECT menu_uuid, 'en-us' FROM v_menus
	UNION SELECT menu_uuid, 'fa-ir' FROM v_menus
), target_items AS (
	SELECT menu_item_uuid, menu_uuid, menu_item_title
	FROM v_menu_items
	WHERE uuid IN (
		'929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid,
		'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid,
		'db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid,
		'94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid,
		'44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid,
		'161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid
	)
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
	md5('fonik-addons-menu-language:' || i.menu_item_uuid::text || ':' || l.menu_language)::uuid,
	i.menu_uuid,
	i.menu_item_uuid,
	l.menu_language,
	i.menu_item_title,
	CURRENT_TIMESTAMP
FROM target_items AS i
JOIN menu_languages AS l ON l.menu_uuid = i.menu_uuid
WHERE NOT EXISTS (
	SELECT 1 FROM v_menu_languages AS existing
	WHERE existing.menu_item_uuid = i.menu_item_uuid
		AND existing.menu_language = l.menu_language
);

WITH menu_groups(item_uuid, group_name) AS (
	VALUES
		('929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'admin'),
		('929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid, 'superadmin'),
		('b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid, 'superadmin'),
		('db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid, 'admin'),
		('db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid, 'superadmin'),
		('94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid, 'admin'),
		('94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid, 'superadmin'),
		('44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid, 'admin'),
		('44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid, 'superadmin'),
		('161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid, 'admin'),
		('161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid, 'superadmin')
), target_items AS (
	SELECT menu_item_uuid, menu_uuid, uuid FROM v_menu_items
), target_groups AS (
	SELECT group_uuid, group_name FROM v_groups
	WHERE domain_uuid IS NULL AND group_name IN ('admin', 'superadmin')
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
	md5('fonik-addons-menu-group:' || i.menu_item_uuid::text || ':' || g.group_uuid::text)::uuid,
	i.menu_uuid,
	i.menu_item_uuid,
	g.group_name,
	g.group_uuid,
	CURRENT_TIMESTAMP
FROM menu_groups AS mg
JOIN target_items AS i ON i.uuid = mg.item_uuid
JOIN target_groups AS g ON g.group_name = mg.group_name
WHERE NOT EXISTS (
	SELECT 1 FROM v_menu_item_groups AS existing
	WHERE existing.menu_item_uuid = i.menu_item_uuid
		AND existing.group_uuid = g.group_uuid
);

COMMIT;

SELECT COUNT(*) AS fonik_permission_count
FROM v_permissions
WHERE application_uuid = 'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid;

SELECT COUNT(*) AS fonik_default_setting_count
FROM v_default_settings
WHERE default_setting_category = 'fonik_addons';

SELECT menu_uuid, COUNT(*) AS fonik_menu_item_count
FROM v_menu_items
WHERE uuid IN (
	'929a8d95-1ddb-4400-9a12-6d94b461ec66'::uuid,
	'b0af7bb9-c86a-4252-9ce0-ecd8488d895b'::uuid,
	'db0eefde-f64b-408f-ae9f-789fc24fca1a'::uuid,
	'94b4cce1-a6c4-4918-a631-0252bfb18957'::uuid,
	'44903cd9-a1e4-45ab-a004-f24b8bdd9061'::uuid,
	'161ebc6f-fea1-4a57-8897-2804ab28085d'::uuid
)
GROUP BY menu_uuid;
