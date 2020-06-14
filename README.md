# Contao Dev Bundle

## About
Use this bundle while building a new website.
It let's you overwrite the robots tag and the "no-cache" for all pages based on the current url (dev-domains).
So no development-domain get indexed ever again :)

## Installation
Install [composer](https://getcomposer.org) if you haven't already.
Afterwords, you can add the public repo (not added to the packagist-index) like this, into you composer.json:
```sh
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/mediamotionag/contao-dev-bundle"
    }
],
```
And when that is done, you have to "require" the bundle like all other bundles:
```sh
"mediamotionag/contao-dev-bundle": "^1.0",
```
Now run the composer update (console or contao-manager).

## Usage
1. Install Bundle
2. Go to Contao Settings to see the options
3. Maybe add more development-domains
2. Enjoy

## Contribution
Bug reports and pull requests are welcome
