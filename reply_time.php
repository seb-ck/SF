<?php
require_once('conf.php');
require_once('head.php');
?>

<h1>Average reply time</h1>

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
	
	$sixMonthsAgo = new DateTime();
	$sixMonthsAgo->sub(new DateInterval('P6M'));
	$sixMonthsAgo = $sixMonthsAgo->format('Y-m-d\TH:i:s.000\Z');
	$query = "SELECT OwnerId, Owner.Alias FROM Case WHERE TYPE='Technical Support' AND IsClosed = True AND CreatedDate >= $sixMonthsAgo AND Status != 'Cancelled' AND OwnerId != '00G24000000sKGtEAM' GROUP BY OwnerId, Owner.Alias ORDER BY Owner.Alias ASC";
	$response = $mySforceConnection->query($query);
	$parentIds = array();
	$casesIds = array();
	$cases = array();
	foreach ($response as $re)
	{
		$ownerId = $re->fields->OwnerId;
		$owner = $re->fields->Alias;
		
		// DEBUG SEB
		if ($ownerId !== '00524000000oP8KAAU')
			continue;
	
		$query = "SELECT Id, Subject, CaseNumber, LastModifiedDate, CreatedDate, OwnerId, AccountId, Status, IsEscalated FROM Case WHERE TYPE='Technical Support' AND IsClosed = True AND CreatedDate >= $sixMonthsAgo AND Status != 'Cancelled' AND OwnerId = '$ownerId' ORDER BY LASTMODIFIEDDATE DESC";
		$response = $mySforceConnection->query($query);
		$now = new DateTime();
		
		$casesIds = array();
		foreach ($response as $record)
		{
			$casesIds []= $record->Id;
			$cases[$record->Id] = $record;
		}
		
		$parentIds = implode("','", $casesIds);
	
		$query = "SELECT ParentId, Incoming, MIN(MESSAGEDATE) minDate, MAX(MESSAGEDATE) maxDate FROM EmailMessage WHERE ParentId IN ('$parentIds') GROUP BY ParentId, Incoming";
		$query = "SELECT ParentId, MIN(MESSAGEDATE) minDate, MAX(MESSAGEDATE) maxDate FROM EmailMessage WHERE ParentId IN ('$parentIds') GROUP BY ParentId";
		$response2 = $mySforceConnection->query($query);
		$ids = array();
		
		foreach ($response2 as $record) 
		{
			if (empty($record->fields))
				continue;
				
				var_dump($record);
		
			$cases[$record->fields->ParentId]->minOutgoingDate = $record->fields->minDate;
			$cases[$record->fields->ParentId]->maxOutgoingDate = $record->fields->maxDate;
		}
	
		echo '<table id="results">';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span></th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Status</th>';
		echo '	<th>Account</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '	<th>First outgoing email</th>';
		echo '	<th>Last outgoing email</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';
		
		$cpt = 0;
		foreach ($cases as $c)
		{/*
			if ($cpt++%2)
				$tr = '<tr class="odd">';
			else
				$tr = '<tr>';
			
			$tr .= '<td>' . print_r($c, true) . '</td>';
			
			$tr .= '</tr>';
			
			echo $tr;
			continue;
			*/
			
		
			//$c = $cases[$id];
			
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
			$tr .= '<td>' . $c->countEmails . '</td>';
			
			if ($c->minOutgoingDate)
			{
				$minOutgoingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->minOutgoingDate);
				$tr .= '<td>' . $minOutgoingDate->format('Y-m-d H:i:s') . '</td>';
			}
			else
			{
				$tr .= '<td></td>';
			}
			
			if ($c->maxOutgoingDate)
			{
				$maxOutgoingDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $c->maxOutgoingDate);
				$tr .= '<td>' . $maxOutgoingDate->format('Y-m-d H:i:s') . '</td>';
			}
			else
			{
				$tr .= '<td></td>';
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
	
	echo '<br/><br/><br/>';
} 
catch (Exception $e) 
{
  var_dump($e);
}
