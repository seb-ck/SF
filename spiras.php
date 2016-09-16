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
	
	
	/*
	$fields = ($mySforceConnection->describeSObject('Case'));
	foreach ($fields->fields as $f)
			echo $f->name . '<br/>';
	*/
	
	
	
	$query = "SELECT Id FROM Case WHERE TYPE='Technical Support' AND SPIRA__c != '' AND IsClosed = False AND Status NOT IN ('Waiting consultant', 'On hold') ORDER BY CreatedDate ASC";
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
	
	$results = $mySforceConnection->retrieve('Id, Subject, CaseNumber, LASTMODIFIEDDATE, CreatedDate, OwnerId, SPIRA__c, Status', 'Case', $casesIds);
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
	
	$projectIds = array(14, 10, 8, 9, 29, 15, 25, 26, 12, 27, 24);
	
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
	
	//foreach ($ownerIds as $ownerId => $owner)
	{
		echo '<table border=1 cellspacing=0 id=results>';
		echo '<thead>';
		echo '<tr>';
		echo '	<th>Case Number</th>';
		echo '	<th>Subject</th>';
		echo '	<th>Case status</th>';
		echo '	<th>Owner</th>';
		echo '	<th>Incident status</th>';
		echo '	<th>Creation date</th>';
		echo '	<th>Last modification date</th>';
		echo '	<th>Warnings</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';
		
		$cpt = 0;
		$toClose = 0;
		$warnings = 0;
		
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
				
			$spira = array_map('trim', preg_split('/([, \s])+/', $c->fields->SPIRA__c));
			$spiraHtml = '';
			$allClosed = true;
			$foundIncidents = array();
			$minStatus = 5;
			
			foreach ($spira as $incident)
			{
				$projectIds = array(14, 10, 8, 9, 29, 15, 25, 26, 12, 27, 24);
			
				if (preg_match('/\[RQ:(\d+)\]/', $incident, $matches))
				{
					$rq = $matches[1];
					
					foreach ($projectIds as $project)
					{
						$data = @json_decode(file_get_contents("http://thefactory.crossknowledge.com/Services/v4_0/RestService.svc/projects/14/requirements/$rq?username=sebastien.fabre&api-key={35769756-F0B8-47B7-85F8-8A80380E88AC}"));
						
						if (empty($data) || empty($data->StatusName))
						{
							continue;
						}
						
						$foundIncidents[$data->RequirementId] = $data->StatusName;
						
						if ($data->StatusName !== 'Completed')
							$allClosed = false;
						
						if ($data->StatusName == 'Completed' || $data->StatusName == 'Rejected/Obsolete')
							$minStatus = min($minStatus, 5);
						else if ($data->StatusName == 'Dev Done' || $data->StatusName == 'Val in progress')
							$minStatus = min($minStatus, 4);
						else if ($data->StatusName == 'Val Done' || $data->StatusName == 'Doc & UAT')
							$minStatus = min($minStatus, 3);
						else
							$minStatus = min($minStatus, 1);
						
						$spiraHtml .= '<a href="https://thefactory.crossknowledge.com/14/Requirement/' . intval($rq) . '.aspx">' . 'RQ:' . intval($rq) . '</a>: ' .  $data->StatusName . '<br/>';
						break;
					}
				}
				else if (preg_match('/\[IN:(\d+)\]/', $incident, $matches))
				{
					$incident = $matches[1];
					
					foreach ($projectIds as $project)
					{
						$data = @json_decode(file_get_contents("http://thefactory.crossknowledge.com/Services/v4_0/RestService.svc/projects/14/incidents/$incident?username=sebastien.fabre&api-key={35769756-F0B8-47B7-85F8-8A80380E88AC}"));
						
						if (empty($data) || empty($data->IncidentStatusName))
						{
							continue;
						}
						
						$foundIncidents[$data->IncidentId] = $data->IncidentStatusOpenStatus;
						
						if ($data->IncidentStatusOpenStatus)
							$allClosed = false;
						
						$minStatus = min($minStatus, substr($data->IncidentStatusName, 0, 1));
						
						$spiraHtml .= '<a href="https://thefactory.crossknowledge.com/14/Incident/' . intval($incident) . '.aspx">' . intval($incident) . '</a>: ' .  $data->IncidentStatusName . '<br/>';
						break;
					}
				}
				else
				{
					if (preg_match('@.*thefactory.crossknowledge.com/(\d+)/Incident/(\d+).*@', $incident, $m))
					{
						$projectIds = array($m[1]);
						$incident = $m[2];
					}
					
					foreach ($projectIds as $project)
					{
						$data = @json_decode(file_get_contents("http://thefactory.crossknowledge.com/Services/v4_0/RestService.svc/projects/$project/incidents/$incident?username=sebastien.fabre&api-key={35769756-F0B8-47B7-85F8-8A80380E88AC}"));
						
						if (empty($data) || empty($data->IncidentStatusName))
						{
							continue;
						}
						
						$foundIncidents[$data->IncidentId] = $data->IncidentStatusOpenStatus;
						
						if ($data->IncidentStatusOpenStatus)
							$allClosed = false;
						
						$minStatus = min($minStatus, substr($data->IncidentStatusName, 0, 1));
						
						$spiraHtml .= '<a href="https://thefactory.crossknowledge.com/14/Incident/' . intval($incident) . '.aspx">' . intval($incident) . '</a>: ' .  $data->IncidentStatusName . '<br/>';
						break;
					}
				}
			}
			
			if (empty($spiraHtml))
				$spiraHtml = $c->fields->SPIRA__c;
				
			$tr .= '<td>' . $spiraHtml . '</td>';
			
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->CreatedDate) . '</td>';
			$tr .= '<td>' . str_replace(array('T', '.000Z'), array(' ', ''), $c->fields->LastModifiedDate) . '</td>';
			
			if (empty($spira) || count($spira) != count($foundIncidents))
			{
				$tr .= '<td align=center><img src="404.png" title="At least one of the incidents could not be found" /></td>';
				$warnings++;
			}
			else if ($allClosed)
			{
				$tr .= '<td align=center><img src="warning.png" title="Support case might need to be closed" /></td>';
				$toClose++;
			}
			else 
			{
				if (($minStatus < 3 && $c->fields->Status != 'Waiting bug resolution')
					|| ($minStatus >= 3 && $minStatus < 5 && $c->fields->Status != 'Waiting bug validation')
					|| ($minStatus == 5 && $c->fields->Status != 'Waiting bug delivery'))
				{
					$tr .= '<td align=center><img src="gnome-status.png" title="Case status might need to be updated" /></td>';
					$warnings++;
				}
				else
				{
					$tr .= '<td></td>';
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
		echo '<p style="font-size: 30px; font-weight: bold; text-align: center;">' . $warnings . ' cases might need to be updated.</p>';
		echo '<p style="font-size: 30px; font-weight: bold; text-align: center;">' . $toClose . ' cases might need to be closed.</p>';
		echo '<br/><br/><br/>';
	}
} 
catch (Exception $e) 
{
  var_dump($e);
}
