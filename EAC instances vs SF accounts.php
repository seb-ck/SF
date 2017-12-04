<?php
require_once('bootstrap.php');
require_once('head.php');
?>

	<h1 style="float: left;">EAC instances vs SF accounts</h1>

	<div style="float: right; text-align: left; white-space: nowrap; border: 1px solid #000; padding: 10px; margin: 10px;">
		<span><img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_green.png" style="vertical-align: middle;"/> Exact match</span>
		<span><img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_blue.png" style="vertical-align: middle;"/> Almost exact match</span>
		<span><img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_orange.png" style="vertical-align: middle;"/> Approximate match</span>
	</div>

<?php

try
{
	$conn = mysqli_connect('localhost','root',''); 
	mysqli_select_db($conn, 'eac');

	$sql = "SELECT i.id, short_name, i.url, a.name
					FROM eac_instance i
					INNER JOIN eac_agent a ON i.eac_agent_id = a.id
					WHERE instance_type='production'
					ORDER BY short_name ASC";
//					WHERE (short_name LIKE '%" . mysqli_real_escape_string($conn, $re->fields->Name) . "%'
//							OR i.url LIKE '%" . mysqli_real_escape_string($conn, $re->fields->Name) . "%\.%\.crossknowledge\.%')
//						AND instance_type='production'";
	$query = mysqli_query($conn, $sql);

	$instances = [];
	while ($row = mysqli_fetch_assoc($query))
	{
		$instances[$row['id']] = $row;
	}

	$soql = " SELECT Id, Name 
						FROM Account 
						WHERE (Type='Customer' OR Type = NULL)
							AND (NOT Name LIKE 'CrossKnowledge%') 
						ORDER BY Name ASC";
	$rrresponse = $mySforceConnection->query($soql);

	$accounts = [];
	foreach ($rrresponse as $ac)
	{
		$accounts[$ac->Id] = $ac->fields->Name;
	}

	$parentIds = array();
	$found = 0;
	$total = 0;
	$duplicates = 0;
	
	echo '<table id="results">';
	echo '<thead>';
	echo '<tr>';
	echo '	<th>EAC short name</th>';
	echo '	<th>URL</th>';
	echo '	<th>SF Account</th>';
	echo '	<th>More info</th>';
	echo '</tr>';
	echo '</thead>';
	
	echo '<tbody>';
	
	foreach ($instances as $i)
	{
		$in = strtolower($i['short_name']);
		$in2 = $in;
		$account = [];
		$images = '';

		if (preg_match('@https?://(.*)\..*\.crossknowledge\.com@', $i['url'], $matches))
			$in2 = $matches[1];

		foreach ($accounts as $acId => $acName)
		{
			$acNameStripped = strtolower(str_replace(' ', '', $acName));

			// Exact match
			if (strtolower($acName) == $in || strtolower($acName) == $in2)
			{
				$account []= '<a href="' . $SF_URL . $acId . '">' . $acName . '</a>';
				$images .= '<img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_green.png" title="Exact match" /> ';
			}
			// Exact match, except for spaces and special characters
			else if ($acNameStripped == $in || $acNameStripped == $in2)
			{
				$account []= '<a href="' . $SF_URL . $acId . '">' . $acName . '</a>';
				$images .= '<img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_blue.png" title="Almost exact match" /> ';
			}
			// Approximate match
			else if (strlen($in) > 3 && strlen($acName) > 3) // test if one name contains the other, but only if EAC name > 3 characters
			{
				if (stripos($in, $acName) !== false
					|| stripos($acName, $in) !== false
					|| stripos($acNameStripped, $in) !== false
					|| stripos($in, $acNameStripped) !== false
					|| stripos($in2, $acName) !== false
					|| stripos($acName, $in2) !== false
					|| stripos($acNameStripped, $in2) !== false
					|| stripos($in2, $acNameStripped) !== false)
				{
					$account []= '<a href="' . $SF_URL . $acId . '">' . $acName . '</a>';
					$images .= '<img src="https://cdn0.iconfinder.com/data/icons/blueberry/32/tag_orange.png" title="Approximate match" /> ';
				}
			}
		}

		if (count($account) == 1)
			$found++;
		else if (count($account) > 1)
			$duplicates++;

		echo '<tr>';
		echo '<td><a href="https://eac.lms.crossknowledge.com/data/modules/eac/admin/eac_instance_record.php?eac_instance_id=' . $i['id'] . '">' . $i['short_name'] . '</a></td>';
		echo '<td><a href="' . $i['url'] . '">' . $i['url'] . '</a></td>';
		echo '<td>' . implode('<br/>', $account) . '</td>';
		echo '<td align="center"">' . $images . '</td>';
		echo '</tr>';

		$total++;
	}
	
	echo '</tbody>';
	echo '</table>';
	
//	var_dump('total ' . $total);
//	var_dump('found ' . $found);
//	var_dump('duplicates ' . $duplicates);
	}
catch (Exception $e) 
{
  var_dump($e);
}
