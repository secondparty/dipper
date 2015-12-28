<?php

// require
require_once realpath(__DIR__ . '/../vendor/autoload.php');
//require_once realpath(__DIR__ . '/../Dipper.php');

class DipperTest extends PHPUnit_Framework_TestCase
{
	protected $parsed;
	protected $alternate_parsed;
	
	protected function setUp()
	{
		// create YAML to use
		$yaml = <<<YAML
working: true

string: this is a string
single_quoted_string: 'this is a single-quoted string'
double_quoted_string: "this is a double-quoted string"
non_full_quoted_string: "\"This is\" some text in a string"
string_with_single_quote: this's a string with a single quote
string_with_colon: 'this is a string: containing a colon'
string_with_comma: F j, Y
quoted_number: "120"
quoted_true: "true"
quoted_false: "false"
url: http://something.com

literal_scalar: |
  This is a scalar
  that will preserve
  its line breaks.

folding_scalar: >
  This is a scalar
  that will fold up
  line breaks.

integer: 42            # becomes an integer 
float: -12.12          # becomes a float
octal: 0755            # YAML 1.0-style, becomes an integer, converted from octal
also_octal: 0o755      # YAML 1.2-style, becomes an integer, converted from octal
hex: 0xff              # becomes an integer, converted from hexadecimal
infinite: (inf)        # YAML 1.0-style, becomes INF, PHP constant for infinity
minus_inf: (-inf)      # YAML 1.0-style, becomes -INF, PHP constant for negative infinity
also_nan: (NaN)        # YAML 1.0-style, becomes NAN, PHP constant for not-a-number
also_infinity: .inf    # YAML 1.2-style, becomes INF, PHP constant for infinity
also_minus_inf: -.inf  # YAML 1.2-style, becomes -INF, PHP constant for negative infinity
not_a_number: .NaN     # YAML 1.2-style, becomes NAN, PHP constant for not-a-number

bool_true: true        # becomes true (as a boolean)
bool_false: false      # becomes false (as a boolean)
null_value: null       # becomes a PHP null value
shorthand_null: ~      # becomes a PHP null value
empty_value:           # becomes a PHP null value

regular_list:
  - first item
  - second item
  - third item

shorthand_list: [ first item, second item, third item ]
shorthand_quoted_list: [ 'one', 'two', 'three' ]
empty_shorthand_list: []

map:
  one: first
  two: second
  
shorthand_map: { one: first, two: second, third: "this, the thing" }

nested_map_lists:
  - name: Bill
    job: Architect
  - name: Fred
    job: Designer
  - name: Willie
    job: Builder
    
nested_shorthand_maps:
  - { name: Bill, job: Architect }
  - { name: Fred, job: Designer }
  - { name: Willie, job: Builder }
		
nested_maps:
  first:
    name: Bill
    job: Architect
  second:
    name: Fred
    job: Designer
  third:
    name: Willie
    job: Builder
    
deep_nest_list_shortcut:
  first:
    second:
      third:
        fourth:
          fifth: [ one, two ]

YAML;
		
		$alternate_yaml = <<<YAML
working: true

string: this is a string
single_quoted_string: 'this is a single-quoted string'
double_quoted_string: "this is a double-quoted string"
non_full_quoted_string: "\"This is\" some text in a string"
string_with_single_quote: this's a string with a single quote
string_with_colon: 'this is a string: containing a colon'
string_with_comma: F j, Y
quoted_number: "120"
quoted_true: "true"
quoted_false: "false"
url: http://something.com

literal_scalar: |
  This is a scalar
  that will preserve
  its line breaks.

folding_scalar: >
  This is a scalar
  that will fold up
  line breaks.

integer: 42            # becomes an integer 
float: -12.12          # becomes a float
octal: 0755            # YAML 1.0-style, becomes an integer, converted from octal
also_octal: 0o755      # YAML 1.2-style, becomes an integer, converted from octal
hex: 0xff              # becomes an integer, converted from hexadecimal
infinite: (inf)        # YAML 1.0-style, becomes INF, PHP constant for infinity
minus_inf: (-inf)      # YAML 1.0-style, becomes -INF, PHP constant for negative infinity
also_nan: (NaN)        # YAML 1.0-style, becomes NAN, PHP constant for not-a-number
also_infinity: .inf    # YAML 1.2-style, becomes INF, PHP constant for infinity
also_minus_inf: -.inf  # YAML 1.2-style, becomes -INF, PHP constant for negative infinity
not_a_number: .NaN     # YAML 1.2-style, becomes NAN, PHP constant for not-a-number

bool_true: true        # becomes true (as a boolean)
bool_false: false      # becomes false (as a boolean)
null_value: null       # becomes a PHP null value
shorthand_null: ~      # becomes a PHP null value
empty_value:           # becomes a PHP null value

regular_list:
  - first item
  - second item
  - third item

shorthand_list: [ first item, second item, third item ]
shorthand_quoted_list: [ 'one', 'two', 'three' ]
empty_shorthand_list: []

map:
  one: first
  two: second
  
shorthand_map: { one: first, two: second, third: "this, the thing" }

nested_map_lists:
  - name: Bill
    job: Architect
  - name: Fred
    job: Designer
  - name: Willie
    job: Builder
    
nested_shorthand_maps:
  - { name: Bill, job: Architect }
  - { name: Fred, job: Designer }
  - { name: Willie, job: Builder }
		
nested_maps:
  first:
    name: Bill
    job: Architect
  second:
    name: Fred
    job: Designer
  third:
    name: Willie
    job: Builder
    
deep_nest_list_shortcut:
  first:
    second:
      third:
        fourth:
          fifth: [ one, two ]

YAML;
		
		$this->parsed = \secondparty\Dipper\Dipper::parse($yaml);
		$this->alternate_parsed = \secondparty\Dipper\Dipper::parse($alternate_yaml);
	}
	
	
	// ensure that something was even parsed
	public function testParse()
	{
		$this->assertArrayHasKey('working', $this->parsed);
		
		// parsed is the file with `\n` new-lines,
		// alternate_parsed uses `\r\n` new-lines,
		// testing that these both parsed correctly
		$this->assertEquals(md5(serialize($this->parsed)), md5(serialize($this->alternate_parsed)));
	}
	
	public function testMake()
	{
		$yaml = \secondparty\Dipper\Dipper::make($this->parsed);
		$expected = <<<EXPECTED
---
working: true
string: this is a string
single_quoted_string: this is a single-quoted string
double_quoted_string: this is a double-quoted string
non_full_quoted_string: "\"This is\" some text in a string"
string_with_single_quote: this's a string with a single quote
string_with_colon: 'this is a string: containing a colon'
string_with_comma: F j, Y
quoted_number: '120'
quoted_true: 'true'
quoted_false: 'false'
url: 'http://something.com'
literal_scalar: |
  This is a scalar
  that will preserve
  its line breaks.
folding_scalar: This is a scalar that will fold up line breaks.
integer: 42
float: -12.12
octal: 493
also_octal: 493
hex: 255
infinite: (inf)
minus_inf: (-inf)
also_nan: (NaN)
also_infinity: (inf)
also_minus_inf: (-inf)
not_a_number: (NaN)
bool_true: true
bool_false: false
null_value: 
shorthand_null: 
empty_value: 
regular_list:
  - first item
  - second item
  - third item
shorthand_list:
  - first item
  - second item
  - third item
shorthand_quoted_list:
  - one
  - two
  - three
empty_shorthand_list: []
map:
  one: first
  two: second
shorthand_map:
  one: first
  two: second
  third: this, the thing
nested_map_lists:
  -
    name: Bill
    job: Architect
  -
    name: Fred
    job: Designer
  -
    name: Willie
    job: Builder
nested_shorthand_maps:
  -
    name: Bill
    job: Architect
  -
    name: Fred
    job: Designer
  -
    name: Willie
    job: Builder
nested_maps:
  first:
    name: Bill
    job: Architect
  second:
    name: Fred
    job: Designer
  third:
    name: Willie
    job: Builder
deep_nest_list_shortcut:
  first:
    second:
      third:
        fourth:
          fifth:
            - one
            - two
EXPECTED;

		$this->assertEquals($expected, $yaml);
	}
	
	public function testStrings()
	{
		$this->assertEquals('this is a string', $this->parsed['string']);
		$this->assertEquals('this is a single-quoted string', $this->parsed['single_quoted_string']);
		$this->assertEquals('this is a double-quoted string', $this->parsed['double_quoted_string']);
		$this->assertEquals('this\'s a string with a single quote', $this->parsed['string_with_single_quote']);
		$this->assertEquals('this is a string: containing a colon', $this->parsed['string_with_colon']);
		$this->assertEquals('F j, Y', $this->parsed['string_with_comma']);
		$this->assertSame('120', $this->parsed['quoted_number']);
		$this->assertSame('true', $this->parsed['quoted_true']);
		$this->assertSame('false', $this->parsed['quoted_false']);
		$this->assertEquals('http://something.com', $this->parsed['url']);
	}
	
	public function testScalars()
	{
		$this->assertEquals("This is a scalar\nthat will preserve\nits line breaks.", $this->parsed['literal_scalar']);
		$this->assertEquals("This is a scalar that will fold up line breaks.", $this->parsed['folding_scalar']);
	}
	
	public function testNumbers()
	{
		$this->assertSame(42, $this->parsed['integer']);
		$this->assertSame(-12.12, $this->parsed['float']);
		$this->assertSame(0755, $this->parsed['octal']);
		$this->assertSame(0755, $this->parsed['also_octal']);
		$this->assertSame(0xff, $this->parsed['hex']);
		$this->assertSame(INF, $this->parsed['infinite']);
		$this->assertSame(INF, $this->parsed['also_infinity']);
		$this->assertSame(-INF, $this->parsed['minus_inf']);
		$this->assertSame(-INF, $this->parsed['also_minus_inf']);
		$this->assertTrue(is_nan($this->parsed['not_a_number']));
		$this->assertTrue(is_nan($this->parsed['also_nan']));
	}
	
	public function testBooleans()
	{
		$this->assertSame(true, $this->parsed['bool_true']);
		$this->assertSame(false, $this->parsed['bool_false']);
		$this->assertSame(null, $this->parsed['null_value']);
		$this->assertSame(null, $this->parsed['shorthand_null']);
		$this->assertSame(null, $this->parsed['empty_value']);
	}
	
	public function testLists()
	{
		$this->assertCount(3, $this->parsed['regular_list']);
		$this->assertCount(3, $this->parsed['shorthand_list']);
		$this->assertInternalType('array', $this->parsed['empty_shorthand_list']);
	}
	
	public function testMaps()
	{
		$this->assertCount(2, $this->parsed['map']);
		$this->assertCount(3, $this->parsed['shorthand_map']);
	}
	
	public function testComplexStructures()
	{
		$this->assertCount(3, $this->parsed['nested_map_lists']);
		$this->assertEquals('Fred', $this->parsed['nested_map_lists'][1]['name']);
		$this->assertEquals('Builder', $this->parsed['nested_map_lists'][2]['job']);

		$this->assertCount(3, $this->parsed['nested_shorthand_maps']);
		$this->assertEquals('Fred', $this->parsed['nested_shorthand_maps'][1]['name']);
		$this->assertEquals('Builder', $this->parsed['nested_shorthand_maps'][2]['job']);

		$this->assertCount(3, $this->parsed['nested_maps']);
		$this->assertEquals('Fred', $this->parsed['nested_maps']['second']['name']);
		$this->assertEquals('Builder', $this->parsed['nested_maps']['third']['job']);
		
		$this->assertCount(2, $this->parsed['deep_nest_list_shortcut']['first']['second']['third']['fourth']['fifth']);
		$this->assertEquals('two', $this->parsed['deep_nest_list_shortcut']['first']['second']['third']['fourth']['fifth'][1]);
	}
}
