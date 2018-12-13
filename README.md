# Webhooks for Craft CMS

This plugin adds the ability to manage “webhooks” in Craft CMS, which will create POST requests when certain events occur.

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

Webhooks can optionally be organized into groups. You can create a new group by clicking the “New group” button in the sidebar.

### Creating Webhooks

To create a new webhook, click the “New webhook” button.

Webhooks listen to [events](https://www.yiiframework.com/doc/guide/2.0/en/concept-events) triggered by system classes. So you must determine the name of the class that will be triggering the event (the “Sender Class”), as well as the event name (either an `EVENT_*` constant name, or its value).

The Sender Class can be a subclass of the class that triggers the event. For example, all elements fire an [afterSave](https://docs.craftcms.com/api/v3/craft-base-element.html#event-after-save) event after they’ve been saved, courtesy of their base class, [craft\base\Element](https://docs.craftcms.com/api/v3/craft-base-element.html). However if you’re only interested in sending a webhook when an _entry_ gets saved, you can set the Sender Class to [craft\elements\Entry](https://docs.craftcms.com/api/v3/craft-elements-entry.html).

See [Integrating with Task Automation Tools](#integrating-with-task-automation-tools) for examples on how to get a Webhook URL from various taks automation tools.

By default, webhooks will send a POST request to the Webhook URL, 

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
8. Go to your [Webhooks page] on IFTTT, and click the “Documentation” link.
9. Type the event name you entered in step 6 into the `{event}` text box.
10. Copy the URL beginning with `https://maker.ifttt.com/trigger/`.
11. Go to Webhooks in your Control Panel and click “New webhook”. 
12. Paste the webhook URL into the “Webhook URL” field, fill out the remaining fields, and save the webhook.

**Note:** Unfortunately IFTTT’s webhooks API is pretty limited, so no webhook data will be available to your applet action.
