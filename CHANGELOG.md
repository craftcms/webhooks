# Release Notes for Webhooks for Craft CMS

## Unreleased

- Webhooks now requires Craft CMS 6.0 or later and PHP 8.5 or later, and runs natively on Craft 6 without the `craftcms/yii2-adapter` package. See [UPGRADE.md](UPGRADE.md).
- Webhooks now listen for Laravel event classes, with an optional subject class. Updating converts Craft 5 element and service events, and disables webhooks whose event has no Craft 6 equivalent.
- The plugin’s classes moved to the `CraftCms\Webhooks` namespace.
- Plugin settings are now read from `config/craft/webhooks.php`. Run `php artisan vendor:publish --tag=webhooks` to create it.
- Custom filters are now registered with the `CraftCms\Webhooks\Events\FilterTypesResolving` event, and `FilterInterface` methods receive the event class and event object.
- Webhook requests are now sent by Laravel queue jobs with Laravel’s HTTP client. URLs and redirects are still validated against SSRF, and pinned to the validated IP addresses.
- The Webhooks control panel pages are now built with Craft’s control panel UI components.
- Saving a webhook now shows an error if its payload template uses a tag, filter or function that the Twig sandbox doesn’t allow.

## 3.4.0.1 - 2026-10-11

- Updated incorrect GHSA link.

## 3.4.0 - 2026-10-11

> [!WARNING]
> Webhook URLs that resolve to private IP addresses are now rejected, unless their hostname is listed in the `allowedPrivateHosts` config setting.
> See [Sending Webhooks to Internal Services](https://github.com/craftcms/webhooks#sending-webhooks-to-internal-services) on how to do that.

- Fixed a [medium-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) SSRF vulnerability. (GHSA-fr85-wg7m-6wvq)

## 3.3.0 - 2026-07-06

- Fixed an error that could occur when triggering a webhook without a URL. ([#103](https://github.com/craftcms/webhooks/issues/103))
- Fixed a [high-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) SSRF vulnerability. ([GHSA-j6vc-h4j5-7chh](https://github.com/craftcms/webhooks/security/advisories/GHSA-j6vc-h4j5-7chh))

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
