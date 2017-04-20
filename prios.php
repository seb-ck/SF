<?php
require_once('bootstrap.php');

if (!empty($_GET['mail']))
	ob_start();

require_once('head.php');
?>

<h1>All ongoing BU priorities</h1>

<?php

try
{
	$cpt = 0;

	$query = "SELECT Id, Subject, CaseNumber, CreatedDate, Account.Name, Owner.Alias, Status , Most_recent_reply_sent__c, Most_recent_incoming_email__c, Spira__c
						FROM Case 
						WHERE Type='Technical Support' AND IsClosed = False AND Status != 'Cancelled' AND (IsEscalated = true OR Was_Escalated__c = true)
						ORDER BY Most_recent_incoming_email__c DESC NULLS LAST, Most_recent_reply_sent__c DESC NULLS LAST";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$casesPerOwner = array();
	$accounts = array();

	echo '<table id="results">';

	echo '<tr>';
	echo '	<th>Case Number</th>';
	echo '	<th>Subject</th>';
	echo '	<th>Owner</th>';
	echo '	<th>Status</th>';
	echo '	<th>Incident status</th>';
	echo '	<th>Account</th>';
	echo '	<th>Creation date</th>';
	echo '	<th>Latest staff reply</th>';
	echo '	<th>Latest incoming email</th>';
	echo '</tr>';

	foreach ($response as $c)
	{
		$tr = '';

		$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
		if (strlen($c->fields->Subject) > 50)
			$tr .= '<td title="' . $c->fields->Subject . '">' . substr($c->fields->Subject, 0, 50) . '...</td>';
		else
			$tr .= '<td>' . $c->fields->Subject . '</td>';
		$tr .= '<td>' . $c->fields->Owner->fields->Alias . '</td>';
		$tr .= '<td>' . $c->fields->Status . '</td>';

		$spira = array_map('trim', preg_split('/([, \s])+/', $c->fields->SPIRA__c));
		list($spiraHtml, $releaseHtml, $allClosed, $foundIncidents, $minStatus, $releaseDates) = parseIncidents($spira);
		$tr .= '<td>' . $spiraHtml . '</td>';

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

	echo '</table>';

	if (!empty($_GET['mail']) && isset($PRIOS_RECIPIENTS))
	{
		$subject = 'SalesForce ongoing BU priorities';
		$body = ob_get_contents();
		ob_end_clean();
		sendMail($subject, $body, array(), $PRIOS_RECIPIENTS);
	}
}
catch (Exception $e)
{
  var_dump($e);
}
