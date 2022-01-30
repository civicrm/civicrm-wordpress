# WP-CLI integration for CiviCRM

#### wp civicrm api

Command for accessing the CiviCRM API. Syntax is identical to `drush cvap`.

#### wp civicrm cache-clear

Command for accessing clearing cache. Equivilant of running `civicrm/admin/setting/updateConfigBackend&reset=1`.

#### wp civicrm enable-debug

Command for to turn debug on.

#### wp civicrm disable-debug

Command for to turn debug off.

#### wp civicrm member-records

Run the CiviMember UpdateMembershipRecord cron (civicrm member-records).

#### wp civicrm process-mail-queue

Process pending CiviMail mailing jobs.

Example: `wp civicrm process-mail-queue -u admin`

#### wp civicrm rest

Rest interface for accessing CiviCRM APIs. It can return `xml` or `json` formatted data.

#### wp civicrm restore

Restore CiviCRM codebase and database back from the specified backup directory.

#### wp civicrm sql-conf

Show CiviCRM database connection details.

#### wp civicrm sql-connect

A string which connects to the CiviCRM database.

#### wp civicrm sql-cli

Quickly enter the `mysql` command line.

#### wp civicrm sql-dump

Prints the whole CiviCRM database to `STDOUT` or save to a file.

#### wp civicrm sql-query

Usage: `wp civicrm sql-query <query> <options>...`

`<query>` is a SQL statement which can alternatively be passed via `STDIN`. Any additional arguments are passed to the `mysql` command directly.

#### wp civicrm update-cfg

Update `config_backend` to correct config settings, especially when the CiviCRM site has been cloned or migrated.

#### wp civicrm upgrade

Take backups, replace CiviCRM codebase with new specified tarfile and upgrade database by executing the CiviCRM upgrade process - `civicrm/upgrade?reset=1`. Use `wp civicrm restore` to revert to previous state in case anything goes wrong.

#### wp civicrm upgrade-db

Run `civicrm/upgrade?reset=1` just as a web browser would.

#### wp civicrm install

Command for to install CiviCRM. The install command requires that you have downloaded a tarball or zip file first.

Options:

```
--dbhost            MySQL host for your WordPress/CiviCRM database. Defaults to localhost.
--dbname            MySQL database name of your WordPress/CiviCRM database.
--dbpass            MySQL password for your WordPress/CiviCRM database.
--dbuser            MySQL username for your WordPress/CiviCRM database.
--lang              Default language to use for installation.
--langtarfile       Path to your l10n tar.gz file.
--site_url          Base Url for your WordPress/CiviCRM website without http (e.g. mysite.com)
--ssl               Using ssl for your WordPress/CiviCRM website if set to on (e.g. --ssl=on)
--tarfile           Path to your CiviCRM tar.gz file.
--zipfile           Path to your CiviCRM zip file.
```
