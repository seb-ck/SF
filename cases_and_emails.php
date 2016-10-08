<?php
require_once('conf.php');
require_once('head.php');
?>

<h1>SLA Warnings board</h1>

<?php

set_time_limit(0);

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

	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND STATUS IN ('Open', 'Assigned') AND OWNERID != '00G240000014Hsp' ORDER BY LASTMODIFIEDDATE DESC";
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
	$totals = array('alert' => 0, 2 => 0, 7 => 0, 14 => 0, 31 => 0);
	
	foreach ($response as $record) 
	{
		$casesIds []= $record->Id;
	}
	
	$parentIds = implode("', '", $casesIds);
	
	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, AccountId, Status', 'Case', $casesIds);
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
	
	$accounts = $mySforceConnection->retrieve('Name', 'Account', array_keys($accountIds));
	foreach ($accounts as $a)
	{
		if (!empty($a->fields->Name))
			$accountIds[$a->Id] = $a->fields->Name;
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
	
	foreach ($ownerIds as $ownerId => $owner)
	{
		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(' . count($casesPerOwner[$ownerId]) . ' pending cases)</th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Owner</th>';
		echo '	<th>Account</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '	<th>Outgoing emails</th>';
		echo '	<th>Last outgoing email</th>';
		echo '	<th>Last incoming email</th>';
		echo '	<th>Warnings</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';
		
		$cpt = 0;
		foreach ($casesPerOwner[$ownerId] as $id)
		{
			$c = $cases[$id];
			
			$tr = '';
			
			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';
			
			if (!empty($ownerIds[$c->fields->OwnerId]))
				$tr .= '<td>' . $ownerIds[$c->fields->OwnerId] . '</td>';
			else
				$tr .= '<td></td>';
			
			if (!empty($accountIds[$c->fields->AccountId]))
				$tr .= '<td>' . $accountIds[$c->fields->AccountId] . '</td>';
			else
				$tr .= '<td></td>';
			
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';
			$tr .= '<td>' . $c->countEmails . '</td>';
			
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
					continue;
					
				if ($diff > 30)
				{
					$tr .= '<td align="center"><img src="./31.png" title="Last staff reply was over a month ago" /></td>';
					$totals[31]++;
				}
				else if ($diff > 14)
				{
					$tr .= '<td align="center"><img src="./14.png" title="Last staff reply was at least two weeks ago" /></td>';
					$totals[14]++;
				}
				else
				{
					$tr .= '<td align="center"><img src="./7.png" title="Last staff reply was at least a week ago" /></td>';
					$totals[7]++;
				}
			}
			else
			{
				$lastModifiedDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->LastModifiedDate);
				$diff = $lastModifiedDate->diff($now)->days;
				$weekDiff = $now->format('W') - $lastModifiedDate->format('W');

				$diff = $diff - 2*$weekDiff;	// remove weekends
				
				if ($diff < 2)
					continue;
				
				$tr .= '<td></td>';
				
				if ($c->maxIncomingDate)
				{
					$maxIncomingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->maxIncomingDate);
					$tr .= '<td>' . $maxIncomingDate->format('Y-m-d H:i:s') . '</td>';
				}
				else
					$tr .= '<td></td>';
				
				if ($diff < 5)
				{
					$tr .= '<td align="center"><img src="./2.png" title="Keyze created at least two working days ago but no staff reply yet" /></td>';
					$totals[2]++;
				}
				else
				{
					$tr .= '<td align="center"><img src="./alert.png" title="Keyze created at least ONE WEEK ago but no staff reply yet" /></td>';
					$totals['alert']++;
				}
			}
			
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
		echo "<td style='font-size: 30px; line-height: 30px;' valign=middle><img src='./$i.png' valign=bottom /> $count cases</td>";
	}
	echo '</tr>';
	echo '</table>';
	echo '<br/><br/><br/>';
} 
catch (Exception $e) 
{
  var_dump($e);
}
