-- Configure the shared Fonik API connection.
-- Replace both CHANGE-ME values before executing. Keep this edited file out of Git.

BEGIN;

DO $configure$
DECLARE
	target_api_base_url text := 'CHANGE-ME-API-BASE-URL';
	target_admin_api_key text := 'CHANGE-ME-ADMIN-API-KEY';
BEGIN
	IF target_api_base_url = 'CHANGE-ME-API-BASE-URL'
		OR target_admin_api_key = 'CHANGE-ME-ADMIN-API-KEY' THEN
		RAISE EXCEPTION 'Set target_api_base_url and target_admin_api_key before running this script';
	END IF;

	IF target_api_base_url !~ '^https?://[^[:space:]]+/$' THEN
		RAISE EXCEPTION 'The API base URL must be HTTP(S), contain no whitespace, and end with /';
	END IF;

	IF target_admin_api_key = '' OR target_admin_api_key ~ E'[\\r\\n]' THEN
		RAISE EXCEPTION 'The admin API key must be non-empty and contain no newline';
	END IF;

	UPDATE v_default_settings
	SET default_setting_value = target_api_base_url,
		default_setting_enabled = TRUE,
		update_date = CURRENT_TIMESTAMP
	WHERE default_setting_category = 'fonik_addons'
		AND default_setting_subcategory = 'api_base_url'
		AND default_setting_name = 'text';
	IF NOT FOUND THEN
		RAISE EXCEPTION 'Fonik api_base_url setting is missing; run install.sql first';
	END IF;

	UPDATE v_default_settings
	SET default_setting_value = target_admin_api_key,
		default_setting_enabled = TRUE,
		update_date = CURRENT_TIMESTAMP
	WHERE default_setting_category = 'fonik_addons'
		AND default_setting_subcategory = 'admin_api_key'
		AND default_setting_name = 'text';
	IF NOT FOUND THEN
		RAISE EXCEPTION 'Fonik admin_api_key setting is missing; run install.sql first';
	END IF;
END
$configure$;

COMMIT;

SELECT
	default_setting_subcategory,
	CASE
		WHEN default_setting_subcategory = 'admin_api_key' THEN '[configured]'
		ELSE default_setting_value
	END AS configured_value,
	default_setting_enabled
FROM v_default_settings
WHERE default_setting_category = 'fonik_addons'
	AND default_setting_subcategory IN ('api_base_url', 'admin_api_key')
ORDER BY default_setting_subcategory;
