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