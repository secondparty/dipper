# Dipper

[![Build Status](https://travis-ci.org/secondparty/dipper.svg)](https://travis-ci.org/secondparty/dipper)

Dipper is fast YAML parser that parses for the more commonly-used subset of YAML’s v1.0 and v1.2 official specifications. It is being made freely available for use in personal or commercial projects under the BSD 3-clause license.


## Philosophy

One has to question themselves when they sit down to create a YAML parser considering there are already a number of nice solutions out there. There’s SPYC, which does a wonderful job parsing for YAML 1.0, and Symfony also has a YAML parser that parses to YAML 1.2 specifications. Neither project claims to completely support all aspects of the specs that they repsectively parse for (the specs *are* quite large and in-depth), but they both support a large subset of the defined features. 

But it occurred to us that we rarely use most of those features. Perhaps we’re not YAML power-users, but the subset we find ourselves using mostly is the same YAML that those libraries use when they convert straight PHP back to YAML. Mostly: normal key/value pairs (strings, scalars, numbers & booleans), lists, and maps.

Part of YAML’s design was defining a somewhat complex-to-parse syntax in exchange for simple (yet powerful) formatting for human readability. We found that a lot of the parsing complexity came in supporting features above and beyond the simple subset of YAML that we actually use.

And thus, Dipper was born. It’s built for speed, micro-optimized to parse the parts of YAML that we actually use, and nothing more.

### Results

We’ve run a couple of benchmarks to make sure that Dipper *is* quick and thus, is worth releasing, and here is what we found. Keep in mind that we’re not scientists, but are here as an example of comparison.

```
  Parser    |  YAML->PHP  |  PHP->YAML
------------+-------------+-------------
  SPYC      |    ~22ms    |    ~17ms
  Symfony   |    ~24ms    |    ~12ms
  Dipper    |    ~10ms    |    ~ 3ms
```

We ran the same 500-line YAML file through each of the parsers described in this document 250 times. We then took the average time for each parser to parse the document to get the `YAML->PHP` time. Next, we parsed the 500-line YAML file into PHP and then converted that back into YAML 250 times per parser. The average time to convert it back are the `PHP->YAML` times.

As you can see by these times, Dipper comes out ahead in both parsing YAML and building it back up.


## Usage

Dipper performs two tasks: it converts well-formed YAML into PHP, and it converts PHP into well-formed YAML.

```php
// include Dipper and ready it for use
require('Dipper.php');
use secondparty\Dipper\Dipper as Dipper;

// now you can convert YAML into PHP
$php = Dipper::parse($yaml);

// or you can convert PHP into YAML
$yaml = Dipper::make($php);
```

That’s all there is to it.


## What It Will Parse

Below is a complete list of the subset of YAML that Dipper will parse.

### Strings

```yaml
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
```

### Scalars

```yaml
literal_scalar: |
  This is a scalar
  that will preserve
  its line breaks.

folding_scalar: >
  This is a scalar
  that will fold up
  line breaks.
```

### Numbers

```yaml
integer: 42            # becomes an integer 
float: -12.12          # becomes a float
octal: 0755            # YAML 1.0-style, becomes an integer, converted from octal
also_octal: 0o755      # YAML 1.2-style, becomes an integer, converted from octal
hex: 0xff              # becomes an integer, converted from hexadecimal
infinite: (inf)        # YAML 1.0-style, becomes INF, PHP constant for infinity
minus_inf: (-inf)      # YAML 1.0-style, becomes -INF, PHP constant for negative infinity
not_a_number: (NaN)    # YAML 1.0-style, becomes NAN, PHP constant for not-a-number
also_infinity: .inf    # YAML 1.2-style, becomes INF, PHP constant for infinity
also_minus_inf: -.inf  # YAML 1.2-style, becomes -INF, PHP constant for negative infinity
also_nan: .NaN         # YAML 1.2-style, becomes NAN, PHP constant for not-a-number
```

### Booleans & Null Values

```yaml
bool_true: true        # becomes true (as a boolean)
bool_false: false      # becomes false (as a boolean)
null_value: null       # becomes a PHP null value
shorthand_null: ~      # becomes a PHP null value
empty_value:           # becomes a PHP null value
```

### Lists

```yaml
regular_list:
  - first item
  - second item
  - third item

shorthand_list: [ first item, second item, third item ]
```

### Maps

```yaml
regular_map:
  one: first
  two: second
  
shorthand_map: { one: first, two: second }
```

### Combinations of These

In addition to each of these elements individually, you can also combine and nest them as you’d expect to create more complex structures.
Shorthand versions of lists and maps shouldn’t nest other lists or maps.


## What it Makes

Below is a complete list of the PHP that Dipper will build from the YAML passed to it.

- strings
- integers
- floats (including any float constants)
- booleans
- null values
- empty strings
- sequential arrays (into lists)
- associative arrays (into maps)
- objects (if they’ve implemented `__toString`)


## Notes
- Like SPYC and Symfony’s code, Dipper also supports the `syck` YAML parsing extension for PHP if it’s installed and enabled on your server. This moves YAML parsing down to the system level, resulting in parsing that is much, much faster than what straight PHP code itself can deliver.
- In addition to YAML, we also really like Markdown. To better support Markdown, literal scalars will not right-trim each line for extra whitespace, allowing you to define Markdown-style new lines by ending a line with two spaces.


## Thanks

A special thank you to [Thomas Weinert](https://github.com/ThomasWeinert) for doing the leg work of getting dipper into the composer, travis, and phpunit arenas.