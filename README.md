__ ALPHA version - DO NOT use in production __

# WP Pydio SSO
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/pydio/wp-pydio-sso/badges/quality-score.png?s=f5bbc36f627ada7a688a14aee41cdb9ae0f84872)](https://scrutinizer-ci.com/g/pydio/wp-pydio-sso/)

[Homepage](http://pyd.io/cms-bridges/) |
[GitHub-Repository](https://github.com/pydio/wp-pydio-sso) |
[Issue-Tracker](https://github.com/pydio/wp-pydio-sso/issues) |

Associate Pydio and WordPress users directly using WP as the master user database

## How to contribute

- Report issues or feature requests here on the [issue tracker](https://github.com/pydio/wp-pydio-sso/issues)
- Fork and send us your Pull Requests

## Installation

### Requirements

- Pydio: >= 5
- Wordpress: >= 3.x

### Set up WordPress

1. Install the plugin
2. Activate the plugin
3. Go to `Settings > Pydio Bridge`
4. Set `Pydio Path` and `Secret Key`

### Set up Pydio
@todo
1. Log in to your Pydio instance as 'admin'
2. Go to `Settings > Core Configurations > Authentication`
3. Switch the `Main Instance` to __"Remote Authentication"__  [Make sure you have __NO__ "Second Instance" (the plugin does not support multi auth yet)]
4. Set `CMS Type` __"Wordpress"__, and set up the necessary parameters. The secret key must be the same as the one you've set in your WP plugin settings.
```
 !! Warning, if you use "$" in your secret key (on the wordpress side), add a \ symbol before it in the configuration. To be on the safe side just avoid using the "$" sign in your secret key.
```
