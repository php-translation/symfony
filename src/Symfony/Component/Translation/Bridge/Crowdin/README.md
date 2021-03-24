Crowdin Translation Provider
============================

Provides Crowdin integration for Symfony Translation.

DSN example
-----------

```
// .env file
CROWDIN_DSN=crowdin://PROJECT_ID:API_TOKEN@default?domain=ORGANIZATION_DOMAIN
```

where:
 - `PROJECT_ID` is your Crowdin Project ID
 - `API_KEY` is your Crowdin API Token
 - `ORGANIZATION_DOMAIN` is your Crowdin Enterprise Organization domain (required only for Crowdin Enterprise usage)

To generate a new token in Crowdin, follow these steps:
 - Go to Account Settings > API tab, Personal Access Tokens section, and click New Token.
 - Specify Token Name and click Create.

To generate a new token in Crowdin Enterprise, follow these steps:
 - Go to Account Settings > Access tokens tab and click New token.
 - Specify Token Name, select Scopes and Projects, click Create.

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
