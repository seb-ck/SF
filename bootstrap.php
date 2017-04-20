<?php
require_once('conf.php');
set_time_limit(0);

// Include various files
include_once('htmlMimeMail/htmlMimeMail.php');

// Include SF libraries
define("SOAP_CLIENT_BASEDIR", "Force.com-Toolkit-for-PHP-master/soapclient");
require_once (SOAP_CLIENT_BASEDIR.'/SforcePartnerClient.php');
require_once (SOAP_CLIENT_BASEDIR.'/SforceHeaderOptions.php');

// Initialize connection to SF API
$mySforceConnection = new SforcePartnerClient();
$mySoapClient = $mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR.'/partner.wsdl.xml');
$mylogin = $mySforceConnection->login($USERNAME, $PASSWORD);

// TODO: put this in cache somewhere

// Preload the list of support members
$supportUsers = array();
$response = $mySforceConnection->query("SELECT id, alias FROM user  WHERE id in (SELECT userorgroupid FROM groupmember WHERE group.name = 'Tech Support Group') ORDER BY Alias ASC");
foreach ($response as $user)
{
	$supportUsers[$user->Id] = $user->fields->Alias;
}


// Functions
function sendMail($subject, $body, $attachments, $recipients)
{
	global $SMTP_HOST;

	$mail = new htmlMimeMail();
	$mail->setHeadCharset('UTF-8');
	$mail->setHtmlCharset('UTF-8');
	$mail->setTextCharset('UTF-8');

	$mail->smtp_params['host'] = $SMTP_HOST;
	$mail->setFrom('noreply@crossknowledge.com');
	$mail->setSubject($subject);
	$mail->setHtml($body);

	foreach ($attachments as $att)
		$mail->addAttachment($mail->getFile($att), basename($att));

	$mail->send($recipients, 'smtp');
}

function parseIncidents($spira)
{
	$spiraHtml = '';
	$releaseHtml = '';
	$allClosed = true;
	$foundIncidents = array();
	$minStatus = 5;
	$releaseDates = array();

	foreach ($spira as $incident)
	{
		if (preg_match('/\[RQ:(\d+)\]/', $incident, $matches) || preg_match('@.*thefactory.crossknowledge.com/(\d+)/Requirement/(\d+).*@', $incident, $matches))
		{
			$rq = $matches[1];

			$releaseData = getSpiraReq($rq);
			if (!empty($releaseData))
			{
				$releaseId = $releaseData['RELEASE_ID'];
				$projectId = $releaseData['PROJECT_ID'];
				$statusName = $releaseData['REQUIREMENT_STATUS_NAME'];
				$release = $releaseData['RELEASE_VERSION_NUMBER'];

				if (!empty($releaseId))
				{
					$releaseData = getSpiraRelease($releaseId);
					$releaseDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000', $releaseData['CUST_01'], new DateTimeZone('UTC'));
					if (!empty($releaseDate))
						$releaseDates []= $releaseDate->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
				}

				$releaseHtml .= '<a href="https://thefactory.crossknowledge.com/' . $projectId . '/Release/' . intval($releaseId) . '.aspx">' . $release . '</a><br/>';

				$foundIncidents[intval($rq)] = $statusName;

				if ($statusName !== 'DONE' && $statusName !== 'REJECTED')
					$allClosed = false;

				if ($statusName == 'DONE' || $statusName == 'REJECTED')
					$minStatus = min($minStatus, 5);
				else if ($statusName == 'VAL')
					$minStatus = min($minStatus, 4);
				else if ($statusName == 'DEV')
					$minStatus = min($minStatus, 3);
				else
					$minStatus = min($minStatus, 1);

				$spiraHtml .= '<a href="https://thefactory.crossknowledge.com/' . $projectId . '/Requirement/' . intval($rq) . '.aspx">' . $incident . '</a>: ' .  $statusName . '<br/>';
			}
		}
		else
		{
			if (preg_match('/\[IN:(\d+)\]/', $incident, $matches))
			{
				$incident = $matches[1];
			}
			else if (preg_match('@.*thefactory.crossknowledge.com/(\d+)/Incident/(\d+).*@', $incident, $m))
			{
				$incident = $m[2];
			}

			if (is_numeric($incident))
			{
				$data = getSpiraIncident($incident);

				if (!empty($data))
				{
					$release = $data['VERIFIED_RELEASE_VERSION_NUMBER'];
					$releaseId = $data['VERIFIED_RELEASE_ID'];
					$projectId = $data['PROJECT_ID'];
					$statusName = $data['INCIDENT_STATUS_NAME'];

					if (empty($releaseId))
						$releaseId = $data['RESOLVED_RELEASE_ID'];

					if (!empty($releaseId))
					{
						$releaseData = getSpiraRelease($releaseId);
						$releaseDate = DateTime::createFromFormat('Y-m-d\TH:i:s.000', $releaseData['CUST_01'], new DateTimeZone('UTC'));
						if (!empty($releaseDate))
							$releaseDates []= $releaseDate->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
					}

					$foundIncidents[intval($incident)] = $data['INCIDENT_STATUS_IS_OPEN_STATUS'];

					$releaseHtml .= '<a href="https://thefactory.crossknowledge.com/' . $projectId . '/Release/' . intval($releaseId) . '.aspx">' . $release . '</a><br/>';

					if ($data['INCIDENT_STATUS_IS_OPEN_STATUS'])
						$allClosed = false;

					$minStatus = min($minStatus, substr($data['INCIDENT_STATUS_IS_OPEN_STATUS'], 0, 1));

					$spiraHtml .= '<a href="https://thefactory.crossknowledge.com/' . $projectId . '/Incident/' . intval($incident) . '.aspx">' . intval($incident) . '</a>: ' .  $statusName . '<br/>';
				}
			}
			else
			{
				$spiraHtml .= $incident;
			}
		}
	}

	return array($spiraHtml, $releaseHtml, $allClosed, $foundIncidents, $minStatus, $releaseDates);
}


function getSpiraIncident($incidentId) {
	$query= 'select
			*
                        from
                                 RPT_INCIDENTS
                        left join RPT_CUSTOM_LIST_VALUES c2 on CAST(c2.CUSTOM_PROPERTY_VALUE_ID as varchar) = RPT_INCIDENTS.CUST_19
			LEFT JOIN RPT_RELEASES ON RPT_RELEASES.RELEASE_ID = RPT_INCIDENTS.RESOLVED_RELEASE_ID
                        where
                                RPT_INCIDENTS.INCIDENT_ID=' . $incidentId;


	$query_result = mssql_query($query);
	$aRow = mssql_fetch_array($query_result, MSSQL_ASSOC);
	if ($aRow)
		return $aRow;
	else
		return null;
}

function getSpiraReq($reqId) {
	$query = 'select
				*
                  from
				RPT_REQUIREMENTS
		FULL JOIN RPT_RELEASES ON RPT_RELEASES.RELEASE_ID = RPT_REQUIREMENTS.RELEASE_ID
                  where
				REQUIREMENT_ID = "' . $reqId . '"';

	$query_result = mssql_query($query);
	$aRow = mssql_fetch_array($query_result, MSSQL_ASSOC);
	if ($aRow)
		return $aRow;
	else
		return null;
}


function getSpiraRelease($relId) {
	$query = 'select
                                *
                  from
                                RPT_RELEASES
                  where
                                RELEASE_ID = "' . $relId . '"';

	$query_result = mssql_query($query);
	$aRow = mssql_fetch_array($query_result, MSSQL_ASSOC);
	if ($aRow)
		return $aRow;
	else
		return null;
}