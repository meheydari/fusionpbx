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
*/

//includes files
	require_once dirname(__DIR__, 4) . "/resources/require.php";
	require_once "resources/check_auth.php";

//add multi-lingual support
	$language = new text;
	$text = $language->get($_SESSION['domain']['language']['code'], 'app/recordings');
	$dashboard_text = function($key, $default) use ($text) {
		return $text[$key] ?? $default;
	};

//current domain recording path
	$domain_name = $_SESSION['domain_name'] ?? $_SESSION['domain']['name'] ?? '';
	$recordings_dir = $_SESSION['switch']['recordings']['dir'] ?? '/var/lib/freeswitch/recordings';
	$domain_recordings_dir = rtrim($recordings_dir, '/').'/'.$domain_name;

//defaults
	$disk_total = '';
	$disk_used = '';
	$disk_free = '';
	$disk_percent = 0;
	$disk_mount = '';
	$disk_filesystem = '';
	$disk_error = '';

//get disk usage for the domain
	if (empty($domain_name)) {
		$disk_error = $dashboard_text('message-no_quota_allocated', 'No storage quota allocated for this domain.');
	}
	else if (!stristr(PHP_OS, 'Linux') && !stristr(PHP_OS, 'BSD')) {
		$disk_error = $dashboard_text('message-disk_usage_unavailable', 'Disk usage is available on Linux and BSD.');
	}
	else {
		//parse a single "df -h" style line into the disk columns
		$parse_df_line = function($line) {
			$columns = preg_split('/\s+/', trim($line), 6);
			if (is_array($columns) && count($columns) >= 6) {
				return [
					'filesystem' => $columns[0],
					'total'      => $columns[1],
					'used'       => $columns[2],
					'free'       => $columns[3],
					'percent'    => (int) rtrim($columns[4], '%'),
					'mount'      => $columns[5],
				];
			}
			return null;
		};

		//1) look for the domain in the live df output
		$result = trim(shell_exec('df -h 2>/dev/null | grep -F '.escapeshellarg($domain_name)));

		//2) fall back to the cached snapshot at /opt/df-h when the live df has no match
		if ($result === '' && is_readable('/opt/df-h')) {
			$result = trim(shell_exec('grep -F '.escapeshellarg($domain_name).' /opt/df-h 2>/dev/null'));
		}

		//parse the first matching line (if any)
		$disk = ($result !== '') ? $parse_df_line(strtok($result, "\n")) : null;

		if (!empty($disk)) {
			$disk_filesystem = $disk['filesystem'];
			$disk_total      = $disk['total'];
			$disk_used       = $disk['used'];
			$disk_free       = $disk['free'];
			$disk_percent    = $disk['percent'];
			$disk_mount      = $disk['mount'];
		}
		else {
			//3) nothing found in either source - show the empty state
			$disk_error = $dashboard_text('message-no_quota_allocated', 'No storage quota allocated for this domain.');
		}
		unset($result, $disk, $parse_df_line);
	}

//show the widget
	echo "<div class='hud_box'>\n";

	if ($disk_total != '') {
		$chart_id = 'domain_disk_usage_chart';
		?>
		<div style='display: flex; flex-wrap: wrap; justify-content: center; padding-bottom: 20px;' onclick="$('#hud_domain_disk_usage_details').slideToggle('fast');">
			<div><canvas id='<?php echo $chart_id; ?>' width='175px' height='175px'></canvas></div>
		</div>

		<script>
			var domain_disk_usage_chart_background_color;
			if ('<?php echo $disk_percent; ?>' <= 80) {
				domain_disk_usage_chart_background_color = '<?php echo $_SESSION['dashboard']['disk_usage_chart_main_background_color'][0] ?? '#2ca58d'; ?>';
			} else if ('<?php echo $disk_percent; ?>' <= 90) {
				domain_disk_usage_chart_background_color = '<?php echo $_SESSION['dashboard']['disk_usage_chart_main_background_color'][1] ?? '#f0ad4e'; ?>';
			} else if ('<?php echo $disk_percent; ?>' > 90) {
				domain_disk_usage_chart_background_color = '<?php echo $_SESSION['dashboard']['disk_usage_chart_main_background_color'][2] ?? '#d9534f'; ?>';
			}

			const domain_disk_usage_chart = new Chart(
				document.getElementById('<?php echo $chart_id; ?>').getContext('2d'),
				{
					type: 'doughnut',
					data: {
						datasets: [{
							data: ['<?php echo $disk_percent; ?>', 100 - '<?php echo $disk_percent; ?>'],
							backgroundColor: [
								domain_disk_usage_chart_background_color,
								'<?php echo $_SESSION['dashboard']['disk_usage_chart_sub_background_color']['text'] ?? '#eeeeee'; ?>'
							],
							borderColor: '<?php echo $_SESSION['dashboard']['disk_usage_chart_border_color']['text'] ?? '#ffffff'; ?>',
							borderWidth: '<?php echo $_SESSION['dashboard']['disk_usage_chart_border_width']['text'] ?? '1'; ?>',
							cutout: chart_cutout
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						circumference: 180,
						rotation: 270,
						plugins: {
							chart_counter_2: {
								chart_text: '<?php echo $disk_percent; ?>'
							},
							legend: {
								display: false
							},
							title: {
								display: true,
								text: <?php echo json_encode($dashboard_text('title-domain_disk_usage', 'Domain Disk Usage')); ?>
							}
						}
					},
					plugins: [chart_counter_2],
				}
			);
		</script>
		<?php
	}
	else {
		//no chart to click on - show the status/message directly so the widget is not blank
		//(the details table below is inside hud_details which is hidden by default)
		echo "<div style='text-align: center; padding: 20px 10px; color: #888;'>".escape($disk_error)."</div>\n";
	}

	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

	echo "<div class='hud_details hud_box' id='hud_domain_disk_usage_details'>";
	echo "<table class='tr_hover' width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
	echo "<tr>\n";
	echo "<th class='hud_heading' width='50%'>".escape($dashboard_text('label-item', 'Item'))."</th>\n";
	echo "<th class='hud_heading' style='text-align: right;'>".escape($dashboard_text('label-value', 'Value'))."</th>\n";
	echo "</tr>\n";

	if ($disk_total != '') {
		$rows = [
			$dashboard_text('label-domain', 'Domain') => $domain_name,
			$dashboard_text('label-allocated', 'Allocated') => $disk_total,
			$dashboard_text('label-used', 'Used') => $disk_used,
			$dashboard_text('label-remaining', 'Remaining') => $disk_free,
			$dashboard_text('label-use_percent', 'Use') => $disk_percent.'%',
		];
	}
	else {
		$rows = [
			$dashboard_text('label-domain', 'Domain') => $domain_name,
			$dashboard_text('label-status', 'Status') => $disk_error,
		];
	}

	foreach ($rows as $label => $value) {
		echo "<tr class='tr_link_void'>\n";
		echo "<td valign='top' class='".$row_style[$c]." hud_text'>".escape($label)."</td>\n";
		echo "<td valign='top' class='".$row_style[$c]." hud_text' style='text-align: right;'>".escape($value)."</td>\n";
		echo "</tr>\n";
		$c = ($c) ? 0 : 1;
	}
	unset($rows);

	echo "</table>\n";
	echo "</div>";
	echo "<span class='hud_expander' onclick=\"$('#hud_domain_disk_usage_details').slideToggle('fast');\"><span class='fas fa-ellipsis-h'></span></span>";
	echo "</div>\n";

?>
