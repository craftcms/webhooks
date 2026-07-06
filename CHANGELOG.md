# Release Notes for Webhooks for Craft CMS

## 3.3.0 - 2026-07-06

- Fixed an error that could occur when a triggering a webhook without a URL. ([#103](https://github.com/craftcms/webhooks/issues/103))
- Fixed a [high-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) SSRF vulnerability.

## 3.2.0 - 2026-02-12

> [!WARNING]
> Payload, header, webhook URL, and debounce key templates are now rendered in a sandboxed Twig environment, when `enableTwigSandbox` is enabled.
 
- Webhooks now requires Craft 4.17+ or 5.9+.
- Fixed a [high-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) RCE vulnerability. ([GHSA-8wg7-wm29-2rvg](https://github.com/craftcms/webhooks/security/advisories/GHSA-8wg7-wm29-2rvg))

## 3.1.1 - 2025-07-29

- Fixed a PHP error that could occur if a webhook header was null. ([#97](https://github.com/craftcms/webhooks/issues/97))

## 3.1.0 - 2024-03-19

- Added Craft 5 compatibility.
- Fixed a bug where request timestamps in the Activity section weren’t using the system time zone. ([#78](https://github.com/craftcms/webhooks/pull/78))

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
