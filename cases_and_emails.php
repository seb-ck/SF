<?php
require_once('bootstrap.php');
require_once('head.php');

$d2 = new DateTime();
$d2->sub(new DateInterval('P15D'));
$d2 = $d2->format('Y-m-d\TH:i:s\Z');

$viewAll = !empty($_GET['all']);
$all = $viewAll ? ", 'On hold'" :  '';
$wc = " OR (Status = 'Waiting Consultant'" . (!$viewAll ? " AND LastModifiedDate <= $d2)" : ')');
$wb = (!empty($_GET['wb']) ? " OR Status LIKE 'Waiting Bug%'" : '');
$filter = "(Status IN ('Open', 'Assigned'$all)$wc $wb)";
$name = !empty($_GET['name']) ? preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['name']) : '';

?>

  <h1 style="float: left;">SLA Warnings board</h1>

  <form method="get" id="warningsForm">
    <div style="float: right; text-align: left; white-space: nowrap; border: 1px solid #000; padding: 10px; margin: 10px;">
			<span>Show only cases assigned to <?= getSupportUsersSelect($name) ?></span>
      <span><input type="checkbox" name="all" id="all" value="1" <?= (!empty($_GET['all']) ? 'checked="checked"' : '') ?> onclick="this.form.submit()" /><label for="all">Show ongoing cases without warning</label></span>
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

	$query = "SELECT Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, Owner.Id, Status, ISESCALATED, Ingoing_Emails_Count__c, Global_Priority__c, Owner.Alias, Account.Id, Account.Name
						FROM Case WHERE Type='Technical Support' AND $filter AND OwnerId != '00G240000014Hsp' $filterOwner ORDER BY Global_priority__c DESC";
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

	$totals = array('alert' => 0, 2 => 0, 7 => 0, 14 => 0, 31 => 0, 60 => 0, 100 => 0, 'feedback' => 0, 'trash' => 0);
	if ($wb)
		$totals['bug_green'] = 0;

	foreach ($response as $record)
	{
		$casesIds []= $record->Id;

		$cases[$record->Id] = $record;
		$cases[$record->Id]->countEmails = 0;
		$cases[$record->Id]->maxDate = null;

		if ($record->fields->Owner->Id)
		{
			$ownerIds[$record->fields->Owner->Id] = $record->fields->Owner->fields->Alias;
			$casesPerOwner[$record->fields->Owner->Id] []= $record->Id;
		}

		if (!empty($record->fields->Account->Id))
		{
			$accountIds[$record->fields->Account->Id] = $record->fields->Account->fields->Name;
		}
	}

	$parentIds = implode("', '", $casesIds);

	foreach (array_keys($ownerIds) as $id)
	{
		if (empty($ownerIds[$id]))
		{
			$group = $mySforceConnection->retrieve('Name', 'Group', array($id));
			$ownerIds[$id] = $group[0]->fields->Name;
		}
	}

	asort($ownerIds);

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
				$escalated = '&nbsp;<img src="' . $SF_URL . 'img/func_icons/util/escalation12.gif" />';

			$tr .= '<td><a href="' . $SF_URL . $c->Id . '">' . $c->fields->CaseNumber . '</a>' . $escalated . '</td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';

			if (!empty($c->fields->Account) && !empty($accountIds[$c->fields->Account->Id]))
				$tr .= '<td>' . $accountIds[$c->fields->Account->Id] . '</td>';
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
          {
            echo '<tr' . ($cpt++%2 ? ' class="odd"' : '') . '>' . $tr . $reminders . '<td align="center"><img src="./images/bug_green.png" title="Keyze is waiting for a bug to be fixed and delivered" /></td>' . $priority . '</tr>';
            $totals['bug_green']++;
					}
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
					$totals['bug_green']++;
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
					if ($diff > 99)
					{
						$tr .= '<td align="center"><img src="./images/100.png" title="Last staff reply was more than a hundred days ago" width=32 height=32 /></td>';
						$totals[100]++;
					}
					else if ($diff > 60)
					{
						$tr .= '<td align="center"><img src="./images/60.png" title="Last staff reply was over two months ago" width=32 height=32 /></td>';
						$totals[60]++;
					}
					else if ($diff > 30)
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

	echo '<table width="100%">';
	echo '<tr>';
	foreach ($totals as $i => $count)
	{
		echo "<td style='font-size: 30px; line-height: 30px;' valign=middle><img src='./images/$i.png' valign=bottom width=32 height=32 /> $count cases</td>";
	}
	echo '</tr>';
	echo '</table>';
	echo '<br/><br/><br/>';
}
catch (Exception $e)
{
  var_dump($e);
}
