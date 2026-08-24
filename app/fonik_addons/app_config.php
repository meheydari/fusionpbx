<?php

	//application details
		$apps[$x]['name'] = "Fonik Add-ons";
		$apps[$x]['uuid'] = "a8bc49c2-cf17-4a68-a796-3031a059ddbf";
		$apps[$x]['category'] = "Applications";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "";
		$apps[$x]['description']['en-us'] = "Manage the Fonik company and add-on services for the current tenant.";
		$apps[$x]['description']['en-gb'] = "Manage the Fonik company and add-on services for the current tenant.";
		$apps[$x]['description']['fa-ir'] = "مدیریت شرکت و سرویس‌های افزودنی Fonik برای tenant جاری.";

	//default settings
		$y = 0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "5b08111b-9974-4fac-91cf-f54e6657eb16";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "fonik_addons";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable Fonik Add-ons for the current tenant. Disabled by default.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "577348ee-42b5-42fd-b6f8-ce21d29fe398";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "fonik_addons";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "api_base_url";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "http://172.22.100.20:8000/api/call/";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Fonik API base URL, including /api/call/.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "ebeece37-c875-43e8-8667-68b9369f7467";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "fonik_addons";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "admin_api_key";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Value sent in the ADMIN-API-KEY header. Configure this as a protected setting.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "0aedf08b-07f0-4571-b86f-cf3798af7a78";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "fonik_addons";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "connect_timeout";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "5";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Connection timeout in seconds.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "6f9dc4d4-70c4-4e07-b2bd-1945c0f77092";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "fonik_addons";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "request_timeout";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "15";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Request timeout in seconds.";

	//permission details - the per-domain enabled setting is the tenant feature gate
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = "fonik_addons_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "929a8d95-1ddb-4400-9a12-6d94b461ec66";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_company_add";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_company_edit";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_click_to_call_manage";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_popup_manage";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_callback_manage";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "fonik_gateway_sync";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";

?>
