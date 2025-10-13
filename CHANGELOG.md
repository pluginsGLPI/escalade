# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed

- Fix compatibility check with Behaviors plugin

## [2.10.0] - 2025-10-01

### Added

- GLPI 11 compatibility

## [2.9.18] - 2025-30-09

### Fixed

- Fix template mandatory field validation interference when adding solutions to tickets
- Fix the relationship when cloning a ticket: if the `Close linked tickets at the same time` option is enabled, the relationship is `DUPLICATED_WITH` otherwise it's `LINK_TO`
- Fix tech assignment should not trigger escalation behavior (as defined in the documentation)

## [2.9.17] - 2025-08-27

### Fixed

- Rename options related to ticket cloning and closure to avoid ambiguity
- Fix rule doesn't trigger when user uses "Assign myself" button

## [2.9.16] - 2025-07-10

### Fixed

- Improved access control checks when cloning ticket

## [2.9.15] - 2025-07-08

- Fix duplicate notifications being sent during escalation by implementing two additional targets.
- Fix 8 warnings in the `php-errors.log` file
- Fix reopening of a cloned ticket when the parent ticket is reopened
- Fix ticket task not added to timeline during escalation

## [2.9.14] - 2025-05-28

- Fix Escalate timeline button execute RuleTicket
- Fix group assign with `reassign_group_from_cat` option
- Fix the `remove_tech` option when a user was added to a ticket


## [2.9.13] - 2025-03-31

- Fix `show_history` option when using the `Escalate` button.
- Fix `use_assign_user_group` option wich delete assing users
- Fix `ticket_last_status` option when using the `Associate myself` button.

## [2.9.12] - 2025-03-20

### Fixed

- Calculation of status when a technician self-assigns to a ticket
- Fixed `Bypass filtering on the groups assignment` option
- Fixed technician deletion when ticket updated
- Fixed `Ticket status after an escalation` option
- Do not perform escalation when mandatory ticket fields are missing

## [2.9.11] - 2025-03-11

### Fixed

- Ensure that when several technicians are assigned, they are treated correctly during the escalation.
- Redirect users without ticket rights after escalation.
- Fix private task added when ticket mandatory fields are not filled
- Remove redundant notifications
- Fixed assignment of requester group to ticket
- Ensure plugin works seamlessly in external contexts (e.g., from plugins)
- Fixed `Close cloned tickets at the same time` option
- Fixed `Bypass filtering on the groups assignment` option
- Rename the option **"Don't change"** to **"Default (not managed by plugin)"** for the **"Ticket status after an escalation"** setting to reduce ambiguity.
- Remove the user when a ticket escalates to a group with `remote_tech option` set to `true`

### Security

- Check permissions before displaying group history or escalating access
- Prevents undefined index `comment` when escalating

## [2.9.10] - 2024-11-27

### Fixed

- Remove redundant notifications

## [2.9.9] - 2024-09-10

### Fixed

- Correction of the task message generated during escalation
- Fix task not added when escalating with history
- Fix escalating with history
- Fix ```status``` after escalation
- Prevent anonymous user to be deleted during escalation

## [2.9.8] - 2024-07-15

### Fixed

- Prevent an escalation when a ticket is updated

### Changed

- Full history window (now is a modal)

## [2.9.7] - 2024-07-04

### Added

- Add config option for default assignation

### Fixed

- Fix rules execution before escalation
- Set ```assign as observer``` unchecked by default
- Fixed ```blocking of user deletion```
- Fix ```use technician group``` option

## [2.9.6] - 2024-05-17

### Fixed

- Fix ```Display delete button``` option

## [2.9.5] - 2024-05-06

### Fixed

- Fix unauthorized deletion of ticket actors according to plugin configuration
- Fix unsended notifications while `Delete old groups when adding a new one` is set to `No`

## [2.9.4] - 2024-04-03

### Fixed

- Fix behavior for `Ticket status after an escalation` option
- Fix cloning error display

### Fixed
- Fix group filtering


## [2.9.3] - 2024-02-21

### Added

- Add short label for split action buttons in timeline footer

### Fixed

- Fix group dropdown depending on the configuration ```use_filter_assign_group```
- Fix permission checks in ticket escalation
- Fix group filtering in escalation process
