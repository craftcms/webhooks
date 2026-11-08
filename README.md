<p align="center"><img src="./resources/icon.svg" width="100" height="100" alt="Webhooks icon"></p>

<h1 align="center">Webhooks for Craft CMS</h1>

This plugin adds the ability to manage “webhooks” in Craft CMS, which will send GET or POST requests when certain events occur.

It can be used to integrate your Craft project with task automation tools like [Zapier](https://zapier.com) or [Netlify](https://www.netlify.com) build hooks.

## Requirements

This plugin requires Craft CMS 6.0 or later and PHP 8.5 or later. It doesn’t need the `craftcms/yii2-adapter` package.

Upgrading from Webhooks 3.x? See [UPGRADE.md](UPGRADE.md). For Craft 4 and 5, use [Webhooks 3.x](https://github.com/craftcms/webhooks/tree/3.x).

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Webhooks”. Then press **Install** in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/webhooks

# tell Craft to install the plugin
php artisan craft:plugin:install webhooks
```

## Configuration

To configure Webhooks, go to **Settings** → **Webhooks**, or create a `config/craft/webhooks.php` file, which returns an array. Run `php artisan vendor:publish --tag=webhooks` to create one with every setting.

```php
<?php
return [
    'disableAllWebhooks' => false,
    'maxDepth' => 10,
    'maxAttempts' => 3,
    'initialDelay' => null,
    'retryDelay' => 120,
    'purgeDuration' => 604800, // 7 days
    'allowedPrivateHosts' => [],
];
```

The array can define the following keys:

- `disableAllWebhooks` – Whether all webhooks should be disabled.
- `maxDepth` – The maximum depth that the plugin should go into objects/arrays when converting them to arrays for event payloads. (Default is `5`.)
- `maxAttempts` – The maximum number of attempts each webhook should have before giving up, if the requests are coming back with non 2xx responses. (Default is `1`.)
- `initialDelay` – The delay (in seconds) that initial webhook request attempts should have.
- `retryDelay` – The delay (in seconds) between webhook attempts. (Default is `60`.)
- `purgeDuration` – The time (in seconds) that request history should be saved in the database before being deletable via garbage collection.
- `allowedPrivateHosts` – Hostnames that are allowed to resolve to private IP addresses (e.g. `10.0.0.0/8`, `192.168.0.0/16`), for webhooks sent to internal services. By default, URLs that resolve to private IP addresses are rejected. Can only be set in `config/craft/webhooks.php`. See [Sending Webhooks to Internal Services](#sending-webhooks-to-internal-services).

### Sending Webhooks to Internal Services

Webhook URLs (and any URLs they redirect to) that resolve to private IP addresses (e.g. `10.0.0.0/8`, `192.168.0.0/16`) are rejected by default. If your webhooks need to reach internal services, for example in local development or between containers in Docker or Kubernetes, list their hostnames in `config/craft/webhooks.php`.

Use an exact hostname, or a leading `*.` wildcard to match any subdomain (`*.site.testing.local` matches `api.site.testing.local`, but not `site.testing.local` itself):

```php
'allowedPrivateHosts' => [
    'my-api.internal',
    '*.ddev.site',
],
```

Only the private IP range check is relaxed for these hosts. Loopback, link-local and cloud metadata addresses are still rejected.

> [!WARNING]
> Only list hostnames whose DNS you control, and list individual services rather than whole internal domains.

## Managing Webhooks

To manage your webhooks, go to **Webhooks** in your project’s control panel.

### Webhook Groups

Webhooks can optionally be organized into groups. You can create a new group with the “New group” button on the Webhooks index page.

If a group is deleted, any webhooks in it will become ungrouped. (They will **not** be deleted along with the group.)

### Creating Webhooks

To create a new webhook, click the “New webhook” button.

Webhooks listen for [Laravel events](https://laravel.com/docs/events) dispatched by Craft and plugins. Choose the event class in the “Event” field, which suggests the events available in your project.

You can also set a “Subject Class” to narrow the webhook down. The webhook only runs if one of the event’s properties is an instance of that class, and that object becomes the event’s “sender”. For example, Craft dispatches `CraftCms\Cms\Element\Events\ElementLifecycleSaved` after any element is saved, with the element in its `element` property. To send a webhook only when an _entry_ is saved, set the Subject Class to `CraftCms\Cms\Entry\Elements\Entry`.

Webhook URLs can be set to an environment variable (`$VARIABLE_NAME`) or Twig code. If you set it to Twig code, you can reference the triggered event via an `event` variable. 

See [Integrating with Task Automation Tools](#integrating-with-task-automation-tools) for examples on how to get a Webhook URL from various task automation tools.

![Screenshot of the Edit Webhook page](./images/edit-webhook.png)

Webhooks can either send a GET request, or a POST request with a JSON body containing the following keys:

- `time` – an ISO-8601-formatted timestamp of the exact moment the event was triggered. (Webhooks are sent via the queue so there will be a slight delay between the time the event was triggered and the webhook was sent.)
- `user` – an object representing the logged-in user at the time the event was triggered.
- `name` – the event class’s short name, such as `ElementLifecycleSaved`.
- `senderClass` – the class name of the event’s sender (its subject).
- `sender` – an object representing the event’s sender.
- `eventClass` – the class name of the event.
- `event` – an object with the event’s public properties. (For example, `ElementLifecycleSaved` has `element` and `isNew` properties.)

#### Filtering Events

Some events can have filters applied to them, which prevent webhooks from being executed under certain conditions.

<img src="./images/event-filters.png" width="560" height="830" alt="Screenshot of the Event Filters setting">

Filters set to “Any” have no impact. Filters set to “Yes” are required for a webhook to execute, and filters set to “No” prevent it. Some filters can’t be combined, so setting one disables the filters it conflicts with.

Only events with an `element` property have any filters out of the box, but modules and plugins can register additional filters by listening for the `CraftCms\Webhooks\Events\FilterTypesResolving` event.

```php
use CraftCms\Webhooks\Events\FilterTypesResolving;
use Illuminate\Support\Facades\Event;

Event::listen(function (FilterTypesResolving $event): void {
    $event->types[] = ArticleFilter::class;
});
```

Filter type classes must implement `CraftCms\Webhooks\Filters\FilterInterface`:

```php
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Webhooks\Filters\FilterInterface;

class ArticleFilter implements FilterInterface
{
    public static function displayName(): string
    {
        return 'Entry has an “Article” type';
    }

    public static function show(string $eventClass): bool
    {
        // Only show this filter for events with an `element` property
        return property_exists($eventClass, 'element');
    }

    public static function check(object $event, bool $value): bool
    {
        // Filter based on whether the entry’s type is 'article'
        if (! $event->element instanceof Entry) {
            return true;
        }

        return ($event->element->getType()->handle === 'article') === $value;
    }
}
```

#### Debouncing Webhooks

You can prevent multiple similar webhooks from being sent by setting a “Debounce Key Format” on your webhook. This is a Twig template that defines a “debounce key” for the webhook. If two webhooks generate the same debounce key, only the second one will actually be sent.  

An `event` variable will be available to it that references the event that was triggered.

For example, if your webhook is for an entry (`CraftCms\Cms\Entry\Elements\Entry`), then you could set the Debounce Key Format to `{{ event.sender.id }}` to prevent multiple webhook requests from being queued up at the same time.

#### Sending Custom Headers

You can send custom headers along with webhook requests using the Custom Headers setting.

<img src="./images/custom-headers.png" width="952" height="215" alt="Screenshot of the Custom Headers setting">

Header values can be set to an environment variable using the `$VARIABLE_NAME` syntax, or a Twig template.

An `event` variable will be available to the Twig template, set to the event that triggered the webhook.

You can have multiple headers that have the same name, and if a header value takes up multiple lines (after any empty lines have been discarded), each line will be sent as its own header, all using the same header name.

#### Sending More Data

If you need more data than what’s in the default POST request payload, you can fill in the “Extra User Attributes”, “Extra Sender Attributes”, and “Extra Event Attributes” fields.

The attributes listed here (separated by newlines) will be included when the user, sender or event property is converted to an array (for elements and other Craft components, they’re passed to `toArray()` as extra fields).

For “Extra Event Attributes”, each attribute should be prefixed with the name of the property and a dot (e.g. `element.author` will include the `author` attribute of an `$element` property).

#### Sending Custom Payloads

You can completely customize the webhook payload by ticking the “Send a custom payload” checkbox. That will reveal the “Payload Template” field, where you can enter the desired body contents.

That field supports Twig, so you can make this dynamic. An `event` variable will be available to it that references the event that was triggered.

```twig
{% set entry = event.sender %}
{{
  {
    time: 'now'|date('c'),
    user: currentUser.username ?? null,
    name: event.name,
    entry: {
      id: entry.id,
      title: entry.title,
      slug: entry.slug,
      isNew: event.isNew
    }
  }|json_encode|raw
}}
```

If the output is valid JSON, then webhook requests will be sent with an `application/json` content type.

Craft renders payload templates in the Twig sandbox, which only allows some tags, filters and functions. The sandbox doesn’t allow the `json_encode` filter by default, so allow it in `config/craft/twig-sandbox.php` to use templates like this one:

```php
return [
    'allowedFilters' => ['json_encode'],
];
```

### Toggling Webhooks

Webhooks can be enabled or disabled from both the Webhooks index page and within their Edit Webhook pages.

Only enabled webhooks will send webhook requests when their corresponding events are triggered.

You can disable *all* webhooks by setting `disableAllWebhooks` to `true` in your `config/craft/webhooks.php` file.

```php
return [
    'disableAllWebhooks' => true,
    // ...
];
```

## Integrating with Task Automation Tools

### Netlify

To trigger a [Netlify](https://www.netlify.com) build using Webhooks, follow these steps:

1. Within your Netlify dashboard, go to **Settings** → **Build & deploy** → **Continuous deployment** → **Build hooks**.
2. Select **Add build hook**.
3. Fill out the build hook settings and save it.
4. Copy the build hook URL.
5. Within your Craft control panel, go to **Webhooks** → **New webhook**.
6. Paste the build hook URL into the **Webhook URL** field, fill out the remaining settings, and save the webhook.

### Zapier

To integrate Webhooks with [Zapier](https://zapier.com), follow these steps:

1. Within your [Zapier dashboard](https://zapier.com/app/dashboard), click **Make a Zap!**.
2. Select **Webhooks** under **Built-in Apps**.
3. Select **Catch Hook** and click **Save + Continue**.
4. Click **Continue** without entering anything in the **Pick off a Child Key** field.
5. Copy the webhook URL.
6. Within your Craft control panel, go to to **Webhooks** → **New webhook**. 
7. Paste the webhook URL into the **Webhook URL** field, fill out the remaining settings, and save the webhook.
8. Perform an action in Craft that will trigger your webhook.
9. Back in Zapier, click the **Ok, I did this** button.
10. Ensure that Zapier pulled in the webhook, and click **Continue**.
11. Finish setting up the zap and make sure it’s enabled.

### IFTTT

To integrate Webhooks with [IFTTT](https://ifttt.com), follow these steps:

1. Within your [My Applets](https://ifttt.com/my_applets) page in IFTTT, click **New Applet**.
2. Click on **+this**.
3. Search for “webhooks” and select **Webhooks** below.
4. Click **Connect**.
5. Click on the **Receive a web request** box.
6. Give your trigger an event name based on your Craft webhook name, but in `snake_case`.
7. Finish setting up the applet.
8. Go to your [Webhooks page](https://ifttt.com/maker_webhooks) on IFTTT, and click the **Documentation** link.
9. Type the event name you entered in step 6 into the `{event}` text box.
10. Copy the URL beginning with `https://maker.ifttt.com/trigger/`.
6. Within your Craft control panel, go to to **Webhooks** → **New webhook**. 
12. Paste the webhook URL into the **Webhook URL** field, fill out the remaining settings, and save the webhook.

**Note:** Unfortunately IFTTT’s webhooks API is pretty limited, so no webhook data will be available to your applet action.
