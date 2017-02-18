<?php
// */5 * * * * curl --request GET 'http://exemple.com/path/check.php?param1=1&param2=2' > /dev/null

set_time_limit(0);

@mkdir('pdf');

// Set the list of tasks
$tasks = array(
	'cases_and_emails' => 'every monday',
	'spiras' => 'every monday',
	'spam' => 'every day',
//	'picking' => 'every friday',
//	'surveys' => 'every day',
);

// Define the functions
function cases_and_emails()
{
	$url = 'http://support-salesforce.qalmstest.crossknowledge.com/cases_and_emails.php';
	$pdf = file_get_contents('http://html2pdf.lms.crossknowledge.com?c=' . md5('9B1442F5-0F23-15A4-D957-A37F4B1ABF5E' . $url) . '&url=' . urlencode($url));

	@copy($pdf, 'pdf/cases_and_emails_' . date('Y-m-d') . '.pdf');
}

function spiras()
{
	$ctx = stream_context_create(array('http'=>
    array(
			'timeout' => 600,
		)
	));

	$url = 'http://support-salesforce.qalmstest.crossknowledge.com/spiras.php';
	$pdf = file_get_contents('http://html2pdf.lms.crossknowledge.com?c=' . md5('9B1442F5-0F23-15A4-D957-A37F4B1ABF5E' . $url) . '&url=' . urlencode($url), false, $ctx);

	@copy($pdf, 'pdf/spiras_' . date('Y-m-d') . '.pdf');
}

function spam()
{
	file_get_contents('http://support-salesforce.qalmstest.crossknowledge.com/spam.php?purge=1');
}

// Default: just list the tasks, don't actually do anything
if (!isset($_GET['run']))
{
	echo '<h3>Available tasks</h3>';
	echo '<pre>';
	print_r($tasks);
	echo '</pre>';
	
	echo '<h3>Available GET parameters</h3>';
	echo ' -- run=1 - run the daily tasks<br/>';
	echo ' -- force=XXX - force run the task XXX<br/>';
	die;
}

// Force one specific task
if (isset($_GET['force']))
{
	if (!isset($tasks[$_GET['force']]))
	{
		echo 'Error: task not found';
		die;
	}
	
	$_GET['force']();
	die;
}

// Process the daily tasks
switch (date('w'))
{
	case 0:
		break;
	
	case 1:
		cases_and_emails();
		spiras();
		break;
	
	case 2:
		break;
	
	case 3:
		break;
	
	case 4:
		break;
	
	case 5:
		// picking();
		break;
	
	case 6:
		break;
	
	case 7:
		break;
}

// Finally, run the everyday tasks
spam();
