# Command Line Tools for CiviCRM

Manage CiviCRM through the command line.

The latest iteration of the Command Line Tools for CiviCRM is built in a multi-class structure that WP-CLI expects and which enables proper documentation of the `wp civicrm` command and all its sub-commands. The following details what has changed, what has stayed the same, and what is new.

### Dropped Commands

* `wp civicrm rest`: Use [CiviCRM's WordPress REST API](https://github.com/civicrm/civicrm-wordpress/tree/master/wp-rest) or `wp civicrm api` instead.

### Unchanged Commands

* `wp civicrm api`: For the time-being this command is an alias of `wp civicrm api3`. It may become an alias for `wp civicrm api4` when that command is added, so it is definitely better to use `wp civicrm api3` directly to avoid problems in the future. There will, of course, be a deprecation notice issued well before the switch, so don't worry!
* `wp civicrm pipe`: This command will remain in the top-level namespace.

### New Commands

There is a new command `wp civicrm core <command>` which (sort of) mirrors the functionality in `wp core <command>`. It holds the commands that apply to CiviCRM as a whole.

| Command | Description | Old Command |
| --- | --- | --- |
| `wp civicrm core activate` | Activates the CiviCRM plugin and loads the database. | New |
| `wp civicrm core backup` | Back up the CiviCRM plugin, CiviCRM files and database. | New |
| `wp civicrm core check-update` | Checks for CiviCRM updates via Version Check API. | New |
| `wp civicrm core check-version` | Checks for a CiviCRM version or matching localization archive. | New |
| `wp civicrm core download` | Downloads core CiviCRM files. | New |
| `wp civicrm core install` | Installs the CiviCRM plugin. | `wp civicrm install` but without activation |
| `wp civicrm core is-installed` | Checks if CiviCRM is installed. | Not implemented yet |
| `wp civicrm core restore` | Restore the CiviCRM plugin, CiviCRM files and database from a backup. | Requires a backup made with `wp civicrm core backup` |
| `wp civicrm core update` | Updates CiviCRM to a newer version. | `wp civicrm upgrade` |
| `wp civicrm core update-cfg` | Reset paths to correct config settings. | `wp civicrm update-cfg` |
| `wp civicrm core update-db` | Runs the CiviCRM database update procedure. | `wp civicrm upgrade-db` |
| `wp civicrm core verify-checksums` | Verifies CiviCRM files against checksums via `googleapis`. | Not implemented yet |
| `wp civicrm core version` | Displays the CiviCRM version. | New |

Use `wp help civicrm <command>` or `wp help civicrm core <command>` for full details and examples.

There is a new command `wp civicrm db <command>` which (sort of) mirrors the functionality in `wp db <command>`. It holds the commands that apply to interaction with the CiviCRM database.

| Command | Description | Old Command |
| --- | --- | --- |
| `wp civicrm db clear` | Drop all CiviCRM tables, views, functions and stored procedures from the database. | New |
| `wp civicrm db cli` | Quickly enter the MySQL command line. | `wp civicrm sql-cli` |
| `wp civicrm db config` | Show the CiviCRM database connection details. | `wp civicrm sql-conf` |
| `wp civicrm db connect` | Get a string which connects to the CiviCRM database. | `wp civicrm sql-connect` |
| `wp civicrm db drop` | Drop the CiviCRM database when it is not shared with WordPress. | New |
| `wp civicrm db drop-tables` | Drop the CiviCRM tables from the database. | New |
| `wp civicrm db dump` | Dump the whole CiviCRM database and print to STDOUT or save to a file. | `wp civicrm sql-dump` |
| `wp civicrm db export` | Export the CiviCRM database and print to STDOUT or save to a file. | New |
| `wp civicrm db functions` | Get the list of CiviCRM functions in the database. | New |
| `wp civicrm db import` | Loads a whole CiviCRM database. | New |
| `wp civicrm db is-shared` | Check if CiviCRM shares a database with WordPress. | New |
| `wp civicrm db procedures` | Get the list of CiviCRM procedures in the database. | New |
| `wp civicrm db query` | Perform a query on the CiviCRM database. | `wp civicrm sql-query` |
| `wp civicrm db tables` | Gets a set of CiviCRM tables in the database. | New |

Use `wp help civicrm db <command>` for full details and examples.

### Deprecated Commands

All previous commands still exist for the time being. However, because they were attached to the top-level `wp civicrm` namespace, it seems sensible to deprecate them in favour of better-namespaced new commands. The following table shows you replacement commands:

| Old Command | New Command |
| --- | --- |
| `wp civicrm cache-clear` | `wp civicrm cache flush` |
| `wp civicrm disable-debug` | `wp civicrm debug disable` |
| `wp civicrm enable-debug` | `wp civicrm debug enable` |
| `wp civicrm install` | See [Composite Commands](#composite-commands) below |
| `wp civicrm member-records` | `wp civicrm job member-records` or `wp civicrm job membership` |
| `wp civicrm process-mail-queue` | `wp civicrm job process-mail-queue` or `wp civicrm job mailing` |
| `wp civicrm restore` | Only works with backups made by `wp civicrm upgrade` |
| `wp civicrm sql-conf` | `wp civicrm db config` or `wp civicrm db conf` |
| `wp civicrm sql-connect` | `wp civicrm db connect` |
| `wp civicrm sql-cli` | `wp civicrm db cli` |
| `wp civicrm sql-dump` | `wp civicrm db dump` |
| `wp civicrm sql-query` | `wp civicrm db query` |
| `wp civicrm update-cfg` | `wp civicrm core update-cfg` |
| `wp civicrm upgrade` | See [Composite Commands](#composite-commands) below |
| `wp civicrm upgrade-db` | `wp civicrm core update-db` |

As above, use `wp help civicrm <command>` for full details and examples.

### Composite Commands

There are two special cases in the set of old commands:

* `wp civicrm install`
* `wp civicrm upgrade`

These are both composite commands.

The `wp civicrm install` command calls the following sequence:

1. `wp civicrm core install`
2. `wp civicrm core activate`

The `wp civicrm upgrade` command calls the following sequence:

1. A custom backup procedure. Better to call `wp civicrm core backup` instead, but `wp civicrm restore` requires the custom procedure.
2. `wp civicrm core update`
3. `wp civicrm core update-db`

It is preferable to call the new commands individually in the same sequence - but there may also be a case for a set of composite commands like these for commonly used sequences.
