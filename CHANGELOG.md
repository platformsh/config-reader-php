# Changelog

## [2.3.1] - 2019-11-04

### Added

* `CHANGELOG` added.
* `onDedicated` method that determines if the current environment is a Platform.sh Dedicated environment. Replaces deprecated `onEnterprise` method.

### Changed

* Deprecates `onEnterprise` method - which is for now made to wrap around the added `onDedicated` method. `onEnterprise` **will be removed** in a future release, so update your projects to use `onDedicated` instead as soon as possible.

## [2.3.0] - 2019-09-19

### Added

* `getPrimaryRoute` method for accessing routes marked "primary" in `routes.yaml`.
* `getUpstreamRoutes` method returns an object map that includes only those routes that point to a valid upstream.

## [2.2.2] - 2019-04-29

### Changed

* Updates `routes` method to use `routesDef` instead of `variablesDef` while checking if routes are defined in a Platform.sh environment.
* Updates `getRoute` method documentation in `README`.

### Removed

* Guard on the `variables` method.

## [2.2.1] - 2019-04-25

### Changed

* Improved the error handling for missing property variables.

## [2.2.0] - 2019-04-24

### Changed

* Route URL addition moved to constructor.
* Switch to more permissive checking of variable availability.

## [2.1.0] - 2019-03-22

### Added

* Adds `hasRelationship` method, which determines if a relationship is defined, and thus has credentials available.
