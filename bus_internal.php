<!DOCTYPE html>
<meta charset="utf-8">
<style>

.chart div {
  font: 10px sans-serif;
  background-color: steelblue;
  text-align: right;
  padding: 3px;
  margin: 1px;
  color: white;
}

</style>

<script src="https://d3js.org/d3.v4.min.js"></script>

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
		echo '<pre>';
		print_r($mySforceConnection->describeSObject('Case'));
		echo '</pre>';
	*/
	
		$query = "SELECT BU__c, Contact.AccountId, Contact.Account.Name, CALENDAR_YEAR(CreatedDate) year, CALENDAR_MONTH(CreatedDate) month, COUNT(Id) countId
							FROM Case 
							WHERE type = 'Technical support' AND OwnerId != '00G24000000sbxJ'
							GROUP BY BU__c, Contact.AccountId, Contact.Account.Name, CALENDAR_YEAR(CreatedDate), CALENDAR_MONTH(CreatedDate)";
		//$query = "SELECT COUNT(Id) countId FROM Case WHERE BU__c = 'France 1' AND CreatedDate >= 2016-01-01T00:00:00Z AND type = 'Technical support' AND OwnerId != '00G24000000sbxJ' AND ContactId = ''";
		$response = $mySforceConnection->query($query);
		
		// Retrieve the accounts attached to the cases
		$accountIds = array();
		$casesCount = array();
		
		foreach ($response as $record) 
		{
			$accountIds[$record->fields->AccountId] = $record->fields->countId;
			$date = $record->fields->year . '-' . str_pad($record->fields->month, 2, "0", STR_PAD_LEFT);
			
			if (!isset($casesCount[$record->fields->BU__c][$date]))
				$casesCount[$record->fields->BU__c][$date] = array(
					'customers' => 0,
					'crossknowledge' => 0,
				);
			
			if (!isset($casesCount['Consolidated'][$date]))
				$casesCount['Consolidated'][$date] = array(
					'customers' => 0,
					'crossknowledge' => 0,
				);
		}
		
		// And finally retrieve
		$accounts = $mySforceConnection->retrieve('Id, ParentId, Name', 'Account', array_values(array_filter(array_keys($accountIds))));
		$parentAccounts = array();
		
		foreach ($accounts as $account)
		{
			$parentAccounts[$account->Id] = $account->fields->ParentId;
		}
		
		foreach ($response as $record) 
		{
			$accountId = $record->fields->AccountId;
			$date = $record->fields->year . '-' . str_pad($record->fields->month, 2, "0", STR_PAD_LEFT);
			
			if (isset($parentAccounts[$accountId]) && $parentAccounts[$accountId] == '0012400000AA0TTAA1')
			{
				$casesCount[$record->fields->BU__c][$date]['crossknowledge'] += $record->fields->countId;
				if (!in_array($record->fields->BU__c, array('Integration', 'Technology', 'Solutions')))
					$casesCount['Consolidated'][$date]['crossknowledge'] += $record->fields->countId;
			}
			else
			{
				$casesCount[$record->fields->BU__c][$date]['customers'] += $record->fields->countId;
				if (!in_array($record->fields->BU__c, array('Integration', 'Technology', 'Solutions')))
					$casesCount['Consolidated'][$date]['customers'] += $record->fields->countId;
			}
		}
		/*
		var_dump($casesCount);
		
		
		echo '<pre>';
		print_r($response);
		echo '</pre>';
		*/
} 
catch (Exception $e) 
{
  var_dump($e);
	die;
}

$casesCount['NO BU'] = $casesCount[''];
unset($casesCount['']);

ksort($casesCount);

$casesCount = array('Consolidated' => $casesCount['Consolidated']) + $casesCount;

$chart = 0;
foreach ($casesCount as $bu => $timeData)
{
	$chart++;
	echo "<h1>$bu</h1>";
	echo "<svg id='chart$chart' width='960' height='500'></svg>";
	
	$labels = array();
	$data = array();
	ksort($casesCount[$bu]);
	ksort($timeData);
	
	$samples = count($timeData);
	$labels = array_keys($timeData);
	
	foreach ($timeData as $date => $stuff)
	{
		$data[0] []= $stuff['customers'];
		$data[1] []= $stuff['crossknowledge'];
	}

	$ddata = '';
	foreach ($data as $i => $stuff)
	{
		//"['" . implode("', '", $data[0]) . "']";
		
		$s = '';
		foreach ($stuff as $j => $val)
		{
			$s .= "{x: $j,y: $val},";
		}
		
		$ddata .= '[' . rtrim($s, ",") . '],';
	}

	$ddata = '[' . rtrim($ddata, ",") . ']';
	
	$f = fopen("temp/data$chart.csv", "w");
	
	fputcsv($f, array('Date', 'Customers', 'Internal'));
	foreach ($timeData as $date => $stuff)
		fputcsv($f, array($date, $stuff['customers'], $stuff['crossknowledge']));
	
	fclose($f);

?>

<script>
svg<?= $chart ?> = d3.select("#chart<?= $chart ?>"),
    margin<?= $chart ?> = {top: 20, right: 20, bottom: 30, left: 40},
    width<?= $chart ?> = +svg<?= $chart ?>.attr("width") - margin<?= $chart ?>.left - margin<?= $chart ?>.right,
    height<?= $chart ?> = +svg<?= $chart ?>.attr("height") - margin<?= $chart ?>.top - margin<?= $chart ?>.bottom,
    g<?= $chart ?> = svg<?= $chart ?>.append("g").attr("transform", "translate(" + margin<?= $chart ?>.left + "," + margin<?= $chart ?>.top + ")");

x<?= $chart ?> = d3.scaleBand()
    .rangeRound([0, width<?= $chart ?>])
    .padding(0.1)
    .align(0.1);

y<?= $chart ?> = d3.scaleLinear()
    .rangeRound([height<?= $chart ?>, 0]);

z<?= $chart ?> = d3.scaleOrdinal()
    .range(["#ada", "#556"]);

stack<?= $chart ?> = d3.stack();

d3.csv("temp/data<?= $chart ?>.csv", type, function(error, data<?= $chart ?>) {
  if (error) throw error;

  //data.sort(function(a, b) { return b.total - a.total; });

  x<?= $chart ?>.domain(data<?= $chart ?>.map(function(d) { return d.Date; }));
  y<?= $chart ?>.domain([0, d3.max(data<?= $chart ?>, function(d) { return d.total; })]).nice();
  z<?= $chart ?>.domain(data<?= $chart ?>.columns.slice(1));

  g<?= $chart ?>.selectAll(".serie")
    .data(stack<?= $chart ?>.keys(data<?= $chart ?>.columns.slice(1))(data<?= $chart ?>))
    .enter().append("g")
      .attr("class", "serie")
      .attr("fill", function(d) { return z<?= $chart ?>(d.key); })
    .selectAll("rect")
    .data(function(d) { return d; })
    .enter().append("rect")
      .attr("x", function(d) { return x<?= $chart ?>(d.data.Date); })
      .attr("y", function(d) { return y<?= $chart ?>(d[1]); })
      .attr("height", function(d) { return y<?= $chart ?>(d[0]) - y<?= $chart ?>(d[1]); })
      .attr("width", x<?= $chart ?>.bandwidth())
			.append("svg:title")
   .text(function(d) { return d[1] - d[0]; });

  g<?= $chart ?>.append("g")
      .attr("class", "axis axis--x")
      .attr("transform", "translate(0," + height<?= $chart ?> + ")")
      .call(d3.axisBottom(x<?= $chart ?>));

  g<?= $chart ?>.append("g")
      .attr("class", "axis axis--y")
      .call(d3.axisLeft(y<?= $chart ?>).ticks(10, "s"))
    .append("text")
      .attr("x", 2)
      .attr("y", y<?= $chart ?>(y<?= $chart ?>.ticks(10).pop()))
      .attr("dy", "0.35em")
      .attr("text-anchor", "start")
      .attr("fill", "#000")
      .text("Tickets");

  legend<?= $chart ?> = g<?= $chart ?>.selectAll(".legend")
    .data(data<?= $chart ?>.columns.slice(1).reverse())
    .enter().append("g")
      .attr("class", "legend")
      .attr("transform", function(d, i) { return "translate(0," + i * 20 + ")"; })
      .style("font", "10px sans-serif");

  legend<?= $chart ?>.append("rect")
      .attr("x", width<?= $chart ?> - 18)
      .attr("width", 18)
      .attr("height", 18)
      .attr("fill", z<?= $chart ?>);

  legend<?= $chart ?>.append("text")
      .attr("x", width<?= $chart ?> - 24)
      .attr("y", 9)
      .attr("dy", ".35em")
      .attr("text-anchor", "end")
      .text(function(d) { return d; });
});

function type(d, i, columns) {
  for (i = 1, t = 0; i < columns.length; ++i) t += d[columns[i]] = +d[columns[i]];
  d.total = t;
  return d;
}


</script>

<?php 
	} // end foreach BU
?>
