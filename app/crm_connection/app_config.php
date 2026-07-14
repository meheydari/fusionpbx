<?php

	//application details
		$apps[$x]['name'] = "CRM Connection";
		$apps[$x]['uuid'] = "0ef11c79-5c01-4957-89ff-8b5289897ac2";
		$apps[$x]['category'] = "Applications";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "";
		$apps[$x]['description']['en-us'] = "Create and update the tenant company in the CRM integration service.";
		$apps[$x]['description']['en-gb'] = "Create and update the tenant company in the CRM integration service.";

	//default settings
		$y = 0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "dc46e7db-42df-4123-8fde-8e9898b24816";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "crm_connection";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "api_url";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Company API URL, including /api/call/company/.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "6a164cdb-bec2-44e7-b650-575714777e27";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "crm_connection";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "admin_api_key";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Value sent in the ADMIN-API-KEY request header.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "b0589003-15c4-4bfe-b1aa-8130750c5c5c";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "crm_connection";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "connect_timeout";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "5";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Company API connection timeout in seconds.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "18555a9b-5053-4da8-9854-540b13ee1021";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "crm_connection";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "request_timeout";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "numeric";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "15";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Company API request timeout in seconds.";

	//permission details
		$y = 0;
		$apps[$x]['permissions'][$y]['name'] = "crm_connection_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "f83986a8-f7fc-4a1d-a6f6-1ff053fb480e";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "crm_connection_add";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "crm_connection_edit";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";

?>
