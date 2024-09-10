# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [unrelease] -

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
