<?php

/**
 * Copyright (c) 2007-2009 Jos de Ruijter <jos@dutnie.nl>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/**
 * Super Serious Stats
 * HTML_MySQL.class.php
 *
 * Class for building a fancy webpage out of stored channel data.
 */

final class HTML_MySQL
{
	// The correct way for changing the variables below is from the startup script.
	private $bar_night = 'b.png';
	private $bar_morning = 'g.png';
	private $bar_afternoon = 'y.png';
	private $bar_evening = 'r.png';
	private $channel = '#example';
	private $decimals = 2;
	private $minRows = 3;
	private $stylesheet = 'default.css';

	// The following variables shouldn't be tampered with.
	private $l_total = 0;
	private $l_minimum = 0;
	private $days = 0;

	public function setValue($var, $value)
	{
		$this->$var = $value;
	}

	public function makeHTML()
	{
		@mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS) or exit('MySQL: '.mysql_error());
		@mysql_select_db(MYSQL_DB) or exit('MySQL: '.mysql_error());

		$query_l_total = @mysql_query('SELECT SUM(`l_total`) AS `l_total` FROM `channel`') or exit('MySQL: '.mysql_error());
		$result_l_total = @mysql_fetch_object($query_l_total);
		$this->l_total = $result_l_total->l_total;

		if (empty($this->l_total))
			exit('No data.');

		$query_days = @mysql_query('SELECT COUNT(*) AS `days` FROM `channel`') or exit('MySQL: '.mysql_error());
		$result_days = @mysql_fetch_object($query_days);
		$this->days = $result_days->days;

		$query_date_first = @mysql_query('SELECT MIN(`date`) AS `date` FROM `channel`') or exit('MySQL: '.mysql_error());
		$result_date_first = @mysql_fetch_object($query_date_first);
		$this->date_first = $result_date_first->date;

		$query_date_last = @mysql_query('SELECT MAX(`date`) AS `date` FROM `channel`') or exit('MySQL: '.mysql_error());
		$result_date_last = @mysql_fetch_object($query_date_last);
		$this->date_last = $result_date_last->date;

		/**
		 *  This variable is used to shape most statistics. 1/1000th of the total lines typed in the channel.
		 *  500 will be the minimum minimum! So tables will look somewhat more sane on low volume channels.
		 */
		if (round($this->l_total / 1000) >= 500)
			$this->l_minimum = round($this->l_total / 1000);
		else
			$this->l_minimum = 500;

		$this->year = date('Y', strtotime('yesterday'));
		$this->month = date('m', strtotime('yesterday'));
		$this->month_name = date('F', strtotime('yesterday'));
		$this->day_of_month = date('d', strtotime('yesterday'));
		$this->day_of_year = date('z', strtotime('yesterday')) + 1;

		// Build the HTML page.
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'."\n\n"
		   . '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">'."\n\n"
		   . '<head>'."\n".'<title>'.$this->channel.', seriously.</title>'."\n"
		   . '<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />'."\n"
		   . '<meta http-equiv="Content-Style-Type" content="text/css" />'."\n"
		   . '<link rel="stylesheet" type="text/css" href="'.$this->stylesheet.'" />'."\n"
		   . '<!--[if IE]>'."\n".'  <link rel="stylesheet" type="text/css" href="iefix.css" />'."\n".'<![endif]-->'."\n";
		$query = @mysql_query('SELECT COUNT(DISTINCT YEAR(`date`)) AS `total` FROM `channel`');

		$result = @mysql_fetch_object($query);
		if ($result->total > 0) {
			$width = 2 + ($result->total * 34);
			echo '<style type="text/css">'."\n".'  table.yearly {width:'.$width.'px}'."\n".'</style>'."\n";
		}
		echo '</head>'."\n\n".'<body>'."\n";
		echo '<div class="box">'."\n\n";
		echo '<div class="info">'.$this->channel.', seriously.<br /><br />'.number_format($this->days).' days logged from '.date('M j, Y', strtotime($this->date_first)).' to '.date('M j, Y', strtotime($this->date_last)).'.<br />';

		$query = @mysql_query('select avg(`l_total`) as `avg` from `channel` limit 1');
		$result = @mysql_fetch_object($query);
		$avg = $result->avg;

		$query = @mysql_query('select `l_total` as `max`, `date` from `channel` order by `l_total` desc limit 1');
		$result = @mysql_fetch_object($query);
		$max = $result->max;
		$maxdate = $result->date;

		$query = @mysql_query('select sum(`l_total`) as `sum` from `channel` limit 1');
		$result = @mysql_fetch_object($query);
		$sum = number_format($result->sum);

		echo '<br />Logs contain '.$sum.' lines, an average of '.number_format($avg).' lines per day.<br />Most active day was '.date('M j, Y', strtotime($maxdate)).' with a total of '.number_format($max).' lines typed.</div>'."\n";
		echo '<div class="head">Activity</div>'."\n";

		$this->makeTable_MostActiveTimes('Most Active Times');
		$this->makeTable_Activity('days', 'Daily Activity');
		$this->makeTable_Activity('months', 'Monthly Activity');
		$this->makeTable_MostActiveDays('Most Active Days');
		$this->makeTable_Activity('years', 'Yearly Activity');
		$this->makeTable_MostActivePeople('alltime', 30, 'Most Active People, Alltime', array('Percentage', 'Lines', 'User', 'When?', 'Last Seen', 'Quote'));

		if (date('m') == 1)
			$this->makeTable_MostActivePeople('year', 10, 'Most Active People, '.($this->year - 1), array('Percentage', 'Lines', 'User', 'When?', 'Last Seen', 'Quote'), ($this->year - 1));
		else
			$this->makeTable_MostActivePeople('year', 10, 'Most Active People, '.$this->year, array('Percentage', 'Lines', 'User', 'When?', 'Last Seen', 'Quote'), $this->year);

		$this->makeTable_MostActivePeople('month', 10, 'Most Active People, '.$this->month_name.' '.$this->year, array('Percentage', 'Lines', 'User', 'When?', 'Last Seen', 'Quote'), $this->year, $this->month);
		$this->makeTable_TimeOfDay('Activity, by Time of Day', array('Nightcrawlers<br />0h - 5h', 'Early Birds<br />6h - 11h', 'Afternoon Shift<br />12h - 17h', 'Evening Chatters<br />18h - 23h'));

		/**
		Bots are excluded from statistics unless stated otherwise.
		They are, however, included in the (channel) totals.
		*/

		echo '<div class="head">General Chat</div>'."\n";

		$this->makeTable('small', 5, 'Most Talkative Chatters', array('', 'Lines/Day', 'User'), 1, FALSE, array('SELECT `csNick` AS `v2`, (`l_total` / `activeDays`) AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('small', 5, 'Most Fluent Chatters', array('', 'Words/Line', 'User'), 1, FALSE, array('SELECT `csNick` AS `v2`, (`words` / `l_total`) AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('small', 5, 'Most Tedious Chatters', array('', 'Chars/Line', 'User'), 1, FALSE, array('SELECT `csNick` AS `v2`, (`characters` / `l_total`) AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));

		////////////////////
		//TOP DAYS - ALLTIME
		$query = @mysql_query('SELECT `RUID`, `v1` FROM (SELECT `RUID`, SUM(`l_total`) AS `v1` FROM `user_status` JOIN `user_activity` ON `user_status`.`UID` = `user_activity`.`UID` GROUP BY `date`, `RUID` ORDER BY `v1` DESC LIMIT 100) AS `sub` GROUP BY `RUID` ORDER BY `v1` DESC') or exit('MySQL: '.mysql_error());
		$tmpArr = array();
		while ($result = @mysql_fetch_object($query)) {
			$query2 = @mysql_query('SELECT `status`, `csNick` AS `v2` FROM `user_status` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `user_status`.`UID` = '.$result->RUID);
			$result2 = @mysql_fetch_object($query2);
			if ($result2->status != 3)
				$tmpArr[] = array('v1' => $result->v1, 'v2' => $result2->v2);
		}
		$this->makeTable2('small', 5, 'Individual Top Days, Alltime', array('', 'Lines', 'User'), 0, FALSE, $tmpArr);

		/////////////////
		//TOP DAYS - YEAR
		if (date('m') != 1) {
		$query = @mysql_query('SELECT `RUID`, `v1` FROM (SELECT `RUID`, SUM(`l_total`) AS `v1` FROM `user_status` JOIN `user_activity` ON `user_status`.`UID` = `user_activity`.`UID` WHERE YEAR(`date`) = '.$this->year.' GROUP BY `date`, `RUID` ORDER BY `v1` DESC LIMIT 100) AS `sub` GROUP BY `RUID` ORDER BY `v1` DESC') or exit('MySQL: '.mysql_error());
		$tmpArr = array();
		while ($result = @mysql_fetch_object($query)) {
			$query2 = @mysql_query('SELECT `status`, `csNick` AS `v2` FROM `user_status` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `user_status`.`UID` = '.$result->RUID);
			$result2 = @mysql_fetch_object($query2);
			if ($result2->status != 3)
				$tmpArr[] = array('v1' => $result->v1, 'v2' => $result2->v2);
		}
		$this->makeTable2('small', 5, 'Individual Top Days, '.$this->year, array('', 'Lines', 'User'), 0, FALSE, $tmpArr);
		}

		//////////////////
		//TOP DAYS - MONTH
		$query = @mysql_query('SELECT `RUID`, `v1` FROM (SELECT `RUID`, SUM(`l_total`) AS `v1` FROM `user_status` JOIN `user_activity` ON `user_status`.`UID` = `user_activity`.`UID` WHERE YEAR(`date`) = '.$this->year.' AND MONTH(`date`) = '.$this->month.' GROUP BY `date`, `RUID` ORDER BY `v1` DESC LIMIT 100) AS `sub` GROUP BY `RUID` ORDER BY `v1` DESC') or exit('MySQL: '.mysql_error());
		$tmpArr = array();
		while ($result = @mysql_fetch_object($query)) {
			$query2 = @mysql_query('SELECT `status`, `csNick` AS `v2` FROM `user_status` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `user_status`.`UID` = '.$result->RUID);
			$result2 = @mysql_fetch_object($query2);
			if ($result2->status != 3)
				$tmpArr[] = array('v1' => $result->v1, 'v2' => $result2->v2);
		}
		$this->makeTable2('small', 5, 'Individual Top Days, '.$this->month_name.' '.$this->year, array('', 'Lines', 'User'), 0, FALSE, $tmpArr);

		////////////////////
		//ACTIVITY - ALLTIME
		$this->makeTable('small', 5, 'Most Active Chatters, Alltime', array('', 'Activity', 'User'), 2, TRUE, array('SELECT `csNick` AS `v2`, (`activeDays` / '.$this->days.') * 100 AS `v1` FROM `user_status` JOIN `query_lines` ON `user_status`.`UID` = `query_lines`.`UID` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `status` != 3 ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));

		/////////////////
		//ACTIVITY - YEAR
		if (date('m') != 1) {
		$query = @mysql_query('SELECT `RUID`, (COUNT(DISTINCT `date`) / '.$this->day_of_year.') * 100 AS `v1` FROM `user_status` JOIN `user_activity` ON `user_status`.`UID` = `user_activity`.`UID` WHERE YEAR(`date`) = '.$this->year.' GROUP BY `RUID` ORDER BY `v1` DESC LIMIT 25') or exit('MySQL: '.mysql_error());
		$tmpArr = array();
		while ($result = @mysql_fetch_object($query)) {
			$query2 = @mysql_query('SELECT `status`, `csNick` AS `v2` FROM `user_status` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `user_status`.`UID` = '.$result->RUID);
			$result2 = @mysql_fetch_object($query2);
			if ($result2->status != 3)
				$tmpArr[] = array('v1' => $result->v1, 'v2' => $result2->v2);
		}
		$this->makeTable2('small', 5, 'Most Active Chatters, '.$this->year, array('', 'Activity', 'User'), 2, TRUE, $tmpArr);
		}

		//////////////////
		//ACTIVITY - MONTH
		$query = @mysql_query('SELECT `RUID`, (COUNT(DISTINCT `date`) / '.$this->day_of_month.') * 100 AS `v1` FROM `user_status` JOIN `user_activity` ON `user_status`.`UID` = `user_activity`.`UID` WHERE YEAR(`date`) = '.$this->year.' AND MONTH(`date`) = '.$this->month.' GROUP BY `RUID` ORDER BY `v1` DESC LIMIT 25') or exit('MySQL: '.mysql_error());
		$tmpArr = array();
		while ($result = @mysql_fetch_object($query)) {
			$query2 = @mysql_query('SELECT `status`, `csNick` AS `v2` FROM `user_status` JOIN `user_details` ON `user_status`.`UID` = `user_details`.`UID` WHERE `user_status`.`UID` = '.$result->RUID);
			$result2 = @mysql_fetch_object($query2);
			if ($result2->status != 3)
				$tmpArr[] = array('v1' => $result->v1, 'v2' => $result2->v2);
		}
		$this->makeTable2('small', 5, 'Most Active Chatters, '.$this->month_name.' '.$this->year, array('', 'Activity', 'User'), 2, TRUE, $tmpArr);

		$this->makeTable('large', 5, 'Most Exclamations', array('', 'Percentage', 'User', 'Example'), 2, TRUE, array('SELECT `csNick` AS `v2`, (`exclamations` / `l_total`) * 100 AS `v1`, `ex_exclamations` AS `v3` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `exclamations` != 0 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('large', 5, 'Most Questions', array('', 'Percentage', 'User', 'Example'), 2, TRUE, array('SELECT `csNick` AS `v2`, (`questions` / `l_total`) * 100 AS `v1`, `ex_questions` AS `v3` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `questions` != 0 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('large', 5, 'Most UPPERCASED Lines', array('', 'Percentage', 'User', 'Example'), 2, TRUE, array('SELECT `csNick` AS `v2`, (`uppercased` / `l_total`) * 100 AS `v1`, `ex_uppercased` AS `v3` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `uppercased` != 0 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('small', 5, 'Most URLs, by Users', array('', 'URLs', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `URLs` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `URLs` != 0 ORDER BY `URLs` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`URLs`) AS `v0` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3'));
		$this->makeTable('small', 5, 'Most URLs, by Bots', array('', 'URLs', 'Bot'), 0, FALSE, array('SELECT `csNick` AS `v2`, `URLs` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` = 3 AND `URLs` != 0 ORDER BY `URLs` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`URLs`) AS `v0` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` = 3'));
		$this->makeTable('small', 5, 'Most Monologues', array('', 'Monologues', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `monologues` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `monologues` != 0 ORDER BY `monologues` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`monologues`) AS `v0` FROM `query_lines`'));
		$this->makeTable('small', 5, 'Most Slaps, Given', array('', 'Slaps', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `slaps` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `slaps` != 0 ORDER BY `slaps` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`slaps`) AS `v0` FROM `query_lines`'));
		$this->makeTable('small', 5, 'Most Slaps, Received', array('', 'Slaps', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `slapped` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `slapped` != 0 ORDER BY `slapped` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`slapped`) AS `v0` FROM `query_lines`'));
		$this->makeTable('small', 5, 'Longest Monologue', array('', 'Lines', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `topMonologue` AS `v1` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `topMonologue` != 0 ORDER BY `topMonologue` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('large', 5, 'Most Actions', array('', 'Percentage', 'User', 'Example'), 2, TRUE, array('SELECT `csNick` AS `v2`, (`actions` / `l_total`) * 100 AS `v1`, `ex_actions` AS `v3` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `actions` != 0 AND `l_total` >= '.$this->l_minimum.' ORDER BY `v1` DESC, `csNick` ASC LIMIT 5'));
		$this->makeTable('small', 5, 'Most Mentioned Nicks', array('', 'Mentioned', 'Nick'), 0, FALSE, array('SELECT `csNick` AS `v2`, `total` AS `v1` FROM `user_details` JOIN `user_status` ON `user_details`.`UID` = `user_status`.`UID` JOIN `words` ON `user_details`.`csNick` = `words`.`word` WHERE `status` = 1 ORDER BY `total` DESC, `csNick` ASC LIMIT 5'));

		echo '<div class="head">Modes</div>'."\n";

		$this->makeTable('small', 5, 'Most Ops \'+o\', Given', array('', 'Ops', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_op` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_op` != 0 ORDER BY `m_op` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_op`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Ops \'+o\', Received', array('', 'Ops', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_opped` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_opped` != 0 ORDER BY `m_opped` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_opped`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most deOps \'-o\', Given', array('', 'deOps', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_deOp` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_deOp` != 0 ORDER BY `m_deOp` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_deOp`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most deOps \'-o\', Received', array('', 'deOps', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_deOpped` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_deOpped` != 0 ORDER BY `m_deOpped` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_deOpped`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Voices \'+v\', Given', array('', 'Voices', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_voice` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_voice` != 0 ORDER BY `m_voice` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_voice`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Voices \'+v\', Received', array('', 'Voices', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_voiced` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_voiced` != 0 ORDER BY `m_voiced` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_voiced`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most deVoices \'-v\', Given', array('', 'deVoices', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_deVoice` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_deVoice` != 0 ORDER BY `m_deVoice` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_deVoice`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most deVoices \'-v\', Received', array('', 'deVoices', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `m_deVoiced` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `m_deVoiced` != 0 ORDER BY `m_deVoiced` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`m_deVoiced`) AS `v0` FROM `query_events`'));

		echo '<div class="head">Events</div>'."\n";

		$this->makeTable('large', 5, 'Most Kicks', array('', 'Kicks', 'User', 'Example'), 0, FALSE, array('SELECT `csNick` AS `v2`, `kicks` AS `v1`, `ex_kicks` AS `v3` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `kicks` != 0 ORDER BY `kicks` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`kicks`) AS `v0` FROM `query_events`'));
		$this->makeTable('large', 5, 'Most Kicked', array('', 'Kicked', 'User', 'Example'), 0, FALSE, array('SELECT `csNick` AS `v2`, `kicked` AS `v1`, `ex_kicked` AS `v3` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `kicked` != 0 ORDER BY `kicked` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`kicked`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Joins', array('', 'Joins', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `joins` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `joins` != 0 ORDER BY `joins` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`joins`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Parts', array('', 'Parts', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `parts` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `parts` != 0 ORDER BY `parts` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`parts`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Quits', array('', 'Quits', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `quits` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `quits` != 0 ORDER BY `quits` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`quits`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Nick Changes', array('', 'Nick Changes', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `nickChanges` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `nickChanges` != 0 ORDER BY `nickChanges` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`nickChanges`) AS `v0` FROM `query_events`'));
		$this->makeTable('small', 5, 'Most Aliases', array('', 'Aliases', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, COUNT(*) AS `v1` FROM `user_details` JOIN `user_status` ON `user_details`.`UID` = `user_status`.`UID` WHERE `status` != 3 GROUP BY `RUID` ORDER BY `v1` DESC, `csNick` ASC LIMIT 5', 'SELECT COUNT(*) AS `v0` FROM `user_status`'));
		$this->makeTable('small', 5, 'Most Topics', array('', 'Topics', 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `topics` AS `v1` FROM `query_events` JOIN `user_details` ON `query_events`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_events`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `topics` != 0 ORDER BY `topics` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`topics`) AS `v0` FROM `query_events`'));
		$this->table_topics();

		echo '<div class="head">Smileys</div>'."\n";

		$smileys = array('Big Cheerful Smile' => array('=]', 's_01')
				,'Cheerful Smile' => array('=)', 's_02')
				,'Lovely Kiss' => array(';x', 's_03')
				,'Retard' => array(';p', 's_04')
				,'Big Winky' => array(';]', 's_05')
				,'Classic Winky' => array(';-)', 's_06')
				,'Winky' => array(';)', 's_07')
				,'Cry' => array(';(', 's_08')
				,'Kiss' => array(':x', 's_09')
				,'Tongue' => array(':P', 's_10')
				,'Laugh' => array(':D', 's_11')
				,'Funny' => array(':>', 's_12')
				,'Big Smile' => array(':]', 's_13')
				,'Skeptical I' => array(':\\', 's_14')
				,'Skeptical II' => array(':/', 's_15')
				,'Classic Happy' => array(':-)', 's_16')
				,'Happy' => array(':)', 's_17')
				,'Sad' => array(':(', 's_18')
				,'Cheer' => array('\\o/', 's_19')
				);

		foreach ($smileys as $k => $v) {
			$query = @mysql_query('SELECT SUM(`'.$v[1].'`) AS `total` FROM `query_smileys`');
			$result = @mysql_fetch_object($query);

			if ($result->total >= $this->l_minimum)
				$this->makeTable('small', 5, $k, array('', $v[0], 'User'), 0, FALSE, array('SELECT `csNick` AS `v2`, `'.$v[1].'` AS `v1` FROM `user_details`, `query_smileys`, `user_status` WHERE `user_details`.`UID` = `query_smileys`.`UID` AND `user_details`.`UID` = `user_status`.`UID` AND `status` != 3 AND `'.$v[1].'` != 0 ORDER BY `'.$v[1].'` DESC, `csNick` ASC LIMIT 5', 'SELECT SUM(`'.$v[1].'`) AS `v0` FROM `query_smileys`'));
		}

		echo '<div class="info">Statistics created with <a href="http://code.google.com/p/superseriousstats/">superseriousstats</a> on '.date('M j, Y \a\\t g:i A').'.</div>'."\n\n";
		echo '</div>'."\n".'</body>'."\n\n".'</html>'."\n";

		@mysql_close();
	}

	private function makeTable_TimeOfDay($head, $keys)
	{
		$l_total_high = 0;
		$times = array('night', 'morning', 'afternoon', 'evening');

		foreach ($times as $time) {
			$query = @mysql_query('SELECT `csNick`, `l_'.$time.'` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `l_'.$time.'` != 0 ORDER BY `l_'.$time.'` DESC, `csNick` ASC LIMIT 10');
			$i = 0;

			while ($result = @mysql_fetch_object($query)) {
				$i++;
				${$time}[$i]['user'] = $result->csNick;
				${$time}[$i]['lines'] = $result->{'l_'.$time};

				if ($i == 1 && ${$time}[$i]['lines'] > $l_total_high)
					$l_total_high = ${$time}[$i]['lines'];
			}
		}

		$barWidth = (190 / $l_total_high);
		$output = '<table class="tod"><tr><th colspan="5">'.$head.'</th></tr><tr><td class="pos"></td><td class="k">'.$keys[0].'</td><td class="k">'.$keys[1].'</td><td class="k">'.$keys[2].'</td><td class="k">'.$keys[3].'</td></tr>';

		for ($i = 1; $i <= 10; $i++) {
			$output .= '<tr><td class="pos">'.$i.'</td>';

			foreach ($times as $time)
				if (isset(${$time}[$i]['lines'])) {
					if (round(${$time}[$i]['lines'] * $barWidth) == 0)
						$output .= '<td class="v">'.htmlspecialchars(${$time}[$i]['user']).' - '.number_format(${$time}[$i]['lines']).'</td>';
					else
						$output .= '<td class="v">'.htmlspecialchars(${$time}[$i]['user']).' - '.number_format(${$time}[$i]['lines']).'<br /><img src="'.$this->{'bar_'.$time}.'" width="'.round(${$time}[$i]['lines'] * $barWidth).'" alt="" /></td>';
				} else
					$output .= '<td class="v"></td>';

			$output .= '</tr>';
		}

		echo $output.'</table>'."\n";
	}

	//maketable2, don't know what the deal will be yet
	private function makeTable2($size, $rows, $head, $keys, $decimals, $percentage, $result)
	{
		$i = 0;

		foreach ($result as $row) {
			$i++;

			if ($i > 5)
				break;

			if ($size == 'small')
				$data[] = array($i, number_format($row['v1'], $decimals).($percentage ? '%' : ''), htmlspecialchars($row['v2']));
			elseif ($size == 'large')
				$data[] = array($i, number_format($row['v1'], $decimals).($percentage ? '%' : ''), htmlspecialchars($row['v2']), htmlspecialchars($row['v3']));
		}

		// If there are less rows to display than the desired minimum amount of rows we skip this table.
		if ($this->minRows <= $rows && $i < $this->minRows)
			return;

		for ($i = count($data); $i < $rows; $i++)
			if ($size == 'small')
				$data[] = array('&nbsp;', '', '');
			elseif ($size == 'large')
				$data[] = array('&nbsp;', '', '', '');

		if ($size == 'small') {
			$output = '<table class="small">';
			$output .= '<tr><th colspan="3">'.$head.'</th></tr>';
			$output .= '<tr><td class="k1">'.$keys[1].'</td><td class="pos">'.$keys[0].'</td><td class="k2">'.$keys[2].'</td></tr>';

			foreach ($data as $row)
				$output .= '<tr><td class="v1">'.$row[1].'</td><td class="pos">'.$row[0].'</td><td class="v2">'.$row[2].'</td></tr>';

			echo $output.'</table>'."\n";
		} elseif ($size == 'large') {
			$output = '<table class="large">';
			$output .= '<tr><th colspan="4">'.$head.'</th></tr>';
			$output .= '<tr><td class="k1">'.$keys[1].'</td><td class="pos">'.$keys[0].'</td><td class="k2">'.$keys[2].'</td><td class="k3">'.$keys[3].'</td></tr>';

			foreach ($data as $row)
				$output .= '<tr><td class="v1">'.$row[1].'</td><td class="pos">'.$row[0].'</td><td class="v2">'.$row[2].'</td><td class="v3">'.$row[3].'</td></tr>';

			echo $output.'</table>'."\n";
		}
	}

	//maketable from file needs review
	private function makeTable($size, $rows, $head, $keys, $decimals, $percentage, $queries)
	{
		$query = @mysql_query($queries[0]);
		$i = 0;

		while ($result = @mysql_fetch_object($query)) {
			$i++;

			if ($size == 'small')
				$data[] = array($i, number_format($result->v1, $decimals).($percentage ? '%' : ''), htmlspecialchars($result->v2));
			elseif ($size == 'large')
				$data[] = array($i, number_format($result->v1, $decimals).($percentage ? '%' : ''), htmlspecialchars($result->v2), htmlspecialchars($result->v3));
		}

		// If there are less rows to display than the desired minimum amount of rows we skip this table.
		if ($this->minRows <= $rows && $i < $this->minRows)
			return;

		for ($i = count($data); $i < $rows; $i++)
			if ($size == 'small')
				$data[] = array('&nbsp;', '', '');
			elseif ($size == 'large')
				$data[] = array('&nbsp;', '', '', '');

		if (!empty($queries[1])) {
			$query = @mysql_query($queries[1]);
			$result = @mysql_fetch_object($query);
		}

		if ($size == 'small') {
			$output = '<table class="small">';
			$output .= '<tr><th colspan="3"><span class="left">'.$head.'</span>'.(empty($result->v0) ? '' : '<span class="right">'.number_format($result->v0).' total</span>').'</th></tr>';
			$output .= '<tr><td class="k1">'.$keys[1].'</td><td class="pos">'.$keys[0].'</td><td class="k2">'.$keys[2].'</td></tr>';

			foreach ($data as $row)
				$output .= '<tr><td class="v1">'.$row[1].'</td><td class="pos">'.$row[0].'</td><td class="v2">'.$row[2].'</td></tr>';

			echo $output.'</table>'."\n";
		} elseif ($size == 'large') {
			$output = '<table class="large">';
			$output .= '<tr><th colspan="4"><span class="left">'.$head.'</span>'.(empty($result->v0) ? '' : '<span class="right">'.number_format($result->v0).' total</span>').'</th></tr>';
			$output .= '<tr><td class="k1">'.$keys[1].'</td><td class="pos">'.$keys[0].'</td><td class="k2">'.$keys[2].'</td><td class="k3">'.$keys[3].'</td></tr>';

			foreach ($data as $row)
				$output .= '<tr><td class="v1">'.$row[1].'</td><td class="pos">'.$row[0].'</td><td class="v2">'.$row[2].'</td><td class="v3">'.$row[3].'</td></tr>';

			echo $output.'</table>'."\n";
		}
	}

	//maketable most active times from file needs review
	private function makeTable_MostActiveTimes($head)
	{
		$query = @mysql_query('SELECT SUM(`l_00`) AS `l_00`, SUM(`l_01`) AS `l_01`, SUM(`l_02`) AS `l_02`, SUM(`l_03`) AS `l_03`, SUM(`l_04`) AS `l_04`, SUM(`l_05`) AS `l_05`, SUM(`l_06`) AS `l_06`, SUM(`l_07`) AS `l_07`, SUM(`l_08`) AS `l_08`, SUM(`l_09`) AS `l_09`, SUM(`l_10`) AS `l_10`, SUM(`l_11`) AS `l_11`, SUM(`l_12`) AS `l_12`, SUM(`l_13`) AS `l_13`, SUM(`l_14`) AS `l_14`, SUM(`l_15`) AS `l_15`, SUM(`l_16`) AS `l_16`, SUM(`l_17`) AS `l_17`, SUM(`l_18`) AS `l_18`, SUM(`l_19`) AS `l_19`, SUM(`l_20`) AS `l_20`, SUM(`l_21`) AS `l_21`, SUM(`l_22`) AS `l_22`, SUM(`l_23`) AS `l_23` FROM `channel`') or exit('MySQL: '.mysql_error());
		$result = @mysql_fetch_object($query);
		$l_total_high = 0;

		for ($hour = 0; $hour < 24; $hour++) {
			if ($hour < 10)
				$l_total[$hour] = $result->{'l_0'.$hour};
			else
				$l_total[$hour] = $result->{'l_'.$hour};

			if ($l_total[$hour] > $l_total_high) {
				$l_total_high = $l_total[$hour];
				$l_total_high_hour = $hour;
			}
		}

		$output = '<table class="graph"><tr><th colspan="24">'.$head.'</th></tr><tr class="bars">';

		for ($hour = 0; $hour < 24; $hour++) {
			if ($l_total[$hour] != 0) {
				$output .= '<td>';

				if ((($l_total[$hour] / $this->l_total) * 100) >= 9.95)
					$output .= round(($l_total[$hour] / $this->l_total) * 100).'%';
				else
					$output .= number_format(($l_total[$hour] / $this->l_total) * 100, 1).'%';

				$barHeight = round(($l_total[$hour] / $l_total_high) * 100);

				if ($barHeight != 0 && $hour >= 0 && $hour <= 5)
					$output .= '<img src="'.$this->bar_night.'" height="'.$barHeight.'" alt="" title="'.number_format($l_total[$hour]).'" />';
				elseif ($barHeight != 0 && $hour >= 6 && $hour <= 11)
					$output .= '<img src="'.$this->bar_morning.'" height="'.$barHeight.'" alt="" title="'.number_format($l_total[$hour]).'" />';
				elseif ($barHeight != 0 && $hour >= 12 && $hour <= 17)
					$output .= '<img src="'.$this->bar_afternoon.'" height="'.$barHeight.'" alt="" title="'.number_format($l_total[$hour]).'" />';
				elseif ($barHeight != 0 && $hour >= 18 && $hour <= 23)
					$output .= '<img src="'.$this->bar_evening.'" height="'.$barHeight.'" alt="" title="'.number_format($l_total[$hour]).'" />';

				$output .= '</td>';
			} else
				$output .= '<td><span class="grey">n/a</span></td>';
		}

		$output .= '</tr><tr class="sub">';

		for ($hour = 0; $hour < 24; $hour++)
			if ($l_total_high != 0 && $l_total_high_hour == $hour)
				$output .= '<td class="bold">'.$hour.'h</td>';
			else
				$output .= '<td>'.$hour.'h</td>';

		echo $output.'</tr></table>'."\n";
	}

	//maketable most active ppl from file needs review
	private function makeTable_MostActivePeople($type, $rows, $head, $keys, $year = NULL, $month = NULL)
	{
		switch ($type) {
		case 'alltime':
			$query = @mysql_query('SELECT `RUID`, `csNick`, `l_total`, `quote`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` JOIN `user_status` ON `query_lines`.`UID` = `user_status`.`UID` WHERE `status` != 3 AND `l_total` != 0 ORDER BY `l_total` DESC, `csNick` DESC LIMIT '.$rows) or exit('MySQL: '.mysql_error());
			$query_l_total = @mysql_query('SELECT SUM(`l_total`) AS `l_total` FROM `channel`') or exit('MySQL: '.mysql_error());
			$result_l_total = @mysql_fetch_object($query_l_total);
			$getNick = FALSE;
			break;
		case 'year':
			//check wether ruid != 3 prevents us from getting results of aliases of bots (not using query tables here so..)
			$query = @mysql_query('SELECT `RUID`, SUM(`l_total`) AS `l_total`, SUM(`l_night`) AS `l_night`, SUM(`l_morning`) AS `l_morning`, SUM(`l_afternoon`) AS `l_afternoon`, SUM(`l_evening`) AS `l_evening` FROM `user_activity` JOIN `user_status` ON `user_activity`.`UID` = `user_status`.`UID` WHERE (SELECT `status` FROM `user_status` AS `t1` WHERE `UID` = `user_status`.`RUID`) != 3 AND YEAR(`date`) = '.$year.' GROUP BY `RUID` ORDER BY `l_total` DESC LIMIT '.$rows) or exit('MySQL: '.mysql_error());
			$query_l_total = @mysql_query('SELECT SUM(`l_total`) AS `l_total` FROM `user_activity` WHERE YEAR(`date`) = '.$year) or exit('MySQL: '.mysql_error());
			$result_l_total = @mysql_fetch_object($query_l_total);
			$getNick = TRUE;
			break;
		case 'month':
			//check wether ruid != 3 prevents us from getting results of aliases of bots (not using query tables here so..)
			$query = @mysql_query('SELECT `RUID`, SUM(`l_total`) AS `l_total`, SUM(`l_night`) AS `l_night`, SUM(`l_morning`) AS `l_morning`, SUM(`l_afternoon`) AS `l_afternoon`, SUM(`l_evening`) AS `l_evening` FROM `user_activity` JOIN `user_status` ON `user_activity`.`UID` = `user_status`.`UID` WHERE (SELECT `status` FROM `user_status` AS `t1` WHERE `UID` = `user_status`.`RUID`) != 3 AND YEAR(`date`) = '.$year.' AND MONTH(`date`) = '.$month.' GROUP BY `RUID` ORDER BY `l_total` DESC LIMIT '.$rows) or exit('MySQL: '.mysql_error());
			$query_l_total = @mysql_query('SELECT SUM(`l_total`) AS `l_total` FROM `user_activity` WHERE YEAR(`date`) = '.$year.' AND MONTH(`date`) = '.$month) or exit('MySQL: '.mysql_error());
			$result_l_total = @mysql_fetch_object($query_l_total);
			$getNick = TRUE;
			break;
		}

		$output = '<table class="map"><tr><th colspan="7">'.$head.'</th></tr><tr><td class="k1">'.$keys[0].'</td><td class="k2">'.$keys[1].'</td><td class="pos"></td><td class="k3">'.$keys[2].'</td><td class="k4">'.$keys[3].'</td><td class="k5">'.$keys[4].'</td><td class="k6">'.$keys[5].'</td></tr>';
		$i = 0;

		// Go throught the results and construct the output line for each user.
		while ($result = @mysql_fetch_object($query)) {
			$i++;

			// Calculate the line percentage.
			$l_total_percentage = number_format(($result->l_total / $result_l_total->l_total) * 100, 2);

			// Get the nick and quote.
			if ($getNick) {
				$query_csNick = @mysql_query('SELECT `csNick`, `quote` FROM `query_lines` JOIN `user_details` ON `query_lines`.`UID` = `user_details`.`UID` WHERE `query_lines`.`UID` = '.$result->RUID) or exit('MySQL: '.mysql_error());
				$result_csNick = @mysql_fetch_object($query_csNick);
				$result->csNick = $result_csNick->csNick;
				$result->quote = $result_csNick->quote;
			}

			/**
			 * Make sure that quotes don't exceed the limit of 300px in width.
			 * Below is the old and crappy way of doing so, i'd love to have this tidied up one day.
			 */
			$chars_lower = strlen($result->quote) - strlen(preg_replace('/[a-z]/', '', $result->quote));
			$chars_upper = strlen($result->quote) - strlen(preg_replace('/[A-Z0-9]/', '', $result->quote));
			$chars_other = strlen($result->quote) - $chars_upper - $chars_lower;

			if ((($chars_upper * 7) + ($chars_lower * 6) + ($chars_other * 7)) > 300) {
				$chars_length = 0;
				$chars_str = '';

				for ($g = 0; $g < strlen($result->quote); $g++) {
					if (preg_match('/[a-z]/', $result->quote[$g])) {
						$chars_length += 6;
						$chars_str .= $result->quote[$g];
					} elseif (preg_match('/[A-Z0-9]/', $result->quote[$g])) {
						$chars_length += 7;
						$chars_str .= $result->quote[$g];
					} else {
						$chars_length += 7;
						$chars_str .= $result->quote[$g];
					}

					if ($chars_length >= 300)
						break;
				}

				$hover = '<a title="'.htmlspecialchars($result->quote).'">...</a>';
				$result->quote = rtrim($chars_str);
			} else
				$hover = '';

			// Get the last seen data.
			$query_lastSeen = @mysql_query('SELECT `lastSeen` FROM `user_details` JOIN `user_status` ON `user_details`.`UID` = `user_status`.`UID` WHERE `RUID` = '.$result->RUID.' ORDER BY `lastSeen` DESC LIMIT 1') or exit('MySQL: '.mysql_error());
			$result_lastSeen = @mysql_fetch_object($query_lastSeen);
			$lastSeen = explode(' ', $result_lastSeen->lastSeen);
			//$lastSeen = explode('-', $lastSeen[0]);
			//$lastSeen = round((mktime(0, 0, 0, $this->current_month, $this->current_day_of_month, $this->current_year) - mktime(0, 0, 0, $lastSeen[1], $lastSeen[2], $lastSeen[0])) / 86400);
			$lastSeen = round((strtotime('today') - strtotime($lastSeen[0])) / 86400);
			if (($lastSeen / 365) >= 1)
				$lastSeen = rtrim(number_format($lastSeen / 365, 1), '.0').' Years Ago';
			elseif (($lastSeen / 30.42) >= 1)
				$lastSeen = rtrim(number_format($lastSeen / 30.42, 1), '.0').' Months Ago';
			elseif ($lastSeen == 1)
				$lastSeen = 'Yesterday';
			else
				$lastSeen = $lastSeen.' Days Ago';

			// Format the "when?" bar.
			$when_output = '';
			$when_width = 50;
			$times = array('night', 'morning', 'afternoon', 'evening');
			unset($l_night_width_real, $l_night_width, $l_morning_width_real, $l_morning_width, $l_afternoon_width_real, $l_afternoon_width, $l_evening_width_real, $l_evening_width, $remainders, $when_night, $when_morning, $when_afternoon, $when_evening);

			foreach ($times as $time) {
				if ($result->{'l_'.$time} != 0) {
					${'l_'.$time.'_width_real'} = ($result->{'l_'.$time} / $result->l_total) * 50;

					if (is_int(${'l_'.$time.'_width_real'})) {
						${'l_'.$time.'_width'} = ${'l_'.$time.'_width_real'};
						$when_width -= ${'l_'.$time.'_width'};
					} else {
						${'l_'.$time.'_width'} = floor(${'l_'.$time.'_width_real'});
						$when_width -= ${'l_'.$time.'_width'};
						$remainders[$time] = round((number_format(${'l_'.$time.'_width_real'}, 2) - ${'l_'.$time.'_width'}) * 100);
					}
				}
			}

			if (!empty($remainders)) {
				arsort($remainders);

				foreach ($remainders as $time => $remainder) {
					if ($when_width != 0) {
						$when_width--;
						${'when_'.$time} = '<img src="'.$this->{'bar_'.$time}.'" width="'.++${'l_'.$time.'_width'}.'" alt="" />';
					} else
						${'when_'.$time} = '<img src="'.$this->{'bar_'.$time}.'" width="'.${'l_'.$time.'_width'}.'" alt="" />';
				}
			} else
				foreach ($times as $time)
					if (!empty(${'l_'.$time.'_width'}))
						${'when_'.$time} = '<img src="'.$this->{'bar_'.$time}.'" width="'.${'l_'.$time.'_width'}.'" alt="" />';

			foreach ($times as $time)
				if (!empty(${'when_'.$time}))
					$when_output .= ${'when_'.$time};

			$output .= '<tr><td class="v1">'.$l_total_percentage.'%</td><td class="v2">'.number_format($result->l_total).'</td><td class="pos">'.$i.'</td><td class="v3">'.$result->csNick.'</td><td class="v4">'.$when_output.'</td><td class="v5">'.$lastSeen.'</td><td class="v6">'.htmlspecialchars($result->quote).$hover.'</td></tr>';
		}

		echo $output.'</table>'."\n";
	}

	//makeTable_Activity from file needs review
	private function makeTable_Activity($type, $head)
	{
		// Remember that log data is _always_ one day old!
		switch ($type) {
			case 'days':
				$cols = 24;
				$minus = 24;
				$startDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('j') - $minus, date('Y')));
				$query = @mysql_query('SELECT `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` FROM `channel` WHERE `date` >= \''.$startDate.'\' ORDER BY `date` ASC') or exit('MySQL: '.mysql_error());
				$table_class = 'graph';
				break;
			case 'months':
				$cols = 24;

				if (date('j') == 1)
					$minus = 24;
				else
					$minus = 23;

				$startDate = date('Y-m-01', mktime(0, 0, 0, date('m') - $minus, date('j'), date('Y')));
				$query = @mysql_query('SELECT `date`, SUM(`l_total`) AS `l_total`, SUM(`l_night`) AS `l_night`, SUM(`l_morning`) AS `l_morning`, SUM(`l_afternoon`) AS `l_afternoon`, SUM(`l_evening`) AS `l_evening` FROM `channel` WHERE `date` >= \''.$startDate.'\' GROUP BY YEAR(`date`), MONTH(`date`) ORDER BY `date` ASC') or exit('MySQL: '.mysql_error());
				$table_class = 'graph';
				break;
			case 'years':
				$query = @mysql_query('SELECT COUNT(DISTINCT YEAR(`date`)) AS `total` FROM `channel`');
				$result = @mysql_fetch_object($query);
				$cols = $result->total;

				if (date('jn') == 11)
					$minus = $result->total;
				else
					$minus = $result->total - 1;

				if ($minus < 1)
					break;

				$startDate = date('Y-01-01', mktime(0, 0, 0, date('m'), date('j'), date('Y') - $minus));
				$query = @mysql_query('SELECT `date`, SUM(`l_total`) AS `l_total`, SUM(`l_night`) AS `l_night`, SUM(`l_morning`) AS `l_morning`, SUM(`l_afternoon`) AS `l_afternoon`, SUM(`l_evening`) AS `l_evening` FROM `channel` WHERE `date` >= \''.$startDate.'\' GROUP BY YEAR(`date`) ORDER BY `date` ASC') or exit('MySQL: '.mysql_error());
				$table_class = 'yearly';
				break;
		}

		$sums = array('l_total', 'l_night', 'l_morning', 'l_afternoon', 'l_evening');
		$l_total_high = 0;

		while ($result = @mysql_fetch_object($query)) {
			$year = date('Y', strtotime($result->date));

			if ($type == 'years')
				$month = 1;
			else
				$month = date('n', strtotime($result->date));

			if ($type == 'months' || $type == 'years')
				$day = 1;
			else
				$day = date('j', strtotime($result->date));

			foreach ($sums as $sum)
				$activity[$year][$month][$day][$sum] = $result->$sum;

			if ($result->l_total > $l_total_high) {
				$l_total_high = $result->l_total;
				$l_total_high_date = $result->date;
			}
		}

		$tmp = $cols;
		$output = '<table class="'.$table_class.'"><tr><th colspan="'.$cols.'">'.$head.'</th></tr><tr class="bars">';

		for ($i = $minus; $i >= 0; $i--) {
			if ($tmp == 0)
				break;

			$tmp--;

			switch ($type) {
				case 'days':
					$year = date('Y', mktime(0, 0, 0, date('m'), date('j') - $i, date('Y')));
					$month = date('n', mktime(0, 0, 0, date('m'), date('j') - $i, date('Y')));
					$day = date('j', mktime(0, 0, 0, date('m'), date('j') - $i, date('Y')));
					break;
				case 'months':
					$year = date('Y', mktime(0, 0, 0, date('m') - $i, date('j'), date('Y')));
					$month = date('n', mktime(0, 0, 0, date('m') - $i, date('j'), date('Y')));
					$day = 1;
					break;
				case 'years':
					$year = date('Y', mktime(0, 0, 0, date('m'), date('j'), date('Y') - $i));
					$month = 1;
					$day = 1;
					break;
			}

			if (!empty($activity[$year][$month][$day]['l_total'])) {
				$output .= '<td>';

				if ($activity[$year][$month][$day]['l_total'] >= 10000)
					$output .= round($activity[$year][$month][$day]['l_total'] / 1000).'K';
				else
					$output .= $activity[$year][$month][$day]['l_total'];

				if ($activity[$year][$month][$day]['l_evening'] != 0) {
					$l_evening_barHeight = round(($activity[$year][$month][$day]['l_evening'] / $l_total_high) * 100);

					if ($l_evening_barHeight != 0)
						$output .= '<img src="'.$this->bar_evening.'" height="'.$l_evening_barHeight.'" alt="" title="" />';
				}

				if ($activity[$year][$month][$day]['l_afternoon'] != 0) {
					$l_afternoon_barHeight = round(($activity[$year][$month][$day]['l_afternoon'] / $l_total_high) * 100);

					if ($l_afternoon_barHeight != 0)
						$output .= '<img src="'.$this->bar_afternoon.'" height="'.$l_afternoon_barHeight.'" alt="" title="" />';
				}

				if ($activity[$year][$month][$day]['l_morning'] != 0) {
					$l_morning_barHeight = round(($activity[$year][$month][$day]['l_morning'] / $l_total_high) * 100);

					if ($l_morning_barHeight != 0)
						$output .= '<img src="'.$this->bar_morning.'" height="'.$l_morning_barHeight.'" alt="" title="" />';
				}

				if ($activity[$year][$month][$day]['l_night'] != 0) {
					$l_night_barHeight = round(($activity[$year][$month][$day]['l_night'] / $l_total_high) * 100);

					if ($l_night_barHeight != 0)
						$output .= '<img src="'.$this->bar_night.'" height="'.$l_night_barHeight.'" alt="" title="" />';
				}

				$output .= '</td>';
			} else
				$output .= '<td><span class="grey">n/a</span></td>';
		}

		$tmp = $cols;
		$output .= '</tr><tr class="sub">';

		for ($i = $minus; $i >= 0; $i--) {
			if ($tmp == 0)
				break;

			$tmp--;

			switch ($type) {
				case 'days':
					$date = date('Y-m-d', mktime(0, 0, 0, date('m'), date('j') - $i, date('Y')));

					if ($l_total_high_date == $date)
						$output .= '<td class="bold">'.date('D', strtotime($date)).'<br />'.date('j', strtotime($date)).'</td>';
					else
						$output .= '<td>'.date('D', strtotime($date)).'<br />'.date('j', strtotime($date)).'</td>';

					break;
				case 'months':
					$date = date('Y-m-01', mktime(0, 0, 0, date('m') - $i, date('j'), date('Y')));

					if ($l_total_high_date == $date)
						$output .= '<td class="bold">'.date('M', strtotime($date)).'<br />'.date('\'y', strtotime($date)).'</td>';
					else
						$output .= '<td>'.date('M', strtotime($date)).'<br />'.date('\'y', strtotime($date)).'</td>';

					break;
				case 'years':
					$date = date('Y-01-01', mktime(0, 0, 0, date('m'), date('j'), date('Y') - $i));

					if ($l_total_high_date == $date)
						$output .= '<td class="bold">'.date('\'y', strtotime($date)).'</td>';
					else
						$output .= '<td>'.date('\'y', strtotime($date)).'</td>';

					break;
			}
		}

		echo $output.'</tr></table>'."\n";
	}

	//makeTable_mostactivedays from file needs review
	private function makeTable_MostActiveDays($head)
	{
		$query = @mysql_query('SELECT SUM(`l_mon_night`) AS `l_mon_night`, SUM(`l_mon_morning`) AS `l_mon_morning`, SUM(`l_mon_afternoon`) AS `l_mon_afternoon`, SUM(`l_mon_evening`) AS `l_mon_evening`, SUM(`l_tue_night`) AS `l_tue_night`, SUM(`l_tue_morning`) AS `l_tue_morning`, SUM(`l_tue_afternoon`) AS `l_tue_afternoon`, SUM(`l_tue_evening`) AS `l_tue_evening`, SUM(`l_wed_night`) AS `l_wed_night`, SUM(`l_wed_morning`) AS `l_wed_morning`, SUM(`l_wed_afternoon`) AS `l_wed_afternoon`, SUM(`l_wed_evening`) AS `l_wed_evening`, SUM(`l_thu_night`) AS `l_thu_night`, SUM(`l_thu_morning`) AS `l_thu_morning`, SUM(`l_thu_afternoon`) AS `l_thu_afternoon`, SUM(`l_thu_evening`) AS `l_thu_evening`, SUM(`l_fri_night`) AS `l_fri_night`, SUM(`l_fri_morning`) AS `l_fri_morning`, SUM(`l_fri_afternoon`) AS `l_fri_afternoon`, SUM(`l_fri_evening`) AS `l_fri_evening`, SUM(`l_sat_night`) AS `l_sat_night`, SUM(`l_sat_morning`) AS `l_sat_morning`, SUM(`l_sat_afternoon`) AS `l_sat_afternoon`, SUM(`l_sat_evening`) AS `l_sat_evening`, SUM(`l_sun_night`) AS `l_sun_night`, SUM(`l_sun_morning`) AS `l_sun_morning`, SUM(`l_sun_afternoon`) AS `l_sun_afternoon`, SUM(`l_sun_evening`) AS `l_sun_evening` FROM `query_lines`') or exit('MySQL: '.mysql_error());
		$result = @mysql_fetch_object($query);
		$l_total_high = 0;
		$days = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

		foreach ($days as $day) {
			$l_total[$day] = $result->{'l_'.$day.'_night'} + $result->{'l_'.$day.'_morning'} + $result->{'l_'.$day.'_afternoon'} + $result->{'l_'.$day.'_evening'};

			if ($l_total[$day] > $l_total_high) {
				$l_total_high = $l_total[$day];
				$l_total_high_day = $day;
			}

			$l_night[$day] = $result->{'l_'.$day.'_night'};
			$l_morning[$day] = $result->{'l_'.$day.'_morning'};
			$l_afternoon[$day] = $result->{'l_'.$day.'_afternoon'};
			$l_evening[$day] = $result->{'l_'.$day.'_evening'};
		}

		$output = '<table class="mad"><tr><th colspan="7">'.$head.'</th></tr><tr class="bars">';

		foreach ($days as $day) {
			if ($l_total[$day] != 0) {
				$output .= '<td>';

				if ((($l_total[$day] / $this->l_total) * 100) >= 9.95)
					$output .= round(($l_total[$day] / $this->l_total) * 100).'%';
				else
					$output .= number_format(($l_total[$day] / $this->l_total) * 100, 1).'%';

				if ($l_evening[$day] != 0) {
					$l_evening_barHeight = round(($l_evening[$day] / $l_total_high) * 100);

					if ($l_evening_barHeight != 0)
						$output .= '<img src="'.$this->bar_evening.'" height="'.$l_evening_barHeight.'" alt="" title="'.number_format($l_total[$day]).'" />';
				}

				if ($l_afternoon[$day] != 0) {
					$l_afternoon_barHeight = round(($l_afternoon[$day] / $l_total_high) * 100);

					if ($l_afternoon_barHeight != 0)
						$output .= '<img src="'.$this->bar_afternoon.'" height="'.$l_afternoon_barHeight.'" alt="" title="'.number_format($l_total[$day]).'" />';
				}

				if ($l_morning[$day] != 0) {
					$l_morning_barHeight = round(($l_morning[$day] / $l_total_high) * 100);

					if ($l_morning_barHeight != 0)
						$output .= '<img src="'.$this->bar_morning.'" height="'.$l_morning_barHeight.'" alt="" title="'.number_format($l_total[$day]).'" />';
				}

				if ($l_night[$day] != 0) {
					$l_night_barHeight = round(($l_night[$day] / $l_total_high) * 100);

					if ($l_night_barHeight != 0)
						$output .= '<img src="'.$this->bar_night.'" height="'.$l_night_barHeight.'" alt="" title="'.number_format($l_total[$day]).'" />';
				}

				$output .= '</td>';
			} else
				$output .= '<td><span class="grey">n/a</span></td>';
		}

		$output .= '</tr><tr class="sub">';

		foreach ($days as $day)
			if ($l_total_high != 0 && $l_total_high_day == $day)
				$output .= '<td class="bold">'.ucfirst($day).'</td>';
			else
				$output .= '<td>'.ucfirst($day).'</td>';

		echo $output.'</tr></table>'."\n";
	}

	// I'm too stupid to come up with a cool SQL query that does half the below work for me, so here's a less elegant solution.
	private function table_topics()
	{
		$query = @mysql_query('SELECT `csTopic`, `setDate`, `csNick` FROM `user_topics` JOIN `user_details` ON `user_topics`.`UID` = `user_details`.`UID` ORDER BY `setDate` ASC');

		while ($result = @mysql_fetch_object($query)) {
			if (isset($lastDate)) {
				$days = floor((strtotime($result->setDate) - strtotime($lastDate)) / 86400);
				$topics[] = array($days, $lastUser, $lastTopic);
			}

			$lastTopic = $result->csTopic;
			$lastUser = $result->csNick;
			$lastDate = $result->setDate;
		}

		$days = floor((strtotime('yesterday') - strtotime($lastDate)) / 86400);
		$topics[] = array($days, $lastUser, $lastTopic);
		rsort($topics);

		for ($i = 1; $i <= 5; $i++) {
			$rows[] = array('v1' => $topics[$i-1][0]
				       ,'v2' => $topics[$i-1][1]
				       ,'v3' => $topics[$i-1][2]);
		}

		$this->makeTable2('large', 5, 'Longest Standing Topics', array('', 'Days', 'User', 'Topic'), 0, FALSE, $rows);
	}
}

?>
