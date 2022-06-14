# WORK IN PROGRESS

This extension is not even remotely finished. It does the very basic job of copying page text and that's it. It doesn't check for namespaces or do text replacement right now.

Things to do:
* Check target namespaces
* Check source namespace
* Do text replacement

# BulkPageCreate

The BulkPageCreate extension allows for creation of lots of pages from a single source.

## Why an extension?

I wanted something that would be faster and easier to set up than a bot. I also wanted to poke around the MediaWiki core more. This got me to learn more about special pages, the job queue, and interacting with articles.

## Use cases

* Mass create different kinds of categories, such as dated ones.
* Create placeholder pages that might contain some basic information until someone comes around to write the actual content.
* Mass redirects can be created to point to one page. For example, if your page is called `Pokémon X and Y`, you might want to create these as redirects to help those trying to find it with search:  
  ```
  Pokemon X
  Pokemon Y
  Pokemon x
  Pokemon y
  Pokemon XY
  Pokemon xy
  Pokemon X/Y
  Pokemon X and Y
  ```

## Installation

* [Download](https://github.com/ihaveamac/mediawiki-extensions-BulkPageCreate/archive/refs/heads/main.tar.gz) and place the file(s) in a directory called `DownloadWithFilename` in your `extensions/` folder.
* Add the following code at the bottom of your [LocalSettings.php](https://www.mediawiki.org/wiki/Manual:LocalSettings.php):
  ```php
  wfLoadExtension( 'BulkPageCreate' );
  ```
* [Configure as required.](#Configuration)
* ✅ **Done** – Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Usage

The process happens through the `Special:BulkPageCreate` special page.

### Notes

The content model of the source page is used. It's assumed to be a subclass of TextContent (meaning the default content handlers for CSS, JS, and Wikitext will work). I'm not sure what happens yet if you try to use this on two pages with different content models.

This works on the revision ID of the page at the time the jobs are created.

## Configuration

### User rights

None of these rights are granted by default. If you want something to quickly copy and paste to enable this for Administrators:
```php
$wgGroupPermissions['sysop']['bulkpagecreate'] = true;
```

| Right                        | Description                                                      | Notes                               |
|------------------------------|------------------------------------------------------------------|-------------------------------------|
| bulkpagecreate               | Use the Special:BulkPageCreate page                              |                                     |
| ~~bulkpagecreate-overwrite~~ | (NYI) Overwrite existing pages instead of just creating new ones | Requires the _bulkpagecreate_ right |

### Parameters

#### $wgBPCMaxPageTargets

Maximum amount of pages that can be queued at once. In other words, the amount of pages that can be put in the list at Special:BulkPageCreate.

#### ~~$wgBPCRestrictedSourceNamespaces~~

(Not yet implemented)

Namespaces that can only be used as a source for content. Default is empty, meaning any namespace can be used. Value should be an array of namespace IDs.

```php
# default
$wgBPCRestrictedSourceNamespaces = [];

# example configuration
# (for example Project:BPC/Redirect_for_X or Template:BPC/Redirect_for_X could be used as a source)
$wgBPCRestrictedSourceNamespaces = [ NS_PROJECT, NS_TEMPLATE, NS_CATEGORY ];
```

#### ~~$wgBPCRestrictedTargetNamespaces~~

(Not yet implemented)

Namespaces that can only be used as a target for creation. Default is empty, meaning any namespace can be used. Value should be an array of namespace IDs.
```php
# default
$wgBPCRestrictedTargetNamespaces = [];

# example configuration
# (for example you might want to create mass redirects in mainspace or project space)
$wgBPCRestrictedTargetNamespaces = [ NS_MAIN, NS_PROJECT ];
```

## License

BulkPageCreate is licensed under the MIT license.
