# REST API integration for CiviCRM

This code exposes CiviCRM's [extern](https://github.com/civicrm/civicrm-core/tree/master/extern) scripts as WordPress REST endpoints.

### Requirements

-   PHP 7.3+
-   WordPress 4.7+
-   CiviCRM to be installed and activated.

### Endpoints

1. `civicrm/v3/rest` - a wrapper around `civicrm_api3()`

    **Parameters**:

    - `key` - **required**, the site key
    - `api_key` - **required**, the contact api key
    - `entity` - **required**, the API entity
    - `action` - **required**, the API action
    - `json` - **optional**, json formatted string with the API parameters/argumets, or `1` as in `json=1`

    By default all calls to `civicrm/v3/rest` return XML formatted results, to get `json` formatted result pass `json=1` or a json formatted string with the API parameters, like in the example 2 below.

    **Examples**:

    1. `https://example.com/wp-json/civicrm/v3/rest?entity=Contact&action=get&key=<site_key>&api_key=<api_key>&group=Administrators`

    2. `https://example.com/wp-json/civicrm/v3/rest?entity=Contact&action=get&key=<site_key>&api_key=<api_key>&json={"group": "Administrators"}`

2. `civicrm/v3/url` - a substition for `civicrm/extern/url.php` mailing tracking

3. `civicrm/v3/open` - a substition for `civicrm/extern/open.php` mailing tracking

4. `civicrm/v3/authorizeIPN` - a substition for `civicrm/extern/authorizeIPN.php` (for testing Authorize.net as per [docs](https://docs.civicrm.org/sysadmin/en/latest/setup/payment-processors/authorize-net/#shell-script-testing-method))

    **_Note_**: this endpoint has **not been tested**

5. `civicrm/v3/ipn` - a substition for `civicrm/extern/ipn.php` (for PayPal Standard and Pro live transactions)

    **_Note_**: this endpoint has **not been tested**

6. `civicrm/v3/cxn` - a substition for `civicrm/extern/cxn.php`

7. `civicrm/v3/pxIPN` - a substition for `civicrm/extern/pxIPN.php`

    **_Note_**: this endpoint has **not been tested**

8. `civicrm/v3/widget` - a substition for `civicrm/extern/widget.php`

    **_Note_**: this endpoint has **not been tested**

### Settings

Set the `CIVICRM_WP_REST_REPLACE_MAILING_TRACKING` constant to `true` to replace mailing url and open tracking calls with their counterpart REST endpoints, `civicrm/v3/url` and `civicrm/v3/open`.

_Note: use this setting with caution, it may affect performance on large mailings, see `CiviCRM_WP_REST\Civi\Mailing_Hooks` class._
