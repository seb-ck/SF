<?php
require_once('bootstrap.php');
require_once('head.php');
?>

<h1>Reply time from staff, assignment delays <small style="vertical-align: middle;">(last six months)</small></h1>

<?php

function formatDate($d)
{
	if ($d->d == 1)
		return $d->format('1 day %h hours');
	
	if ($d->d > 0)
		return $d->format('%d days %h hours');
	
	if ($d->h == 1)
		return $d->format('%h hour');
	
	if ($d->h == 0)
		return '-';
	
	return $d->format('%h hours');
}

function getHours($d)
{
	return $d->d * 24 + $d->h;
}

try 
{
	$sixMonthsAgo = new DateTime();
	$sixMonthsAgo->sub(new DateInterval('P6M'));
//	$sixMonthsAgo->sub(new DateInterval('P6D'));
	$sixMonthsAgo = $sixMonthsAgo->format('Y-m-d\TH:i:s.000\Z');
	$parentIds = [];
	$casesIds = [];
	$cases = [];
	
	$avg = [];
	$avgUsers = [];
	$avgMonths = [];
	$avgUsersMonths = [];
	$months = [];

	$avgBU = [];
	$avgUsersBU = [];
	$avgMonthsBU = [];
	$avgUsersMonthsBU = [];
	
	foreach ($supportUsers as $ownerId => $owner)
	{
		// DEBUG SEB
//		if ($ownerId !== '00524000000oP8KAAU')
	//		continue;
	
		$query = "SELECT Id, Subject, CaseNumber, CreatedDate, ClosedDate, OwnerId, Status, First_Response__c, IsEscalated, Was_Escalated__c
							FROM Case 
							WHERE TYPE='Technical Support' AND IsClosed = True AND ClosedDate >= $sixMonthsAgo AND Status != 'Cancelled' AND OwnerId = '$ownerId' AND (NOT Subject LIKE 'Potentially crashed%')
							ORDER BY ClosedDate DESC";
		$response = $mySforceConnection->query($query);
		$now = new DateTime();
		
		$casesIds = [];
		foreach ($response as $record)
		{
			$casesIds []= $record->Id;
			$cases[$record->Id] = $record;
		}
		
		$parentIds = implode("','", $casesIds);
	
		echo '<table class="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=12 align=left><span style="font-size: 1.5em">' . $owner . '</span></th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>&nbsp;</th>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>&nbsp;</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Assignment date</th>';
		echo '	<th>First staff reply</th>';
		echo '	<th>&nbsp;</th>';
		echo '	<th>Creation date &rArr; Assignment date</th>';
		echo '	<th>Creation date &rArr; first reply</th>';
		echo '	<th>Assignment date &rArr; first reply</th>';
		echo '	<th>&nbsp;</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';

		$assSql = "SELECT CaseId, CreatedDate, Field, OldValue, NewValue FROM CaseHistory WHERE Field = 'Owner' AND CaseId IN ('$parentIds')";
		$assResponse = $mySforceConnection->query($assSql);

		$assignments = [];
		foreach ($assResponse as $assr)
		{
		  if (strpos($assr->fields->OldValue, ' ') !== false 
				&& $assr->fields->NewValue !== 'Technical Support'
				&& $assr->fields->NewValue !== 'IT Exploitation')
					$assignments[$assr->fields->CaseId] []= $assr;
		}

		$cpt = 0;
		foreach ($cases as $c)
		{
		  if (empty($assignments[$c->Id]) || empty($c->First_Response__c))
		    continue;

			$escalated = ($c->fields->IsEscalated == 'true' || $c->fields->Was_Escalated__c == 'true');
			$firstResponse = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->First_Response__c);
			$creationDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->CreatedDate);
			$closedDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->ClosedDate);
			$assignmentDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $assignments[$c->Id][0]->fields->CreatedDate);

			$diffAssignmentCreated = $creationDate->diff($assignmentDate);
			$diffFirstCreated = $creationDate->diff($firstResponse);
			$diffFirstAssignment = $assignmentDate->diff($firstResponse);
			
			if ($diffFirstAssignment->invert)
				$diffFirstAssignment = new DateInterval('PT0S');
				
			$h = [getHours($diffAssignmentCreated), getHours($diffFirstCreated), getHours($diffFirstAssignment)];
			for ($i=0; $i<3; $i++)
			{
				@$avg[$i] += $h[$i];
				@$avgUsers[$owner][$i] += $h[$i];
				@$avgUsersMonths[$owner][$closedDate->format('Y-m')][$i] += $h[$i];
				@$avgMonths[$closedDate->format('Y-m')][$i] += $h[$i];

				if ($escalated)
				{
					@$avgBU[$i] += $h[$i];
					@$avgUsersBU[$owner][$i] += $h[$i];
					@$avgUsersMonthsBU[$owner][$closedDate->format('Y-m')][$i] += $h[$i];
					@$avgMonthsBU[$closedDate->format('Y-m')][$i] += $h[$i];
				}
			}
			
			@$avg[3]++;
			@$avgUsers[$owner][3]++;
			@$avgUsersMonths[$owner][$closedDate->format('Y-m')][3]++;
			@$avgMonths[$closedDate->format('Y-m')][3]++;
			$months[$closedDate->format('Y-m')] = $closedDate->format('Y-m');

			if ($escalated)
			{
				@$avgBU[3]++;
				@$avgUsersBU[$owner][3]++;
				@$avgUsersMonthsBU[$owner][$closedDate->format('Y-m')][3]++;
				@$avgMonthsBU[$closedDate->format('Y-m')][3]++;
			}
			
			$tr = '';
			$tr .= '<th>&nbsp;</th>';
			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			if (strlen($c->fields->Subject) > 50)
			  $tr .= '<td title="' . $c->fields->Subject . '">' . substr($c->fields->Subject, 0, 50) . '...</td>';
			else
			  $tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<th>&nbsp;</th>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
      $tr .= '<td><pre>' . $assignmentDate->format('Y-m-d H:i:s') . '</pre></td>';
      $tr .= '<td>' . $firstResponse->format('Y-m-d H:i:s') . '</td>';
      $tr .= '<th>&nbsp;</th>';
      $tr .= '<td align=right>' . formatDate($diffAssignmentCreated) . '</td>';
      $tr .= '<td align=right>' . formatDate($diffFirstCreated) . '</td>';
      $tr .= '<td align=right>' . formatDate($diffFirstAssignment) . '</td>';
			$tr .= '<th>&nbsp;</th>';
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

	echo '<br/><br/>';

	echo '<h1>Average first reply time after being assigned a case (hours)</h1>';

	ksort($months);

	echo '<table class="results">';
	echo '<thead>';
	echo '<tr>';
	echo '	<th>Staff</th>';
	foreach ($months as $month)
	{
		echo '<th>' . $month . '</th>';
	}
	echo '<th>Totals</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($supportUsers as $userId => $owner)
	{
		echo '<tr>';
		echo '<th>' . $owner . '</th>';

		foreach ($months as $month)
		{
			if (isset($avgUsersMonths[$owner][$month]))
				echo '<td>' . floor($avgUsersMonths[$owner][$month][2] / $avgUsersMonths[$owner][$month][3]) . '</td>';
			else
				echo '<td> </td>';
		}

		if (isset($avgUsers[$owner]))
			echo '<th>' . floor($avgUsers[$owner][2] / $avgUsers[$owner][3]) . '</th>';
		else
			echo '<th> </th>';

		echo '</tr>';
	}

	echo '<tr>';
	echo '	<th>Totals</th>';
	foreach ($months as $month)
	{
		if (isset($avgMonths[$month]))
			echo '<th>' . floor($avgMonths[$month][2] / $avgMonths[$month][3]) . '</th>';
		else
			echo '<th> </th>';
	}

	echo '<th>' . floor($avg[2] / $avg[3]) . '</th>';
	echo '</tr>';

	echo '</tbody>';
	echo '</table>';

	echo '<br/><br/>';

	echo '<h1>.... Only for BU priorities</h1>';

	echo '<table class="results">';
	echo '<thead>';
	echo '<tr>';
	echo '	<th>Staff</th>';
	foreach ($months as $month)
	{
		echo '<th>' . $month . '</th>';
	}
	echo '<th>Totals</th>';
	echo '</tr>';
	echo '</thead>';

	echo '<tbody>';

	foreach ($supportUsers as $userId => $owner)
	{
		echo '<tr>';
		echo '<th>' . $owner . '</th>';

		foreach ($months as $month)
		{
			if (isset($avgUsersMonthsBU[$owner][$month]))
				echo '<td>' . floor($avgUsersMonthsBU[$owner][$month][2] / $avgUsersMonthsBU[$owner][$month][3]) . '</td>';
			else
				echo '<td> </td>';
		}

		if (isset($avgUsersBU[$owner]))
			echo '<th>' . floor($avgUsersBU[$owner][2] / $avgUsersBU[$owner][3]) . '</th>';
		else
			echo '<th> </th>';

		echo '</tr>';
	}

	echo '<tr>';
	echo '	<th>Totals</th>';
	foreach ($months as $month)
	{
		if (isset($avgMonthsBU[$month]))
			echo '<th>' . floor($avgMonthsBU[$month][2] / $avgMonthsBU[$month][3]) . '</th>';
		else
			echo '<th> </th>';
	}

	echo '<th>' . floor($avgBU[2] / $avgBU[3]) . '</th>';
	echo '</tr>';

	echo '</tbody>';
	echo '</table>';

	echo '<br/><br/>';

	echo '<h1>Average delay between case creation and assignment</h1>';

	echo '<table class="results">';
	echo '<thead>';
	echo '<tr>';
	foreach ($months as $month)
	{
		echo '<th>' . $month . '</th>';
	}
	echo '<th>Totals</th>';
	echo '</tr>';

	echo '<tr>';

	foreach ($months as $month)
	{
		if (isset($avgMonths[$month]))
			echo '<td>' . floor($avgMonths[$month][0] / $avgMonths[$month][3]) . '</td>';
		else
			echo '<td> </td>';
	}

	if (isset($avg[$owner]))
		echo '<th>' . floor($avg[0] / $avg[3]) . '</th>';
	else
		echo '<th> </th>';

	echo '</tr>';
	echo '</tbody>';
	echo '</table>';
}
catch (Exception $e) 
{
  var_dump($e);
}
