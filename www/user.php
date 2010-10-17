<?php

/**
 * Copyright (c) 2007-2010, Jos de Ruijter <jos@dutnie.nl>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
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
 * Class for creating userstats.
 */
final class user
{
	/**
	 * USER EDITABLE SETTINGS: the important stuff; database, timezone, etc.
	 */
	private $channel = '#yourchan';
	private $db_host = '127.0.0.1';
	private $db_port = 3306;
	private $db_user = 'user';
	private $db_pass = 'pass';
	private $db_name = 'sss';
	private $timezone = 'Europe/Amsterdam';

	/**
	 * USER EDITABLE SETTINGS: less important stuff; style and presentation.
	 */
	private $bar_afternoon = 'y.png';
	private $bar_evening = 'r.png';
	private $bar_morning = 'g.png';
	private $bar_night = 'b.png';
	private $stylesheet = 'sss.css';

	/**
	 * Only set to true when troubleshooting.
	 */
	private $debug = false;

	/**
	 * Variables that shouldn't be tampered with.
	 */
	private $csnick = '';
	private $date_lastlogparsed = '';
	private $date_max = '';
	private $dayofmonth = 0;
	private $firstseen = '';
	private $l_avg = 0;
	private $l_max = 0;
	private $l_total = 0;
	private $lastseen = '';
	private $month = 0;
	private $mood = '';
	private $mysqli;
	private $ruid = 0;
	private $uid = 0;
	private $year = 0;
	private $years = 0;

	public function __construct($uid)
	{
		$this->uid = $uid;
		date_default_timezone_set($this->timezone);
	}

	/**
	 * Exit with ($debug = true) or without ($debug = false) an error message.
	 */
	private function output($type, $msg)
	{
		if ($this->debug) {
			exit($msg."\n");
		} else {
			exit;
		}
	}

	public function make_html()
	{
		$this->mysqli = @mysqli_connect($this->db_host, $this->db_user, $this->db_pass, $this->db_name, $this->db_port) or $this->output('critical', 'mysqli: '.mysqli_connect_error());
		$query = @mysqli_query($this->mysqli, 'select `ruid`, `csnick` from `user_status` join `user_details` on `user_status`.`ruid` = `user_details`.`uid` where `user_status`.`uid` = '.$this->uid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			exit('This user doesn\'t exist.'."\n");
		}

		$result = mysqli_fetch_object($query);
		$this->ruid = (int) $result->ruid;
		$this->csnick = $result->csnick;
		$query = @mysqli_query($this->mysqli, 'select min(`firstseen`) as `firstseen`, max(`lastseen`) as `lastseen`, `l_total`, (`l_total` / `activedays`) as `l_avg` from `q_lines` join `user_status` on `q_lines`.`ruid` = `user_status`.`ruid` join `user_details` on `user_status`.`uid` = `user_details`.`uid` where `q_lines`.`ruid` = '.$this->ruid.' and `firstseen` != \'0000-00-00 00:00:00\' group by `q_lines`.`ruid`') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);

		if ((int) $result->l_total == 0) {
			exit('This user has no lines.'."\n");
		}

		$this->firstseen = $result->firstseen;
		$this->lastseen = $result->lastseen;
		$this->l_avg = (float) $result->l_avg;
		$this->l_total = (int) $result->l_total;

		/**
		 * Fetch the users mood.
		 */
		$query = @mysqli_query($this->mysqli, 'select `s_01` as `s_01`, `s_02` as `s_02`, `s_03` as `s_03`, `s_04` as `s_04`, `s_05` as `s_05`, `s_06` as `s_06`, `s_07` as `s_07`, `s_08` as `s_08`, `s_09` as `s_09`, `s_10` as `s_10`, `s_11` as `s_11`, `s_12` as `s_12`, `s_13` as `s_13`, `s_14` as `s_14`, `s_15` as `s_15`, `s_16` as `s_16`, `s_17` as `s_17`, `s_18` as `s_18`, `s_19` as `s_19` from `q_smileys` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (!empty($rows)) {
			$result = mysqli_fetch_object($query);
			$high_key = '';
			$high_value = 0;

			foreach ($result as $key => $value) {
				if ((int) $value > $high_value) {
					$high_key = $key;
					$high_value = (int) $value;
				}
			}

			$smileys = array(
				's_01' => '=]',
				's_02' => '=)',
				's_03' => ';x',
				's_04' => ';p',
				's_05' => ';]',
				's_06' => ';-)',
				's_07' => ';)',
				's_08' => ';(',
				's_09' => ':x',
				's_10' => ':P',
				's_11' => ':D',
				's_12' => ':>',
				's_13' => ':]',
				's_14' => ':\\',
				's_15' => ':/',
				's_16' => ':-)',
				's_17' => ':)',
				's_18' => ':(',
				's_19' => '\\o/');
			$this->mood = ' '.$smileys[$high_key];
		}

		/**
		 * Date and time variables used throughout the script. We take the date of the last logfile parsed. These variables are used to define our scope.
		 */
		$query = @mysqli_query($this->mysqli, 'select max(`date`) as `date` from `parse_history`') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$this->date_lastlogparsed = $result->date;
		$this->dayofmonth = (int) date('j', strtotime($this->date_lastlogparsed));
		$this->month = (int) date('n', strtotime($this->date_lastlogparsed));
		$this->year = (int) date('Y', strtotime($this->date_lastlogparsed));
		$this->years = $this->year - (int) date('Y', strtotime($this->firstseen)) + 1;

		/**
		 * If we have less than 3 years of data we set the amount of years to 3 so we have that many columns in our table. Looks better.
		 */
		if ($this->years < 3) {
			$this->years = 3;
		}

		/**
		 * HTML Head
		 */
		$query = @mysqli_query($this->mysqli, 'select `date` as `date_max`, `l_total` as `l_max` from `q_activity_by_day` where `ruid` = '.$this->ruid.' order by `l_max` desc, `date_max` asc limit 1') or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$result = mysqli_fetch_object($query);
		$this->date_max = $result->date_max;
		$this->l_max = (int) $result->l_max;
		$output = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'."\n\n"
			. '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">'."\n\n"
			. '<head>'."\n".'<title>'.htmlspecialchars($this->csnick).', seriously.</title>'."\n"
			. '<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />'."\n"
			. '<meta http-equiv="Content-Style-Type" content="text/css" />'."\n"
			. '<link rel="stylesheet" type="text/css" href="'.$this->stylesheet.'" />'."\n"
			. '<style type="text/css">'."\n"
			. '  .yearly {width:'.(2 + ($this->years * 34)).'px}'."\n"
			. '</style>'."\n"
			. '</head>'."\n\n".'<body>'."\n"
			. '<div class="box">'."\n\n"
			. '<div class="info">'.htmlspecialchars($this->csnick).', seriously'.($this->mood != '' ? $this->mood : '.').'<br /><br />First seen on '.date('M j, Y', strtotime($this->firstseen)).' and last seen on '.date('M j, Y', strtotime($this->lastseen)).'.<br />'
			. '<br />'.htmlspecialchars($this->csnick).' typed '.number_format($this->l_total).' lines on '.htmlspecialchars($this->channel).', an average of '.number_format($this->l_avg).' lines per day.<br />Most active day was '.date('M j, Y', strtotime($this->date_max)).' with a total of '.number_format($this->l_max).' lines typed.</div>'."\n";

		/**
		 * Activity section
		 */
		$output .= '<div class="head">Activity</div>'."\n";
		$output .= $this->make_table_mostactivetimes();
		$output .= $this->make_table_activity('daily');
		$output .= $this->make_table_activity('monthly');
		$output .= $this->make_table_mostactivedays();
		$output .= $this->make_table_activity('yearly');

		/**
		 * HTML Foot
		 */
		$output .= '<div class="info">Statistics created with <a href="http://code.google.com/p/superseriousstats/">superseriousstats</a> on '.date('r').'.</div>'."\n\n";
		$output .= '</div>'."\n".'</body>'."\n\n".'</html>'."\n";
		@mysqli_close($this->mysqli);
		return $output;
	}

	private function make_table_mostactivetimes()
	{
		$query = @mysqli_query($this->mysqli, 'select `l_00`, `l_01`, `l_02`, `l_03`, `l_04`, `l_05`, `l_06`, `l_07`, `l_08`, `l_09`, `l_10`, `l_11`, `l_12`, `l_13`, `l_14`, `l_15`, `l_16`, `l_17`, `l_18`, `l_19`, `l_20`, `l_21`, `l_22`, `l_23` from `q_lines` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			return;
		}

		$result = mysqli_fetch_object($query);
		$high_key = '';
		$high_value = 0;

		foreach ($result as $key => $value) {
			if ((int) $value > $high_value) {
				$high_key = $key;
				$high_value = (int) $value;
			}
		}

		$tr1 = '<tr><th colspan="24">Most Active Times</th></tr>';
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($result as $key => $value) {
			if (substr($key, -2, 1) == '0') {
				$hour = (int) substr($key, -1);
			} else {
				$hour = (int) substr($key, -2);
			}

			if ((int) $value == 0) {
				$tr2 .= '<td><span class="grey">n/a</span></td>';
			} else {
				$perc = ((int) $value / $this->l_total) * 100;

				if ($perc >= 9.95) {
					$tr2 .= '<td>'.round($perc).'%';
				} else {
					$tr2 .= '<td>'.number_format($perc, 1).'%';
				}

				$height = round(((int) $value / $high_value) * 100);

				if ($height != 0) {
					if ($hour >= 0 && $hour <= 5) {
						$tr2 .= '<img src="'.$this->bar_night.'" height="'.$height.'" alt="" title="'.number_format((int) $value).'" />';
					} elseif ($hour >= 6 && $hour <= 11) {
						$tr2 .= '<img src="'.$this->bar_morning.'" height="'.$height.'" alt="" title="'.number_format((int) $value).'" />';
					} elseif ($hour >= 12 && $hour <= 17) {
						$tr2 .= '<img src="'.$this->bar_afternoon.'" height="'.$height.'" alt="" title="'.number_format((int) $value).'" />';
					} elseif ($hour >= 18 && $hour <= 23) {
						$tr2 .= '<img src="'.$this->bar_evening.'" height="'.$height.'" alt="" title="'.number_format((int) $value).'" />';
					}
				}

				$tr2 .= '</td>';
			}

			if ($high_key == $key) {
				$tr3 .= '<td class="bold">'.$hour.'h</td>';
			} else {
				$tr3 .= '<td>'.$hour.'h</td>';
			}
		}

		$tr2 .= '</tr>';
		$tr3 .= '</tr>';
		return '<table class="graph">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}

	private function make_table_activity($type)
	{
		if ($type == 'daily') {
			$class = 'graph';
			$cols = 24;

			for ($i = 23; $i >= 0; $i--) {
				$dates[] = date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - $i, $this->year));
			}

			$head = 'Daily Activity';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_day` where `date` > \''.date('Y-m-d', mktime(0, 0, 0, $this->month, $this->dayofmonth - 24, $this->year)).'\' and `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		} elseif ($type == 'monthly') {
			$class = 'graph';
			$cols = 24;

			for ($i = 23; $i >= 0; $i--) {
				$dates[] = date('Y-m', mktime(0, 0, 0, $this->month - $i, 1, $this->year));
			}

			$head = 'Monthly Activity';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_month` where `date` > \''.date('Y-m', mktime(0, 0, 0, $this->month - 24, 1, $this->year)).'\' and `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		} elseif ($type == 'yearly') {
			$class = 'yearly';
			$cols = $this->years;

			for ($i = $this->years - 1; $i >= 0; $i--) {
				$dates[] = $this->year - $i;
			}

			$head = 'Yearly Activity';
			$query = @mysqli_query($this->mysqli, 'select `date`, `l_total`, `l_night`, `l_morning`, `l_afternoon`, `l_evening` from `q_activity_by_year` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		}

		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			return;
		}

		$high_date = '';
		$high_value = 0;

		while ($result = mysqli_fetch_object($query)) {
			$l_night[$result->date] = (int) $result->l_night;
			$l_morning[$result->date] = (int) $result->l_morning;
			$l_afternoon[$result->date] = (int) $result->l_afternoon;
			$l_evening[$result->date] = (int) $result->l_evening;
			$l_total[$result->date] = (int) $result->l_total;

			if ($l_total[$result->date] > $high_value) {
				$high_date = $result->date;
				$high_value = $l_total[$result->date];
			}
		}

		$tr1 = '<tr><th colspan="'.$cols.'">'.$head.'</th></tr>';
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($dates as $date) {
			if (!array_key_exists($date, $l_total) || $l_total[$date] == 0) {
				$tr2 .= '<td><span class="grey">n/a</span></td>';
			} else {
				if ($l_total[$date] >= 999500) {
					$tr2 .= '<td>'.number_format($l_total[$date] / 1000000, 1).'M';
				} elseif ($l_total[$date] >= 10000) {
					$tr2 .= '<td>'.round($l_total[$date] / 1000).'K';
				} else {
					$tr2 .= '<td>'.$l_total[$date];
				}

				$times = array('evening', 'afternoon', 'morning', 'night');

				foreach ($times as $time) {
					if (${'l_'.$time}[$date] != 0) {
						$height = round((${'l_'.$time}[$date] / $high_value) * 100);

						if ($height != 0) {
							$tr2 .= '<img src="'.$this->{'bar_'.$time}.'" height="'.$height.'" alt="" title="" />';
						}
					}
				}

				$tr2 .= '</td>';
			}

			if ($type == 'daily') {
				if ($high_date == $date) {
					$tr3 .= '<td class="bold">'.date('D', strtotime($date)).'<br />'.date('j', strtotime($date)).'</td>';
				} else {
					$tr3 .= '<td>'.date('D', strtotime($date)).'<br />'.date('j', strtotime($date)).'</td>';
				}
			} elseif ($type == 'monthly') {
				if ($high_date == $date) {
					$tr3 .= '<td class="bold">'.date('M', strtotime($date.'-01')).'<br />'.date('\'y', strtotime($date.'-01')).'</td>';
				} else {
					$tr3 .= '<td>'.date('M', strtotime($date.'-01')).'<br />'.date('\'y', strtotime($date.'-01')).'</td>';
				}
			} elseif ($type == 'yearly') {
				if ($high_date == $date) {
					$tr3 .= '<td class="bold">'.date('\'y', strtotime($date.'-01-01')).'</td>';
				} else {
					$tr3 .= '<td>'.date('\'y', strtotime($date.'-01-01')).'</td>';
				}
			}
		}

		$tr2 .= '</tr>';
		$tr3 .= '</tr>';
		return '<table class="'.$class.'">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}

	private function make_table_mostactivedays()
	{
		$query = @mysqli_query($this->mysqli, 'select `l_mon_night`, `l_mon_morning`, `l_mon_afternoon`, `l_mon_evening`, `l_tue_night`, `l_tue_morning`, `l_tue_afternoon`, `l_tue_evening`, `l_wed_night`, `l_wed_morning`, `l_wed_afternoon`, `l_wed_evening`, `l_thu_night`, `l_thu_morning`, `l_thu_afternoon`, `l_thu_evening`, `l_fri_night`, `l_fri_morning`, `l_fri_afternoon`, `l_fri_evening`, `l_sat_night`, `l_sat_morning`, `l_sat_afternoon`, `l_sat_evening`, `l_sun_night`, `l_sun_morning`, `l_sun_afternoon`, `l_sun_evening` from `q_lines` where `ruid` = '.$this->ruid) or $this->output('critical', 'mysqli: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			return;
		}

		$result = mysqli_fetch_object($query);
		$high_day = '';
		$high_value = 0;
		$days = array('mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun');

		foreach ($days as $day) {
			$l_night[$day] = (int) $result->{'l_'.$day.'_night'};
			$l_morning[$day] = (int) $result->{'l_'.$day.'_morning'};
			$l_afternoon[$day] = (int) $result->{'l_'.$day.'_afternoon'};
			$l_evening[$day] = (int) $result->{'l_'.$day.'_evening'};
			$l_total[$day] = $l_night[$day] + $l_morning[$day] + $l_afternoon[$day] + $l_evening[$day];

			if ($l_total[$day] > $high_value) {
				$high_day = $day;
				$high_value = $l_total[$day];
			}
		}

		$tr1 = '<tr><th colspan="7">Most Active Days</th></tr>';
		$tr2 = '<tr class="bars">';
		$tr3 = '<tr class="sub">';

		foreach ($days as $day) {
			if ($l_total[$day] == 0) {
				$tr2 .= '<td><span class="grey">n/a</span></td>';
			} else {
				$perc = ($l_total[$day] / $this->l_total) * 100;

				if ($perc >= 9.95) {
					$tr2 .= '<td>'.round($perc).'%';
				} else {
					$tr2 .= '<td>'.number_format($perc, 1).'%';
				}

				$times = array('evening', 'afternoon', 'morning', 'night');

				foreach ($times as $time) {
					if (${'l_'.$time}[$day] != 0) {
						$height = round((${'l_'.$time}[$day] / $high_value) * 100);

						if ($height != 0) {
							$tr2 .= '<img src="'.$this->{'bar_'.$time}.'" height="'.$height.'" alt="" title="'.number_format($l_total[$day]).'" />';
						}
					}
				}

				$tr2 .= '</td>';
			}

			if ($high_day == $day) {
				$tr3 .= '<td class="bold">'.ucfirst($day).'</td>';
			} else {
				$tr3 .= '<td>'.ucfirst($day).'</td>';
			}
		}

		$tr2 .= '</tr>';
		$tr3 .= '</tr>';
		return '<table class="mad">'.$tr1.$tr2.$tr3.'</table>'."\n";
	}
}

if (isset($_GET['uid']) && preg_match('/^[1-9]\d{0,8}$/', $_GET['uid'])) {
	$user = new user((int) $_GET['uid']);
	echo $user->make_html();
}

?>
