# Escalade GLPI plugin

[![License](https://img.shields.io/github/license/pluginsGLPI/escalade.svg?&label=License)](https://github.com/pluginsGLPI/escalade/blob/develop/LICENSE)
[![Follow twitter](https://img.shields.io/twitter/follow/Teclib.svg?style=social&label=Twitter&style=flat-square)](https://twitter.com/teclib)
[![Telegram Group](https://img.shields.io/badge/Telegram-Group-blue.svg)](https://t.me/glpien)
[![Project Status: Active](http://www.repostatus.org/badges/latest/active.svg)](http://www.repostatus.org/#active)
[![GitHub release](https://img.shields.io/github/release/pluginsGLPI/escalade.svg)](https://github.com/pluginsGLPI/escalade/releases)
[![GitHub build](https://travis-ci.org/pluginsGLPI/escalade.svg?)](https://travis-ci.org/pluginsGLPI/escalade/)

## ⚠️ IMPORTANT NOTICE - END OF LIFE

**Escalade 3.0.0 is a migration-only plugin: its features are now provided by the [MoreOptions](https://github.com/pluginsGLPI/moreoptions) plugin.**

Plugin marking the end of life of `escalade`.
It updates database tables and data to the final version, required before the migration of the group filter to MoreOptions.
⚠️ This plugin must be installed in production only to perform the update, and then uninstalled once the operation is complete.

Usage Instructions:
--------------------------
1. Install this final version (3.0.0).
2. Install and enable the MoreOptions plugin.
3. Run the migration of the group filter using the following command:
    - "`php bin/console plugins:moreoptions:migrate_escalade`".
    - The groups a group can escalate to become links of the escalation tree of the root entity, replicated in all its sub-entities.
    - An active group filter is activated in the root entity, which the sub-entities inherit.
    - The users allowed to bypass the filter cannot be migrated: MoreOptions allows profiles to bypass it.
4. Check the migration status from the configuration page of the plugin.
5. Uninstall the `Escalade` plugin once all data has been migrated and verified.

This version provides support for migration only. For escalation features, use the MoreOptions plugin.

## Documentation

We maintain a detailed documentation here -> [Documentation](https://glpi-plugins.readthedocs.io/en/latest/escalade/index.html)

## Contact

For notices about major changes and general discussion of Escalade, subscribe to the [/r/glpi](https://www.reddit.com/r/glpi/) subreddit.
You can also chat with us via [@glpi on Telegram](https://t.me/glpien).

## Professional Services

The GLPI Network services are available through our [Partner's Network](http://www.teclib-edition.com/en/partners/). We provide special training, bug fixes with editor subscription, contributions for new features, and more.

Obtain a personalized service experience, associated with benefits and opportunities.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer

## Copying

* **Code**: you can redistribute it and/or modify it under the terms of the GNU General Public License ([GPL-2.0](https://www.gnu.org/licenses/gpl-2.0.en.html)).
