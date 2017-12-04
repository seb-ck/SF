<?php
require_once('bootstrap.php');
require_once('head.php');

if (!empty($_GET['send']))
{
	ob_start();
}

try
{
	$query = "SELECT id, Name, title__c FROM survey__c WHERE Actif__c =true";
	$response = $mySforceConnection->query($query);

	$surveyId = $response->current()->Id;

	$d1 = new DateTime();
	$d1->sub(new DateInterval('P1D'));
	$d1 = $d1->format('Y-m-d\TH:i:s\Z');	// 2016-01-01T00:00:00Z

	$d2 = new DateTime();
	$d2->sub(new DateInterval('P5D'));
	$d2 = $d2->format('Y-m-d\TH:i:s\Z');

	$query = "SELECT Id FROM Case WHERE TYPE IN ('Technical Support', 'IT Exploitation', 'Hotline') AND Status = 'Closed' AND Survey_Sent__c = False AND ClosedDate >= $d2 AND ClosedDate <= $d1 AND Contact.Email != '' ORDER BY ClosedDate DESC";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = [];
	$cases = [];
	$owners = [];
	$accounts = [];
	$contactIds = [];
	$now = new DateTime();
	$updates = [];

	foreach ($response as $record)
	{
		$casesIds []= $record->Id;
	}

	if (empty($casesIds))
		exit;

	$parentIds = implode("', '", $casesIds);

	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, ClosedDate, CreatedDate, Owner.Alias, Account.Name, Status, Contact.Email', 'Case', $casesIds);
	for ($i=0; $i<count($results); $i++)
	{
		$cases[$results[$i]->Id] = $results[$i];
		$cases[$results[$i]->Id]->countEmails = 0;
		$cases[$results[$i]->Id]->maxDate = null;
	}

	echo '<h1>Surveys not sent (cases recently closed)</h1>';

	echo '<table border=1 cellspacing=0 id=results>';
	echo '<thead>';
	echo '<tr>';
	echo '	<th>Case Number</th>';
	echo '	<th>Subject</th>';
	echo '	<th>Status</th>';
	echo '	<th>Owner</th>';
	echo '	<th>Account</th>';
	echo '	<th>Contact email</th>';
	echo '	<th>Creation date</th>';
	echo '	<th>Closed date</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	$cpt = 0;
	foreach ($cases as $c)
	{
		$tr = '';

		$tr .= '<td><a href="' . $SF_URL . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
		$tr .= '<td>' . $c->fields->Subject . '</td>';
		$tr .= '<td>' . $c->fields->Status . '</td>';

		if (!empty($c->fields->Owner->fields->Alias))
			$tr .= '<td>' . $c->fields->Owner->fields->Alias . '</td>';
		else
			$tr .= '<td></td>';

		if (!empty($c->fields->Account->fields->Name))
			$tr .= '<td>' . $c->fields->Account->fields->Name . '</td>';
		else
			$tr .= '<td></td>';

		if (!empty($c->fields->Contact->fields->Email))
			$tr .= '<td>' . $c->fields->Contact->fields->Email . '</td>';
		else
			$tr .= '<td></td>';

		$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
		$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->ClosedDate) . '</td>';

		$tr .= '</tr>';

		if ($cpt++%2)
			$tr = '<tr class="odd">' . $tr;
		else
			$tr = '<tr>' . $tr;

		echo $tr;

		if (!empty($_GET['send']))
		{
			$case = new SObject();
			$case->type = 'Case';
			$case->Id = $c->Id;
			$case->fields = new stdClass();
			$case->fields->Survey_Sent__c = true;
			$case->fields->IDSurvey__c = $surveyId;

			$updates []= $case;

			if (count($updates) >= 20)
			{
				$mySforceConnection->update($updates);
				$updates = [];
			}
		}
	}

	if (!empty($updates))
		$mySforceConnection->update($updates);

	echo '</tbody>';
	echo '</table>';
	echo '<br/><br/><br/>';
}
catch (Exception $e)
{
  var_dump($e);
}

if (!empty($_GET['send']))
{
	ob_end_clean();
}
