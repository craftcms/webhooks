# Release Notes for Webhooks for Craft CMS

## 1.1.2 - 2018-12-21

### Changed
- Webhook requests now include data for any magic event properties defined by `fields()`, if the event class implements `yii\base\Arrayable`. ([#2](https://github.com/craftcms/webhooks/issues/2))   

## 1.1.1 - 2018-12-17

### Added
- Webhooks is now translated into Chinese. ([#1](https://github.com/craftcms/webhooks/pull/1))

### Fixed
- Fixed a bug where the “Extra User Attributes”, “Extra Sender Attributes”, and “Extra Event Attributes” fields were visible when editing an existing webhook with a GET request method.

## 1.1.0 - 2018-12-13

### Added
- Added support for webhooks that send GET requests.
- Webhook names and group names can now contain emojis, even if using MySQL.
- Typing `->` or `=>` into a webhook’s Name field now creates a ➡️.  

## 1.0.1 - 2018-12-13

### Fixed
- Fixed a bug where webhook-send jobs didn’t have descriptions.

## 1.0.0 - 2018-12-13

Initial release.
