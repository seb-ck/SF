<?php
require_once('bootstrap.php');
require_once('head.php');

$name = !empty($_GET['name']) ? preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['name']) : '';

?>

  <h1 style="float: left;">Level 3 backlog</h1>

    <form method="get" id="warningsForm">
      <?php if ($name) echo '<input type="hidden" name="name" value="' . $name . '" />' ?>

      <div style="float: right; text-align: left; white-space: nowrap; border: 1px solid #000; padding: 10px; margin: 10px;">
        <span><input type="checkbox" name="all" id="all" value="1" <?= (!empty($_GET['all']) ? 'checked="checked"' : '') ?> onclick="this.form.submit()" /><label for="all">Show completed tasks</label></span>
      </div>
    </form>

<?php

try
{
  $all = empty($_GET['all']) ? "AND Status__c != 'Completed' AND Status__c != 'Cancelled'" : '';
  $alias = $name ? " AND Owner__r.Alias = '$name'" : '';

	$d = new DateTime();
	$d->sub(new DateInterval('P6M'));
	$d = $d->format('Y-m-d\TH:i:s\Z');
  $date = "AND CreatedDate >= $d";

	$query = "SELECT Status__c, CreatedBy.Alias, Minutes__c, Owner__r.Alias,
                Case__r.CaseNumber, Case__r.Subject, Case__r.Owner.Alias, Case__r.Status, Case__r.IsEscalated,
                Case__r.Account.Name, Case__r.CreatedDate, Case__r.Id, CreatedDate
            FROM Timesheet__c
            WHERE ((Status__c != '' AND Status__c != '-') OR Owner__r.Alias = 'SF')
              $all
              $alias
              $date
            ORDER BY Owner__r.Alias";

	$response = $mySforceConnection->query($query);
	$timesheets = array();
	$owners = array();
	$now = new DateTime();

  foreach ($response as $record)
  {
    $owner = !empty($record->fields->Owner__r->fields->Alias) ? $record->fields->Owner__r->fields->Alias : $record->fields->CreatedBy->fields->Alias;
    $timesheets[$owner] []= $record;
  }

	foreach (array_keys($timesheets) as $owner)
	{
		$assigned = count($timesheets[$owner]);

		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(' . $assigned . ' pending tasks)</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Case Status</th>';
		echo '	<th>Case Creation</th>';
		echo '	<th>Account</th>';
		echo '	<th>Task status</th>';
		echo '	<th>Task creation</th>';
		echo '	<th>Time spent</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		$cpt = 0;
		foreach ($timesheets[$owner] as $ts)
		{
      $c = $ts->fields->Case__r;
			$isEscalated = ($c->fields->IsEscalated == 'true');

			$tr = '';

			$escalated = '';
			if ($isEscalated)
				$escalated = '&nbsp;<img src="https://eu5.salesforce.com/img/func_icons/util/escalation12.gif" />';

			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a>' . $escalated . '</td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';

			if (!empty($c->fields->Account->fields->Name))
				$tr .= '<td>' . $c->fields->Account->fields->Name . '</td>';
			else
				$tr .= '<td></td>';

      $tr .= '<td>' . $ts->fields->Status__c . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $ts->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . (int)$ts->fields->Minutes__c . '</td>';

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
}
catch (Exception $e)
{
  var_dump($e);
}
