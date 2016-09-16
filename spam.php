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
	h1 { font-size: 2.5em; }
</style>

<?php

include_once('c:/www/krumo/class.krumo.php');
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
	
	
	
	
	$query = "SELECT Id FROM EmailMessage WHERE Subject LIKE 'New case email notification. Case number %'";
	$response = $mySforceConnection->query($query);
	
	$ids = array();
	foreach ($response as $em) 
	{
			$ids []= $em->Id;
			
			if (count($ids) == 50)
			{
				$mySforceConnection->delete($ids);
				$ids = array();
			}
		}
		
		if (!empty($ids))
			$mySforceConnection->delete($ids);
		
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
		echo '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
		echo '<td>' . $c->fields->Subject . '</td>';
		echo '<td>' . $c->fields->Status . '</td>';
		echo '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
		echo '</tr>';
	}
	
	echo '</table>';
} 
catch (Exception $e) 
{
  krumo($e);
}
