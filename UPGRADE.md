# Upgrading to Webhooks 4.0 (Craft 6)

Webhooks 4.0 is a native Craft 6 plugin. This guide lists what changed from 3.x and what to update in your project and custom code.

## Requirements

- Craft CMS 6.0 or later
- PHP 8.5 or later
- The `craftcms/yii2-adapter` package is not needed.
- Upgrade from Webhooks 3.4.0 or later.

## Upgrade steps

1. Update Webhooks to 3.4.0 or later on Craft 5, then upgrade your project to Craft 6.
2. Update the plugin: `composer require craftcms/webhooks:^4.0`.
3. Run `php artisan craft:up`. A migration converts your webhooks to Craft 6 events (see [Database and migrations](#database-and-migrations)).
4. Move `config/webhooks.php` to `config/craft/webhooks.php`, or run `php artisan vendor:publish --tag=webhooks` to create a new one.
5. If a payload template uses `json_encode`, allow it in the Twig sandbox, and check templates for other filters and functions the sandbox doesn’t allow (see [Templates and Twig](#templates-and-twig)).
6. Check that a queue worker is running (`php artisan queue:work`). Webhook requests are sent by queue jobs.
7. Review the webhooks that the migration disabled, and update anything that reads webhook payloads.

## Breaking changes

### Events

Craft 6 replaced Yii events with Laravel event classes. Each webhook now listens for one event class, with an optional subject class instead of the Craft 5 sender class.

- **Event** is the event class, such as `CraftCms\Cms\Element\Events\ElementLifecycleSaved`.
- **Subject Class** is optional. When it’s set, the webhook only runs if one of the event’s properties is an instance of that class, such as `CraftCms\Cms\Entry\Elements\Entry` for an event’s `element` property. That object is the event’s `sender` in templates and payloads.

The migration converts these Craft 5 events:

| Before | After |
| --- | --- |
| `craft\elements\Entry` (or another element class), `afterSave` | `ElementLifecycleSaved`, with that element class as the subject class |
| Element class, `beforeSave`, `afterPropagate`, `beforeDelete`, `afterDelete`, `beforeRestore`, `afterRestore`, `beforeMoveInStructure`, `afterMoveInStructure` | The matching `ElementLifecycle*` and `ElementMov*InStructure` events |
| `craft\services\Elements`, `afterSaveElement`, `afterDeleteElement`, `afterResaveElement` and the service’s other events | `ElementSaved`, `ElementDeleted`, `ElementResaved` and the matching `CraftCms\Cms\Element\Events` classes |
| `craft\services\Users`, `afterActivateUser`, `afterSuspendUser`, `afterVerifyEmail` and the service’s other user status events | `UserActivated`, `UserSuspended`, `EmailVerified` and the matching `CraftCms\Cms\User\Events` classes |

Webhooks for any other event are disabled and left unchanged, and the migration logs a warning for each one. Edit them in **Webhooks → Manage Webhooks** and choose a Craft 6 event. The event field suggests events from Craft and installed plugins.

### Payloads

The default JSON payload has the same keys, but some values changed:

| Key | 3.x | 4.0 |
| --- | --- | --- |
| `name` | The Yii event name, such as `afterSave` | The event class’s short name, such as `ElementLifecycleSaved` |
| `eventClass` | A Yii event class, such as `craft\events\ModelEvent` | The Laravel event class |
| `senderClass`, `sender` | The object that triggered the event, such as `craft\services\Elements` | The event’s subject: the first property that matches the webhook’s subject class (or the first object property if it has none), such as the saved entry |
| `event` | The Yii event’s properties, except `sender`, `name`, `handled` and `data` | The event’s public properties |

Element class names in `senderClass` and `sender` also changed, such as `craft\elements\Entry` to `CraftCms\Cms\Entry\Elements\Entry`. Update any automations that read these values.

### Templates and Twig

URL, header, debounce key and payload templates still get an `event` variable. It’s now an array with the event’s public properties, plus `name` (the event class’s short name) and `sender` (the subject). `event.sender.id`, `event.sender.title` and `event.isNew` work as before.

Craft 6 always renders these templates in the Twig sandbox, and the `enableTwigSandbox` setting was removed. The sandbox only allows the tags, filters and functions listed in Craft’s `twig-sandbox` config, so templates that worked on Craft 5 can fail. For example, it doesn’t allow the `json_encode` and `atom` filters or the `className()` function. Use `now|date('c')` instead of `now|atom`, and allow `json_encode` in `config/craft/twig-sandbox.php`:

```php
return [
    'allowedFilters' => ['json_encode'],
];
```

Saving a webhook now shows an error if its payload template uses a tag, filter or function that the sandbox doesn’t allow.

### Filters

Register custom filters by listening for `CraftCms\Webhooks\Events\FilterTypesResolving` instead of `Plugin::EVENT_REGISTER_FILTER_TYPES`.

Before:

```php
Event::on(
    Webhooks::class,
    Webhooks::EVENT_REGISTER_FILTER_TYPES,
    function(RegisterComponentTypesEvent $event) {
        $event->types[] = ArticleFilter::class;
    }
);
```

After:

```php
use CraftCms\Webhooks\Events\FilterTypesResolving;
use Illuminate\Support\Facades\Event;

Event::listen(function (FilterTypesResolving $event): void {
    $event->types[] = ArticleFilter::class;
});
```

`FilterInterface` methods have new signatures:

| Before | After |
| --- | --- |
| `show(string $class, string $event): bool` | `show(string $eventClass): bool` |
| `check(yii\base\Event $event, bool $value): bool` | `check(object $event, bool $value): bool` |

Read the element from the event’s property, such as `$event->element`, rather than `$event->sender`. The migration renames filter classes saved on webhooks.

### Classes and namespaces

| Before | After |
| --- | --- |
| `craft\webhooks\Plugin` | `CraftCms\Webhooks\Plugin` |
| `craft\webhooks\WebhookManager` (`Plugin::getInstance()->getWebhookManager()`) | `CraftCms\Webhooks\Models\Webhook` and `CraftCms\Webhooks\Models\Group` Eloquent models |
| `Plugin::request()`, `sendRequest()`, `getUrlValidator()`, `toArray()`, `getAllFilters()` | `CraftCms\Webhooks\Webhooks::request()`, `send()`, `urlValidator()`, `toArray()`, `filterTypes()` (resolve it from the container) |
| `craft\webhooks\Webhook`, `craft\webhooks\Group` | `CraftCms\Webhooks\Models\Webhook`, `CraftCms\Webhooks\Models\Group` |
| `craft\webhooks\SendRequestJob` | `CraftCms\Webhooks\Jobs\SendRequest` |
| `craft\webhooks\Settings` | `CraftCms\Webhooks\Settings` |
| `craft\webhooks\filters\*` | `CraftCms\Webhooks\Filters\*` |
| `craft\webhooks\WebhookHelper` | `CraftCms\Webhooks\EventSuggestions` |

Requests are stored as `CraftCms\Webhooks\Models\WebhookRequest` models, with a `CraftCms\Webhooks\Enums\RequestStatus` status.

### Settings and config

Settings are read from `config/craft/webhooks.php`. The setting names and defaults didn’t change. `guzzleConfig` and `allowedPrivateHosts` can still only be set in the config file.

### Routes and action URLs

The control panel pages keep their URLs (`webhooks`, `webhooks/new`, `webhooks/{id}` and `webhooks/activity`). The `webhooks/*` controller actions were replaced by Laravel routes named `webhooks.*`. The index page lists groups in a table, and each group is edited at `webhooks/groups/{id}`, which replaces the `webhooks/group/{id}` sidebar filter.

### Permissions

The **Webhooks** plugin access permission (`accessPlugin-webhooks`) still controls access to every page.

### Database and migrations

The plugin keeps its `webhookgroups`, `webhooks` and `webhookrequests` tables. Updating runs two migrations, which:

- make `webhooks.class` nullable,
- convert Craft 5 events and filter class names, as described above,
- disable webhooks whose event has no Craft 6 equivalent,
- change the `url` columns from `varchar(255)` to `mediumtext`, so webhook URLs can be longer than 255 characters.

## Removed

- `Plugin::EVENT_REGISTER_FILTER_TYPES`. Use `FilterTypesResolving`.
- `Plugin::pushPendingJobs()` and `getRequestData()`. Jobs are dispatched after the database transaction commits, and request details are on `WebhookRequest`.
- The Craft 5 control panel templates and JavaScript. The pages are built with Craft’s control panel UI components.

## Need help?

Open an issue at https://github.com/craftcms/webhooks/issues.
