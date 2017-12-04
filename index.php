<?php
	require_once('conf.php');
	require_once('head.php');
?>
<div class="container">
	<h1>SalesForce reports</h1>
	<p class="text-info">Please, don't change these reportings: don't update the filters, or the dates, or the columns...</p>

	<ul>
	<?php
		$reports = array(
			array("Level 2 Activity Dashboard", "01Z24000000bgBe", ""),
			array("Survey Monitoring", "01Z24000000aRvr", ""),
			array("Cases closed with missing fields", "00O24000004WwoD", ""),
			array("Recent Cases with poor survey", "00O24000004OMc6", ""),
		);

		foreach ($reports as $data)
		{
			echo '<li><a href="' . $SF_URL . $data[1] . '" target="_blank">' . $data[0] . '</a></li>';
		}
	?>
	</ul>

	<h1>Custom reports</h1>

	<ul>
		<?php
		$reports = array(
			array("SLA Warnings board", "cases_and_emails.php", ""),
			array("Cases with linked incidents", "spiras.php", ""),
		);

		foreach ($reports as $data)
		{
			echo '<li><a href="' . $data[1] . '" target="_blank">' . $data[0] . '</a></li>';
		}
		?>
	</ul>

</div>
</body>
</html>