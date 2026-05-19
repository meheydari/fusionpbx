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

//get disk usage for the domain recordings path
	if (stristr(PHP_OS, 'Linux') || stristr(PHP_OS, 'BSD')) {
		if (!empty($domain_name) && is_dir($domain_recordings_dir)) {
			$command = '/bin/df -hP '.escapeshellarg($domain_recordings_dir).' 2>&1';
			$result = trim(shell_exec($command));
			$lines = explode("\n", $result);
			if (!empty($lines[1])) {
				$columns = preg_split('/\s+/', trim($lines[1]), 6);
				if (is_array($columns) && count($columns) >= 6) {
					$disk_filesystem = $columns[0];
					$disk_total = $columns[1];
					$disk_used = $columns[2];
					$disk_free = $columns[3];
					$disk_percent = (int) rtrim($columns[4], '%');
					$disk_mount = $columns[5];
				}
			}
			else {
				$disk_error = $result;
			}
			unset($command, $result, $lines, $columns);
		}
		else {
			$disk_error = 'Recording path not found.';
		}
	}
	else {
		$disk_error = 'Disk usage is available on Linux and BSD.';
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
								text: 'Domain Disk Usage'
							}
						}
					},
					plugins: [chart_counter_2],
				}
			);
		</script>
		<?php
	}

	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

	echo "<div class='hud_details hud_box' id='hud_domain_disk_usage_details'>";
	echo "<table class='tr_hover' width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
	echo "<tr>\n";
	echo "<th class='hud_heading' width='50%'>Item</th>\n";
	echo "<th class='hud_heading' style='text-align: right;'>Value</th>\n";
	echo "</tr>\n";

	if ($disk_total != '') {
		$rows = [
			'Domain' => $domain_name,
			'Total' => $disk_total,
			'Used' => $disk_used,
			'Free' => $disk_free,
			'Use' => $disk_percent.'%',
			'Path' => $domain_recordings_dir,
			'Mount' => $disk_mount,
			'Filesystem' => $disk_filesystem,
		];
	}
	else {
		$rows = [
			'Domain' => $domain_name,
			'Path' => $domain_recordings_dir,
			'Status' => $disk_error,
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
