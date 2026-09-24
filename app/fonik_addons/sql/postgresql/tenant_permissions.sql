-- Configure Fonik submenu permissions for one tenant and one standard group.
-- Change the values in the declaration block before executing.

BEGIN;

DO $tenant_permissions$
DECLARE
	target_domain_name text := 'CHANGE-ME.example.com';
	target_group_name text := 'admin';
	allow_click_to_call boolean := FALSE;
	allow_popup boolean := FALSE;
	allow_callback boolean := FALSE;
	allow_gateway_sync boolean := FALSE;
	target_domain_uuid uuid;
	target_group_uuid uuid;
	permission_names text[] := ARRAY[
		'fonik_click_to_call_manage',
		'fonik_popup_manage',
		'fonik_callback_manage',
		'fonik_gateway_sync'
	];
	permission_enabled boolean;
	i integer;
BEGIN
	IF target_domain_name = 'CHANGE-ME.example.com' THEN
		RAISE EXCEPTION 'Set target_domain_name before running this script';
	END IF;

	IF target_group_name NOT IN ('admin', 'superadmin') THEN
		RAISE EXCEPTION 'target_group_name must be admin or superadmin';
	END IF;

	SELECT domain_uuid INTO STRICT target_domain_uuid
	FROM v_domains
	WHERE domain_name = target_domain_name;

	SELECT group_uuid INTO STRICT target_group_uuid
	FROM v_groups
	WHERE domain_uuid IS NULL
		AND group_name = target_group_name;

	IF (
		SELECT COUNT(*) FROM v_permissions
		WHERE permission_name = ANY(permission_names)
	) <> 4 THEN
		RAISE EXCEPTION 'Fonik service permissions are missing; run install.sql first';
	END IF;

	FOR i IN 1..array_length(permission_names, 1) LOOP
		permission_enabled := CASE permission_names[i]
			WHEN 'fonik_click_to_call_manage' THEN allow_click_to_call
			WHEN 'fonik_popup_manage' THEN allow_popup
			WHEN 'fonik_callback_manage' THEN allow_callback
			WHEN 'fonik_gateway_sync' THEN allow_gateway_sync
			ELSE FALSE
		END;

		UPDATE v_group_permissions
		SET permission_assigned = CASE WHEN permission_enabled THEN 'true' ELSE 'false' END
		WHERE domain_uuid = target_domain_uuid
			AND group_uuid = target_group_uuid
			AND permission_name = permission_names[i];

		IF NOT FOUND THEN
			INSERT INTO v_group_permissions (
				group_permission_uuid,
				domain_uuid,
				permission_name,
				permission_protected,
				permission_assigned,
				group_name,
				group_uuid,
				insert_date
			) VALUES (
				md5('fonik-addons-domain-permission:' || target_domain_uuid::text || ':' || target_group_uuid::text || ':' || permission_names[i])::uuid,
				target_domain_uuid,
				permission_names[i],
				'false',
				CASE WHEN permission_enabled THEN 'true' ELSE 'false' END,
				target_group_name,
				target_group_uuid,
				CURRENT_TIMESTAMP
			);
		END IF;
	END LOOP;
END
$tenant_permissions$;

COMMIT;

SELECT
	d.domain_name,
	p.group_name,
	p.permission_name,
	p.permission_assigned
FROM v_group_permissions AS p
JOIN v_domains AS d ON d.domain_uuid = p.domain_uuid
WHERE p.permission_name IN (
	'fonik_click_to_call_manage',
	'fonik_popup_manage',
	'fonik_callback_manage',
	'fonik_gateway_sync'
)
ORDER BY d.domain_name, p.group_name, p.permission_name;
