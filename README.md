# Dipper

Dipper parses YAML for a subset of the v1.0 and v1.2 specs.
We're calling it a Demi-YAML Parser (DYP… which could be pronounced "dip…" thus, Dipper — naming things is hard).


## Focused on Speed

All of Statamic's configuration files are written in YAML.
Every content file has front matter at the top consisting of YAML.
Even templates and layouts can have YAML prepended to them.
The point is, each page load in Statamic can result in quite a bit of YAML parsing.

Statamic has shipped with two YAML parsers for quite a few versions:

- SPYC (the last tagged release from February 2013)
- Symfony (the latest version)

Of the two, we've always prefered SPYC because it's faster and parsing for a friendlier version of the YAML spec.
In the end, we leave the choice of parser up to users with the `_yaml_mode` setting.

However, though SPYC is faster than Symfony, YAML parsing has found its way to the top of the bottleneck list for page rendering in Statamic.
And that's not to blame either of the parser projects.
The fault is part of the trade off of using YAML to begin with: easy human readability in exchange for more complex parsing.

But having built and used Statamic for a couple years now, it occurred to us that while YAML can do a ton of fancy things, we pretty much never use most of those features.
All we do is read and store a small list of data types: strings, numbers, booleans, lists, and maps (we sometimes call these "named lists").
The most complex data structures most of the time are just nested versions of those things.
So what if we made a YAML parser that only parsed the bits that we use?

Say hello to Dipper.


## Goals

Dipper is a YAML parser with two goals:

- only parse the subset of YAML that we actually use
- be as fast as possible

Every line of code in Dipper has been written with both of these things in mind.
The point of Dipper isn't to add on a bunch of cool features, it's to parse a commonly-used subset YAML accurately as quickly as it can.
We're talking micro-optimization levels of refactoring.


## Usage

Dipper is a static object with one public method.
You pass raw YAML text in, it spits parsed PHP arrays out.

```php
// include the file
require('Dipper.php');

// get your YAML and parse it
$raw_yaml = file_get_contents('/path/to/my-file.yaml');
$parsed   = Statamic\Dipper\Dipper::parse($raw_yaml);
```

## Sample Results

In local tests of parsing about 500 lines of YAML, Dipper parses through it in less than half of the time of what SPYC and Symfony are doing. Below are average render times over 250 iterations of each script parsing the same file.

```
SPYC:     ~22ms   - the default Statamic parser
Symfony:  ~24ms
Dipper:   ~10ms
``` 

And while yes, these are *milliseconds* we're talking about, every little bit counts.


## What It Parses

Dipper aims to parse the YAML structures that most YAML parsers will create when converting straight PHP into YAML. It will parse:

### Strings

All of the following will be parsed as a string.

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

Dipper supports both literal and folding scalar values.

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

> Note: Because we love using YAML with Markdown, literal scalars will not right-trim each line for whitespace, allowing you to define new lines by ending a line with two spaces.

### Numbers

All of the following will be parsed as numbers.

```yaml
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
```

### Booleans & Nulls

All of the following will be converted when not quoted.

```yaml
bool_true: true        # becomes true (as a boolean)
bool_false: false      # becomes false (as a boolean)
null_value: null       # becomes a PHP null value
shorthand_null: ~      # becomes a PHP null value
```

### Lists

Both forms of lists will be converted.

```yaml
normal_list:
  - first item
  - second item
  - third item
  
shorthand_list: [ first item, second item, third item ]
```

### Maps

Dipper converts maps (or "named lists" as we sometimes call them).

```yaml
map:
  one: first
  two: second
```

### Combinations of the Above

You can, of course, mix and match the above values into complex structures and Dipper should handle them just fine.


## Notes

- Both SPYC and Symfony will use the `syck` YAML parsing library if it's installed and enabled on your server. In those instances, YAML parsing will be much, much faster and probably won't be a bottleneck anymore.
- Dipper is a one-way operation, where as SPYC and Symfony will also convert raw PHP data back into YAML. These libraries both do a great job of this, and we saw no need to reinvent the wheel here. (For now.)


## License

We're releasing Dipper under the [BSD 3-Clause license](http://opensource.org/licenses/BSD-3-Clause) in the hopes that perhaps it could help other projects out there that parse a subset of YAML like we do.