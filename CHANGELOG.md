# Release Notes for Webhooks for Craft CMS

## 3.0.5 - 2023-09-18

### Fixed

- Fixed a potential SSRF vulnerability.

## 3.0.4 - 2022-06-01

### Fixed
- Fixed a bug where the `FirstSave` and `ProvisionalDraft` filters would not respect negation. ([#74](https://github.com/craftcms/webhooks/pull/74))

## 3.0.3 - 2022-05-20

### Fixed
- Fixed a bug where you couldn’t add custom headers when defining a webhook. ([#73](https://github.com/craftcms/webhooks/pull/73))

## 3.0.2 - 2022-05-12

### Fixed
- Fixed a Twig error when accessing the activity page. ([#71](https://github.com/craftcms/webhooks/issues/71))

## 3.0.1 - 2022-05-11

### Fixed
- Fixed PHP error. ([#70](https://github.com/craftcms/webhooks/issues/70))

## 3.0.0 - 2022-05-03

### Added
- Added Craft 4 compatibility.

### Changed
- The `webhookManager` component can now be configured via `craft\services\Plugins::$pluginConfigs`.