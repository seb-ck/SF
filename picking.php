<?php
require_once('bootstrap.php');

if (!empty($_GET['mail']))
	ob_start();

require_once('head.php');
?>

<h1>Picking</h1>

<?php

try
{
	// Last week dates
	$d1 = new DateTime();
	$d1->setTime(0, 0, 0);
	$w = $d1->format('w') - 1;
	$d1->sub(new DateInterval('P' . $w . 'D'));

	$d2 = clone $d1;
	$d1 = $d1->format('Y-m-d\TH:i:s\Z');

	$d2->setTime(0, 0, 0);
	$d2->sub(new DateInterval('P7D'));
	$d2 = $d2->format('Y-m-d\TH:i:s\Z');

	$query = "SELECT Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, AccountId, Status, ISESCALATED 
						FROM Case 
						WHERE TYPE='Technical Support' AND IsClosed = True AND ClosedDate >= $d2 AND ClosedDate < $d1 
							AND Status = 'Closed' AND Language__c IN ('FR', 'EN', '')
							AND OwnerId IN ('" . implode("', '", array_keys($supportUsers)) . "')";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$casesPerOwner = array();
	$accounts = array();
	$accountIds = array();

	foreach ($response as $record)
	{
		$cases[$record->Id] = $record;
		$cases[$record->Id]->countEmails = 0;
		$cases[$record->Id]->maxDate = null;

		if ($record->fields->OwnerId)
		{
			$casesPerOwner[$record->fields->OwnerId][$record->Id] = $record->Id;
		}
		if ($record->fields->AccountId)
			$accountIds[$record->fields->AccountId] = false;
	}

	$accounts = $mySforceConnection->retrieve('Name', 'Account', array_keys($accountIds));
	foreach ($accounts as $a)
	{
		if (!empty($a->fields->Name))
			$accountIds[$a->Id] = $a->fields->Name;
	}

	foreach (array_keys($supportUsers) as $ownerId)
	{
		if (empty($casesPerOwner[$ownerId]))
			continue;

		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $supportUsers[$ownerId] . '</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Account</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		$ccc = array_rand($casesPerOwner[$ownerId], 2);

		$cpt = 0;
		foreach ($ccc as $id)
		{
			$c = $cases[$id];

			$tr = '';

			$escalated = '';
			if ($c->fields->IsEscalated == 'true')
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

			$tr .= '</tr>';

			if ($cpt++%2)
				$tr = '<tr class="odd">' . $tr;
			else
				$tr = '<tr>' . $tr;

			echo $tr;
		}

		echo '</tbody>';
		echo '</table>';
		echo '<br />';
	}

	if (!empty($_GET['mail']) && isset($PICKING_RECIPIENTS))
	{
		$subject = 'SalesForce Weekly picking';
		$body = ob_get_contents();
		ob_end_clean();
		sendMail($subject, $body, array(), $PICKING_RECIPIENTS);
	}
}
catch (Exception $e)
{
  var_dump($e);
}
