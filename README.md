# Agile Data - Reporting Add-on

This extension for Agile Data implements advanced reporting capabilities:

-   Aggregate models. Provide grouping of existing model.
-   Union models. Combine one or multiple models.

## Documentation

https://github.com/atk4/report/blob/develop/docs/index.md

## Real Usage Example

https://github.com/atk4/report/blob/develop/docs/full-example.md

## Installation

Add the following inside your `composer.json` file:

``` json
{
    "require": {
        "atk4/report": "dev-develop"
    },
    "repositories": [
      {
          "type": "package",
          "package": {
              "name": "atk4/report",
              "version": "dev-develop",
              "type": "package",
              "source": {
                  "url": "git@github.com:atk4/report.git",
                  "type": "git",
                  "reference": "develop"
              }
          }
      }
    ],
}
```


``` console
composer install
```

## Current Status

Report extension is currently under development.
