<?php
require_once('bootstrap.php');
require_once('head.php');
?>

<style>
	#results, #results td, #results th { border: 1px solid grey; border-collapse: collapse; padding: 5px; }
	#results { width: 100%; }
	#results th { background: lightgrey; }
	#results .odd td { background: #eee; }
	* { font-size: 13px; }
	h2 a { font-size: 1.5em; }
	h1 { font-size: 2.5em; }
</style>

<?php

ini_set('default_socket_timeout', 600);

try 
{
  $mySforceConnection = new SforcePartnerClient();
  $mySoapClient = $mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR.'/partner.wsdl.xml');
  $mylogin = $mySforceConnection->login($USERNAME, $PASSWORD);
	
	$query = "SELECT Id, Subject, CaseNumber, ClosedDate, CreatedDate, OwnerId, AccountId, Status FROM Case WHERE OwnerId='00G24000000sbxJ' ORDER BY CreatedDate DESC";
	$response = $mySforceConnection->query($query);

	if (!empty($_GET['purge']))
	{
		$ids = array();
	
		foreach ($response as $c) 
		{
			$ids []= $c->Id;
			
			if (count($ids) == 100)
			{
				$mySforceConnection->delete($ids);
				$ids = array();
			}
		}
		
		if (!empty($ids))
			$mySforceConnection->delete($ids);
		
		// Limit the dates for email messages to avoid searching too much volume
		$d1 = new DateTime();
		$d1->setTime(0, 0, 0);
		$d1->sub(new DateInterval('P5D'));
		$d1 = $d1->format('Y-m-d\TH:i:s\Z');
	
    do
    {
      $query    = "SELECT Id FROM EmailMessage WHERE CreatedDate >= $d1 AND Subject LIKE 'New case email notification. Case number %' LIMIT 100 OFFSET 0";
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
		
		header('location: spam.php');
		exit;
	}
	
	echo '<h1>' . $response->size . ' cases assigned to "SPAM" <button onclick="if (confirm(\'Are you sure?\')) window.location.href=\'?purge=1\';" style="margin-left: 100px;">Purge!</button></h1>';
	
	echo '<table border=1 cellspacing=0 id=results>';
	echo '<tr>';
	echo '	<th>Case Number</th>';
	echo '	<th>Subject</th>';
	echo '	<th>Status</th>';
	echo '	<th>Creation date</th>';
	echo '</tr>';
	
	$cases = array();
	
	foreach ($response as $c) 
	{
		echo '<tr>';
		echo '<td><a href="' . $SF_URL . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
		echo '<td>' . $c->fields->Subject . '</td>';
		echo '<td>' . $c->fields->Status . '</td>';
		echo '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
		echo '</tr>';
	}
	
	echo '</table>';
} 
catch (Exception $e) 
{
  header('location: spam.php');
}
