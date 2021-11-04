= μT - a microtemplating system

μT is a microtemplating system designed to maintain translations, but it may be used in a variety of other applications.
μT is designed as an extensible system being able to easily add custom functionalities.


== Syntax


Variable substitution:
```
    Number of apples on the tree: $apples

```

Pluralization:
```
    There are $apples {pl}apple-|s{/} on the tree.
```

Conditions:
```
    {if $apples>0}There are $apples {pl}apple-|s{/} on the tree.{else}No apple is on the tree.{/}
```
