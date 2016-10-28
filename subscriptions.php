<?php
require_once('conf.php');
require_once('head.php');
?>

<h1>Followed cases</h1>

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

	$query = "SELECT ParentId, SubscriberId FROM EntitySubscription";
	//$query = "SELECT ParentId, SubscriberId FROM EntitySubscription WHERE SubscriberId='00524000000oP8K'"; // ID = SEB
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
		$casesIds []= $record->ParentId;
	}
	
	foreach ($response as $result)
	{
		$cases[$result->Id] = $result;
		
		if ($result->fields->SubscriberId)
		{
			$ownerIds[$result->fields->SubscriberId] = false;
			$casesPerOwner[$result->fields->SubscriberId] []= $result->ParentId;
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
		
		$fetchCases = $mySforceConnection->retrieve('Id, Subject, CaseNumber, Status, OwnerId, LastModifiedDate, CreatedDate', 'Case', $casesPerOwner[$ownerId]);
		
		$cases = array();
		foreach ($fetchCases as $c)
		{
			if (empty($c->type))
				continue;
			
			$cases[$c->Id] = $c;
			
			if (empty($ownerIds[$c->fields->OwnerId]))
			{
				$owners = $mySforceConnection->retrieve('Alias', 'User', array($c->fields->OwnerId));
				$ownerIds[$c->fields->OwnerId] = reset($owners)->fields->Alias;
			}
		}
		
		$cpt = 0;
		foreach ($casesPerOwner[$ownerId] as $id)
		{
			if (empty($cases[$id]))
				continue;
		
			$c = $cases[$id];
			
			$tr = '';
			
			$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
			$tr .= '<td>' . $c->fields->Subject . '</td>';
			$tr .= '<td>' . $c->fields->Status . '</td>';
			
			$tr .= '<td>' . $ownerIds[$c->fields->OwnerId] . '</td>';
			
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';
			
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
