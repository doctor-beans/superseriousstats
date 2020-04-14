<?php

/**
 * Copyright (c) 2007-2020, Jos de Ruijter <jos@dutnie.nl>
 */

declare(strict_types=1);

/**
 * Class for handling word data.
 */
class word
{
	use base;

	private int $length = 0;
	private int $total = 0;
	private object $sqlite3;
	private string $word = '';

	public function __construct(string $word, object $sqlite3)
	{
		$this->word = $word;
		$this->sqlite3 = $sqlite3;
	}

	/**
	 * Store everything in the database.
	 */
	public function write_data(): void
	{
		/**
		 * Write data to database table "words".
		 */
		$this->sqlite3->exec('INSERT INTO words (word, length, total) VALUES (\''.$this->word.'\', '.$this->length.', '.$this->total.') ON CONFLICT (word) DO UPDATE SET total = total + '.$this->total) or output::output('critical', basename(__FILE__).':'.__LINE__.', sqlite3 says: '.$this->sqlite3->lastErrorMsg());
	}
}
