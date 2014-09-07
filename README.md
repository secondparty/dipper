## Dipper

Dipper parses YAML for a subset of the v1.0 spec.
We're calling it a Demi-YAML Parser (DYP… which could be pronounced "dip…" thus, Dipper).


### Focused on Speed

All of Statamic's configuration files are written in YAML.
Every content file has front matter at the top consisting of YAML.
Even templates and layouts can now have YAML prepended to them.
The point is, each page load can result in quite a bit of YAML parsing.

Statamic ships with two YAML parsers:

- SPYC (the last tagged release from February 2013)
- Symfony (the latest version)

Of the two, we prefer SPYC because it's faster and parsing for a friendlier version of the YAML spec.
In the end, we leave the choice of parser up to users, the `_yaml_mode` setting will pick between them.

However, though SPYC is faster than Symfony, YAML parsing has found its way to the top of the bottleneck list for page rendering in Statamic.
And that's not to blame either of the parser projects.
The fault is part of the trade off of using YAML to begin with: easy human readability in exchange for more complex parsing.

Having built and used Statamic for a couple years now, it occurred to us that while YAML can do a ton of fancy things, we pretty much never use most of those features.
All we do is read and store a small list of data types: strings, numbers, booleans, lists, and maps (we sometimes call these "named lists").
The most complex data structures most of the time are just nested versions of those things.
What if we made a YAML parser that only parsed the bits that we use?

Say hello to Dipper.


### Goals

Dipper is a YAML parser with two goals:

- only parse the subset of YAML that we actually use
- be as fast as possible

Every line of code in Dipper has been written with both of these things in mind.
The point of Dipper isn't to add on a bunch of cool features, it's to parse a subset YAML accurately as quickly as it can.
We're talking micro-optimization levels of refactoring.


### Usage

Dipper is a static object with one public method.
You pass raw YAML text in, it spits parsed PHP arrays out.

```php
// include the file
require('Dipper.php');

// get your YAML and parse it
$raw_yaml = file_get_contents('/path/to/my-file.yaml');
$parsed   = Dipper::parse($raw_yaml);
```

### Sample Results

In local tests of parsing about 500 lines of YAML, Dipper parses through it in less than half of the time of what SPYC and Symfony are doing. Below are average render times over 250 iterations of each script parsing the same file.

```
SPYC:     21.541ms   - the default Statamic parser
Symfony:  23.899ms
Dipper:    8.696ms
``` 

And while yes, these are *milliseconds*, every little bit counts.


### Notes

Both SPYC and Symfony will use the `syck` YAML parsing library if it's installed and enabled on your server. In those instances, YAML parsing will be much, much faster and probably won't be a bottleneck anymore.