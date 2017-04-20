<?php
require_once('conf.php');
require_once('head.php');
?>

	<h1>Cases with linked incidents</h1>

<?php

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

	$spiraConnection = mssql_connect($SPIRA_HOST, $SPIRA_USERNAME, $SPIRA_PASSWORD);
	mssql_select_db($SPIRA_DATABASE, $spiraConnection);


/*
$query = "SELECT * FROM information_schema.tables";
$query_result = mssql_query($query);
while ($row = mssql_fetch_array($query_result, MSSQL_ASSOC))
var_dump($row);

$query = "exec sp_columns RPT_RELEASES";
$query_result = mssql_query($query);
while ($row = mssql_fetch_array($query_result, MSSQL_ASSOC))
var_dump($row['COLUMN_NAME']);

var_dump(getSpiraReq(7595));
die;
*/

	$filterOwner = '';
	if (!empty($_GET['name']))
	{
		$owner = $mySforceConnection->query("SELECT Id FROM User WHERE Alias='" . strtoupper(filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING)) . "'");
		foreach ($owner as $o)
		{
			$filterOwner = " AND OwnerId = '{$o->Id}'";
		}
	}

	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND SPIRA__c != '' AND IsClosed = False AND Status NOT IN ('Waiting consultant', 'On hold') $filterOwner ORDER BY CreatedDate ASC";
	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND SPIRA__c != '' $filterOwner ORDER BY CreatedDate ASC";
	$response = $mySforceConnection->query($query);
	$parentIds = '';
	$casesIds = array();
	$cases = array();
	$casesPerOwner = array();
	$owners = array();
	$ownerIds = array();
	$accounts = array();
	$accountIds = array();
	$releases = array();
	$now = new DateTime();

	foreach ($response as $record)
	{
		$casesIds []= $record->Id;
	}

	$parentIds = implode("', '", $casesIds);

	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, SPIRA__c, Status', 'Case', $casesIds);
	for ($i=0; $i<count($results); $i++)
	{
		$cases[$results[$i]->Id] = $results[$i];
		$cases[$results[$i]->Id]->maxDate = null;

		if ($results[$i]->fields->OwnerId)
		{
			$ownerIds[$results[$i]->fields->OwnerId] = false;
			$casesPerOwner[$results[$i]->fields->OwnerId] []= $results[$i]->Id;
		}
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

	$cpt = 0;
	$toClose = 0;
	$warnings = 0;
	$soon = 0;

	foreach ($ownerIds as $ownerId => $owner)
	{
		echo '<table border=1 cellspacing=0 id=results>';
		echo '<thead>';
		echo '<tr>';
		echo '	<th colspan=11 align=left><span style="font-size: 1.5em">' . $owner . '</span></th>';
		echo '</tr>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Case status</th>';
		echo '	<th>Owner</th>';
		echo '	<th>Incident status</th>';
		echo '	<th>Targeted release</th>';
		echo '	<th>Case creation date</th>';
		echo '	<th>Case last update date</th>';
		echo '	<th>Warnings</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

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

			$spira = array_map('trim', preg_split('/([, \s])+/', $c->fields->SPIRA__c));

			list($spiraHtml, $releaseHtml, $allClosed, $foundIncidents, $minStatus, $releaseDates) = parseIncidents($spira);

			if ($allClosed && ($c->fields->Status == 'Closed' || $c->fields->Status == 'Cancelled') )
				continue;

			if (empty($spiraHtml))
				$spiraHtml = $c->fields->SPIRA__c;

			$tr .= '<td>' . $spiraHtml . '</td>';

			$tr .= '<td>' . $releaseHtml . '</td>';

			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';

			if (empty($spira) || count($spira) != count($foundIncidents))
			{
				$tr .= '<td align=center><img src="images/404.png" title="At least one of the incidents could not be found" /></td>';
				$warnings++;
			}
			else if ($allClosed)
			{
				if (!empty($releaseDates) && max($releaseDates) < date("Y-m-d"))
				{
					$tr .= '<td align=center><img src="images/trash.png" title="Support case needs to be closed" /></td>';
					$toClose++;
				}
				else
				{
					$tr .= '<td align=center><img src="images/soon.png" title="Support case will be delivered soon" /></td>';
					$soon++;
				}
			}
			else
			{
				if (($minStatus < 5 && $c->fields->Status != 'Waiting bug resolution')
					|| ($minStatus == 5 && $c->fields->Status != 'Waiting bug delivery'))
				{
					$tr .= '<td align=center><img src="images/refresh.png" title="Status of case or incident might need to be updated" width=32 height=32/></td>';
					$warnings++;
				}
				else
				{
					//$tr .= '<td></td>';
					continue;
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

	echo '<p style="font-size: 30px; font-weight: bold; text-align: center;">' . $cpt . ' cases with pending incidents, or incident with pending case.</p>';
	echo '<table style="width: 100%"><tr>';
	echo '<td style="font-size: 30px; line-height: 30px;" valign="middle"><img src="images/refresh.png" width=32 height=32 valign="bottom" /> ' . $warnings . ' cases</td>';
	echo '<td style="font-size: 30px; line-height: 30px;" valign="middle"><img src="images/soon.png" valign="bottom" /> ' . $soon . ' cases</td>';
	echo '<td style="font-size: 30px; line-height: 30px;" valign="middle"><img src="images/trash.png" valign="bottom" /> ' . $toClose . ' cases</td>';
	echo '</tr></table>';
	echo '<br/><br/><br/>';
}
catch (Exception $e)
{
  var_dump($e);
}
