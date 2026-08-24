-- Enable or disable Fonik Add-ons for exactly one tenant.
-- Change only target_domain_name and target_enabled before executing.

BEGIN;

DO $tenant_toggle$
DECLARE
	target_domain_name text := 'CHANGE-ME.example.com';
	target_enabled boolean := TRUE;
	target_domain_uuid uuid;
BEGIN
	IF target_domain_name = 'CHANGE-ME.example.com' THEN
		RAISE EXCEPTION 'Set target_domain_name before running this script';
	END IF;

	SELECT domain_uuid
	INTO STRICT target_domain_uuid
	FROM v_domains
	WHERE domain_name = target_domain_name;

	UPDATE v_domain_settings
	SET domain_setting_name = 'boolean',
		domain_setting_value = CASE WHEN target_enabled THEN 'true' ELSE 'false' END,
		domain_setting_enabled = TRUE,
		domain_setting_description = 'Enable Fonik Add-ons for this tenant.',
		update_date = CURRENT_TIMESTAMP
	WHERE domain_uuid = target_domain_uuid
		AND domain_setting_category = 'fonik_addons'
		AND domain_setting_subcategory = 'enabled';

	IF NOT FOUND THEN
		INSERT INTO v_domain_settings (
			domain_uuid,
			domain_setting_uuid,
			app_uuid,
			domain_setting_category,
			domain_setting_subcategory,
			domain_setting_name,
			domain_setting_value,
			domain_setting_order,
			domain_setting_enabled,
			domain_setting_description,
			insert_date
		) VALUES (
			target_domain_uuid,
			md5('fonik-addons-domain-enabled:' || target_domain_uuid::text)::uuid,
			'a8bc49c2-cf17-4a68-a796-3031a059ddbf'::uuid,
			'fonik_addons',
			'enabled',
			'boolean',
			CASE WHEN target_enabled THEN 'true' ELSE 'false' END,
			10,
			TRUE,
			'Enable Fonik Add-ons for this tenant.',
			CURRENT_TIMESTAMP
		);
	END IF;
END
$tenant_toggle$;

COMMIT;

SELECT
	d.domain_name,
	s.domain_setting_value AS fonik_addons_enabled,
	s.domain_setting_enabled
FROM v_domain_settings AS s
JOIN v_domains AS d ON d.domain_uuid = s.domain_uuid
WHERE s.domain_setting_category = 'fonik_addons'
	AND s.domain_setting_subcategory = 'enabled'
ORDER BY d.domain_name;
