<?php
header('Content-Type: text/html; charset=utf-8');
?>

<style>
	#results, #results td, #results th { border: 1px solid grey; border-collapse: collapse; padding: 5px; }
	#results { width: 100%; }
	#results th { background: lightgrey; }
	#results .odd td { background: #eee; }
	* { font-size: 13px; }
	h2 a { font-size: 1.5em; }
</style>

<?php

set_time_limit(0);

require_once('conf.php');

define("SOAP_CLIENT_BASEDIR", "Force.com-Toolkit-for-PHP-master/soapclient");
require_once (SOAP_CLIENT_BASEDIR.'/SforcePartnerClient.php');
require_once (SOAP_CLIENT_BASEDIR.'/SforceHeaderOptions.php');

/*
	Case fields:
	ID	ISDELETED	CASENUMBER	CONTACTID	ACCOUNTID	COMMUNITYID	PARENTID	SUPPLIEDNAME	SUPPLIEDEMAIL	SUPPLIEDPHONE	SUPPLIEDCOMPANY	TYPE	RECORDTYPEID	STATUS	REASON	ORIGIN	SUBJECT	PRIORITY	DESCRIPTION	ISCLOSED	CLOSEDDATE	ISESCALATED	OWNERID	CREATEDDATE	CREATEDBYID	LASTMODIFIEDDATE	LASTMODIFIEDBYID	SYSTEMMODSTAMP	LASTVIEWEDDATE	LASTREFERENCEDDATE	CREATORFULLPHOTOURL	CREATORSMALLPHOTOURL	CREATORNAME	
	THEME__C	PRODUCT_VERSION__C	ACCOUNT_NAME_TEMP__C	CASE_IMPORT_ID__C	SPIRA__C	MANTIS__C	CONTACT_EMAIL_IMPORT__C	GEOGRAPHICAL_ZONE__C	NO_TYPE_REFRESH__C	ACTIVITY__C	BACK_IN_QUEUE__C	TIMESPENT_MN__C	SURVEY_SENT__C	MOST_RECENT_REPLY_SENT__C	MOST_RECENT_INCOMING_EMAIL__C	NEW_EMAIL__C	REQUEST_TYPE__C	URL__C	LOGIN__C	PASSWORD__C	BROWSER__C	REPRODUCTION_STEP__C	ASSOCIATED_DEADLINE__C	RELATED_TICKET__C	CC__C	CATEGORIES__C	BILLABLE__C	LANGUAGE__C	KAYAKO_ID__C	COMMENTAIRE_SURVEY__C	IDSURVEY__C	TIME_SPENT_BILLABLE__C	SURVEY_SENT_DATE__C	CONTACT_EMAIL_FOR_INTERNAL_USE__C	OPENED_ON_BEHALF_CUSTOMER__C	BU__C

	EmailMessage fields:
	ID	PARENTID	ACTIVITYID	CREATEDBYID	CREATEDDATE	LASTMODIFIEDDATE	LASTMODIFIEDBYID	SYSTEMMODSTAMP	TEXTBODY	HTMLBODY	HEADERS	SUBJECT	FROMNAME	FROMADDRESS	TOADDRESS	CCADDRESS	BCCADDRESS	INCOMING	HASATTACHMENT	STATUS	MESSAGEDATE	ISDELETED	REPLYTOEMAILMESSAGEID	ISEXTERNALLYVISIBLE																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																								


*/

try 
{
  $mySforceConnection = new SforcePartnerClient();
  $mySoapClient = $mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR.'/partner.wsdl.xml');
  $mylogin = $mySforceConnection->login($USERNAME, $PASSWORD);
	
	
	
	
	if (!empty($_GET['debug']))
	{
	/*
		$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND Bu__c = '' AND AccountId != '0012400000AA0TT' AND Status='Closed' ORDER BY LASTMODIFIEDDATE ASC";
		$response = $mySforceConnection->query($query);
		
		foreach ($response as $record) 
		{
			$casesIds []= $record->Id;
		}
		*/
		
		$casesIds = array('5002400000EuqXeAAJ');
		
		
		$results = $mySforceConnection->retrieve('Id, ContactId', 'Case', $casesIds);
		
		echo '<pre>';
		print_r($mySforceConnection->describeSObject('Contact'));
		echo '</pre>';
		
		
		for ($i=0; $i<count($results); $i++)
		{
		
			$contact = $mySforceConnection->retrieve('Id, AccountId', 'Contact', array($results[$i]->fields->ContactId));
			
			if (empty($contact) || empty($contact[0]))
				continue;
			
			$account = $mySforceConnection->retrieve('Id, BU__c', 'Account', array($contact[0]->fields->AccountId));
			
			if (empty($account) || empty($account[0]))
				continue;
		
			$results[$i]->fields->BU__c = $account[0]->fields->BU__c;
			unset($results[$i]->fields->ContactId);
			
			var_dump($results[$i]);
			
			//$mySforceConnection->update(array($results[$i]));
		}
		
		
		
		die;
	}
	
	
	
	
	//$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND Bu__c = '' AND AccountId = '0012400000AA0TT' AND Status='Closed' ORDER BY CREATEDDATE DESC LIMIT 200";
	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND Bu__c IN ('', 'WW') AND Status='Closed' AND ClosedDate >= LAST_N_DAYS:7 ORDER BY CREATEDDATE DESC";
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
	
	foreach ($response as $record) 
	{
		$casesIds []= $record->Id;
	}
	
	if (empty($casesIds))
	{
		die('no result found');
	}
	
	
	/*
	$casesIds = array_slice($casesIds, 0, 2);
	
	
	$results = $mySforceConnection->retrieve('Id, AccountId, LastModifiedDate', 'Case', $casesIds);
	
	for ($i=0; $i<count($results); $i++)
	{
		$results[$i]->LastModifiedDate = $results[$i]->ClosedDate;
		
		$mySforceConnection->update(array($results[$i]));
	}
	
	echo '<pre>';
	print_r($mySforceConnection->describeSObject('Case'));

	die;
	*/
	
	$parentIds = implode("', '", $casesIds);
	
	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, AccountId, Status, BU__c, ContactId', 'Case', $casesIds);
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
	
	if (!empty($accountIds))
	{
		$accounts = $mySforceConnection->retrieve('Name, BU__c', 'Account', array_keys($accountIds));
		foreach ($accounts as $a)
		{
			if (!empty($a->fields->Name))
				$accountIds[$a->Id] = $a->fields;
		}
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
	
	$casesBus = array();
	
	//foreach ($ownerIds as $ownerId => $owner)
	{
		echo '<table border=1 cellspacing=0 id=results>';
		echo '<thead>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Owner</th>';
		echo '	<th>Case Account</th>';
		echo '	<th>Real Account</th>';
		echo '	<th>Contact</th>';
		echo '	<th>Current BU</th>';
		echo '	<th>Real BU</th>';
		//echo '	<th>Creation date</th>';
		//echo '	<th>Last modification date</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';
		
		$cpt = 0;
		foreach ($cases as $c)
		{
			//$c = $cases[$id];
			
			$tr = '';
			
			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';
			
			if (!empty($ownerIds[$c->fields->OwnerId]))
				$tr .= '<td>' . $ownerIds[$c->fields->OwnerId] . '</td>';
			else
				$tr .= '<td></td>';
			
			if (!empty($accountIds[$c->fields->AccountId]))
				$tr .= '<td>' . $accountIds[$c->fields->AccountId]->Name . '</td>';
			else
				$tr .= '<td></td>';
				
			$newBU = '';
			$accountName = '';
			$contactName = '';
			
			if (!empty($c->fields->ContactId))
			{
				$contact = $mySforceConnection->retrieve('Id, AccountId, Name, BU__c', 'Contact', array($c->fields->ContactId));
				
				// Try to retrieve the BU from the Contact's Account
				if (!empty($contact[0]) && !empty($contact[0]->fields))
				{
					$contactName = $contact[0]->fields->Name;
					$account = $mySforceConnection->retrieve('Id, BU__c, Name', 'Account', array($contact[0]->fields->AccountId));
					
					if (!empty($account[0]) && !empty($account[0]->fields))
					{
						$newBU = $account[0]->fields->BU__c;
						$accountName = $account[0]->fields->Name;
					}
				}
				
				// Try to retrieve the BU from the Contact 
				if (empty($newBU) && !empty($contact[0]->fields->Name))
				{
					$newBU = $contact[0]->fields->BU__c;
				}
			}
				
			
			if (!empty($c->fields->AccountId))
			{
				// Try to retrieve the BU from the Account
				if (empty($newBU) && !empty($accountIds[$c->fields->AccountId]))
				{
					$newBU = $accountIds[$c->fields->AccountId]->BU__c;
				}
			}
			
			$tr .= '<td>' . $accountName . '</td>';
			$tr .= '<td>' . $contactName . '</td>';
			$tr .= '<td>' . $c->fields->BU__c . '</td>';
			$tr .= '<td>' . $newBU . '</td>';
			
			//$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			//$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';
			$tr .= '</tr>';
			
			if ($cpt++%2)
				$tr = '<tr class="odd">' . $tr;
			else
				$tr = '<tr>' . $tr;
			
			echo $tr;
			
			if (!empty($newBU))
			{
				$c->fields->BU__c = $newBU;
				
				//$mySforceConnection->update(array($c));
				$casesBus[$c->Id] = $newBU;
			}
		}
		
		if (!empty($casesBus))
		{
			$results = $mySforceConnection->retrieve('Id, BU__c', 'Case', array_keys($casesBus));
			
			for ($i=0; $i<count($results); $i++)
			{
				$results[$i]->fields->BU__c = $casesBus[$results[$i]->Id];
				$mySforceConnection->update($results);
			}
		}
		
		echo '</tbody>';
		echo '</table>';
		echo '<br/><br/><br/>';
	}
} 
catch (Exception $e) 
{
  var_dump($e);
}
