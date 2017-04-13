<?php
require_once('bootstrap.php');

if (!empty($_GET['mail']))
	ob_start();

require_once('head.php');
?>

<h1>All active BU priorities</h1>

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

	$query = "SELECT Id, Subject, CaseNumber, CreatedDate, OwnerId, Account.Name, Status , Most_recent_reply_sent__c, Most_recent_incoming_email__c 
						FROM Case 
						WHERE Type='Technical Support' AND IsClosed = False AND Status != 'Cancelled' AND (IsEscalated = true OR Was_Escalated__c = true)
						ORDER BY LastModifiedDate DESC";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$casesPerOwner = array();
	$accounts = array();

	foreach ($response as $record)
	{
		$cases[$record->Id] = $record;
		$cases[$record->Id]->countEmails = 0;
		$cases[$record->Id]->maxDate = null;

		if ($record->fields->OwnerId)
		{
			$casesPerOwner[$record->fields->OwnerId][$record->Id] = $record->Id;
		}
	}

	echo '<table id="results" style="border: none;">';

	foreach (array_keys($supportUsers) as $ownerId)
	{
		if (empty($casesPerOwner[$ownerId]))
			continue;

		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $supportUsers[$ownerId] . '</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Account</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Latest staff reply</th>';
		echo '	<th>Latest incoming email</th>';
		echo '</tr>';


		$cpt = 0;
		foreach ($casesPerOwner[$ownerId] as $id)
		{
			$c = $cases[$id];

			$tr = '';

			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';

			if (!empty($c->fields->Account->fields->Name))
				$tr .= '<td>' . $c->fields->Account->fields->Name . '</td>';
			else
				$tr .= '<td></td>';

			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->Most_recent_reply_sent__c) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->Most_recent_incoming_email__c) . '</td>';

			$tr .= '</tr>';

			if ($cpt++%2)
				$tr = '<tr class="odd">' . $tr;
			else
				$tr = '<tr>' . $tr;

			echo $tr;
		}

		echo '<tr>';
		echo '	<td colspan=11 style="border: none;">&nbsp;</td>';
		echo '</tr>';
	}

	echo '</table>';

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
