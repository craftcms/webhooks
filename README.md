<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Webhooks icon"></p>

<h1 align="center">Webhooks for Craft CMS</h1>

This plugin adds the ability to manage “webhooks” in Craft CMS, which will send GET or POST requests when certain events occur.

It can be used to integrate your Craft project with task automation tools like [Zapier](https://zapier.com) and [IFTTT](https://ifttt.com).

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Webhooks”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/webhooks

# tell Craft to install the plugin
./craft install/plugin webhooks
```

## Managing Webhooks

To manage your webhooks, go to Settings → Webhooks in your project’s Control Panel.

### Webhook Groups

Webhooks can optionally be organized into groups. You can create a new group by clicking the “New group” button in the sidebar.

If a group is deleted, any webhooks in it will become ungrouped. (They will **not** be deleted along with the group.)

### Creating Webhooks

To create a new webhook, click the “New webhook” button.

Webhooks listen to [events](https://www.yiiframework.com/doc/guide/2.0/en/concept-events) triggered by system classes. So you must determine the name of the class that will be triggering the event (the “Sender Class”), as well as the event name (either an `EVENT_*` constant name, or its value).

The Sender Class can be a subclass of the class that triggers the event. For example, all elements fire an [afterSave](https://docs.craftcms.com/api/v3/craft-base-element.html#event-after-save) event after they’ve been saved, courtesy of their base class, [craft\base\Element](https://docs.craftcms.com/api/v3/craft-base-element.html). However if you’re only interested in sending a webhook when an _entry_ gets saved, you can set the Sender Class to [craft\elements\Entry](https://docs.craftcms.com/api/v3/craft-elements-entry.html).

See [Integrating with Task Automation Tools](#integrating-with-task-automation-tools) for examples on how to get a Webhook URL from various task automation tools.

![Screenshot of the Edit Webhook page](./screenshot.png)

Webhooks can either send a GET request, or a POST request with a JSON body containing the following keys:

- `time` – an ISO-8601-formatted timestamp of the exact moment the event was triggered. (Webhooks are sent via the queue so there will be a slight delay between the time the event was triggered and the webhook was sent.)
- `user` – an object representing the logged-in user at the time the event was triggered.
- `name` – the name of the event.
- `senderClass` – the class name of the event sender.
- `sender` – an object representing the event sender.
- `eventClass` – the class name of the event.
- `event` – an object with keys representing any event class properties that aren’t declared by [yii\base\Event](https://www.yiiframework.com/doc/api/2.0/yii-base-event). (For example, if a [craft\events\ElementEvent](https://docs.craftcms.com/api/v3/craft-events-elementevent.html) is triggered, this will contain [element](https://docs.craftcms.com/api/v3/craft-events-elementevent.html#property-element) and [isNew](https://docs.craftcms.com/api/v3/craft-events-elementevent.html#property-isnew) keys.)

#### Sending More Data

If you need more data than what’s in the default POST request payload, you can fill in the “Extra User Attributes”, “Extra Sender Attributes”, and “Extra Event Attributes” fields.

The attributes listed here (separated by newlines) will be passed to the `$extraFields` argument of the user/sender/event-property’s [toArray()](https://www.yiiframework.com/doc/api/2.0/yii-base-arrayabletrait#toArray()-detail) method (if it has one).

For “Extra Event Attributes”, each attribute should be prefixed with the name of the property and a dot (e.g. `element.author` will include the `author` attribute of an `$element` property).

### Toggling Webhooks

Webhooks can be enabled or disabled from both the Webhooks index page and within their Edit Webhook pages.

Only enabled webhooks will send webhook requests when their corresponding events are triggered.

## Integrating with Task Automation Tools

### Zapier

To integrate Webhooks with [Zapier](https://zapier.com), follow these steps:

1. Create a new zap by clicking the “Make a Zap!” button on your [Zapier dashboard](https://zapier.com/app/dashboard).
2. Select “Webhooks” under “Built-in Apps”.
3. Select “Catch Hook” and click “Save + Continue”.
4. Click “Continue” without entering anything in the “Pick off a Child Key” field.
5. Copy the webhook URL.
6. Go to Webhooks in your Control Panel and click “New webhook”. 
7. Paste the webhook URL into the “Webhook URL” field, fill out the remaining fields, and save the webhook.
8. Perform an action in Craft that will trigger your webhook.
9. Back in Zapier, click the “Ok, I did this” button.
10. Ensure that Zapier pulled in the webhook, and click “Continue”.
11. Finish setting up the zap and make sure it’s enabled.

### IFTTT

To integrate Webhooks with [IFTTT](https://ifttt.com), follow these steps:

1. Create a new applet by clicking the “New Applet” button on your [My Applets](https://ifttt.com/my_applets) page.
2. Click on “+this”.
3. Search for “webhooks” and select “Webhooks” below.
4. Click “Connect”.
5. Click on the “Receive a web request” box.
6. Give your trigger an event name based on your Craft webhook name, but in `snake_case`.
7. Finish setting up the applet.
8. Go to your [Webhooks page](https://ifttt.com/maker_webhooks) on IFTTT, and click the “Documentation” link.
9. Type the event name you entered in step 6 into the `{event}` text box.
10. Copy the URL beginning with `https://maker.ifttt.com/trigger/`.
11. Go to Webhooks in your Control Panel and click “New webhook”. 
12. Paste the webhook URL into the “Webhook URL” field, fill out the remaining fields, and save the webhook.

**Note:** Unfortunately IFTTT’s webhooks API is pretty limited, so no webhook data will be available to your applet action.
