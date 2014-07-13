<!DOCTYPE html>
<html>
	<head>
		<title>EE1 Custom Field Overview</title>
		<style type="text/css">
			* { -webkit-box-sizing: border-box; -moz-box-sizing: border-box; box-sizing: border-box; }
			html, body { font-family: sans-serif; color: #333; background-color: #ddd; }
			table { border-collapse: collapse; border: 0; }
			table table { width: 100%; }
			tr { background-color: rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05); }
			tr:nth-child(odd) { background-color: rgba(0,0,0,0.05); }
			tr:nth-child(even) { background-color: rgba(255,255,255,0.05); }
			table, th, td { border: 0; }
			th, td { border-right: 1px solid rgba(0,0,0,0.02); }
			th:first-child, td:first-child { border-left: 1px solid rgba(0,0,0,0.02); }
			th { padding: 5px 10px; background-color: #828A97; color: #eee; text-align: left; }
			td { padding: 20px 10px; }
			caption { background-color: #828A97; color: #000; font-size: 1.2em; padding: 3px; }
			ol { font-size: 1rem; }
			ol ol { list-style-type: lower-alpha; }
			li { margin: 1em 0 2em; }
			textarea { display: block; width: 100%; font-family: monospace; color: #A6E22A; background-color: #222; padding: 10px; }
			.terminal { font-family: monospace; color: #A6E22A; background-color: #222; padding: 10px; }
			.highlight { font-weight: bold; color: red; }
		</style>
	</head>
	<body>
		<p>
			<a href="#queries">Jump to generated MySQL queries</a> | <span class="highlight">nGen File rows are highlighted</span> | <span class="terminal">MySQL queries are in terminal colors</span>
		</p>
<?php

	// Change to 1 to display per-field queries inline.
	$debug = 0;

	// ============================
	// END OF USER-EDITABLE SECTION
	// ============================

	global $DB;

	$file_fields = $file_matrix_cols = $update_queries = $ee_sites = array();

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

	function change_to_text($field_id, $mode = 'field')
	{
		switch ($mode)
		{
			case 'matrix':
				// The serialized string below is the matrix settings for a textfield that has no max length, no formatting, and is not multiline.
				$query = "UPDATE exp_matrix_cols SET col_type = 'text', col_settings = 'YToyOntzOjQ6Im1heGwiO3M6MDoiIjtzOjM6ImZtdCI7czo0OiJub25lIjt9' WHERE col_id = " . $field_id . ";";
			break;

			case 'field':
				$query = "UPDATE exp_weblog_fields SET field_type = 'text' WHERE field_id = " . $field_id . ";";
			break;
		}
		return $query;
	}

	function change_to_ee2_file($field_id, $upload_dir_id, $mode = 'field')
	{
		switch ($mode)
		{
			case 'matrix':
				$col_settings = array
				(
					'directory' => $upload_dir_id, // Only one dir can be specified, despite key name
					'content_type' => 'all'
				);
				$prepared_col_settings = base64_encode(serialize($col_settings));
				$query = "UPDATE exp_matrix_cols
SET col_type = 'file', col_settings = '" . $prepared_col_settings . "'
WHERE col_id = " . $field_id . ";";
			break;

			case 'field':
				$field_settings = array
				(
					'field_content_type' => 'all',
					'allowed_directories' => $upload_dir_id, // Only one dir can be specified, despite key name
					'field_show_smileys' => 'n',
					'field_show_glossary' => 'n',
					'field_show_spellcheck' => 'n',
					'field_show_formatting_btns' => 'n',
					'field_show_file_selector' => 'n',
					'field_show_writemode' => 'n'
				);
				$prepared_field_settings = base64_encode(serialize($field_settings));
				$query = "UPDATE exp_channel_fields
SET field_type = 'file', field_settings = '" . $prepared_field_settings . "'
WHERE field_id = " . $field_id . ";";
			break;
		}
		return $query;
	}

/* --------------------------------------------------------- */

// Open the table
	echo "
<table border='1' cellspacing='0' cellpadding='2'>
	<thead>
		<tr>
			<th>site_id</th>
			<th>group_name</th>
			<th>field_id</th>
			<th>field_name</th>
			<th>field_type</th>
			<th>class</th>
			<th>ff_settings</th>
		</tr>
	</thead>";


// Fetch primary list of fields
	$query = $DB->query("SELECT g.site_id, g.group_name, f.field_name, f.field_id, f.field_type, ft.class, f.ff_settings
FROM `exp_field_groups` AS g
INNER JOIN `exp_weblog_fields` as f
  ON g.group_id = f.group_id
LEFT JOIN `exp_ff_fieldtypes` as ft
  ON f.field_type = CONCAT('ftype_id_', CAST(ft.fieldtype_id AS CHAR))
ORDER BY g.site_id ASC, g.group_name ASC");



	if ($query->num_rows > 0)
	{
		// Display each field row
		foreach($query->result as $row)
		{

			// Open row & highlight if nGen File field
			echo "<tr";
			if ($row['class'] == 'ngen_file_field')
			{
				echo " class='highlight'";
			}
			echo ">";

			// Display field information
			echo "<td>" . $ee_sites[$row['site_id']] . BR . "(ID: " . $row['site_id'] . ")</td>";
			echo "<td>" . $row['group_name'] . "</td>";
			echo "<td>" . $row['field_id'] . "</td>";
			echo "<td>" . $row['field_name'] . "</td>";
			echo "<td>" . $row['field_type'] . "</td>";
			if ($row['class'])
			{
				echo "<td>" . $row['class'] . "</td>";
			}

			// Open field settings table cell
			echo "<td>";

			// Deal with nGen File Fields
			if ($row['class'] == 'ngen_file_field')
			{

				// Get field settings as array
				$settings = unserialize($row['ff_settings']);

				// Get upload destination info as array
				$dir_info = getUploadDirInfo($settings['options']);

				// Generate and output table of upload destination info
				$dir_info_table = tableize($dir_info, $row['field_name'] . ' Upload Dir');
				echo $dir_info_table;

				// If debug mode is on, display query to update this field's DATA
				if ($debug)
				{
					echo "<textarea rows='4' cols='54' readonly>";
					$update_queries[] = $ngen_query = update_ngen_file_data($row['field_id'], $settings['options']);
					echo $ngen_query;
					echo "</textarea>";
				}

				// Create array of fields (keys) and their upload dirs (values) for later use
				$file_fields[$row['field_id']] = $settings['options'];

			}


			// Deal with Matrix fields
			elseif ($row['class'] == 'matrix')
			{
				// Display matrix column information in sub-table
				echo '
				<table border="1" cellspacing="0" cellpadding="2">
					<tr>
						<th>col_id</th>
						<th>col_name</th>
						<th>col_label</th>
						<th>col_type</th>
						<th>col_settings</th>
					</tr>';

				// Get Matrix settings as array (including list of Matrix columns)
				$matrix_data = unserialize($row['ff_settings']);

				// For each column...
				foreach ($matrix_data['col_ids'] as $matrix_col)
				{
					// Fetch col info
					$query_2 = $DB->query("SELECT col_id, col_name, col_label, col_type, col_settings" .
					" FROM `exp_matrix_cols`" .
					" WHERE col_id = " . $matrix_col);

					if ($query_2->num_rows > 0)
					{
						// Show column info
						foreach($query_2->result as $row_2)
						{
							// Open row & highlight if nGen File field
							echo "<tr";
							if ($row_2['col_type'] == 'ngen_file_field')
							{
								echo " class='highlight'";
							}
							echo ">";

							// Display col information
							echo "<td>" . $row_2['col_id'] . "</td>";
							echo "<td>" . $row_2['col_name'] . "</td>";
							echo "<td>" . $row_2['col_label'] . "</td>";
							echo "<td>" . $row_2['col_type'] . "</td>";

							// Deal with nGen File columns
							if ($row_2['col_type'] == 'ngen_file_field')
							{
								// Open table cell
								echo "<td>";

								// Get col settings as array
								$settings = unserialize(base64_decode($row_2['col_settings']));

								// Get upload destination info as array
								$dir_info = getUploadDirInfo($settings['options']);

								// Generate and output table of upload destination info
								$dir_info_table = tableize($dir_info, $row_2['col_name'] . ' Upload Dir');
								echo $dir_info_table;

								// If debug mode is on, display query to update this col's DATA
								if ($debug)
								{
									echo "<textarea rows='4' cols='54' readonly>";
									$update_queries[] = $ngen_query = update_ngen_file_data($row_2['col_id'], $settings['options'], 'matrix');
									echo $ngen_query;
									echo "</textarea>";

								}

								// Close table cell
								echo "</td>";

								// Create array of cols (keys) and their upload dirs (values)
								$file_matrix_cols[$row_2['col_id']] = $settings['options'];
							}

							// Close table row
							echo "</tr>";
						}
					}
				}

				// Close matrix sub-table
				echo "</table>";
			}

			// Show basic field settings for non-nGen File and non-Matrix fields
			else
			{
				if ($row['ff_settings'])
				{
					$settings = unserialize($row['ff_settings']);
					$settings = tableize($settings, $row['field_name'] . ' FF Settings');
					echo $settings;
				}
			}

			// Close field settings table cell
			echo "</td>";
		}

	}
	echo "</table>";

	// Now that the fields/columns and their upload dirs are stored in arrays, let's output the various queries we need. for the upgrade process.
	echo "<hr>
	<a name='queries'></a>
	<ol>
	<li>BEFORE EE1->EE2 UPGRADE:";

// UPDATE FIELDS TO TEXT

	// 1. Generate SQL to change nGen File fields to text
	echo "<ol><li>SQL to change nGen File fields to 'text'";
	echo "<textarea rows='5' cols='60' readonly>";
	$query_change_fields = "UPDATE exp_weblog_fields\nSET field_type = 'text'\nWHERE field_id IN (";
	foreach ($file_fields as $field_id => $upload_dir)
	{
		$query_change_fields .= $field_id . ", ";
	}
	$query_change_fields = rtrim($query_change_fields, ", ");
	$query_change_fields .= ");";
	echo $query_change_fields;
	echo "</textarea></li>";

	// 2. Generate SQL to change nGen File columns (in Matrix) to text
	echo "<li>SQL to change nGen File Matrix columns to 'text'";
	echo "<textarea rows='6' cols='60' readonly>";
	// The serialized string below is the matrix settings for a textfield that has no max length, no formatting, and is not multiline.
	$query_change_matrix_cols = "UPDATE exp_matrix_cols\nSET col_type = 'text', col_settings = 'YToyOntzOjQ6Im1heGwiO3M6MDoiIjtzOjM6ImZtdCI7czo0OiJub25lIjt9'\nWHERE col_id IN (";
	foreach ($file_matrix_cols as $col_id => $upload_dir)
	{
		$query_change_matrix_cols .= $col_id . ", ";
	}
	$query_change_matrix_cols = rtrim($query_change_matrix_cols, ", ");
	$query_change_matrix_cols .= ");";
	echo $query_change_matrix_cols;

	echo "</textarea></li>";
	echo "</ol>";
	echo "</li>";



// AFTER upgrading, run this stuff to update all the old nGen File field DATA, and to switch the fields to the native EE2 File field
	echo "<li>AFTER UPGRADE & MATRIX REINSTALLED:";

	// 1. Display SQL to update FIELD DATA
	echo "<ol><li>SQL to update data in old nGen File fields";
	echo "<textarea rows='20' cols='60' readonly>";
	foreach ($file_fields as $field_id => $upload_dir) {
		echo update_ngen_file_data($field_id, $upload_dir) . "\n";
	}
	echo "</textarea></li>";

	// 2. Display SQL to update MATRIX COL DATA
	echo "<li>SQL to update data in old nGen File Matrix Columns";
	echo "<textarea rows='20' cols='60' readonly>";
	foreach ($file_matrix_cols as $col_id => $upload_dir) {
		echo update_ngen_file_data($col_id, $upload_dir, 'matrix') . "\n";
	}
	echo "</textarea></li>";


	// 3. Display SQL to change old nGen File fields to native EE File fields
	echo "<li>SQL to change old nGen File fields to native EE File fields";
	echo "<textarea rows='20' cols='60' readonly>";
	foreach ($file_fields as $field_id => $upload_dir) {
		echo change_to_ee2_file($field_id, $upload_dir) . "\n";
	}
	echo "</textarea></li>";

	// 4. Display SQL to change old nGen File Matrix Columns to native EE File Matrix Columns
	echo "<li>SQL to change old nGen File Matrix Columns to native EE File Matrix Columns";
	echo "<textarea rows='20' cols='60' readonly>";
	foreach ($file_matrix_cols as $col_id => $upload_dir) {
		echo change_to_ee2_file($col_id, $upload_dir, 'matrix') . "\n";
	}
	echo "</textarea></li>";

	echo "</ol>";
	echo "</li>";
	echo "</ol>";

?>
	</body>
</html>