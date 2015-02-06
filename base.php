<?php

/**
 * Copyright (c) 2010-2015, Jos de Ruijter <jos@dutnie.nl>
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
 * Class with common functions.
 */
abstract class base
{
	/**
	 * Variables that shouldn't be tampered with.
	 */
	private $prevoutput = [];

	final public function add_value($var, $value)
	{
		$this->$var += $value;
	}

	/**
	 * Create parts of the SQLite3 query.
	 */
	final protected function get_queryparts($sqlite3, $columns)
	{
		$queryparts = [];

		foreach ($columns as $key) {
			if (is_int($this->$key) && $this->$key !== 0) {
				$queryparts['columnlist'][] = $key;
				$queryparts['update-assignments'][] = $key.' = '.$key.' + '.$this->$key;
				$queryparts['values'][] = $this->$key;
			} elseif (is_string($this->$key) && $this->$key !== '') {
				$value = '\''.$sqlite3->escapeString($this->$key).'\'';
				$queryparts['columnlist'][] = $key;
				$queryparts['update-assignments'][] = $key.' = '.$value;
				$queryparts['values'][] = $value;
			}
		}

		return $queryparts;
	}

	final public function get_value($var)
	{
		return $this->$var;
	}

	final public function set_value($var, $value)
	{
		$this->$var = $value;
	}
}
