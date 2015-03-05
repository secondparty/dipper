<?php

require 'Dipper.php';

$yaml = <<<YAML
working: true

string: this is a string
single_quoted_string: 'this is a single-quoted string'
double_quoted_string: "this is a double-quoted string"
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

echo '<pre>';
print_r(\secondparty\Dipper\Dipper::parse($yaml));
print_r('<br><br><br>');
print_r(\secondparty\Dipper\Dipper::make(\secondparty\Dipper\Dipper::parse($yaml)));