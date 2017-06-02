<?php
require_once('bootstrap.php');
require_once('head.php');

$d2 = new DateTime();
$d2->sub(new DateInterval('P15D'));
$d2 = $d2->format('Y-m-d\TH:i:s\Z');

$viewAll = !empty($_GET['all']);
$wc = (!empty($_GET['wc']) ? " OR (Status = 'Waiting Consultant'" . ($viewAll ? " AND LastModifiedDate <= $d2)" : ')') : '');
$wb = (!empty($_GET['wb']) ? " OR Status LIKE 'Waiting Bug%'" : '');
$filter = "(Status IN ('Open', 'Assigned')$wc $wb)";
$name = !empty($_GET['name']) ? preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['name']) : '';

?>

  <h1 style="float: left;">SLA Warnings board</h1>

  <form method="get" id="warningsForm">
    <?php if ($name) echo '<input type="hidden" name="name" value="' . $name . '" />' ?>

    <div style="float: right; text-align: left; white-space: nowrap; border: 1px solid #000; padding: 10px; margin: 10px;">
      <span><input type="checkbox" name="all" id="all" value="1" <?= (!empty($_GET['all']) ? 'checked="checked"' : '') ?> onclick="this.form.submit()" /><label for="all">Show ongoing cases without warning</label></span>
      <span><input type="checkbox" name="wc" id="wc" value="1" <?= (!empty($_GET['wc']) ? 'checked="checked"' : '') ?> onclick="this.form.submit()" /><label for="wc">Show cases "Waiting consultant"</label></span>
      <span><input type="checkbox" name="wb" id="wb" value="1" <?= (!empty($_GET['wb']) ? 'checked="checked"' : '') ?> onclick="this.form.submit()" /><label for="wb">Show cases "Waiting bug"</label></span>
    </div>
  </form>

<?php

try
{
	$filterOwner = '';
	if ($name)
	{
		$owner = $mySforceConnection->query("SELECT Id FROM User WHERE Alias='" . $name . "'");
		foreach ($owner as $o)
		{
			$filterOwner = " AND OwnerId = '{$o->Id}'";
		}
	}

	$query = "SELECT Id FROM Case WHERE Type='Technical Support' AND $filter AND OwnerId != '00G240000014Hsp' $filterOwner ORDER BY Global_priority__c DESC";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$casesPerOwner = array();
	$owners = array();
	$ownerIds = array();
	$accounts = array();
	$accountIds = array();
	$now = new DateTime();
	$totals = array('alert' => 0, 2 => 0, 7 => 0, 14 => 0, 31 => 0, 'feedback' => 0, 'trash' => 0);

	foreach ($response as $record)
	{
		$casesIds []= $record->Id;
	}

	$parentIds = implode("', '", $casesIds);

	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, AccountId, Status, ISESCALATED, Ingoing_Emails_Count__c, Global_Priority__c', 'Case', $casesIds);
	for ($i=0; $i<count($results); $i++)
	{
		$cases[$results[$i]->Id] = $results[$i];
		$cases[$results[$i]->Id]->countEmails = 0;
		$cases[$results[$i]->Id]->maxDate = null;

		if ($results[$i]->fields->OwnerId)
		{
			$ownerIds[$results[$i]->fields->OwnerId] = false;
			$casesPerOwner[$results[$i]->fields->OwnerId] []= $results[$i]->Id;
		}
		if ($results[$i]->fields->AccountId)
			$accountIds[$results[$i]->fields->AccountId] = false;
	}

	$owners = $mySforceConnection->retrieve('Alias', 'User', array_keys($ownerIds));
	foreach ($owners as $o)
	{
		if (!empty($o->fields->Alias))
			$ownerIds[$o->Id] = $o->fields->Alias;
	}

	foreach (array_keys($ownerIds) as $id)
	{
		if (empty($ownerIds[$id]))
		{
			$group = $mySforceConnection->retrieve('Name', 'Group', array($id));
			$ownerIds[$id] = $group[0]->fields->Name;
		}
	}

	asort($ownerIds);

	$accounts = $mySforceConnection->retrieve('Name', 'Account', array_keys($accountIds));
	foreach ($accounts as $a)
	{
		if (!empty($a->fields->Name))
			$accountIds[$a->Id] = $a->fields->Name;
	}

	$query = "SELECT ParentId, COUNT(Id) countId, MAX(MESSAGEDATE) maxDate FROM EmailMessage WHERE ParentId IN ('$parentIds') AND Incoming=false GROUP BY ParentId";
	$response = $mySforceConnection->query($query);
	$ids = array();

	foreach ($response as $record)
	{
		$cases[$record->fields->ParentId]->countEmails = $record->fields->countId;
		$cases[$record->fields->ParentId]->maxOutgoingDate = $record->fields->maxDate;
	}

	$query = "SELECT ParentId, COUNT(Id) countId, MAX(MESSAGEDATE) maxDate FROM EmailMessage WHERE ParentId IN ('$parentIds') AND Incoming=true GROUP BY ParentId";
	$response = $mySforceConnection->query($query);
	$ids = array();

	foreach ($response as $record)
	{
		$cases[$record->fields->ParentId]->maxIncomingDate = $record->fields->maxDate;
	}

	foreach ($ownerIds as $ownerId => $owner)
	{
		$assigned = 0;
		foreach ($casesPerOwner[$ownerId] as $id)
		{
			if ($cases[$id]->fields->Status !== 'Waiting consultant')
				$assigned++;
		}

		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(' . $assigned . ' pending cases)</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Account</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '	<th>Last outgoing email</th>';
		echo '	<th>Last incoming email</th>';
		echo '	<th>Number of reminder emails</th>';
		echo '	<th>Warnings</th>';
		echo '	<th>Priority</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		$cpt = 0;
		foreach ($casesPerOwner[$ownerId] as $id)
		{
			$c = $cases[$id];
			$isEscalated = ($c->fields->IsEscalated == 'true');

			$tr = '';

			$escalated = '';
			if ($isEscalated)
				$escalated = '&nbsp;<img src="https://eu5.salesforce.com/img/func_icons/util/escalation12.gif" />';

			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a>' . $escalated . '</td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';

			if (!empty($accountIds[$c->fields->AccountId]))
				$tr .= '<td>' . $accountIds[$c->fields->AccountId] . '</td>';
			else
				$tr .= '<td></td>';

			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';

			$reminders = (int)$c->fields->Ingoing_Emails_Count__c;

			if ($reminders < 2)
				$reminders = '<td style="text-align: center">' . $reminders . '</td>';
			else
				$reminders = '<td style="font-weight: bold; text-align: center; font-size: 15px;">' . $reminders . '</td>';

			$priority = (int)$c->fields->Global_priority__c;
			if ($priority <= 10)
			  $priority = '<td style="text-align: center" title="' . $priority . '"><img src="images/thermo1.png" height="32" /></td>';
			else if ($priority <= 20)
			  $priority = '<td style="text-align: center" title="' . $priority . '"><img src="images/thermo2.png" height="32" /></td>';
			else if ($priority <= 30)
			  $priority = '<td style="text-align: center" title="' . $priority . '"><img src="images/thermo3.png" height="32" /></td>';
			else
			  $priority = '<td style="text-align: center" title="' . $priority . '"><img src="images/thermo4.png" height="32" /></td>';

			if ($c->maxOutgoingDate)
			{
				$maxOutgoingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->maxOutgoingDate);
				$tr .= '<td>' . $maxOutgoingDate->format('Y-m-d H:i:s') . '</td>';

				if ($c->maxIncomingDate)
				{
					$maxIncomingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->maxIncomingDate);
					$tr .= '<td>' . $maxIncomingDate->format('Y-m-d H:i:s') . '</td>'; 
				}
				else
					$tr .= '<td></td>';

				$diff = $maxOutgoingDate->diff($now)->days;

				if ($diff < 7)
				{
					if (strpos($c->fields->Status, 'Waiting bug') === 0)
						echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . $reminders . '<td align="center"><img src="./images/bug_green.png" title="Keyze is waiting for a bug to be fixed and delivered" /></td>' . $priority . '</tr>';
					else if ($isEscalated)
						echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . $reminders . '<td align="center"><img src="./images/red_arrow.png" title="Keyze has been escalated" /></td>' . $priority . '</tr>';
					else if ($viewAll)
						echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . $reminders . '<td></td>' . $priority . '</tr>';
					
					continue;
				}

				$tr .= $reminders;

				if (strpos($c->fields->Status, 'Waiting bug') === 0)
        {
					$tr .= '<td align="center"><img src="./images/bug_green.png" title="Keyze is waiting for a bug to be fixed and delivered" /></td>';
        }
				else if ($c->fields->Status == 'Waiting consultant')
				{
					if ($diff < 60)
					{
						$tr .= '<td align="center"><img src="./images/feedback.png" title="Keyze has been waiting for consultant for at least two weeks" /></td>';
						$totals['feedback']++;
					}
					else
					{
						$tr .= '<td align="center"><img src="./images/trash.png" title="Keyze has been waiting for consultant for at least TWO MONTHS" /></td>';
						$totals['trash']++;
					}
				}
				else
				{
					if ($diff > 30)
					{
						$tr .= '<td align="center"><img src="./images/31.png" title="Last staff reply was over a month ago" /></td>';
						$totals[31]++;
					}
					else if ($diff > 14)
					{
						$tr .= '<td align="center"><img src="./images/14.png" title="Last staff reply was at least two weeks ago" /></td>';
						$totals[14]++;
					}
					else
					{
						$tr .= '<td align="center"><img src="./images/7.png" title="Last staff reply was at least a week ago" /></td>';
						$totals[7]++;
					}
				}
			}
			else
			{
				$lastModifiedDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->LastModifiedDate);
				$diff = $lastModifiedDate->diff($now)->days;
				$weekDiff = $now->format('W') - $lastModifiedDate->format('W');

				$diff = $diff - 2*$weekDiff;	// remove weekends

				if ($diff < 2)
				{
					if ($isEscalated)
						echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . '<td></td><td></td>' . $reminders . '<td align="center"><img src="./images/red_arrow.png" title="Keyze has been escalated" /></td>' . $priority . '</tr>';
					else if ($viewAll)
						echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . '<td></td><td></td>' . $reminders . '<td></td>' . $priority . '</tr>';
					continue;
				}

				$tr .= '<td></td>';

				if ($c->maxIncomingDate)
				{
					$maxIncomingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->maxIncomingDate);
					$tr .= '<td>' . $maxIncomingDate->format('Y-m-d H:i:s') . '</td>';
				}
				else
				{
					$tr .= '<td></td>';
				}

				$tr .= $reminders;

				if ($diff < 5)
				{
					$tr .= '<td align="center"><img src="./images/2.png" title="Keyze created at least two working days ago but no staff reply yet" /></td>';
					$totals[2]++;
				}
				else
				{
					$tr .= '<td align="center"><img src="./images/alert.png" title="Keyze created at least ONE WEEK ago but no staff reply yet" /></td>';
					$totals['alert']++;
				}
			}

			$tr .= $priority;

			$tr .= '</tr>';

			if ($cpt++%2)
				$tr = '<tr class="odd">' . $tr;
			else
				$tr = '<tr>' . $tr;

			echo $tr;
		}

		echo '</tbody>';
		echo '</table>';
		echo '<br/>';
	}

	if (empty($_GET['all']))
	{
		unset($totals['feedback']);
		unset($totals['trash']);
	}

	echo '<table width="100%">';
	echo '<tr>';
	foreach ($totals as $i => $count)
	{
		echo "<td style='font-size: 30px; line-height: 30px;' valign=middle><img src='./images/$i.png' valign=bottom /> $count cases</td>";
	}
	echo '</tr>';
	echo '</table>';
	echo '<br/><br/><br/>';
}
catch (Exception $e)
{
  var_dump($e);
}
