<!DOCTYPE html>
<html>
	<head>
		<title>EE1 Custom Field Overview</title>
		<style type="text/css">
			html, body { font-family: sans-serif; color: #333; }
			table { border-collapse: collapse; }
			caption { background-color: #ddd; color: #444; font-size: 1.2em; padding: 3px; }
		</style>
	</head>
	<body>
<?php

	global $DB;

	$update_queries = array();
	$ee_sites = array();

	// Gets information about the specified EE upload dir
	function getUploadDirInfo($dir_id)
	{
		global $DB;
		$query = $DB->query("SELECT id, name, server_path
							FROM `exp_upload_prefs`
							WHERE id = " . $dir_id);
		if ($query->num_rows > 0)
		{
			foreach($query->result as $row)
			{
				return $row; // Array
			}
		}
	}

	// Gets information about EE MSM sites
	function getSiteInfo()
	{
		global $DB;

		$query = $DB->query("SELECT site_id, site_name
							FROM `exp_sites`");
		if ($query->num_rows > 0)
		{
			foreach($query->result as $row)
			{
				$sites[$row['site_id']] = $row['site_name']; // Populate array
			}
			return $sites;
		}
	}
	//
	$ee_sites = getSiteInfo();

	// Formats an array as an HTML table
	function tableize($array, $caption = '')
	{
		$table = "<table border='1' cellspacing='0' cellpadding='2'>";
		if ($caption) {
			$table .= "<caption>$caption</caption>";
		}
		$table .= "<thead>";
		$table .= "<tr>";
		foreach ($array as $header => $cell) {
			$table .= "<th>" . $header . "</th>";
		}
		$table .= "</tr>";
		$table .= "</thead>";
		$table .= "<tbody>";
		$table .= "<tr>";
		foreach ($array as $header => $cell) {
			$table .= "<td>" . $cell . "</td>";
		}
		$table .= "</tr>";
		$table .= "</tbody>";
		$table .= "</table>";
		return $table;
	}

	// Returns MySQL query to run for updating ex-nGen File Field data to work with native EE File fields
	// the optional $mode parameters can be either 'field' (the default) or 'matrix'
	function update_ngen_file_data($field_id, $upload_dir_id, $mode = 'field')
	{
		switch ($mode)
		{
			case 'matrix':
				$query = "UPDATE exp_matrix_data
SET col_id_" . $field_id . " = CONCAT('{filedir_" . $upload_dir_id . "}', col_id_" . $field_id . ")
WHERE col_id_" . $field_id . " != ''
AND col_id_" . $field_id . " NOT LIKE '{filedir_%';";
				break;

			case 'field':
				$query = "UPDATE exp_channel_data
SET field_id_". $field_id . " = CONCAT('{filedir_" . $upload_dir_id . "}', field_id_". $field_id . ")
WHERE field_id_". $field_id . " != ''
AND field_id_". $field_id . " NOT LIKE '{filedir_%';";
				break;
		}
		return $query;
	}

// Open the table
	echo "<table border='1' cellspacing='0' cellpadding='2'>
<tr>
<th>site_id</th>
<th>group_name</th>
<th>field_id</th>
<th>field_name</th>
<th>field_type</th>
<th>class</th>
<th>ff_settings</th>
</tr>";

// Fetch primary list of fields
	$query = $DB->query("SELECT g.site_id, g.group_name, f.field_name, f.field_id, f.field_type, ft.class, f.ff_settings
FROM `exp_field_groups` AS g
INNER JOIN `exp_weblog_fields` as f
  ON g.group_id = f.group_id
LEFT JOIN `exp_ff_fieldtypes` as ft
  ON f.field_type = CONCAT('ftype_id_', CAST(ft.fieldtype_id AS CHAR))");

	if ($query->num_rows > 0)
	{
		// Display each field row
		foreach($query->result as $row)
		{
			echo "<tr>";
			echo "<td>" . $ee_sites[$row['site_id']] . " (ID: " . $row['site_id'] . ")</td>";
			echo "<td>" . $row['group_name'] . "</td>";
			echo "<td>" . $row['field_id'] . "</td>";
			echo "<td>" . $row['field_name'] . "</td>";
			echo "<td>" . $row['field_type'] . "</td>";
			echo "<td>" . $row['class'] . "</td>";


			// Deal with nGen File Fields
			if ($row['class'] == 'ngen_file_field')
			{
				echo "<td>";
				$settings = unserialize($row['ff_settings']);
				$dir_info = getUploadDirInfo($settings['options']);
				$dir_info_table = tableize($dir_info, $row['field_name'] . ' Upload Dir');
				echo $dir_info_table;

				// Produce Query
				echo "<textarea rows='4' cols='54' readonly>";
				$update_queries[] = $ngen_query = update_ngen_file_data($row['field_id'], $settings['options']);
				echo $ngen_query;
				echo "</textarea>";
				echo "</td>";

			}


			// Deal with Matrix fields
			elseif ($row['class'] == 'matrix')
			{
				echo "<td>";
				echo '
				<table border="1" cellspacing="0" cellpadding="2">
					<tr>
						<th>col_id</th>
						<th>col_name</th>
						<th>col_label</th>
						<th>col_type</th>
						<th>col_settings</th>
					</tr>';
				$matrix_data = unserialize($row['ff_settings']);
				foreach ($matrix_data['col_ids'] as $matrix_col)
				{
					$query_2 = $DB->query("SELECT col_id, col_name, col_label, col_type, col_settings" .
					" FROM `exp_matrix_cols`" .
					" WHERE col_id = " . $matrix_col);

					if ($query_2->num_rows > 0)
					{
						foreach($query_2->result as $row_2)
						{
							echo "<tr>";
							echo "<td>" . $row_2['col_id'] . "</td>";
							echo "<td>" . $row_2['col_name'] . "</td>";
							echo "<td>" . $row_2['col_label'] . "</td>";
							echo "<td>" . $row_2['col_type'] . "</td>";
							if ($row_2['col_type'] == 'ngen_file_field')
							{
								echo "<td>";
								$settings = unserialize(base64_decode($row_2['col_settings']));
								$dir_info = getUploadDirInfo($settings['options']);
								$dir_info_table = tableize($dir_info, $row_2['col_name'] . ' Upload Dir');
								echo $dir_info_table;

								// Produce Query
								echo "<textarea rows='4' cols='54' readonly>";
								$update_queries[] = $ngen_query = update_ngen_file_data($row_2['col_id'], $settings['options'], 'matrix');
								echo $ngen_query;
								echo "</textarea>";
								echo "</td>";
							}
							echo "</tr>";
						}
					}
				}
				echo "</table>";
				echo "</td>";
			}
		}
	}
	echo "</table>";

	echo "<hr><textarea rows='20' cols='60' readonly>";
	foreach ($update_queries as $key => $value) {
		echo $value . "
";
	}
	echo "</textarea>";
?>
	</body>
</html>