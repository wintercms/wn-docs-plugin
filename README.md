# Docs Plugin

[![GitHub Workflow Status (branch)](https://img.shields.io/github/workflow/status/wintercms/wn-docs-plugin/Tests/main?label=tests&style=flat-square)](https://github.com/wintercms/wn-docs-plugin/actions)
[![Codecov](https://img.shields.io/codecov/c/github/wintercms/wn-docs-plugin?style=flat-square)](https://codecov.io/gh/wintercms/wn-docs-plugin)
[![Discord](https://img.shields.io/discord/816852513684193281?label=discord&style=flat-square)](https://discord.gg/D5MFSPH6Ux)

Integrates a full suite of documentation direct into your Winter CMS installation. Documentation can be generated from Markdown files, static HTML files or from PHP code.

## Features

- Load documentation locally from your plugin, or from a remote ZIP file.
- Generate content-based documentation from Markdown files or static HTML files.
- Generate API documentation from PHP docblocks and parsed PHP code.
- Can be used in both the Backend and CMS.

## Getting started

To install the plugin, you may install it through the [Winter CMS Marketplace](https://github.com/wintercms/wn-docs-plugin), or you may install it using Composer:

```bash
composer require winter/wn-docs-plugin
```

Then, run the migrations to ensure the plugin is enabled:

```bash
php artisan winter:up
```

## Registering documentation

Documentation can be registered by adding a `registerDocumentation` method to your Plugin class (`Plugin.php`), and will depend on whether the documentation is content-based or API-based, and whether the documentation or code is stored locally or remotely.

```php
<?php

class MyPlugin extends \System\Classes\PluginBase
{
    // ...

    public function registerDocumentation()
    {
        return [
            'guide' => [
                'name' => 'Documentation Guide',
                'type' => 'user',
                'source' => 'local',
                'path' => 'docs'
            ],
        ];
    }

    // ...
}
```

The method should return an array, with the key of each item representing the "code" of the documentation, and the following parameters in an array as the value:

Parameter | Required | Description
--------- | -------- | -----------
`name` | Yes | The name of this documentation. This will be displayed in documentation lists.
`type` | Yes | The type of documentation. It must be one of the following: `user`, `developer`, `api`. See the [Types of Documentation](#documentation-types) for more information.
`source` | Yes | Where the documentation can be sourced from. Must be either `local` or `remote`.
`path` | No | If `source` is local, this will determine the path - relative to the plugin root - that the documentation or code can be found.
`url` | No | If `source` is remote, this will determine the URL to download the documentation source from. The URL must point to a ZIP file that can be extracted.
`zipFolder` | No | If `source` is remote, this will allow you to limit the source to a folder within the ZIP file, if the ZIP includes other files.
`tocPath` | No | Determines the path, relative to the source, where the table of contents YAML file can be found. By default, the Docs plugin will look for a `toc.yaml` in the root folder of the documentation source.
`image` | No | Provides an image representation of the documentation.
`ignorePaths` | An array of paths to ignore when finding available documentation. Each path may be specified as a glob.

For API documentation (ie. the `type` parameter is `api`), there are a couple of extra parameters that may be specified:

Parameter | Description
--------- | -----------
`sourcePaths` | An array of paths to limit the API parser to. Each path may be specified as a glob. If no source paths are provided, all PHP files are parsed. Note that the `ignorePaths` patterns are still applied.
