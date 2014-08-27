<?php
/**
 * Dipper - The Demi-YAML Parser
 * 
 * @copyright 2014 Statamic
 */
class Dipper
{
	/**
	 * A list of replacements extracts from the raw data string
	 * @private array
	 */
	public static $replacements = array();

	/**
	 * The size of the indent (in spaces) being used
	 * @private int
	 */
	public static $indent = 0;

	/**
	 * A string representation of one empty indent (based on $this->indent size)
	 * @private null
	 */
	public static $empty_indent = null;

	/**
	 * Class-wide iterator used for replacements
	 * @private int
	 */
	public static $i = 0;

	/**
	 * List of strings that register as booleans/null values and their mappings
	 * @private array
	 */
	public static $booleans = array(
		'true'  => true,
		'yes'   => true,
		'false' => false,
		'no'    => false,
		'~'     => null,
		'null'  => null
	);


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
		// remove comments
		$yaml = self::removeComments($yaml);

		// get indent
		self::setIndent($yaml);

		// break into structures
		$structures = self::breakIntoStructures($yaml);

		// parse structures
		return self::parseStructures($structures);
	}
	
	
	
	// the guts
	// --------------------------------------------------------------------------------

	/**
	 * Loops through the given structures, parsing each individually
	 * 
	 * @param array  $structures  
	 * @return array
	 */
	private static function parseStructures($structures)
	{
		$output  = array();

		// loop through each structure, escaping quoted content and comments, then parsing it
		foreach ($structures as $structure) {
			// remove double-quoted strings
			if (strpos($structure, '"') !== false) {
				$structure = preg_replace_callback('/".*?(?<!\\\)"/ms', function($item) {
					$key = '__rpl-' . Dipper::$i++ . '__';
					Dipper::$replacements[$key] = substr($item[0], 1, -1);
					return $key;
				}, $structure);
			}

			// remove single-quoted strings
			if (strpos($structure, '\'') !== false) {
				$structure = preg_replace_callback('/\'.*?(?<!\\\)\'/ms', function($item) {
					$key = '__rpl-' . Dipper::$i++ . '__';
					Dipper::$replacements[$key] = substr($item[0], 1, -1);
					return $key;
				}, $structure);
			}

			// remove comments
			if (strpos($structure, '#') !== false) {
				$structure = preg_replace('/#.*?$/m', '', $structure);
			}

			// add to $output
			if ($result = self::parseStructure($structure)) {
				$output[$result[0]] = $result[1];
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
		list($key, $value) = self::breakIntoKeyValue($structure);

		// transformations that are used more than once
		$first_two        = substr($value, 0, 2);
		$first_character  = $first_two[0];
		$trimmed_lower    = trim(strtolower($value));

		// this is empty, abort
		if ($key === null && empty($value)) {
			return null;
		}

		// what is this?
		if ($value === '') {
			// it's a nothing!
			$new_value = '';
		} elseif ($first_two === '__' && substr($value, 0, 6) === '__rpl-') {
			// it's a replaceable value!
			$new_value = self::unreplace($value);
		} elseif (isset(self::$booleans[$trimmed_lower])) {
			// it's a boolean!
			$new_value = self::$booleans[$trimmed_lower];
		} elseif ($first_character === '[' && substr($value, -1) === ']') {
			// it's a short-hand list!
			$new_value = explode(',', trim($value, '[]'));
			foreach ($new_value as &$line) {
				$line = trim($line);
			}
		} elseif (is_numeric($trimmed_lower)) {
			// it's a number!
			if (strstr($value, '.') !== false) {
				$new_value = (float) $value;
			} elseif ($first_two === '0x') {
				$new_value = hexdec($value);
			} elseif ($value[0] === '0') {
				$new_value = octdec($value);
			} else {
				$new_value = (int) $value;
			}
		} elseif ($first_character === '|') {
			// it's a literal scalar!
			$new_value = substr($value, strpos($value, "\n") + 1);
		} elseif ($first_character === '>') {
			// it's a fold-able scalar!
			$new_value = preg_replace('/^(\S[^\n]*)\n(?=\S)/m', '$1 ', substr($value, strpos($value, "\n") + 1));
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
		} else {
			// it is what it is, a string probably!
			$new_value = self::unreplace($value);
		}

		if (empty($key)) {
			return $new_value;
		} else {
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

		if ($colon === false) {
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
		self::$indent = 0;
		if (preg_match('/^(\s+)\S/m', $yaml, $matches)) {
			self::$indent = strlen($matches[1]);
		}

		self::$empty_indent = str_repeat(' ', self::$indent);
	}


	/**
	 * Removes any full-line comments and document separators
	 * 
	 * @param string  $yaml  YAML string to parse
	 * @return string
	 */
	private static function removeComments($yaml)
	{
		$first_pass = '';

		// slightly faster than array_map	
		$lines = explode("\n", $yaml);
		foreach ($lines as $line) {
			$trimmed = ltrim($line);
			if (substr($trimmed, 0, 1) !== '#' && strpos($trimmed, '---') !== 0) {
				$first_pass .= "\n" . $line;
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
				$chunk .= "\n" . $line;
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
	private static function unreplace($text)
	{
		if (!isset(self::$replacements[$text]) || strpos($text, '__rpl-') === false) {
			return $text;
		}

		return self::$replacements[$text];
	}


	/**
	 * Removes one level of indent, according to the current indent size
	 * 
	 * @param string  $value  Value to outdent
	 * @return string
	 */
	private static function outdent($value)
	{
		$lines = explode("\n", $value);
		$out = '';
		
		foreach ($lines as $line) {
			$first_char = substr($line, 0, 1);
			if ($first_char !== false && $first_char !== ' ') {
				// not something that can be outdent-ed
				return $value;
			}

			if (empty($first_char)) {
				$out .= "\n" . self::$empty_indent;
			} elseif (substr($line, 0, self::$indent) === self::$empty_indent) {
				// remove one level of indenting
				$out .= "\n" . substr($line, self::$indent);
			} else {
				// remove all left-hand space
				$out .= ltrim($line, ' ');
			}
		}

		return ltrim($out);
	}
}