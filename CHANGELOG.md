# Release Notes for Webhooks for Craft CMS

## 3.5.0 - 2026-11-10

- Added Craft CMS 6 compatibility, via the `craftcms/yii2-adapter` package.
- Webhook URLs can now be longer than 255 characters. ([#95](https://github.com/craftcms/webhooks/pull/95))
- Installing the plugin now archives existing Webhooks tables, renaming them with an `_old` suffix, instead of dropping them. ([#98](https://github.com/craftcms/webhooks/pull/98))
- Webhooks whose templates can’t be rendered are now skipped and logged, rather than causing an error in the action that triggered them.
- The README’s payload template example no longer uses `json_encode`, which Craft’s Twig sandbox doesn’t allow. Payload templates that use it need to be rewritten on Craft 6.
- Fixed an error that occurred when a webhook’s URL, headers, debounce key format, or payload template referenced the event on Craft 6.
- Fixed a bug where webhooks triggered from console commands and queue jobs were never sent on Craft 6.
- Fixed a bug where default payloads contained raw date objects, and ignored “Extra Sender Attributes”, on Craft 6.
- Fixed a bug where the Edit Webhook page’s event filters didn’t work, and the Sender Class and Event Name fields didn’t list any suggestions, on Craft 6.
- Fixed a bug where the Edit Webhook page’s event filter buttons were misaligned, and two of them had no icon, on Craft 6.
- Fixed a bug where webhooks couldn’t be enabled or disabled from the Webhooks index page on Craft 6.
- Fixed a bug where request details couldn’t be viewed from the Activity page on Craft 6.
- Fixed a bug where request details on the Activity page were cut off, and their “Redeliver” and “Send now” buttons didn’t work, on Craft 6.

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
