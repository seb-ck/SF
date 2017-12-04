<?php
require_once('bootstrap.php');
require_once('head.php');
?>

<h1>Followed cases</h1>

<?php

try
{
	if (!empty($_GET['purge']))
	{
		// Limit the dates for email messages to avoid searching too much volume
		$d1 = new DateTime();
		$d1->setTime(0, 0, 0);
		$d1->sub(new DateInterval('P6M'));
		$d1 = $d1->format('Y-m-d\TH:i:s\Z');

		do
		{
			$query    = "SELECT Id, ParentId, SubscriberId FROM EntitySubscription WHERE Parent.type='Case' AND CreatedDate < $d1 LIMIT 100 OFFSET 0";
			$response = $mySforceConnection->query($query);

			$ids = array();
			foreach ($response as $em)
			{
				$ids [] = $em->Id;
			}

			if (!empty($ids))
			{
				 $mySforceConnection->delete($ids);
			}
		}
		while ($response->size > 0);

		header('location: subscriptions.php');
		exit;
	}

	$filterOwner = '';
	if (!empty($_GET['name']))
	{
		$owner = $mySforceConnection->query("SELECT Id FROM User WHERE Alias='" . strtoupper(filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING)) . "'");
		foreach ($owner as $o)
		{
			$filterOwner = " AND SubscriberId = '{$o->Id}'";
		}
	}

	$query = "SELECT Id, ParentId, SubscriberId FROM EntitySubscription WHERE Parent.type='Case' $filterOwner";
	//$query = "SELECT Id, ParentId, SubscriberId FROM EntitySubscription WHERE Parent.type='Case' AND SubscriberId='00524000000oP8K'"; // ID = SEB
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
  $oldCases = array();

	foreach ($response as $result)
	{
		$casesIds []= $result->ParentId;

		if ($result->fields->SubscriberId)
		{
			$ownerIds[$result->fields->SubscriberId] = false;
			$casesPerOwner[$result->fields->SubscriberId][$result->Id] = $result->ParentId;
		}
	}

	$owners = $mySforceConnection->retrieve('Alias', 'User', array_keys($ownerIds));
	foreach ($owners as $o)
	{
		if (!empty($o->fields->Alias))
			$ownerIds[$o->Id] = $o->fields->Alias;
	}

	asort($ownerIds);

	foreach ($ownerIds as $ownerId => $owner)
	{
		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(' . count($casesPerOwner[$ownerId]) . ' followed cases)</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Owner</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		$fetchCases = $mySforceConnection->retrieve('Id, Subject, CaseNumber, Status, OwnerId, LastModifiedDate, CreatedDate', 'Case', array_values($casesPerOwner[$ownerId]));

		$cases = array();
		foreach ($fetchCases as $c)
		{
			if (empty($c->type))
				continue;

			$cases[$c->Id] = $c;

			if (empty($ownerIds[$c->fields->OwnerId]))
			{
				$owners = $mySforceConnection->retrieve('Alias', 'User', array($c->fields->OwnerId));
        if (!empty($owners) && reset($owners)->fields)
				    $ownerIds[$c->fields->OwnerId] = reset($owners)->fields->Alias;
			}
		}

		$cpt = 0;
		foreach ($casesPerOwner[$ownerId] as $subs => $id)
		{
			if (empty($cases[$id]))
				continue;

			$c = $cases[$id];

			$tr = '';

			$tr .= '<td><a href="' . $SF_URL . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';

      if (!empty($ownerIds[$c->fields->OwnerId]))
			   $tr .= '<td>' . $ownerIds[$c->fields->OwnerId] . '</td>';
      else
        $tr .= '<td title="Owner not from the support team">?</td>';

			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';

			$tr .= '</tr>';

			if ($cpt++%2)
				$tr = '<tr class="odd">' . $tr;
			else
				$tr = '<tr>' . $tr;

      $lastModifiedDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->LastModifiedDate);
      $diff = $lastModifiedDate->diff($now)->days;

      if ($diff > 30 && ($c->fields->Status == 'Closed' || $c->fields->Status == 'Cancelled'))
        $oldCases []= $subs;

      echo $tr;
		}

		echo '</tbody>';
		echo '</table>';
		echo '<br/>';
	}

  if (!empty($_GET['purge']))
  {
    if (!empty($oldCases))
    {
      foreach (array_chunk($oldCases, 100) as $oldc)
        $mySforceConnection->delete($oldc);
    }
  }

	echo '<br/><br/><br/>';
}
catch (Exception $e)
{
  var_dump($e);
}
