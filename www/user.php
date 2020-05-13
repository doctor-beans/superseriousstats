<?php

/**
 * Copyright (c) 2007-2020, Jos de Ruijter <jos@dutnie.nl>
 */

/**
 * Class for creating user stats.
 */
class user
{
	use common_html_user_history, common_html_user;

	/**
	 * Default settings for this script, which can be overridden in the
	 * configuration file.
	 */
	private $channel = '';
	private $database = 'sss.db3';
	private $main_page = './';
	private $stylesheet = 'sss.css';
	private $timezone = 'UTC';
	private $userpics = false;
	private $userpics_default = '';
	private $userpics_dir = './userpics/';

	/**
	 * Variables that shouldn't be tampered with.
	 */
	private $cid = '';
	private $color = [
		'night' => 'b',
		'morning' => 'g',
		'afternoon' => 'y',
		'evening' => 'r'];
	private $columns_act_year = 0;
	private $csnick = '';
	private $datetime = [];
	private $estimate = false;
	private $l_total = 0;
	private $nick = '';
	private $output = '';
	private $ruid = 0;

	public function __construct($cid, $nick)
	{
		$this->cid = $cid;
		$this->nick = $nick;

		/**
		 * Load settings from vars.php (contained in $settings[]).
		 */
		if ((include 'vars.php') === false) {
			$this->output('error', 'The configuration file could not be read.');
		}

		/**
		 * $cid is the channel ID used in vars.php and is passed along in the URL so
		 * that channel specific settings can be identified and loaded.
		 */
		if (empty($settings[$this->cid])) {
			$this->output('error', 'This channel has not been configured.');
		}

		foreach ($settings[$this->cid] as $key => $value) {
			$this->$key = $value;
		}

		date_default_timezone_set($this->timezone);

		/**
		 * Open the database connection.
		 */
		try {
			$sqlite3 = new SQLite3($this->database, SQLITE3_OPEN_READONLY);
			$sqlite3->busyTimeout(0);
		} catch (Exception $e) {
			$this->output(null, basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$e->getMessage());
		}

		/**
		 * Set SQLite3 PRAGMAs:
		 *  query_only = ON - Disable all changes to database files.
		 *  temp_store = MEMORY - Temporary tables and indices are kept in memory.
		 */
		$pragmas = [
			'query_only' => 'ON',
			'temp_store' => 'MEMORY'];

		foreach ($pragmas as $key => $value) {
			$sqlite3->exec('PRAGMA '.$key.' = '.$value);
		}

		/**
		 * Make stats!
		 */
		echo $this->make_html($sqlite3);
		$sqlite3->close();
	}

	/**
	 * Look for an image in $userpics_dir that has an identical name to one of the
	 * user's aliases and return it.
	 */
	private function get_userpic($sqlite3)
	{
		/**
		 * Try to open and read from $userpics_dir.
		 */
		if (is_dir($this->userpics_dir) && ($dh = opendir($this->userpics_dir)) !== false) {
			while (($file = readdir($dh)) !== false) {
				if (preg_match('/^(?<name>\S+)\.(bmp|gif|jpe?g|png)$/i', $file, $matches)) {
					$files[strtolower($matches['name'])] = $file;
				}
			}

			closedir($dh);
		}

		/**
		 * If there are no images found we can stop here.
		 */
		if (!isset($files)) {
			return null;
		}

		/**
		 * Fetch the user's aliases.
		 */
		$query = $sqlite3->query('SELECT csnick FROM uid_details WHERE ruid = '.$this->ruid) or $this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());

		while ($result = $query->fetchArray(SQLITE3_ASSOC)) {
			$aliases[] = strtolower($result['csnick']);
		}

		/**
		 * Return the first image that matches any of the user's aliases.
		 */
		foreach ($files as $name => $file) {
			if (in_array($name, $aliases)) {
				return '<img src="'.htmlspecialchars(realpath($this->userpics_dir.'/'.$file)).'" alt="" class="userpic">';
			}
		}

		/**
		 * If no specific image is found for the user return the default image provided
		 * in $userpics_default or a randomly selected one if it is a list of images.
		 */
		if (preg_match('/^\S+\.(bmp|gif|jpe?g|png)(,\S+\.(bmp|gif|jpe?g|png))*$/i', $this->userpics_default)) {
			$userpics_default = explode(',', $this->userpics_default);
			return '<img src="'.htmlspecialchars(realpath($this->userpics_dir.'/'.$userpics_default[mt_rand(0, count($userpics_default) - 1)])).'" alt="" class="userpic">';
		}
	}

	/**
	 * Generate the HTML page.
	 */
	private function make_html($sqlite3)
	{
		if (($this->ruid = $sqlite3->querySingle('SELECT ruid FROM uid_details WHERE csnick = \''.$sqlite3->escapeString($this->nick).'\'')) === false) {
			$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
		}

		if (is_null($this->ruid)) {
			$this->output('error', 'Nonexistent nickname.');
		}

		if (($result = $sqlite3->querySingle('SELECT (SELECT csnick FROM uid_details WHERE uid = '.$this->ruid.') AS csnick, MIN(firstseen) AS date_first, MAX(lastseen) AS date_last, l_total, CAST(l_total AS REAL) / activedays AS l_avg FROM uid_details JOIN ruid_lines ON uid_details.ruid = ruid_lines.ruid WHERE uid_details.ruid = '.$this->ruid.' AND firstseen != \'0000-00-00 00:00:00\'', true)) === false) {
			$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
		}

		/**
		 * All queries from this point forward require a non-empty database.
		 */
		if (empty($result['l_total'])) {
			$this->output('error', 'This user does not have any activity logged.');
		}

		$date_first = $result['date_first'];
		$date_last = $result['date_last'];
		$l_avg = (int) round($result['l_avg']);
		$this->csnick = $result['csnick'];
		$this->l_total = $result['l_total'];

		/**
		 * Fetch the users mood.
		 */
		if (($result = $sqlite3->querySingle('SELECT * FROM ruid_smileys WHERE ruid = '.$this->ruid, true)) === false) {
			$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
		}

		if (empty($result)) {
			$mood = '';
		} else {
			$smileys = [
				's_01' => ':)',
				's_02' => ';)',
				's_03' => ':(',
				's_04' => ':P',
				's_05' => ':D',
				's_06' => ';(',
				's_07' => ':/',
				's_08' => '\\o/',
				's_09' => ':))',
				's_10' => '<3',
				's_11' => ':o',
				's_12' => '=)',
				's_13' => ':-)',
				's_14' => ':x',
				's_15' => ':\\',
				's_16' => 'D:',
				's_17' => ':|',
				's_18' => ';-)',
				's_19' => ';P',
				's_20' => '=]',
				's_21' => ':3',
				's_22' => '8)',
				's_23' => ':<',
				's_24' => ':>',
				's_25' => '=P',
				's_26' => ';x',
				's_27' => ':-D',
				's_28' => ';))',
				's_29' => ':]',
				's_30' => ';D',
				's_31' => '-_-',
				's_32' => ':S',
				's_33' => '=/',
				's_34' => '=\\',
				's_35' => ':((',
				's_36' => '=D',
				's_37' => ':-/',
				's_38' => ':-P',
				's_39' => ';_;',
				's_40' => ';/',
				's_41' => ';]',
				's_42' => ':-(',
				's_43' => ':\'(',
				's_44' => '=(',
				's_45' => '-.-',
				's_46' => ';((',
				's_47' => '=X',
				's_48' => ':[',
				's_49' => '>:(',
				's_50' => ';o'];
			arsort($result);

			foreach ($result as $key => $value) {
				if ($key !== 'ruid') {
					$mood = $smileys[$key];
					break;
				}
			}
		}

		if (($date_lastlogparsed = $sqlite3->querySingle('SELECT MAX(date) FROM parse_history')) === false) {
			$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
		}

		/**
		 * Date and time variables used throughout the script. These are based on the
		 * date of the last logfile parsed, and are used to define our scope.
		 */
		$this->datetime['currentyear'] = (int) date('Y');
		$this->datetime['dayofmonth'] = (int) date('j', strtotime($date_lastlogparsed));
		$this->datetime['month'] = (int) date('n', strtotime($date_lastlogparsed));
		$this->datetime['year'] = (int) date('Y', strtotime($date_lastlogparsed));
		$this->datetime['daysleft'] = (int) date('z', strtotime('last day of December '.$this->datetime['year'])) - (int) date('z', strtotime($date_lastlogparsed));

		/**
		 * If there are one or more days to come until the end of the year, display an
		 * additional column in the Activity by Year table with an estimated line count
		 * for the current year.
		 */
		if ($this->datetime['daysleft'] !== 0 && $this->datetime['year'] === $this->datetime['currentyear']) {
			/**
			 * Base the estimation on the activity in the last 90 days logged, if there is
			 * any.
			 */
			if (($activity = $sqlite3->querySingle('SELECT COUNT(*) FROM ruid_activity_by_day WHERE ruid = '.$this->ruid.' AND date > \''.date('Y-m-d', mktime(0, 0, 0, $this->datetime['month'], $this->datetime['dayofmonth'] - 90, $this->datetime['year'])).'\'')) === false) {
				$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
			}

			if ($activity !== 0) {
				$this->estimate = true;
			}
		}

		/**
		 * Show a minimum of 3 and maximum of 24 columns in the Activity by Year table.
		 * In case the data allows for more than 16 columns there won't be any room for
		 * the Activity Distribution by Day table to be adjacent to the right so we pad
		 * the Activity by Year table up to 24 columns so it looks neat.
		 */
		$this->columns_act_year = $this->datetime['year'] - (int) date('Y', strtotime($date_first)) + ($this->estimate ? 1 : 0) + 1;

		if ($this->columns_act_year < 3) {
			$this->columns_act_year = 3;
		} elseif ($this->columns_act_year > 16) {
			$this->columns_act_year = 24;
		}

		/**
		 * HTML Head.
		 */
		if (($result = $sqlite3->querySingle('SELECT MIN(date) AS date, l_total FROM ruid_activity_by_day WHERE ruid = '.$this->ruid.' AND l_total = (SELECT MAX(l_total) FROM ruid_activity_by_day WHERE ruid = '.$this->ruid.')', true)) === false) {
			$this->output($sqlite3->lastErrorCode(), basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$sqlite3->lastErrorMsg());
		}

		$date_l_max = $result['date'];
		$l_max = $result['l_total'];
		$this->output = '<!DOCTYPE html>'."\n\n"
			. '<html>'."\n\n"
			. '<head>'."\n"
			. '<meta charset="utf-8">'."\n"
			. '<title>'.htmlspecialchars($this->csnick).', seriously.</title>'."\n"
			. '<link rel="stylesheet" href="'.$this->stylesheet.'">'."\n"
			. '<style type="text/css">'."\n"
			. '  .act-year { width:'.(2 + ($this->columns_act_year * 34)).'px }'."\n"
			. '</style>'."\n"
			. '</head>'."\n\n"
			. '<body><div id="container">'."\n"
			. '<div class="info">'.($this->userpics ? $this->get_userpic($sqlite3) : '').htmlspecialchars($this->csnick).', seriously'.($mood !== '' ? ' '.htmlspecialchars($mood) : '.').'<br><br>'
			. 'First seen on '.date('M j, Y', strtotime($date_first)).' and last seen on '.date('M j, Y', strtotime($date_last)).'.<br><br>'
			. htmlspecialchars($this->csnick).' typed '.number_format($this->l_total).' line'.($this->l_total > 1 ? 's' : '').' on <a href="'.htmlspecialchars($this->mainpage).'">'.htmlspecialchars($this->channel).'</a> &ndash; an average of '.number_format($l_avg).' line'.($l_avg > 1 ? 's' : '').' per day.<br>'
			. 'Most active day was '.date('M j, Y', strtotime($date_l_max)).' with a total of '.number_format($l_max).' line'.($l_max > 1 ? 's' : '').' typed.</div>'."\n";

		/**
		 * Activity section.
		 */
		$this->output .= '<div class="section">Activity</div>'."\n";
		$this->output .= $this->create_table_activity_distribution_hour('user');
		$this->output .= $this->make_table_activity($sqlite3, 'day');
		$this->output .= $this->make_table_activity($sqlite3, 'month');
		$this->output .= $this->make_table_activity($sqlite3, 'year');
		$this->output .= $this->create_table_activity_distribution_day('user');

		/**
		 * HTML Foot.
		 */
		$this->output .= '<div class="info">Statistics created with <a href="http://sss.dutnie.nl">superseriousstats</a> on '.date('r').'.</div>'."\n";
		$this->output .= '</div></body>'."\n\n".'</html>'."\n";
		return $this->output;
	}

	/**
	 * For compatibility reasons this function has the same name as the version
	 * found in output.php and accepts the same arguments. Its functionality is
	 * slightly different in that it exits on any type of message passed to it.
	 * SQLite3 result code 5 = SQLITE_BUSY, result code 6 = SQLITE_LOCKED.
	 */
	private function output($code, $msg)
	{
		if ($code === 5 || $code === 6) {
			$msg = 'Statistics are currently being updated, this may take a minute.';
		}

		exit('<!DOCTYPE html>'."\n\n".'<html><head><meta charset="utf-8"><title>seriously?</title><link rel="stylesheet" href="sss.css"></head><body><div id="container"><div class="error">'.htmlspecialchars($msg).'</div></div></body></html>'."\n");
	}
}

/**
 * The channel ID must be set, cannot be empty and cannot be of excessive
 * length.
 */
if (empty($_GET['cid']) || !preg_match('/^\S{1,32}$/', $_GET['cid'])) {
	exit('<!DOCTYPE html>'."\n\n".'<html><head><meta charset="utf-8"><title>seriously?</title><link rel="stylesheet" href="sss.css"></head><body><div id="container"><div class="error">Invalid channel ID.</div></div></body></html>'."\n");
}

$cid = $_GET['cid'];

/**
 * The nick must be set, cannot be zero, empty, nor contain invalid characters.
 */
if (empty($_GET['nick']) || !preg_match('/^[][^{}|\\\`_a-z][][^{}|\\\`_a-z0-9-]{0,31}$/i', $_GET['nick'])) {
	exit('<!DOCTYPE html>'."\n\n".'<html><head><meta charset="utf-8"><title>seriously?</title><link rel="stylesheet" href="sss.css"></head><body><div id="container"><div class="error">Erroneous nickname.</div></div></body></html>'."\n");
}

$nick = $_GET['nick'];

/**
 * Make stats!
 */
$user = new user($cid, $nick);
