<?php

if (!empty($_GET['send']))
{
	ob_start();
}

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
	
	$query = "SELECT id, Name, title__c FROM survey__c WHERE Actif__c =true";
	$response = $mySforceConnection->query($query);
	
	$surveyId = $response->current()->Id;
	
	$d1 = new DateTime();
	$d1->sub(new DateInterval('P1D'));
	$d1 = $d1->format('Y-m-d\TH:i:s\Z');	// 2016-01-01T00:00:00Z
	
	$d2 = new DateTime();
	$d2->sub(new DateInterval('P8D'));
	$d2 = $d2->format('Y-m-d\TH:i:s\Z');
	
	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND Status = 'Closed' AND Survey_Sent__c = False AND ClosedDate >= $d2 AND ClosedDate <= $d1 AND Contact.Email != '' ORDER BY ClosedDate DESC";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$owners = array();
	$accounts = array();
	$contactIds = array();
	$now = new DateTime();
	
	foreach ($response as $record) 
	{
		$casesIds []= $record->Id;
	}
	
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
		
		$tr .= '<td><a href="https://eu5.salesforce.com/' . $c->Id . '">' . $c->fields->CaseNumber . '</a></td>';
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
			
			$mySforceConnection->update(array($case));
		}
	}
	
	echo '</tbody>';
	echo '</table>';
	echo '<br/><br/><br/>';
} 
catch (Exception $e) 
{
  var_dump($e);
}

ob_end_clean();
