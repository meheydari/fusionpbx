<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('call_center_queue_add') || permission_exists('call_center_queue_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the defaults
	$queue_name = '';
	$queue_extension = '';
	$queue_time_base_score_sec = '';
	$queue_cid_prefix = '';
	$queue_announce_frequency = '';
	$queue_cc_exit_keys = '';
	$queue_description = '';
	$queue_timeout_action = '';

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$call_center_queue_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//initialize the destinations object
	$destination = new destinations;

//get total call center queues count from the database, check limit, if defined
	if ($action == 'add') {
		if (!empty($_SESSION['limit']['call_center_queues']['numeric'])) {
			$sql = "select count(*) from v_call_center_queues ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$database = new database;
			$total_call_center_queues = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

			if ($total_call_center_queues >= $_SESSION['limit']['call_center_queues']['numeric']) {
				message::add($text['message-maximum_queues'].' '.$_SESSION['limit']['call_center_queues']['numeric'], 'negative');
				header('Location: call_center_queues.php');
				return;
			}
		}
	}

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		//get the post variables a run a security chack on them
			//$domain_uuid = $_POST["domain_uuid"];
			$dialplan_uuid = $_POST["dialplan_uuid"] ?? null;
			$queue_name = $_POST["queue_name"];
			$queue_extension = $_POST["queue_extension"];
			$queue_greeting = $_POST["queue_greeting"];
			$queue_strategy = $_POST["queue_strategy"];
			$call_center_tiers = $_POST["call_center_tiers"];
			$queue_moh_sound = $_POST["queue_moh_sound"];
			$queue_record_template = $_POST["queue_record_template"];
			$queue_time_base_score = $_POST["queue_time_base_score"];
			$queue_time_base_score_sec = $_POST["queue_time_base_score_sec"];
			$queue_max_wait_time = $_POST["queue_max_wait_time"];
			$queue_max_wait_time_with_no_agent = $_POST["queue_max_wait_time_with_no_agent"];
			$queue_max_wait_time_with_no_agent_time_reached = $_POST["queue_max_wait_time_with_no_agent_time_reached"];
			$queue_tier_rules_apply = $_POST["queue_tier_rules_apply"];
			$queue_tier_rule_wait_second = $_POST["queue_tier_rule_wait_second"];
			$queue_tier_rule_wait_multiply_level = $_POST["queue_tier_rule_wait_multiply_level"];
			$queue_tier_rule_no_agent_no_wait = $_POST["queue_tier_rule_no_agent_no_wait"];
			$queue_timeout_action = $_POST["queue_timeout_action"] ?? null;
			$queue_discard_abandoned_after = $_POST["queue_discard_abandoned_after"];
			$queue_abandoned_resume_allowed = $_POST["queue_abandoned_resume_allowed"];
			$queue_cid_prefix = $_POST["queue_cid_prefix"];
			$queue_outbound_caller_id_name = $_POST["queue_outbound_caller_id_name"] ?? null;
			$queue_outbound_caller_id_number = $_POST["queue_outbound_caller_id_number"] ?? null;
			$queue_announce_position = $_POST["queue_announce_position"] ?? null;
			$queue_announce_sound = $_POST["queue_announce_sound"];
			$queue_announce_frequency = $_POST["queue_announce_frequency"];
			$queue_cc_exit_keys = $_POST["queue_cc_exit_keys"];
			$queue_email_address = $_POST["queue_email_address"] ?? null;
			$queue_description = $_POST["queue_description"];

		//remove invalid characters
			$queue_cid_prefix = str_replace(":", "-", $queue_cid_prefix);
			$queue_cid_prefix = str_replace("\"", "", $queue_cid_prefix);
			$queue_cid_prefix = str_replace("@", "", $queue_cid_prefix);
			$queue_cid_prefix = str_replace("\\", "", $queue_cid_prefix);
			$queue_cid_prefix = str_replace("/", "", $queue_cid_prefix);
	}

//delete the tier (agent from the queue)
	if (!empty($_REQUEST["a"]) && $_REQUEST["a"] == "delete" && is_uuid($_REQUEST["id"]) && permission_exists("call_center_tier_delete")) {
		//set the variables
			$call_center_queue_uuid = $_REQUEST["id"];
			$call_center_tier_uuid = $_REQUEST["call_center_tier_uuid"];

		//get the agent details
			$sql = "select t.call_center_agent_uuid, t.call_center_queue_uuid, q.queue_extension  ";
			$sql .= "from v_call_center_tiers as t, v_call_center_queues as q ";
			$sql .= "where t.domain_uuid = :domain_uuid  ";
			$sql .= "and t.call_center_tier_uuid = :call_center_tier_uuid ";
			$sql .= "and t.call_center_queue_uuid = q.call_center_queue_uuid; ";
			$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
			$parameters['call_center_tier_uuid'] = $call_center_tier_uuid;
			$database = new database;
			$tiers = $database->select($sql, $parameters, 'all');
			unset($sql, $parameters);

			if (!empty($tiers)) {
				foreach ($tiers as &$row) {
					$call_center_agent_uuid = $row["call_center_agent_uuid"];
					$call_center_queue_uuid = $row["call_center_queue_uuid"];
					$queue_extension = $row["queue_extension"];
				}
			}

		//delete the agent from freeswitch
			//setup the event socket connection
			$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
			//delete the agent over event socket
			if ($fp) {
				//callcenter_config tier del [queue_name] [agent_name]
				if (is_numeric($queue_extension) && is_uuid($call_center_agent_uuid)) {
					$cmd = "api callcenter_config tier del ".$queue_extension."@".$_SESSION['domain_name']." ".$call_center_agent_uuid;
					$response = event_socket_request($fp, $cmd);
				}
			}

		//delete the tier from the database
			if (!empty($call_center_tier_uuid)) {
				$array['call_center_tiers'][0]['call_center_tier_uuid'] = $call_center_tier_uuid;
				$array['call_center_tiers'][0]['domain_uuid'] = $_SESSION['domain_uuid'];

				$p = new permissions;
				$p->add('call_center_tier_delete', 'temp');

				$database = new database;
				$database->app_name = 'call_centers';
				$database->app_uuid = '95788e50-9500-079e-2807-fd530b0ea370';
				$database->delete($array);
				unset($array);

				$p->delete('call_center_tier_delete', 'temp');
			}
	}

//process the user data and save it to the database
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//get the uuid from the POST
			if ($action == "update") {
				$call_center_queue_uuid = $_POST["call_center_queue_uuid"];
			}
	
		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: call_center_queues.php');
				exit;
			}

		//check for all required data
			$msg = '';
			//if (empty($domain_uuid)) { $msg .= $text['message-required']."domain_uuid<br>\n"; }
			if (empty($queue_name)) { $msg .= $text['message-required'].$text['label-queue_name']."<br>\n"; }
			if (empty($queue_extension)) { $msg .= $text['message-required'].$text['label-extension']."<br>\n"; }
			if (empty($queue_strategy)) { $msg .= $text['message-required'].$text['label-strategy']."<br>\n"; }
			//if (empty($queue_moh_sound)) { $msg .= $text['message-required'].$text['label-music_on_hold']."<br>\n"; }
			//if (empty($queue_record_template)) { $msg .= $text['message-required'].$text['label-record_template']."<br>\n"; }
			//if (empty($queue_time_base_score)) { $msg .= $text['message-required'].$text['label-time_base_score']."<br>\n"; }
			//if (empty($queue_time_base_score_sec)) { $msg .= $text['message-required'].$text['label-time_base_score_sec']."<br>\n"; }
			//if (empty($queue_max_wait_time)) { $msg .= $text['message-required'].$text['label-max_wait_time']."<br>\n"; }
			//if (empty($queue_max_wait_time_with_no_agent)) { $msg .= $text['message-required'].$text['label-max_wait_time_with_no_agent']."<br>\n"; }
			//if (empty($queue_max_wait_time_with_no_agent_time_reached)) { $msg .= $text['message-required'].$text['label-max_wait_time_with_no_agent_time_reached']."<br>\n"; }
			//if (empty($queue_tier_rules_apply)) { $msg .= $text['message-required'].$text['label-tier_rules_apply']."<br>\n"; }
			//if (empty($queue_tier_rule_wait_second)) { $msg .= $text['message-required'].$text['label-tier_rule_wait_second']."<br>\n"; }
			//if (empty($queue_tier_rule_wait_multiply_level)) { $msg .= $text['message-required'].$text['label-tier_rule_wait_multiply_level']."<br>\n"; }
			//if (empty($queue_tier_rule_no_agent_no_wait)) { $msg .= $text['message-required'].$text['label-tier_rule_no_agent_no_wait']."<br>\n"; }
			//if (empty($queue_timeout_action)) { $msg .= $text['message-required'].$text['label-timeout_action']."<br>\n"; }
			//if (empty($queue_discard_abandoned_after)) { $msg .= $text['message-required'].$text['label-discard_abandoned_after']."<br>\n"; }
			//if (empty($queue_abandoned_resume_allowed)) { $msg .= $text['message-required'].$text['label-abandoned_resume_allowed']."<br>\n"; }
			//if (empty($queue_cid_prefix)) { $msg .= $text['message-required'].$text['label-caller_id_name_prefix']."<br>\n"; }
			//if (empty($queue_description)) { $msg .= $text['message-required'].$text['label-description']."<br>\n"; }
			if (!empty($msg) && empty($_POST["persistformvar"])) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//set the domain_uuid
			$_POST["domain_uuid"] = $_SESSION["domain_uuid"];

		//add the call_center_queue_uuid
			if (empty($_POST["call_center_queue_uuid"])) {
				$call_center_queue_uuid = uuid();
				$_POST["call_center_queue_uuid"] = $call_center_queue_uuid;
			}

		//add the dialplan_uuid
			if (empty($_POST["dialplan_uuid"])) {
				$dialplan_uuid = uuid();
				$_POST["dialplan_uuid"] = $dialplan_uuid;
			}

		//update the call centier tiers array
			$x = 0;
			if (!empty($_POST["call_center_tiers"])) {
				foreach ($_POST["call_center_tiers"] as $row) {
					//add the domain_uuid
						if (empty($row["domain_uuid"])) {
							$_POST["call_center_tiers"][$x]["domain_uuid"] = $_SESSION['domain_uuid'];
						}
					//unset ring_group_destination_uuid if the field has no value
						if (empty($row["call_center_agent_uuid"])) {
							unset($_POST["call_center_tiers"][$x]);
						}
					//increment the row
						$x++;
				}
			}

		//get the application and data
			$action_array = explode(":",$queue_timeout_action);
			$queue_timeout_app = $action_array[0];
			unset($action_array[0]);
			$queue_timeout_data = implode($action_array);

		//add the recording path if needed
			if (!empty($queue_greeting)) {
				if (file_exists($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$queue_greeting)) {
					$queue_greeting_path = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$queue_greeting;
				}
				else {
					$queue_greeting_path = trim($queue_greeting);
				}
			}

		//prepare the array
			$array['call_center_queues'][0]['queue_name'] = $queue_name;
			$array['call_center_queues'][0]['queue_extension'] = $queue_extension;
			$array['call_center_queues'][0]['queue_greeting'] = $queue_greeting;
			$array['call_center_queues'][0]['queue_strategy'] = $queue_strategy;
			$array['call_center_queues'][0]['queue_moh_sound'] = $queue_moh_sound;
			$array['call_center_queues'][0]['queue_record_template'] = $queue_record_template;
			$array['call_center_queues'][0]['queue_time_base_score'] = $queue_time_base_score;
			$array['call_center_queues'][0]['queue_time_base_score_sec'] = $queue_time_base_score_sec;
			$array['call_center_queues'][0]['queue_max_wait_time'] = $queue_max_wait_time;
			$array['call_center_queues'][0]['queue_max_wait_time_with_no_agent'] = $queue_max_wait_time_with_no_agent;
			$array['call_center_queues'][0]['queue_max_wait_time_with_no_agent_time_reached'] = $queue_max_wait_time_with_no_agent_time_reached;
			if ($destination->valid($queue_timeout_action)) {
				$array['call_center_queues'][0]['queue_timeout_action'] = $queue_timeout_action;
			}
			$array['call_center_queues'][0]['queue_tier_rules_apply'] = $queue_tier_rules_apply;
			$array['call_center_queues'][0]['queue_tier_rule_wait_second'] = $queue_tier_rule_wait_second;
			$array['call_center_queues'][0]['queue_tier_rule_wait_multiply_level'] = $queue_tier_rule_wait_multiply_level;
			$array['call_center_queues'][0]['queue_tier_rule_no_agent_no_wait'] = $queue_tier_rule_no_agent_no_wait;
			$array['call_center_queues'][0]['queue_discard_abandoned_after'] = $queue_discard_abandoned_after;
			$array['call_center_queues'][0]['queue_abandoned_resume_allowed'] = $queue_abandoned_resume_allowed;
			$array['call_center_queues'][0]['queue_cid_prefix'] = $queue_cid_prefix;
			if (permission_exists('call_center_outbound_caller_id_name')) {
				$array['call_center_queues'][0]['queue_outbound_caller_id_name'] = $queue_outbound_caller_id_name;
			}
			if (permission_exists('call_center_outbound_caller_id_number')) {
				$array['call_center_queues'][0]['queue_outbound_caller_id_number'] = $queue_outbound_caller_id_number;
			}
			$array['call_center_queues'][0]['queue_announce_position'] = $queue_announce_position;
			if (permission_exists('call_center_announce_sound')) {
				$array['call_center_queues'][0]['queue_announce_sound'] = $queue_announce_sound;
			}
			$array['call_center_queues'][0]['queue_announce_frequency'] = $queue_announce_frequency;
			$array['call_center_queues'][0]['queue_cc_exit_keys'] = $queue_cc_exit_keys;
			if (permission_exists('call_center_email_address')) {
				$array['call_center_queues'][0]['queue_email_address'] = $queue_email_address;
			}
			$array['call_center_queues'][0]['queue_description'] = $queue_description;
			$array['call_center_queues'][0]['call_center_queue_uuid'] = $call_center_queue_uuid;
			$array['call_center_queues'][0]['dialplan_uuid'] = $dialplan_uuid;
			$array['call_center_queues'][0]['domain_uuid'] = $domain_uuid;
			
			$y = 0;
			if (!empty($_POST["call_center_tiers"])) {
				foreach ($_POST["call_center_tiers"] as $row) {
					if (is_uuid($row['call_center_tier_uuid'])) {
						$call_center_tier_uuid = $row['call_center_tier_uuid'];
					}
					else {
						$call_center_tier_uuid = uuid();
					}
					if (!empty($row['call_center_agent_uuid'])) {
						$array["call_center_queues"][0]["call_center_tiers"][$y]["call_center_tier_uuid"] = $call_center_tier_uuid;
						$array['call_center_queues'][0]["call_center_tiers"][$y]["call_center_agent_uuid"] = $row['call_center_agent_uuid'];
						$array['call_center_queues'][0]["call_center_tiers"][$y]["tier_level"] = $row['tier_level'];
						$array['call_center_queues'][0]["call_center_tiers"][$y]["tier_position"] = $row['tier_position'];
						$array['call_center_queues'][0]["call_center_tiers"][$y]["domain_uuid"] = $_SESSION['domain_uuid'];
					}
					$y++;
				}
			}

		//add definable export variables can be set in default settings
			$export_variables = 'call_center_queue_uuid,sip_h_Alert-Info';
			if (!empty($_SESSION['call_center']['export_vars'])) {
				foreach ($_SESSION['call_center']['export_vars'] as $export_variable) {
				    $export_variables .= ','.$export_variable;
				}
			}

		//build the xml dialplan
			$dialplan_xml = "<extension name=\"".xml::sanitize($queue_name)."\" continue=\"\" uuid=\"".xml::sanitize($dialplan_uuid)."\">\n";
			$dialplan_xml .= "	<condition field=\"destination_number\" expression=\"^([^#]+#)(.*)\$\" break=\"never\">\n";
			$dialplan_xml .= "		<action application=\"set\" data=\"caller_id_name=\$2\"/>\n";
			$dialplan_xml .= "	</condition>\n";
			$dialplan_xml .= "	<condition field=\"destination_number\" expression=\"^(callcenter\+)?".xml::sanitize($queue_extension)."$\">\n";
			$dialplan_xml .= "		<action application=\"answer\" data=\"\"/>\n";
			if (!empty($call_center_queue_uuid) && is_uuid($call_center_queue_uuid)) {
				$dialplan_xml .= "		<action application=\"set\" data=\"call_center_queue_uuid=".xml::sanitize($call_center_queue_uuid)."\"/>\n";
			}
			if (!empty($queue_extension) && is_numeric($queue_extension)) {
				$dialplan_xml .= "		<action application=\"set\" data=\"queue_extension=".xml::sanitize($queue_extension)."\"/>\n";
			}
			$dialplan_xml .= "		<action application=\"set\" data=\"cc_export_vars=\${cc_export_vars},".$export_variables."\"/>\n";
			$dialplan_xml .= "		<action application=\"set\" data=\"hangup_after_bridge=true\"/>\n";
			if (!empty($queue_time_base_score_sec)) {
				$dialplan_xml .= "		<action application=\"set\" data=\"cc_base_score=".xml::sanitize($queue_time_base_score_sec)."\"/>\n";
			}
			if (!empty($queue_greeting_path)) {
				$dialplan_xml .= "		<action application=\"sleep\" data=\"1000\"/>\n";
				$greeting_array = explode(':', $queue_greeting_path);
				if (count($greeting_array) == 1) {
					$dialplan_xml .= "		<action application=\"playback\" data=\"".xml::sanitize($queue_greeting_path)."\"/>\n";
				}
				else {
					if ($greeting_array[0] == 'say' || $greeting_array[0] == 'tone_stream' || $greeting_array[0] == 'phrase') {
						$dialplan_xml .= "		<action application=\"".xml::sanitize($greeting_array[0])."\" data=\"".xml::sanitize($greeting_array[1])."\"/>\n";
					}
				}
			}
			if (!empty($queue_cid_prefix)) {
				$dialplan_xml .= "		<action application=\"set\" data=\"effective_caller_id_name=".xml::sanitize($queue_cid_prefix)."#\${caller_id_name}\"/>\n";
			}
			if (!empty($queue_cc_exit_keys)) {
				$dialplan_xml .= "		<action application=\"set\" data=\"cc_exit_keys=".xml::sanitize($queue_cc_exit_keys)."\"/>\n";
			}
			$dialplan_xml .= "		<action application=\"callcenter\" data=\"".xml::sanitize($queue_extension)."@".$_SESSION["domain_name"]."\"/>\n";
			if ($destination->valid($queue_timeout_app.':'.$queue_timeout_data)) {
				$dialplan_xml .= "		<action application=\"".xml::sanitize($queue_timeout_app)."\" data=\"".xml::sanitize($queue_timeout_data)."\"/>\n";
			}
			$dialplan_xml .= "	</condition>\n";
			$dialplan_xml .= "</extension>\n";

		//build the dialplan array
			$array['dialplans'][0]["domain_uuid"] = $_SESSION['domain_uuid'];
			$array['dialplans'][0]["dialplan_uuid"] = $dialplan_uuid;
			$array['dialplans'][0]["dialplan_name"] = $queue_name;
			$array['dialplans'][0]["dialplan_number"] = $queue_extension;
			$array['dialplans'][0]["dialplan_context"] = $_SESSION['domain_name'];
			$array['dialplans'][0]["dialplan_continue"] = "false";
			$array['dialplans'][0]["dialplan_xml"] = $dialplan_xml;
			$array['dialplans'][0]["dialplan_order"] = "230";
			$array['dialplans'][0]["dialplan_enabled"] = "true";
			$array['dialplans'][0]["dialplan_description"] = $queue_description;
			$array['dialplans'][0]["app_uuid"] = "95788e50-9500-079e-2807-fd530b0ea370";

		//add the dialplan permission
			$p = new permissions;
			$p->add("dialplan_add", "temp");
			$p->add("dialplan_edit", "temp");

		//save to the data
			$database = new database;
			$database->app_name = 'call_centers';
			$database->app_uuid = '95788e50-9500-079e-2807-fd530b0ea370';
			$database->save($array);
			$message = $database->message;

		//remove the temporary permission
			$p->delete("dialplan_add", "temp");
			$p->delete("dialplan_edit", "temp");

		//debug info
			//echo "<pre>". print_r($message, true) ."</pre>"; exit;

		//apply settings reminder
			$_SESSION["reload_xml"] = true;

		//clear the cache
			$cache = new cache;
			$cache->delete("dialplan:".$_SESSION["domain_name"]);

		//clear the destinations session array
			if (isset($_SESSION['destinations']['array'])) {
				unset($_SESSION['destinations']['array']);
			}

		//redirect the user
			if (isset($action)) {
				if ($action == "add") {
					message::add($text['message-add']);
				}
				if ($action == "update") {
					message::add($text['message-update']);
				}
			}

		//synchronize the configuration
			save_call_center_xml();
			remove_config_from_cache('configuration:callcenter.conf');

		//add agent/tier to queue
			$agent_name = $_POST["agent_name"] ?? null;
			$tier_level = $_POST["tier_level"] ?? null;
			$tier_position = $_POST["tier_position"] ?? null;

			if (!empty($agent_name)) {
				//setup the event socket connection
					$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
				//add the agent using event socket
					if ($fp) {
						/* syntax:
							callcenter_config tier add [queue_name] [agent_name] [level] [position]
							callcenter_config tier set state [queue_name] [agent_name] [state]
							callcenter_config tier set level [queue_name] [agent_name] [level]
							callcenter_config tier set position [queue_name] [agent_name] [position]
						*/
						//add the agent
						if (is_numeric($queue_extension) && is_uuid($call_center_agent_uuid) && is_numeric($tier_level) && is_numeric($tier_position)) {
							$cmd = "api callcenter_config tier add ".$queue_extension."@".$_SESSION["domain_name"]." ".$call_center_agent_uuid." ".$tier_level." ".$tier_position;
							$response = event_socket_request($fp, $cmd);
						}
						usleep(200);
						//agent set level
						if (is_numeric($queue_extension) && is_numeric($tier_level)) {
							$cmd = "api callcenter_config tier set level ".$queue_extension."@".$_SESSION["domain_name"]." ".$call_center_agent_uuid." ".$tier_level;
							$response = event_socket_request($fp, $cmd);
						}
						usleep(200);
						//agent set position
						if (is_numeric($queue_extension) && is_numeric($tier_position)) {
							$cmd = "api callcenter_config tier set position ".$queue_extension."@".$_SESSION["domain_name"]." ".$tier_position;
							$response = event_socket_request($fp, $cmd);
						}
						usleep(200);
					}
			}

		//syncrhonize configuration
			save_call_center_xml();

		//clear the cache
			$cache = new cache;
			$cache->delete('configuration:callcenter.conf');

		//redirect the user
			if (is_uuid($call_center_queue_uuid)) {
				header("Location: call_center_queue_edit.php?id=".urlencode($call_center_queue_uuid));
			}
			return;

	} //(count($_POST)>0 && empty($_POST["persistformvar"]))

//pre-populate the form
	if (!empty($_GET) && is_uuid($_GET["id"]) && empty($_POST["persistformvar"])) {
		$call_center_queue_uuid = $_GET["id"];
		$sql = "select * from v_call_center_queues ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and call_center_queue_uuid = :call_center_queue_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['call_center_queue_uuid'] = $call_center_queue_uuid;
		$database = new database;
		$call_center_queues = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);

		if (!empty($call_center_queues)) {
			foreach ($call_center_queues as &$row) {
				$queue_name = $row["queue_name"];
				$dialplan_uuid = $row["dialplan_uuid"];
				$database_queue_name = $row["queue_name"];
				$queue_extension = $row["queue_extension"];
				$queue_greeting = $row["queue_greeting"];
				$queue_strategy = $row["queue_strategy"];
				$queue_moh_sound = $row["queue_moh_sound"];
				$queue_record_template = $row["queue_record_template"];
				$queue_time_base_score = $row["queue_time_base_score"];
				$queue_time_base_score_sec = $row["queue_time_base_score_sec"];
				$queue_max_wait_time = $row["queue_max_wait_time"];
				$queue_max_wait_time_with_no_agent = $row["queue_max_wait_time_with_no_agent"];
				$queue_max_wait_time_with_no_agent_time_reached = $row["queue_max_wait_time_with_no_agent_time_reached"];
				$queue_timeout_action = $row["queue_timeout_action"];
				$queue_tier_rules_apply = $row["queue_tier_rules_apply"];
				$queue_tier_rule_wait_second = $row["queue_tier_rule_wait_second"];
				$queue_tier_rule_wait_multiply_level = $row["queue_tier_rule_wait_multiply_level"];
				$queue_tier_rule_no_agent_no_wait = $row["queue_tier_rule_no_agent_no_wait"];
				$queue_discard_abandoned_after = $row["queue_discard_abandoned_after"];
				$queue_abandoned_resume_allowed = $row["queue_abandoned_resume_allowed"];
				$queue_cid_prefix = $row["queue_cid_prefix"];
				$queue_outbound_caller_id_name = $row["queue_outbound_caller_id_name"];
				$queue_outbound_caller_id_number = $row["queue_outbound_caller_id_number"];
				$queue_announce_position = $row["queue_announce_position"];
				$queue_announce_sound = $row["queue_announce_sound"];
				$queue_announce_frequency = $row["queue_announce_frequency"];
				$queue_cc_exit_keys = $row["queue_cc_exit_keys"];
				$queue_email_address = $row["queue_email_address"];
				$queue_description = $row["queue_description"];
			}
		}
	}

//get the tiers
	$sql = "select t.call_center_tier_uuid, t.call_center_agent_uuid, t.call_center_queue_uuid, t.tier_level, t.tier_position, a.agent_name ";
	$sql .= "from v_call_center_tiers as t, v_call_center_agents as a ";
	$sql .= "where t.call_center_queue_uuid = :call_center_queue_uuid ";
	$sql .= "and t.call_center_agent_uuid = a.call_center_agent_uuid ";
	$sql .= "and t.domain_uuid = :domain_uuid ";
	$sql .= "order by tier_level asc, tier_position asc, a.agent_name asc";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$parameters['call_center_queue_uuid'] = $call_center_queue_uuid ?? null;
	$database = new database;
	$tiers = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//add an empty row to the tiers array
	if (count($tiers) == 0) {
		$rows = $_SESSION['call_center']['agent_add_rows']['numeric'] ?? null;
		$id = 0;
	}
	if (count($tiers) > 0) {
		$rows = $_SESSION['call_center']['agent_edit_rows']['numeric'];
		$id = count($tiers)+1;
	}
	for ($x = 0; $x < $rows; $x++) {
		$tiers[$id]['call_center_tier_uuid'] = uuid();
		$tiers[$id]['call_center_agent_uuid'] = '';
		$tiers[$id]['call_center_queue_uuid'] = $call_center_queue_uuid ?? null;
		$tiers[$id]['tier_level'] = '';
		$tiers[$id]['tier_position'] = '';
		$tiers[$id]['agent_name'] = '';
		$id++;
	}

//get the agents
	$sql = "select call_center_agent_uuid, agent_name from v_call_center_agents ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by agent_name asc";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$agents = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the sounds
	$sounds = new sounds;
	$sounds = $sounds->get();

//set default values
	if (empty($queue_strategy)) { $queue_strategy = "longest-idle-agent"; }
	if (empty($queue_moh_sound)) { $queue_moh_sound = "\$\${hold_music}"; }
	if (empty($queue_time_base_score)) { $queue_time_base_score = "system"; }
	if (empty($queue_time_base_score)) { $queue_time_base_score = ""; }
	if (empty($queue_max_wait_time)) { $queue_max_wait_time = "0"; }
	if (empty($queue_max_wait_time_with_no_agent)) { $queue_max_wait_time_with_no_agent = "90"; }
	if (empty($queue_max_wait_time_with_no_agent_time_reached)) { $queue_max_wait_time_with_no_agent_time_reached = "30"; }
	if (empty($queue_tier_rules_apply)) { $queue_tier_rules_apply = "false"; }
	if (empty($queue_tier_rule_wait_second)) { $queue_tier_rule_wait_second = "30"; }
	if (empty($queue_tier_rule_wait_multiply_level)) { $queue_tier_rule_wait_multiply_level = "true"; }
	if (empty($queue_tier_rule_no_agent_no_wait)) { $queue_tier_rule_no_agent_no_wait = "true"; }
	if (empty($queue_discard_abandoned_after)) { $queue_discard_abandoned_after = "900"; }
	if (empty($queue_abandoned_resume_allowed)) { $queue_abandoned_resume_allowed = "false"; }

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//get the recordings
	$sql = "select recording_name, recording_filename from v_recordings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by recording_name asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the phrases
	$sql = "select * from v_phrases ";
	$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$phrases = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//show the header
	if ($action == "add") {
		$document['title'] = $text['title-call_center_queue_add'];
	}
	if ($action == "update") {
		$document['title'] = $text['title-call_center_queue_edit'];
	}
	require_once "resources/header.php";

//only allow a uuid
	if (empty($call_center_queue_uuid)) {
		$call_center_queue_uuid = null;
	}

//option to change select to text
	if (if_group("superadmin")) {
		echo "<script>\n";
		echo "var Objs;\n";
		echo "\n";
		echo "function changeToInput(obj){\n";
		echo "	tb=document.createElement('INPUT');\n";
		echo "	tb.type='text';\n";
		echo "	tb.name=obj.name;\n";
		echo "	tb.setAttribute('class', 'formfld');\n";
		//echo "	tb.setAttribute('style', 'width: 380px;');\n";
		echo "	tb.value=obj.options[obj.selectedIndex].value;\n";
		echo "	tbb=document.createElement('INPUT');\n";
		echo "	tbb.setAttribute('class', 'btn');\n";
		echo "	tbb.setAttribute('style', 'margin-left: 4px;');\n";
		echo "	tbb.type='button';\n";
		echo "	tbb.value=$('<div />').html('&#9665;').text();\n";
		echo "	tbb.objs=[obj,tb,tbb];\n";
		echo "	tbb.onclick=function(){ Replace(this.objs); }\n";
		echo "	obj.parentNode.insertBefore(tb,obj);\n";
		echo "	obj.parentNode.insertBefore(tbb,obj);\n";
		echo "	obj.parentNode.removeChild(obj);\n";
		echo "}\n";
		echo "\n";
		echo "function Replace(obj){\n";
		echo "	obj[2].parentNode.insertBefore(obj[0],obj[2]);\n";
		echo "	obj[0].parentNode.removeChild(obj[1]);\n";
		echo "	obj[0].parentNode.removeChild(obj[2]);\n";
		echo "}\n";
		echo "</script>\n";
		echo "\n";
	}

//show the content
	echo "<form name='frm' id='frm' method='post'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['header-call_center_queue_add']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['header-call_center_queue_edit']."</b>";
	}
	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','style'=>'margin-right: 15px;','link'=>'call_center_queues.php']);

	if ($action == "update") {
		if (permission_exists('call_center_wallboard')) {
			echo button::create(['type'=>'button','label'=>$text['button-wallboard'],'icon'=>'th','link'=>PROJECT_PATH.'/app/call_center_wallboard/call_center_wallboard.php?queue_name='.urlencode($call_center_queue_uuid)]);
		}
		//echo button::create(['type'=>'button','label'=>$text['button-stop'],'icon'=>$_SESSION['theme']['button_icon_stop'],'link'=>'cmd.php?cmd=unload&id='.urlencode($call_center_queue_uuid)]);
		//echo button::create(['type'=>'button','label'=>$text['button-start'],'icon'=>$_SESSION['theme']['button_icon_start'],'link'=>'cmd.php?cmd=load&id='.urlencode($call_center_queue_uuid)]);
		echo button::create(['type'=>'button','label'=>$text['button-reload'],'icon'=>$_SESSION['theme']['button_icon_reload'],'link'=>'cmd.php?cmd=reload&id='.urlencode($call_center_queue_uuid)]);
		echo button::create(['type'=>'button','label'=>$text['button-view'],'icon'=>$_SESSION['theme']['button_icon_view'],'style'=>'margin-right: 15px;','link'=>PROJECT_PATH.'/app/call_center_active/call_center_active.php?queue_name='.urlencode($call_center_queue_uuid)]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-queue_name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='queue_name' maxlength='255' value=\"".escape($queue_name)."\" required='required'>\n";
	echo "<br />\n";
	echo $text['description-queue_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-extension']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='number' name='queue_extension' maxlength='255' min='0' step='1' value=\"".escape($queue_extension)."\" required='required'>\n";
	echo "<br />\n";
	echo $text['description-extension']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-greeting']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "<select name='queue_greeting' class='formfld' style='width: 200px;' ".((if_group("superadmin")) ? "onchange='changeToInput(this);'" : null).">\n";
	echo "	<option value=''></option>\n";
	foreach($sounds as $key => $value) {
		echo "<optgroup label=".$text['label-'.$key].">\n";
		$selected = false;
		foreach($value as $row) {
			if (!empty($queue_greeting) && $queue_greeting == $row["value"]) {
				$selected = true;
				echo "	<option value='".escape($row["value"])."' selected='selected'>".escape($row["name"])."</option>\n";
			}
			else {
				echo "	<option value='".escape($row["value"])."'>".escape($row["name"])."</option>\n";
			}
		}
		echo "</optgroup>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-greeting']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-strategy']."\n";
echo '<svg data-toggle="tooltip" data-placement="top" data-html="true"
 title="Agent With Least Talk Time: ارسال تماس به اپراتوری که کمترین زمان مکالمه را داشته است
 <br>
Agent With Fewest Calls: ارسال تماس به اپراتوری که کمترین تعداد تماس را داشته است
<br>
Longest Idle Agent: ارسال تماس به اپراتوری که مدت طولانی تری نسبت به سطح گروه اپراتوریش بیکار بوده است
<br> 
Ring All: ارسال تماس به همه اپراتورها بصورت همزمان
<br>
Random: ارسال تماس به اپراتورها بصورت کاملا رندوم
<br>
Ring Progressively: ارسال تماس به اپراتورها بترتیب از اپراتور اول و هر بار اپراتور قبل هم با اپراتور جدید زنگ میخورد، بطوریکه سری آخر همه با هم زنگ میخورند
<br> 
Round Robin: ارسال تماس به اپراتور ها به ترتیب و بصورت چرخشی
<br>
Sequentially By Agent Order: ارسال تماس به اپراتورها به ترتیب و اولویت گروه اپراتوری
<br> 
Top Down: ارسال تماس به اپراتورها به ترتیب و هر بار از اپراتور اول" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M10 0C11.9778 0 13.9112 0.58649 15.5557 1.6853C17.2002 2.78412 18.4819 4.3459 19.2388 6.17317C19.9957 8.00043 20.1937 10.0111 19.8078 11.9509C19.422 13.8907 18.4696 15.6725 17.0711 17.0711C15.6725 18.4696 13.8907 19.422 11.9509 19.8079C10.0111 20.1937 8.00042 19.9957 6.17316 19.2388C4.3459 18.4819 2.78411 17.2002 1.6853 15.5557C0.586487 13.9112 -3.8147e-06 11.9778 -3.8147e-06 10C-3.8147e-06 8.68678 0.258654 7.38642 0.7612 6.17317C1.26375 4.95991 2.00034 3.85752 2.92893 2.92893C3.85751 2.00035 4.9599 1.26375 6.17316 0.761205C7.38642 0.258658 8.68678 0 10 0ZM10 15.59C10.1483 15.59 10.2933 15.546 10.4167 15.4636C10.54 15.3812 10.6361 15.2641 10.6929 15.127C10.7497 14.99 10.7645 14.8392 10.7356 14.6937C10.7066 14.5482 10.6352 14.4146 10.5303 14.3097C10.4254 14.2048 10.2918 14.1333 10.1463 14.1044C10.0008 14.0755 9.85003 14.0903 9.71298 14.1471C9.57594 14.2039 9.4588 14.3 9.37639 14.4233C9.29398 14.5467 9.25 14.6917 9.25 14.84C9.25259 15.0381 9.33243 15.2274 9.47253 15.3675C9.61262 15.5076 9.80189 15.5874 10 15.59ZM8.64 10.36C8.82955 10.4632 8.98686 10.6169 9.09452 10.804C9.20218 10.991 9.25599 11.2042 9.25 11.42V12.24C9.25 12.4389 9.32901 12.6297 9.46967 12.7703C9.61032 12.911 9.80108 12.99 10 12.99C10.1989 12.99 10.3897 12.911 10.5303 12.7703C10.671 12.6297 10.75 12.4389 10.75 12.24V11.42C10.7574 10.9204 10.6246 10.4288 10.3665 10.001C10.1084 9.57317 9.7354 9.22637 9.29 9C9.02801 8.87322 8.80475 8.67861 8.6434 8.43639C8.48205 8.19416 8.3885 7.91314 8.37247 7.62254C8.35644 7.33193 8.41851 7.04234 8.55224 6.78383C8.68597 6.52532 8.88647 6.30734 9.13292 6.15251C9.37937 5.99769 9.66278 5.91168 9.95371 5.90342C10.2446 5.89515 10.5325 5.96493 10.7873 6.10552C11.0422 6.2461 11.2547 6.45235 11.4029 6.70285C11.5511 6.95335 11.6295 7.23895 11.63 7.53C11.63 7.72891 11.709 7.91968 11.8497 8.06033C11.9903 8.20098 12.1811 8.28 12.38 8.28C12.5789 8.28 12.7697 8.20098 12.9103 8.06033C13.051 7.91968 13.13 7.72891 13.13 7.53C13.1283 7.07205 13.0265 6.62 12.8317 6.20556C12.6368 5.79111 12.3537 5.4243 12.0021 5.13084C11.6505 4.83739 11.239 4.62438 10.7964 4.50677C10.3538 4.38916 9.89087 4.36978 9.43999 4.45C8.81051 4.56842 8.23208 4.87593 7.78184 5.33153C7.3316 5.78713 7.03096 6.36915 6.92 7C6.79857 7.67117 6.90116 8.36364 7.21196 8.97078C7.52275 9.57792 8.02452 10.066 8.64 10.36Z" fill="#AC965E"/>
</svg>';
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_strategy'>\n";
	if ($queue_strategy == "ring-all") {
		echo "	<option value='ring-all' selected='selected' >".$text['option-ring_all']."</option>\n";
	}
	else {
		echo "	<option value='ring-all'>".$text['option-ring_all']."</option>\n";
	}
	if ($queue_strategy == "longest-idle-agent") {
		echo "	<option value='longest-idle-agent' selected='selected' >".$text['option-longest_idle_agent']."</option>\n";
	}
	else {
		echo "	<option value='longest-idle-agent'>".$text['option-longest_idle_agent']."</option>\n";
	}
	if ($queue_strategy == "round-robin") {
		echo "	<option value='round-robin' selected='selected'>".$text['option-round_robin']."</option>\n";
	}
	else {
		echo "	<option value='round-robin'>".$text['option-round_robin']."</option>\n";
	}
	if ($queue_strategy == "top-down") {
		echo "	<option value='top-down' selected='selected'>".$text['option-top_down']."</option>\n";
	}
	else {
		echo "	<option value='top-down'>".$text['option-top_down']."</option>\n";
	}
	if ($queue_strategy == "agent-with-least-talk-time") {
		echo "	<option value='agent-with-least-talk-time' selected='selected'>".$text['option-agent_with_least_talk_time']."</option>\n";
	}
	else {
		echo "	<option value='agent-with-least-talk-time'>".$text['option-agent_with_least_talk_time']."</option>\n";
	}

	if ($queue_strategy == "agent-with-fewest-calls") {
		echo "	<option value='agent-with-fewest-calls' selected='selected'>".$text['option-agent_with_fewest_calls']."</option>\n";
	}
	else {
		echo "	<option value='agent-with-fewest-calls'>".$text['option-agent_with_fewest_calls']."</option>\n";
	}
	if ($queue_strategy == "sequentially-by-agent-order") {
		echo "	<option value='sequentially-by-agent-order' selected='selected'>".$text['option-sequentially_by_agent_order']."</option>\n";
	}
	else {
		echo "	<option value='sequentially-by-agent-order'>".$text['option-sequentially_by_agent_order']."</option>\n";
	}
	if ($queue_strategy == "sequentially-by-next-agent-order") {
		echo "	<option value='sequentially-by-next-agent-order' selected='selected'>".$text['option-sequentially_by_next_agent_order']."</option>\n";
	}
	else {
		echo "	<option value='sequentially-by-next-agent-order'>".$text['option-sequentially_by_next_agent_order']."</option>\n";
	}
	if ($queue_strategy == "random") {
		echo "	<option value='random' selected='selected'>".$text['option-random']."</option>\n";
	}
	else {
		echo "	<option value='random'>".$text['option-random']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-strategy']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('call_center_tier_view') && !empty($agents) && is_array($agents)) {
		echo "<tr>";
		echo "	<td class='vncell' valign='top'>".$text['label-agents']."</td>";
		echo "	<td class='vtable' align='left'>";
		echo "			<table border='0' cellpadding='0' cellspacing='0'>\n";
		echo "			<tr>\n";
		echo "				<td class='vtable'>".$text['label-agent_name']."</td>\n";
		echo "				<td class='vtable' style='text-align: center;'>".$text['label-tier_level']."</td>\n";
		echo "				<td class='vtable' style='text-align: center;'>".$text['label-tier_position']."</td>\n";
		echo "				<td></td>\n";
		echo "			</tr>\n";
		$x = 0;
		if (is_array($tiers)) {
			foreach($tiers as $field) {
				echo "	<tr>\n";
				echo "		<td class=''>";
				if (!empty($field['call_center_tier_uuid'])) {
					echo "				<input name='call_center_tiers[".$x."][call_center_tier_uuid]' type='hidden' value=\"".escape($field['call_center_tier_uuid'])."\">\n";
				}
				echo "				<select name=\"call_center_tiers[$x][call_center_agent_uuid]\" class=\"formfld\" style=\"width: 200px\">\n";
				if (is_uuid($field['call_center_agent_uuid'])) {
					echo "				<option value=\"".escape($field['call_center_agent_uuid'])."\">".escape($field['agent_name'])."</option>\n";
				}
				else {
					echo "					<option value=\"\"></option>\n";
					foreach($agents as $row) {
						echo "				<option value=\"".escape($row['call_center_agent_uuid'])."\">".escape($row['agent_name'])."</option>\n";
					}
				}
				echo "				</select>";
				echo "		</td>\n";
				echo "		<td class='' style='text-align: center;'>";
				echo "				 <select name=\"call_center_tiers[$x][tier_level]\" class=\"formfld\">\n";
				$i=0;
				while($i<=9) {
					$selected = ($i == $field['tier_level']) ? "selected" : null;
					echo "				<option value=\"$i\" ".escape($selected).">$i</option>\n";
					$i++;
				}
				echo "				</select>\n";
				echo "		</td>\n";

				echo "		<td class='' style='text-align: center;'>\n";
				echo "				<select name=\"call_center_tiers[$x][tier_position]\" class=\"formfld\">\n";
				$i=0;
				while($i<=9) {
					$selected = ($i == $field['tier_position']) ? "selected" : null;
					echo "				<option value=\"$i\" ".escape($selected).">$i</option>\n";
					$i++;
				}
				echo "				</select>\n";
				echo "		</td>\n";
				echo "		<td class=''>";
				if (permission_exists('call_center_tier_delete')) {
					echo "			<a href=\"call_center_queue_edit.php?id=".escape($call_center_queue_uuid)."&call_center_tier_uuid=".escape($field['call_center_tier_uuid'])."&a=delete\" alt=\"".$text['button-delete']."\" onclick=\"return confirm('".$text['confirm-delete']."');\">$v_link_label_delete</a>";
				}
				echo "		</td>\n";
				echo "	</tr>\n";
				$assigned_agents[] = $field['agent_name'];
				$x++;
			}
			unset ($tiers);
			echo "		</table>\n";
			echo "		<br>\n";
			echo "		".$text['description-tiers']."\n";
			echo "		<br />\n";
			echo "	</td>";
			echo "</tr>";
		}
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-music_on_hold']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";

	$ringbacks = new ringbacks;
	echo $ringbacks->select('queue_moh_sound', $queue_moh_sound);

	echo "<br />\n";
	echo $text['description-music_on_hold']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-record_template']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	$record_template = $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}/\${uuid}.\${record_ext}";
	echo "	<select class='formfld' name='queue_record_template'>\n";
	if (!empty($queue_record_template)) {
		echo "	<option value='".escape($queue_record_template)."' selected='selected' >".$text['option-true']."</option>\n";
	}
	else {
		echo "	<option value='".escape($record_template)."'>".$text['option-true']."</option>\n";
	}
	if (empty($queue_record_template)) {
		echo "	<option value='' selected='selected' >".$text['option-false']."</option>\n";
	}
	else {
		echo "	<option value=''>".$text['option-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-record_template']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-time_base_score']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_time_base_score'>\n";
	if ($queue_time_base_score == "system") {
		echo "	<option value='system' selected='selected' >".$text['option-system']."</option>\n";
	}
	else {
		echo "	<option value='system'>".$text['option-system']."</option>\n";
	}
	if ($queue_time_base_score == "queue") {
		echo "	<option value='queue' selected='selected' >".$text['option-queue']."</option>\n";
	}
	else {
		echo "	<option value='queue'>".$text['option-queue']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-time_base_score']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-time_base_score_sec']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_time_base_score_sec' maxlength='255' min='0' step='1' value='".escape($queue_time_base_score_sec)."'>\n";
	echo "<br />\n";
	echo $text['description-time_base_score_sec']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-max_wait_time']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_max_wait_time' maxlength='255' min='0' step='1' value='".escape($queue_max_wait_time)."'>\n";
	echo "<br />\n";
	echo $text['description-max_wait_time']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-max_wait_time_with_no_agent']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_max_wait_time_with_no_agent' maxlength='255' min='0' step='1' value='".escape($queue_max_wait_time_with_no_agent)."'>\n";
	echo "<br />\n";
	echo $text['description-max_wait_time_with_no_agent']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-max_wait_time_with_no_agent_time_reached']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_max_wait_time_with_no_agent_time_reached' maxlength='255' min='0' step='1' value='".escape($queue_max_wait_time_with_no_agent_time_reached)."'>\n";
	echo "<br />\n";
	echo $text['description-max_wait_time_with_no_agent_time_reached']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "    ".$text['label-timeout_action']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo $destination->select('dialplan', 'queue_timeout_action', $queue_timeout_action);
	echo "<br />\n";
	echo $text['description-timeout_action']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-tier_rules_apply']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_tier_rules_apply'>\n";
	if ($queue_tier_rules_apply == "true") {
		echo "	<option value='true' selected='selected' >".$text['option-true']."</option>\n";
	}
	else {
		echo "	<option value='true'>".$text['option-true']."</option>\n";
	}
	if ($queue_tier_rules_apply == "false") {
		echo "	<option value='false' selected='selected' >".$text['option-false']."</option>\n";
	}
	else {
		echo "	<option value='false'>".$text['option-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-tier_rules_apply']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-tier_rule_wait_second']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_tier_rule_wait_second' maxlength='255' min='0' step='1' value='".escape($queue_tier_rule_wait_second)."'>\n";
	echo "<br />\n";
	echo $text['description-tier_rule_wait_second']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-tier_rule_wait_multiply_level']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_tier_rule_wait_multiply_level'>\n";
	if ($queue_tier_rule_wait_multiply_level == "true") {
		echo "	<option value='true' selected='selected' >".$text['option-true']."</option>\n";
	}
	else {
		echo "	<option value='true'>".$text['option-true']."</option>\n";
	}
	if ($queue_tier_rule_wait_multiply_level == "false") {
		echo "	<option value='false' selected='selected' >".$text['option-false']."</option>\n";
	}
	else {
		echo "	<option value='false'>".$text['option-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-tier_rule_wait_multiply_level']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-tier_rule_no_agent_no_wait']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_tier_rule_no_agent_no_wait'>\n";
	if ($queue_tier_rule_no_agent_no_wait == "true") {
		echo "	<option value='true' selected='selected' >".$text['option-true']."</option>\n";
	}
	else {
		echo "	<option value='true'>".$text['option-true']."</option>\n";
	}
	if ($queue_tier_rule_no_agent_no_wait == "false") {
		echo "	<option value='false' selected='selected' >".$text['option-false']."</option>\n";
	}
	else {
		echo "	<option value='false'>".$text['option-false']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-tier_rule_no_agent_no_wait']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-discard_abandoned_after']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='number' name='queue_discard_abandoned_after' maxlength='255' min='0' step='1' value='".escape($queue_discard_abandoned_after)."'>\n";
	echo "<br />\n";
	echo $text['description-discard_abandoned_after']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-abandoned_resume_allowed']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='queue_abandoned_resume_allowed'>\n";
	if ($queue_abandoned_resume_allowed == "false") {
		echo "	<option value='false' selected='selected' >".$text['option-false']."</option>\n";
	}
	else {
		echo "	<option value='false'>".$text['option-false']."</option>\n";
	}
	if ($queue_abandoned_resume_allowed == "true") {
		echo "	<option value='true' selected='selected' >".$text['option-true']."</option>\n";
	}
	else {
		echo "	<option value='true'>".$text['option-true']."</option>\n";
	}
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-abandoned_resume_allowed']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-caller_id_name_prefix']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='text' name='queue_cid_prefix' maxlength='255' value='".escape($queue_cid_prefix)."'>\n";
	echo "<br />\n";
	echo $text['description-caller_id_name_prefix']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('call_center_outbound_caller_id_name')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	".$text['label-outbound_caller_id_name']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld' type='text' name='queue_outbound_caller_id_name' maxlength='255' value='".escape($queue_outbound_caller_id_name ?? '')."'>\n";
		echo "<br />\n";
		echo $text['description-outbound_caller_id_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (permission_exists('call_center_outbound_caller_id_number')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	".$text['label-outbound_caller_id_number']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld' type='text' name='queue_outbound_caller_id_number' maxlength='255' value='".escape($queue_outbound_caller_id_number ?? '')."'>\n";
		echo "<br />\n";
		echo $text['description-outbound_caller_id_number']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (permission_exists('call_center_announce_position')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "  ".$text['label-queue_announce_position']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='queue_announce_position'>\n";
		echo "		<option value='false'>".$text['option-false']."</option>\n";
		echo "		<option value='true' ".(!empty($queue_announce_position) && $queue_announce_position == "true" ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "	</select>\n";
		echo "<br />\n";
		echo ($text['description-queue_announce_position'] ?? '')."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (permission_exists('call_center_announce_sound')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "  ".$text['label-caller_announce_sound']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";

		$destination_id = "queue_announce_sound";
		$script = "<script>\n";
		$script .= "var objs;\n";
		$script .= "\n";
		$script .= "function changeToInput".$destination_id."(obj){\n";
		$script .= "	tb=document.createElement('INPUT');\n";
		$script .= "	tb.type='text';\n";
		$script .= "	tb.name=obj.name;\n";
		$script .= "	tb.className='formfld';\n";
		$script .= "	tb.setAttribute('id', '".$destination_id."');\n";
		$script .= "	tb.setAttribute('style', '".!empty($select_style)."');\n";
		if (!empty($on_change)) {
			$script .= "	tb.setAttribute('onchange', \"".$on_change."\");\n";
			$script .= "	tb.setAttribute('onkeyup', \"".$on_change."\");\n";
		}
		$script .= "	tb.value=obj.options[obj.selectedIndex].value;\n";
		$script .= "	document.getElementById('btn_select_to_input_".$destination_id."').style.visibility = 'hidden';\n";
		$script .= "	tbb=document.createElement('INPUT');\n";
		$script .= "	tbb.setAttribute('class', 'btn');\n";
		$script .= "	tbb.setAttribute('style', 'margin-left: 4px;');\n";
		$script .= "	tbb.type='button';\n";
		$script .= "	tbb.value=$('<div />').html('&#9665;').text();\n";
		$script .= "	tbb.objs=[obj,tb,tbb];\n";
		$script .= "	tbb.onclick=function(){ Replace".$destination_id."(this.objs); }\n";
		$script .= "	obj.parentNode.insertBefore(tb,obj);\n";
		$script .= "	obj.parentNode.insertBefore(tbb,obj);\n";
		$script .= "	obj.parentNode.removeChild(obj);\n";
		$script .= "	Replace".$destination_id."(this.objs);\n";
		$script .= "}\n";
		$script .= "\n";
		$script .= "function Replace".$destination_id."(obj){\n";
		$script .= "	obj[2].parentNode.insertBefore(obj[0],obj[2]);\n";
		$script .= "	obj[0].parentNode.removeChild(obj[1]);\n";
		$script .= "	obj[0].parentNode.removeChild(obj[2]);\n";
		$script .= "	document.getElementById('btn_select_to_input_".$destination_id."').style.visibility = 'visible';\n";
		if (!empty($on_change)) {
			$script .= "	".$on_change.";\n";
		}
		$script .= "}\n";
		$script .= "</script>\n";
		$script .= "\n";
		echo $script;
		
		echo "<select name='queue_announce_sound' id='queue_announce_sound' class='formfld'>\n";
		echo "	<option></option>\n";

		//recordings
		$tmp_selected = false;
		if (!empty($recordings)) {
			echo "<optgroup label='Recordings'>\n";
			foreach ($recordings as &$row) {
				$recording_name = $row["recording_name"];
				$recording_filename = $row["recording_filename"];
				if (!empty($queue_announce_sound) && $queue_announce_sound == $_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$recording_filename) {
					$tmp_selected = true;
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else if (!empty($queue_announce_sound) && $queue_announce_sound == $recording_filename) {
					$tmp_selected = true;
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."' selected='selected'>".escape($recording_name)."</option>\n";
				}
				else {
					echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($recording_filename)."'>".escape($recording_name)."</option>\n";
				}
			}
			echo "</optgroup>\n";
		}

		if (!$tmp_selected && !empty($queue_announce_sound)) {
			echo "<optgroup label='Selected'>\n";
			if (file_exists($_SESSION['switch']['recordings']['dir']."/".$_SESSION['domain_name']."/".$queue_announce_sound)) {
				echo "	<option value='".escape($_SESSION['switch']['recordings']['dir'])."/".escape($_SESSION['domain_name'])."/".escape($queue_announce_sound)."' selected='selected'>".escape($queue_announce_sound)."</option>\n";
			}
			else if (substr($queue_announce_sound, -3) == "wav" || substr($queue_announce_sound, -3) == "mp3") {
				echo "	<option value='".escape($queue_announce_sound)."' selected='selected'>".escape($queue_announce_sound)."</option>\n";
			}
			else {
				echo "	<option value='".escape($queue_announce_sound)."' selected='selected'>".escape($queue_announce_sound)."</option>\n";
			}
			echo "</optgroup>\n";
		}
		
		unset($tmp_selected);

		echo "	</select>\n";
		echo "<input type='button' id='btn_select_to_input_".escape($destination_id)."' class='btn' name='' alt='back' onclick='changeToInput".escape($destination_id)."(document.getElementById(\"".escape($destination_id)."\"));this.style.visibility = \"hidden\";' value='&#9665;'>";
		
		unset($destination_id);

		echo "	<br />\n";
		echo $text['description-caller_announce_sound']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	if (permission_exists('call_center_announce_frequency')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "  ".$text['label-caller_announce_frequency']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld' type='number' name='queue_announce_frequency' maxlength='255' min='0' step='1' value='".escape($queue_announce_frequency)."'>\n";
		echo "<br />\n";
		echo $text['description-caller_announce_frequency']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "  ".$text['label-exit_keys']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "  <input class='formfld' type='text' name='queue_cc_exit_keys' value='".escape($queue_cc_exit_keys)."'>\n";
	echo "<br />\n";
	echo $text['description-exit_keys']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('call_center_email_address')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap>\n";
		echo "	".$text['label-queue_email_address']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "  <input class='formfld' type='text' name='queue_email_address' maxlength='255' value='".escape($queue_email_address ?? '')."'>\n";
		echo "<br />\n";
		echo $text['description-queue_email_address']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='queue_description' maxlength='255' value=\"".escape($queue_description)."\">\n";
	echo "<br />\n";
	echo $text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br><br>";

	if ($action == "update") {
		echo "<input type='hidden' name='call_center_queue_uuid' value='".escape($call_center_queue_uuid)."'>\n";
		echo "<input type='hidden' name='dialplan_uuid' value='".escape($dialplan_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
