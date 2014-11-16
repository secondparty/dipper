<?php
/**
 * Dipper
 * A demi-YAML parser by Statamic.
 * View full documentation at http://github.com/statamic/dipper
 *
 * Copyright (c) 2014, Statamic
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 *    may be used to endorse or promote products derived from this software
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
namespace Statamic\Dipper;

class Dipper
{
	/**
	 * A list of replacements extracts from the raw data string
	 * @public array
	 */
	public static $replacements = array();

	/**
	 * Replacement types
	 * @public array
	 */
	public static $replacement_types = array();

	/**
	 * The size of the indent (in spaces) being used
	 * @public int
	 */
	public static $indent_size = 0;

	/**
	 * A string representation of one empty indent (based on self::$indent_size)
	 * @public null
	 */
	public static $empty_indent = null;

	/**
	 * Iterator used for replacements
	 * @public int
	 */
	public static $i = 0;

	/**
	 * List of strings that register as booleans/null values and their mappings
	 * @public array
	 */
	public static $booleans = array(
		'true'  => true,
		'false' => false,
		'~'     => null,
		'null'  => null
	);

	/**
	 * Maximum line length when creating YAML
	 * @public int
	 */
	public static $max_line_length = 80;


	// public interface
	// --------------------------------------------------------------------------------

	/**
	 * Takes a given piece of text and parses it for YAML
	 *
	 * @param string  $yaml  The YAML to parse
	 * @return array
	 */
	public static function parse($yaml)
	{
		// reset replacements since these don't apply from parse to parse
		self::$replacements = array();
		self::$replacement_types = array();
		self::$i = 0;

		// prepare the YAML by removing comments, standardizing line-endings
		$yaml = self::prepare($yaml);

		// get indent
		self::setIndent($yaml);

		// break into structures
		$structures = self::breakIntoStructures($yaml);

		// parse structures
		return self::parseStructures($structures, true);
	}


	/**
	 * Makes YAML from PHP
	 *
	 * @param mixed  $php  PHP to make into YAML
	 * @return string
	 */
	public static function make($php)
	{
		// ensure that this is an array
		$php = (array) $php;

		// set the indent size
		self::$indent_size   = 2;
		self::$empty_indent  = str_repeat(' ', self::$indent_size);

		// output to build up for returning
		$output = "---\n";

		// parse through php array
		$output = $output . self::build($php);

		return $output;
	}



	// the guts
	// --------------------------------------------------------------------------------

	/**
	 * Loops through the given structures, parsing each individually
	 *
	 * @param array  $structures  List of structures that need parsing
	 * @param boolean  $is_root  Is this the top-most structure?
	 * @return array
	 */
	private static function parseStructures($structures, $is_root=false)
	{
		$output = array();

		// loop through each structure, escaping quoted content and comments, then parsing it
		foreach ($structures as $structure) {
			// remove double-quoted strings
			if (strpos($structure, '"') !== false) {
				$structure = preg_replace_callback('/".*?(?<!\\\)"/m', function($item) {
					$key = '__r@-' . self::$i++ . '__';
					self::$replacement_types[$key] = '"';
					self::$replacements[$key] = substr($item[0], 1, -1);
					return $key;
				}, $structure);
			}

			// remove single-quoted strings
			if (strpos($structure, '\'') !== false) {
				$structure = preg_replace_callback('/\'.*?(?<!\\\)\'/m', function($item) {
					$key = '__r@-' . self::$i++ . '__';
					self::$replacement_types[$key] = '\'';
					self::$replacements[$key] = substr($item[0], 1, -1);
					return $key;
				}, $structure);
			}

			// remove comments
			if (strpos($structure, '#') !== false) {
				$colon = strpos($structure, ':');
				if ($colon !== false) {
					$first_value_char = substr(trim(substr($structure, $colon + 1)), 0, 1);
					if ($first_value_char !== '>' && $first_value_char !== '|') {
						$structure = preg_replace('/#.*?$/m', '', $structure);
					}
				}
			}

			// add to $output
			if ($result = self::parseStructure($structure)) {
				if ($is_root && $structure[0] === '-' && empty($result[1]) && !empty($result[0])) {
					// handles cases where the outer-most element is just a list
					$output[] = $result[0];
				} else {
					$output[$result[0]] = $result[1];
				}
			}
		}

		return $output;
	}


	/**
	 * Takes one structure or single value and parses it
	 *
	 * @param mixed  $structure  Structure or value to parse
	 * @return array|float|null|number|string
	 */
	private static function parseStructure($structure)
	{
		// separate key from value
		$out    = self::breakIntoKeyValue($structure);
		$key    = $out[0];  // this is slightly faster...
		$value  = $out[1];  // ...than using list() out of the method

		// transformations that are used more than once
		$first_two        = substr($value, 0, 2);
		$first_character  = $first_two[0];
		$trimmed_value    = trim($value);
		$trimmed_lower    = strtolower($trimmed_value);

		// this is empty, abort
		if (!isset($key) && empty($value)) {
			return null;
		}

		// what is this?
		if ($value === '') {
			// it's a nothing!
			$new_value = null;
		} elseif ($first_two === '__' && substr($value, 0, 5) === '__r@-') {
			// it's a replaceable value!
			$new_value = self::unreplace($value);
		} elseif (array_key_exists($trimmed_lower, self::$booleans)) {
			// it's a boolean!
			$new_value = self::$booleans[$trimmed_lower];
		} elseif ($first_character === '[' && substr($trimmed_lower, -1) === ']') {
			// it's a short-hand list!
			$new_value = explode(',', trim(self::unreplaceAll($value, true), '[]'));
			foreach ($new_value as &$line) {
				$line = trim($line);
			}
		} elseif ($first_character === '|') {
			// it's a literal scalar!
			$new_value = self::unreplaceAll(substr($value, strpos($value, "\n") + 1), true);
		} elseif ($first_character === '>') {
			// it's a fold-able scalar!
			$new_value = self::unreplaceAll(preg_replace('/^(\S[^\n]*)\n(?=\S)/m', '$1 ', substr($value, strpos($value, "\n") + 1)), true);
		} elseif ($first_two === '- ' || $first_two === "-\n") {
			// it's a standard list!
			$items = self::breakIntoStructures($value);

			$new_value = array();
			foreach ($items as $item) {
				$item = trim(self::outdent(substr($item, 1)));

				if (strpos($item, ': ') || strpos($item, ":\n")) {
					$structures = self::breakIntoStructures($item);
					$new_value[] = self::parseStructures($structures);
				} else {
					$new_value[] = self::parseStructure($item);
				}
			}
		} elseif (strpos($value, ': ') || strpos($value, ":\n")) {
			// it's a map! which in this system, means it's a structure
			$structures  = self::breakIntoStructures(self::outdent($value));
			$new_value   = self::parseStructures($structures);
		} elseif (is_numeric($trimmed_lower) || $first_two === '0o') {
			// it's a number!
			if (strpos($value, '.') !== false) {
				// float
				$new_value = (float)$value;
			} elseif ($first_two === '0x') {
				// hex
				$new_value = hexdec($value);
			} elseif ($first_character === '0') {
				// octal
				$new_value = octdec($value);
			} else {
				// plain-old integer
				$new_value = (int)$value;
			}
		} elseif ($first_two === '0o') {
			// it's a yaml 1.2 octal
			$new_value = octdec(substr($value, 2));
		} elseif ($trimmed_lower === '.inf' || $trimmed_lower === '(inf)') {
			// it's infinite!
			$new_value = INF;
		} elseif ($trimmed_lower === '-.inf' || $trimmed_lower === '(-inf)') {
			// it's negatively infinite!
			$new_value = -INF;
		} elseif ($trimmed_value === '.NaN' || $trimmed_value === '(NaN)') {
			// it's specifically not a number!
			$new_value = NAN;
		} else {
			// it is what it is, a string probably!
			$new_value = rtrim(self::unreplaceAll($value, true));
		}

		if (empty($key)) {
			// no key is set, so this is probably a value-parsing end-point
			return $new_value;
		} else {
			// there's a key, so this is probably a structure-parsing end-point
			return array(trim(self::unreplace($key)), $new_value);
		}
	}


	/**
	 * Breaks the given text into a key/value pair
	 *
	 * @param string  $text  Text to break into key/value pair
	 * @return array
	 */
	private static function breakIntoKeyValue($text)
	{
		$colon = strpos($text, ':');

		if (empty($colon)) {
			return array(null, self::outdent($text));
		}

		$key    = substr($text, 0, $colon);
		$value  = self::outdent(substr($text, $colon + 1));

		return array($key, $value);
	}


	/**
	 * Sets the indent level currently being used
	 *
	 * @param string  $yaml  YAML to examine for indents
	 */
	private static function setIndent($yaml)
	{
		self::$indent_size = 0;
		if (preg_match('/^( +)\S/m', $yaml, $matches)) {
			self::$indent_size = strlen($matches[1]);
		}

		// create an empty indent for later
		self::$empty_indent = str_repeat(' ', self::$indent_size);
	}


	/**
	 * Removes any full-line comments and document separators, standardize line-endings
	 *
	 * @param string  $yaml  YAML string to parse
	 * @return string
	 */
	private static function prepare($yaml)
	{
		$first_pass = '';

		// slightly faster than array_map	
		$lines = explode(PHP_EOL, $yaml);
		foreach ($lines as $line) {
			$trimmed = ltrim($line);
			if (substr($line, 0, 1) !== '#' && strpos($trimmed, '---') !== 0) {
				$first_pass = $first_pass . "\n" . $line;
			}
		}

		return substr($first_pass, 1);
	}


	/**
	 * Breaks a raw YAML string into easier parse-able chunks that we're calling "structures"
	 *
	 * @param string  $yaml  YAML string to parse
	 * @return array
	 */
	private static function breakIntoStructures($yaml)
	{
		$lines  = explode("\n", $yaml);
		$parts  = array();
		$chunk  = null;

		foreach ($lines as $line) {
			if (isset($line[0]) && $line[0] !== ' ' && $line[0] !== "\n") {
				if ($chunk !== null) {
					$parts[] = rtrim($chunk);
				}

				$chunk = $line;
			} else {
				$chunk = $chunk . "\n" . $line;
			}
		}

		// add what we were last building
		$parts[] = rtrim($chunk);

		return $parts;
	}


	/**
	 * Converts and replaced strings with the original value
	 *
	 * @param string  $text  Text to consider for unreplacing
	 * @return string
	 */
	public static function unreplace($text)
	{
		// check that there's an unreplace-able string here
		if (!isset(self::$replacements[$text]) || strpos($text, '__r@-') === false) {
			return $text;
		}

		return self::$replacements[$text];
	}


	/**
	 * Converts multiple replaced strings with the original values
	 *
	 * @param string  $text  Text to consider for unreplacing
	 * @param bool  $include_type  Should we include the quotes with it?
	 * @return string
	 */
	private static function unreplaceAll($text, $include_type=false)
	{
		// check that there's an unreplace-able string here
		if (!is_string($text) || strpos($text, '__r@-') === false) {
			return $text;
		}

		// unreplace all
		return preg_replace_callback('/__r@-\d+__/', function($matches) use ($include_type) {
			if ($include_type) {
				// we want to add in the same quotes (single or double) that this originally came with
				return self::$replacement_types[$matches[0]] . self::unreplace($matches[0]) . self::$replacement_types[$matches[0]];
			}

			return self::unreplace($matches[0]);
		}, $text);
	}


	/**
	 * Removes one level of indent, according to the current indent size
	 *
	 * @param string  $value  Value to outdent
	 * @return string
	 */
	private static function outdent($value)
	{
		$lines  = explode("\n", $value);
		$out    = '';

		foreach ($lines as $line) {
			if (isset($line[0]) && $line[0] !== ' ' && $line[0] !== "\n") {
				// not something that can be outdent-ed
				return $value;
			}

			if (!isset($line[0])) {
				$out = $out . "\n" . self::$empty_indent;
			} elseif (substr($line, 0, self::$indent_size) === self::$empty_indent) {
				// remove one level of indenting
				$out = $out . "\n" . substr($line, self::$indent_size);
			} else {
				// remove all left-hand space
				$out = $out . ltrim($line, ' ');
			}
		}

		return ltrim($out);
	}


	/**
	 * Takes a mixed value and returns YAML
	 *
	 * @param mixed  $value  Value to convert to YAML
	 * @param int  $depth  Indent depth to prepend to each line
	 * @return string
	 */
	private static function build($value, $depth=0)
	{
		if ($value === '' || is_null($value) || $value === 'null' || $value === '~') {
			// this is empty or a null value
			return '';
		} elseif (is_array($value)) {
			// this is an array
			$output = array();

			// but is this a list or a map?
			if (array_keys($value) === range(0, count($value) - 1)) {
				// this is a list
				foreach ($value as $subvalue) {
					if (is_array($subvalue)) {
						$output[] = "-\n" . self::build($subvalue, $depth + 1);
					} else {
						$output[] = "- " . self::build($subvalue, $depth + 1);
					}
				}
			} else {
				// this is a map
				foreach ($value as $key => $subvalue) {
					if (is_array($subvalue)) {
						$output[] = $key . ":\n" . self::build($subvalue, $depth + 1);
					} else {
						$output[] = $key . ": " . self::build($subvalue, $depth + 1);
					}
				}
			}

			// indent this as necessary
			if ($depth > 0) {
				foreach ($output as &$line) {
					$line = str_repeat(self::$empty_indent, $depth) . $line;
				}
			}

			return join("\n", $output);
		} elseif (is_bool($value)) {
			// this is a boolean
			if ($value) {
				return 'true';
			}

			return 'false';
		} elseif (!is_string($value) && (is_int($value) || is_float($value))) {
			// this is a number
			if (is_infinite($value)) {
				// an *infinite* number
				if ($value > 0) {
					return '(inf)';
				}

				return '(-inf)';
			} elseif (is_nan($value)) {
				// this is a not-a-number
				return '(NaN)';
			}

			// this is some sort of other number
			return (string) $value;
		}

		// this is either a string or an object
		if (is_object($value)) {
			// this is an object
			if (!method_exists($value, '__toString')) {
				// but can't be converted to strings
				return '';
			}

			// convert this to a string
			$value = (string) $value;
		}

		// determine string formatting
		$needs_quoting  = strpos($value, ':') !== false || $value === 'true' || $value === 'false' || is_numeric($value);
		$needs_scalar   = strpos($value, "\n") !== false || strlen($value) > self::$max_line_length;
		$needs_literal  = strpos($value, "\n") !== false;

		if ($needs_scalar) {
			// this is a scalar
			$string  = ">";

			if ($needs_literal) {
				$string = "|";
			}

			$string  = $string . "\n" . wordwrap($value, (self::$max_line_length - self::$indent_size * $depth + 1), "\n");
			$output  = explode("\n", $string);

			$first = true;
			foreach ($output as &$line) {
				if ($first) {
					// leave first line untouched
					$first = null;
					continue;
				}

				$line = str_repeat(self::$empty_indent, $depth) . $line;
			}

			return join("\n", $output);
		} elseif ($needs_quoting) {
			// this is a quoted string
			return trim('\'' . str_replace('\'', '\\\'', $value) . '\'');
		}

		// this is a small, no-quote-needed string
		return trim($value);
	}
}