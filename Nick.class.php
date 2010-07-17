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
 * Class for handling user data.
 */
final class Nick extends Base
{
	/**
	 * Variables used in database table "user_details".
	 */
	private $UID = 0;
	protected $csNick = '';
	protected $firstSeen = '';
	protected $lastSeen = '';

	/**
	 * Variables used in database table "user_hosts".
	 */
	private $hosts_list = array();

	/**
	 * Variables used in database table "user_topics".
	 */
	private $topics_list = array();

	/**
	 * Variables used in database table "user_URLs".
	 */
	private $URLs_objs = array();

	/**
	 * Variables used in database table "user_events".
	 */
	protected $m_op = 0;
	protected $m_opped = 0;
	protected $m_voice = 0;
	protected $m_voiced = 0;
	protected $m_deOp = 0;
	protected $m_deOpped = 0;
	protected $m_deVoice = 0;
	protected $m_deVoiced = 0;
	protected $joins = 0;
	protected $parts = 0;
	protected $quits = 0;
	protected $kicks = 0;
	protected $kicked = 0;
	protected $nickchanges = 0;
	protected $topics = 0;
	protected $ex_kicks = '';
	protected $ex_kicked = '';

	/**
	 * Variables used in database tables "user_lines" and "user_activity".
	 */
	protected $l_00 = 0;
	protected $l_01 = 0;
	protected $l_02 = 0;
	protected $l_03 = 0;
	protected $l_04 = 0;
	protected $l_05 = 0;
	protected $l_06 = 0;
	protected $l_07 = 0;
	protected $l_08 = 0;
	protected $l_09 = 0;
	protected $l_10 = 0;
	protected $l_11 = 0;
	protected $l_12 = 0;
	protected $l_13 = 0;
	protected $l_14 = 0;
	protected $l_15 = 0;
	protected $l_16 = 0;
	protected $l_17 = 0;
	protected $l_18 = 0;
	protected $l_19 = 0;
	protected $l_20 = 0;
	protected $l_21 = 0;
	protected $l_22 = 0;
	protected $l_23 = 0;
	protected $l_night = 0;
	protected $l_morning = 0;
	protected $l_afternoon = 0;
	protected $l_evening = 0;
	protected $l_total = 0;
	protected $l_mon_night = 0;
	protected $l_mon_morning = 0;
	protected $l_mon_afternoon = 0;
	protected $l_mon_evening = 0;
	protected $l_tue_night = 0;
	protected $l_tue_morning = 0;
	protected $l_tue_afternoon = 0;
	protected $l_tue_evening = 0;
	protected $l_wed_night = 0;
	protected $l_wed_morning = 0;
	protected $l_wed_afternoon = 0;
	protected $l_wed_evening = 0;
	protected $l_thu_night = 0;
	protected $l_thu_morning = 0;
	protected $l_thu_afternoon = 0;
	protected $l_thu_evening = 0;
	protected $l_fri_night = 0;
	protected $l_fri_morning = 0;
	protected $l_fri_afternoon = 0;
	protected $l_fri_evening = 0;
	protected $l_sat_night = 0;
	protected $l_sat_morning = 0;
	protected $l_sat_afternoon = 0;
	protected $l_sat_evening = 0;
	protected $l_sun_night = 0;
	protected $l_sun_morning = 0;
	protected $l_sun_afternoon = 0;
	protected $l_sun_evening = 0;
	protected $URLs = 0;
	protected $words = 0;
	protected $characters = 0;
	protected $monologues = 0;
	protected $topMonologue = 0;
	protected $activeDays = 0;
	protected $slaps = 0;
	protected $slapped = 0;
	protected $exclamations = 0;
	protected $questions = 0;
	protected $actions = 0;
	protected $uppercased = 0;
	protected $quote = '';
	protected $ex_exclamations = '';
	protected $ex_questions = '';
	protected $ex_actions = '';
	protected $ex_uppercased = '';
	protected $lastTalked = '';

	/**
	 * Variables used in database table "user_smileys".
	 */
	protected $s_01 = 0;
	protected $s_02 = 0;
	protected $s_03 = 0;
	protected $s_04 = 0;
	protected $s_05 = 0;
	protected $s_06 = 0;
	protected $s_07 = 0;
	protected $s_08 = 0;
	protected $s_09 = 0;
	protected $s_10 = 0;
	protected $s_11 = 0;
	protected $s_12 = 0;
	protected $s_13 = 0;
	protected $s_14 = 0;
	protected $s_15 = 0;
	protected $s_16 = 0;
	protected $s_17 = 0;
	protected $s_18 = 0;
	protected $s_19 = 0;

	/**
	 * Variables that shouldn't be tampered with.
	 */
	private $long_ex_actions_list = array();
	private $long_ex_exclamations_list = array();
	private $long_ex_questions_list = array();
	private $long_ex_uppercased_list = array();
	private $long_quote_list = array();
	private $short_ex_actions_list = array();
	private $short_ex_exclamations_list = array();
	private $short_ex_questions_list = array();
	private $short_ex_uppercased_list = array();
	private $short_quote_list = array();
	protected $date = '';
	protected $mysqli;

	/**
	 * Constructor.
	 */
	public function __construct($csNick)
	{
		$this->csNick = $csNick;
	}

	/**
	 * Keep a list of hosts this user has been seen using.
	 */
	public function addHost($csHost)
	{
		$host = strtolower($csHost);

		if (!in_array($host, $this->hosts_list)) {
			$this->hosts_list[] = $host;
		}
	}

	/**
	 * Keep two lists of the various types of quotes for the user; one with short quotes and one with long quotes.
	 */
	public function addQuote($type, $length, $line)
	{
		$this->{$length.'_'.$type.'_list'}[] = $line;
	}

	/**
	 * Keep a list of topics set by the user.
	 */
	public function addTopic($csTopic, $dateTime)
	{
		$this->topics_list[] = array('csTopic' => $csTopic
					    ,'setDate' => $dateTime);
	}

	/**
	 * Keep a list of URLs pasted by the user. The last used case sensitivity will be stored for the specific URL.
	 */
	public function addURL($csURL, $dateTime)
	{
		$URL = strtolower($csURL);

		if (!array_key_exists($URL, $this->URLs_objs)) {
			$this->URLs_objs[$URL] = new URL($csURL);
		} else {
			$this->URLs_objs[$URL]->setValue('csURL', $csURL);
		}

		$this->URLs_objs[$URL]->addValue('total', 1);
		$this->URLs_objs[$URL]->lastUsed($dateTime);
	}

	/**
	 * Store the date and time of when the user was first and last seen.
	 */
	public function lastSeen($dateTime)
	{
		if ($this->firstSeen == '' || strtotime($dateTime) < strtotime($this->firstSeen)) {
			$this->firstSeen = $dateTime;
		}

		if ($this->lastSeen == '' || strtotime($dateTime) > strtotime($this->lastSeen)) {
			$this->lastSeen = $dateTime;
		}
	}

	/**
	 * Store the date and time of when the user last typed a "normal" line.
	 */
	public function lastTalked($dateTime)
	{
		if ($this->lastTalked == '' || strtotime($dateTime) > strtotime($this->lastTalked)) {
			$this->lastTalked = $dateTime;
		}
	}

	/**
	 * Write user data to the database.
	 */
	public function writeData($mysqli)
	{
		$this->mysqli = $mysqli;

		/**
		 * Pick a random line from either the list of long quotes or, when there are no long quotes, from the list of short quotes.
		 * Long quotes are preferred since these look better on the statspage and give away more about the subject.
		 */
		$types = array('ex_actions', 'ex_exclamations', 'ex_questions', 'ex_uppercased', 'quote');

		foreach ($types as $type) {
			if (!empty($this->{'long_'.$type.'_list'})) {
				$this->$type = $this->{'long_'.$type.'_list'}[mt_rand(0, count($this->{'long_'.$type.'_list'}) - 1)];
			} elseif (!empty($this->{'short_'.$type.'_list'})) {
				$this->$type = $this->{'short_'.$type.'_list'}[mt_rand(0, count($this->{'short_'.$type.'_list'}) - 1)];
			}
		}

		/**
		 * Write data to database tables "user_details" and "user_status".
		 */
		$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_details` WHERE `csNick` = \''.mysqli_real_escape_string($this->mysqli, $this->csNick).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			$createdQuery = $this->createInsertQuery(array('csNick', 'firstSeen', 'lastSeen'));
			@mysqli_query($this->mysqli, 'INSERT INTO `user_details` SET `UID` = 0,'.$createdQuery) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			$this->UID = mysqli_insert_id($this->mysqli);
			@mysqli_query($this->mysqli, 'INSERT INTO `user_status` SET `UID` = '.$this->UID.', `RUID` = '.$this->UID.', `status` = 0') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		} else {
			$result = mysqli_fetch_object($query);
			$this->UID = $result->UID;

			/**
			 * Explicitly not update $csNick if "seen" data hasn't changed. Prevents lowercase $prevNick from becoming new $csNick.
			 */
			$createdQuery = $this->createUpdateQuery($result, array('UID', 'csNick'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'UPDATE `user_details` SET'.$createdQuery.' WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		}

		/**
		 * Write data to database table "user_activity".
		 */
		$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_activity` WHERE `UID` = '.$this->UID.' AND `date` = \''.mysqli_real_escape_string($this->mysqli, $this->date).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			$createdQuery = $this->createInsertQuery(array('l_night', 'l_morning', 'l_afternoon', 'l_evening', 'l_total'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'INSERT INTO `user_activity` SET `UID` = '.$this->UID.', `date` = \''.mysqli_real_escape_string($this->mysqli, $this->date).'\','.$createdQuery) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		} else {
			$result = mysqli_fetch_object($query);
			$createdQuery = $this->createUpdateQuery($result, array('UID', 'date'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'UPDATE `user_activity` SET'.$createdQuery.' WHERE `UID` = '.$this->UID.' AND `date` = \''.mysqli_real_escape_string($this->mysqli, $this->date).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		}

		/**
		 * Write data to database table "user_events".
		 */
		$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_events` WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			$createdQuery = $this->createInsertQuery(array('m_op', 'm_opped', 'm_voice', 'm_voiced', 'm_deOp', 'm_deOpped', 'm_deVoice', 'm_deVoiced', 'joins', 'parts', 'quits', 'kicks', 'kicked', 'nickchanges', 'topics', 'ex_kicks', 'ex_kicked'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'INSERT INTO `user_events` SET `UID` = '.$this->UID.','.$createdQuery) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		} else {
			$result = mysqli_fetch_object($query);
			$createdQuery = $this->createUpdateQuery($result, array('UID'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'UPDATE `user_events` SET'.$createdQuery.' WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		}

		/**
		 * Write data to database table "user_lines".
		 */
		$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_lines` WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			$createdQuery = $this->createInsertQuery(array('l_00', 'l_01', 'l_02', 'l_03', 'l_04', 'l_05', 'l_06', 'l_07', 'l_08', 'l_09', 'l_10', 'l_11', 'l_12', 'l_13', 'l_14', 'l_15', 'l_16', 'l_17', 'l_18', 'l_19', 'l_20', 'l_21', 'l_22', 'l_23', 'l_night', 'l_morning', 'l_afternoon', 'l_evening', 'l_total', 'l_mon_night', 'l_mon_morning', 'l_mon_afternoon', 'l_mon_evening', 'l_tue_night', 'l_tue_morning', 'l_tue_afternoon', 'l_tue_evening', 'l_wed_night', 'l_wed_morning', 'l_wed_afternoon', 'l_wed_evening', 'l_thu_night', 'l_thu_morning', 'l_thu_afternoon', 'l_thu_evening', 'l_fri_night', 'l_fri_morning', 'l_fri_afternoon', 'l_fri_evening', 'l_sat_night', 'l_sat_morning', 'l_sat_afternoon', 'l_sat_evening', 'l_sun_night', 'l_sun_morning', 'l_sun_afternoon', 'l_sun_evening', 'URLs', 'words', 'characters', 'monologues', 'topMonologue', 'activeDays', 'slaps', 'slapped', 'exclamations', 'questions', 'actions', 'uppercased', 'quote', 'ex_exclamations', 'ex_questions', 'ex_actions', 'ex_uppercased', 'lastTalked'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'INSERT INTO `user_lines` SET `UID` = '.$this->UID.','.$createdQuery) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		} else {
			$result = mysqli_fetch_object($query);
			$createdQuery = $this->createUpdateQuery($result, array('UID'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'UPDATE `user_lines` SET'.$createdQuery.' WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		}

		/**
		 * Write data to database table "user_smileys".
		 */
		$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_smileys` WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
		$rows = mysqli_num_rows($query);

		if (empty($rows)) {
			$createdQuery = $this->createInsertQuery(array('s_01', 's_02', 's_03', 's_04', 's_05', 's_06', 's_07', 's_08', 's_09', 's_10', 's_11', 's_12', 's_13', 's_14', 's_15', 's_16', 's_17', 's_18', 's_19'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'INSERT INTO `user_smileys` SET `UID` = '.$this->UID.','.$createdQuery) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		} else {
			$result = mysqli_fetch_object($query);
			$createdQuery = $this->createUpdateQuery($result, array('UID'));

			if (!is_null($createdQuery)) {
				@mysqli_query($this->mysqli, 'UPDATE `user_smileys` SET'.$createdQuery.' WHERE `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
			}
		}

		/**
		 * Write data to database table "user_hosts".
		 */
		if (!empty($this->hosts_list)) {
			foreach ($this->hosts_list as $host) {
				$query = @mysqli_query($this->mysqli, 'SELECT `HID` FROM `user_hosts` WHERE `host` = \''.mysqli_real_escape_string($this->mysqli, $host).'\' GROUP BY `host`') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
				$rows = mysqli_num_rows($query);

				if (empty($rows)) {
					@mysqli_query($this->mysqli, 'INSERT INTO `user_hosts` SET `HID` = 0, `UID` = '.$this->UID.', `host` = \''.mysqli_real_escape_string($this->mysqli, $host).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
				} else {
					$result = mysqli_fetch_object($query);
					$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_hosts` WHERE `HID` = '.$result->HID.' AND `UID` = '.$this->UID) or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
					$rows = mysqli_num_rows($query);

					if (empty($rows)) {
						@mysqli_query($this->mysqli, 'INSERT INTO `user_hosts` SET `HID` = '.$result->HID.', `UID` = '.$this->UID.', `host` = \''.mysqli_real_escape_string($this->mysqli, $host).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
					}
				}
			}
		}

		/**
		 * Write data to database table "user_topics".
		 */
		if (!empty($this->topics_list)) {
			foreach ($this->topics_list as $topic) {
				$query = @mysqli_query($this->mysqli, 'SELECT `TID` FROM `user_topics` WHERE `csTopic` = \''.mysqli_real_escape_string($this->mysqli, $topic['csTopic']).'\' GROUP BY `csTopic`') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
				$rows = mysqli_num_rows($query);

				if (empty($rows)) {
					@mysqli_query($this->mysqli, 'INSERT INTO `user_topics` SET `TID` = 0, `UID` = '.$this->UID.', `csTopic` = \''.mysqli_real_escape_string($this->mysqli, $topic['csTopic']).'\', `setDate` = \''.mysqli_real_escape_string($this->mysqli, $topic['setDate']).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
				} else {
					$result = mysqli_fetch_object($query);
					$query = @mysqli_query($this->mysqli, 'SELECT * FROM `user_topics` WHERE `TID` = '.$result->TID.' AND `UID` = '.$this->UID.' AND `setDate` = \''.mysqli_real_escape_string($this->mysqli, $topic['setDate']).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
					$rows = mysqli_num_rows($query);

					if (empty($rows)) {
						@mysqli_query($this->mysqli, 'INSERT INTO `user_topics` SET `TID` = '.$result->TID.', `UID` = '.$this->UID.', `csTopic` = \''.mysqli_real_escape_string($this->mysqli, $topic['csTopic']).'\', `setDate` = \''.mysqli_real_escape_string($this->mysqli, $topic['setDate']).'\'') or $this->output('critical', 'MySQLi: '.mysqli_error($this->mysqli));
					}
				}
			}
		}

		/**
		 * Write data to database table "user_URLs".
		 */
		foreach ($this->URLs_objs as $URL) {
			$URL->writeData($this->mysqli, $this->UID);
		}
	}
}

?>
