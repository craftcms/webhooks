<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Table as TableControl;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Callout;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Group;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\Ui\UiPayload;
use CraftCms\Cms\Ui\UiResolver;
use CraftCms\Webhooks\EventSuggestions;
use CraftCms\Webhooks\Filters\ExclusiveFilterInterface;
use CraftCms\Webhooks\Filters\FilterInterface;
use CraftCms\Webhooks\Http\Requests\SaveWebhookRequest;
use CraftCms\Webhooks\Models\Group as GroupModel;
use CraftCms\Webhooks\Models\Webhook;
use CraftCms\Webhooks\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Lists, edits and deletes webhooks.
 *
 * @since 4.0.0
 */
readonly class WebhooksController
{
    use RespondsWithFlash;

    public function __construct(
        private Webhooks $webhooks,
        private EventSuggestions $suggestions,
        private UiResolver $uiResolver,
    ) {}

    public function index(): CpScreenResponse
    {
        $webhooks = Webhook::query()->with('group')->orderBy('name')->get();
        $groups = GroupModel::query()->withCount('webhooks')->orderBy('name')->get();

        $webhooksTable = Table::make('webhooks')
            ->columns([
                ['key' => 'name', 'label' => t('Webhook', category: 'webhooks')],
                ['key' => 'event', 'label' => t('Event', category: 'webhooks')],
                ['key' => 'group', 'label' => t('Group', category: 'webhooks')],
            ])
            ->rows($webhooks->map(fn (Webhook $webhook) => [
                'id' => $webhook->id,
                'name' => ['label' => $webhook->name, 'url' => route('webhooks.edit', ['webhookId' => $webhook->id])],
                'event' => class_basename($webhook->event),
                'group' => $webhook->group->name ?? t('Ungrouped', category: 'webhooks'),
                '_status' => $webhook->enabled,
                '_deleteUrl' => route('webhooks.destroy', ['webhookId' => $webhook->id]),
                '_deleteConfirmMessage' => t('Are you sure you want to delete the webhook “{name}”?', ['name' => $webhook->name], 'webhooks'),
            ]))
            ->emptyMessage(t('No webhooks exist yet.', category: 'webhooks'))
            ->createAction(t('New webhook', category: 'webhooks'), route('webhooks.create'))
            ->createActionInPageHeader()
            ->searchable()
            ->statusFilter()
            ->statusActions([
                ['label' => t('Enabled', category: 'webhooks'), 'url' => route('webhooks.status'), 'params' => ['enabled' => true], 'fill' => 'green'],
                ['label' => t('Disabled', category: 'webhooks'), 'url' => route('webhooks.status'), 'params' => ['enabled' => false]],
            ])
            ->deletable();

        $groupsTable = Table::make('webhook-groups')
            ->columns([
                ['key' => 'name', 'label' => t('Group', category: 'webhooks')],
                ['key' => 'webhooks', 'label' => t('Webhooks', category: 'webhooks')],
            ])
            ->rows($groups->map(fn (GroupModel $group) => [
                'id' => $group->id,
                'name' => ['label' => $group->name, 'url' => route('webhooks.groups.edit', ['groupId' => $group->id])],
                'webhooks' => $group->webhooks_count,
                '_deleteUrl' => route('webhooks.groups.destroy', ['groupId' => $group->id]),
                '_deleteConfirmMessage' => t('Are you sure you want to delete this group?', category: 'webhooks'),
            ]))
            ->emptyMessage(t('No groups exist yet.', category: 'webhooks'))
            ->createAction(t('New group', category: 'webhooks'), route('webhooks.groups.create'))
            ->deletable();

        return new CpScreenResponse()
            ->title(t('Manage Webhooks', category: 'webhooks'))
            ->selectedSubnavItem('manage')
            ->ui(Ui::make([$webhooksTable, $groupsTable]));
    }

    public function create(Request $request): CpScreenResponse
    {
        return $this->editScreen(new Webhook([
            'groupId' => $request->integer('groupId') ?: null,
        ]));
    }

    public function edit(int $webhookId): CpScreenResponse
    {
        return $this->editScreen(Webhook::query()->findOrFail($webhookId));
    }

    public function store(SaveWebhookRequest $request): Response
    {
        return $this->save($request, new Webhook);
    }

    public function update(SaveWebhookRequest $request, int $webhookId): Response
    {
        return $this->save($request, Webhook::query()->findOrFail($webhookId));
    }

    public function destroy(Request $request, ?int $webhookId = null): Response
    {
        Webhook::query()->whereKey($webhookId ?? $request->input('id'))->delete();

        return $this->asSuccess(t('Webhook deleted.', category: 'webhooks'));
    }

    /**
     * Enables or disables the selected webhooks.
     */
    public function setStatus(Request $request): Response
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'enabled' => ['required', 'boolean'],
        ]);

        Webhook::query()->whereKey($data['ids'])->get()->each->update(['enabled' => (bool) $data['enabled']]);

        return $this->asSuccess($data['enabled']
            ? t('Webhook enabled.', category: 'webhooks')
            : t('Webhook disabled.', category: 'webhooks'));
    }

    /**
     * Rebuilds the edit UI for the submitted values, so the filters follow the event and the payload fields follow
     * the method and “Send a custom payload”.
     */
    public function renderUi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array'],
        ]);

        return new JsonResponse([
            'ui' => $this->ui($data['values']),
        ]);
    }

    private function save(SaveWebhookRequest $request, Webhook $webhook): Response
    {
        $webhook->fill($request->webhookAttributes())->save();

        return $this->asModelSuccess(
            $webhook,
            t('Webhook saved.', category: 'webhooks'),
            'webhook',
            redirect: $this->getPostedRedirectUrl($webhook) ?? route('webhooks.index'),
        );
    }

    private function editScreen(Webhook $webhook): CpScreenResponse
    {
        $title = $webhook->exists
            ? ($webhook->name ?: t('Edit Webhook', category: 'webhooks'))
            : t('Create a new webhook', category: 'webhooks');

        $response = new CpScreenResponse()
            ->title($title)
            ->selectedSubnavItem('manage')
            ->addCrumb(t('Webhooks', category: 'webhooks'), route('webhooks.index'))
            ->redirectUrl(route('webhooks.index'));

        if ($webhook->exists) {
            $response->addAltAction(t('Delete', category: 'webhooks'), [
                'variant' => 'danger',
                'action' => [
                    'type' => 'http',
                    'method' => 'DELETE',
                    'url' => route('webhooks.destroy', ['webhookId' => $webhook->id]),
                    'body' => [
                        'redirect' => Crypt::encrypt(route('webhooks.index')),
                    ],
                    'confirm' => t('Are you sure you want to delete the webhook “{name}”?', ['name' => $webhook->name], 'webhooks'),
                ],
            ]);
        }

        return $response
            ->ui($this->ui($this->values($webhook)), props: [
                'submit' => [
                    'method' => 'post',
                    'url' => $webhook->exists
                        ? route('webhooks.update', ['webhookId' => $webhook->id])
                        : route('webhooks.store'),
                ],
                'refreshUrl' => route('webhooks.render-ui'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Webhook $webhook): array
    {
        $filters = [];

        foreach ($this->webhooks->filterTypes() as $class) {
            $filters[$class] = match ($webhook->filters[$class] ?? null) {
                true => 'yes',
                false => 'no',
                default => '',
            };
        }

        return [
            'name' => $webhook->name,
            'groupId' => $webhook->groupId ? (string) $webhook->groupId : '',
            'enabled' => $webhook->enabled,
            'event' => $webhook->event,
            'class' => $webhook->class,
            'filters' => $filters,
            'debounceKeyFormat' => $webhook->debounceKeyFormat,
            'method' => $webhook->method,
            'url' => $webhook->url,
            'headers' => $webhook->headers ?? [],
            'customPayload' => $webhook->payloadTemplate !== null,
            'userAttributes' => $webhook->userAttributes,
            'senderAttributes' => $webhook->senderAttributes,
            'eventAttributes' => $webhook->eventAttributes,
            'payloadTemplate' => $webhook->payloadTemplate,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function ui(array $values): UiPayload
    {
        $eventClass = is_string($values['event'] ?? null) ? trim($values['event'], ' \\') : '';
        $hasBody = ($values['method'] ?? 'post') !== 'get';
        $customPayload = (bool) ($values['customPayload'] ?? false);
        $envOrTwig = t('Can be set to an environment variable (`$VARIABLE_NAME`) or Twig code.', category: 'webhooks');

        $groupOptions = [['label' => t('(Ungrouped)', category: 'webhooks'), 'value' => '']];

        foreach (GroupModel::query()->orderBy('name')->get() as $group) {
            $groupOptions[] = ['label' => $group->name, 'value' => (string) $group->id];
        }

        return $this->uiResolver->resolve(Ui::make([
            Field::make(t('Name', category: 'webhooks'), Text::make('name')->autofocus())
                ->instructions(t('What the webhook will be called within the Control Panel.', category: 'webhooks'))
                ->required(),
            Field::make(t('Group', category: 'webhooks'), Choice::make('groupId')->options($groupOptions)->withoutPlaceholder()),
            Field::make(t('Enabled', category: 'webhooks'), Lightswitch::make('enabled')),
            Field::make(
                t('Event', category: 'webhooks'),
                Combobox::make('event')
                    ->options(array_map(fn (array $event) => ['label' => $event['label'], 'value' => $event['value']], $this->suggestions->events()))
                    ->placeholder('CraftCms\\Cms\\Element\\Events\\ElementSaved')
                    ->clearable()
                    ->reactive(),
            )->instructions(t('The event class that triggers the webhook.', category: 'webhooks'))->required(),
            Field::make(
                t('Subject Class', category: 'webhooks'),
                Combobox::make('class')
                    ->options(array_map(fn (string $class) => ['label' => $class, 'value' => $class], $this->suggestions->subjectClasses()))
                    ->placeholder('CraftCms\\Cms\\Entry\\Elements\\Entry')
                    ->clearable(),
            )->instructions(t('If set, the event must carry an object of this class (e.g. the saved element), which is used as the payload’s `sender`.', category: 'webhooks')),
            $this->filtersNode($eventClass, $values['filters'] ?? []),
            Field::make(t('Debounce Key Format', category: 'webhooks'), Text::make('debounceKeyFormat')->monospace())
                ->instructions(t('Template that defines the webhook’s “debounce key” format. If two webhooks generate the same debounce key, only the latter will be sent.', category: 'webhooks').' '.$envOrTwig),
            Field::make(t('Request Method', category: 'webhooks'), Choice::make('method')->options([
                ['label' => 'GET', 'value' => 'get'],
                ['label' => 'POST', 'value' => 'post'],
                ['label' => 'PUT', 'value' => 'put'],
            ])->withoutPlaceholder()->reactive())->required(),
            Field::make(t('URL', category: 'webhooks'), Text::make('url')->placeholder('https://')->monospace())
                ->instructions(t('The URL that the webhook should send the request to.', category: 'webhooks').' '.$envOrTwig)
                ->required(),
            Field::make(t('Custom Headers', category: 'webhooks'), TableControl::make('headers')->columns([
                'name' => ['heading' => t('Name', category: 'webhooks'), 'type' => 'singleline', 'code' => true],
                'value' => ['heading' => t('Value', category: 'webhooks'), 'type' => 'multiline', 'code' => true],
            ])->allowAdd()->allowDelete()->allowReorder())
                ->instructions(t('Any custom headers that should be sent.', category: 'webhooks').' '.$envOrTwig),
            Group::make('payload', [
                Field::make(t('Send a custom payload', category: 'webhooks'), Lightswitch::make('customPayload')->reactive()),
                Field::make(t('Extra User Attributes', category: 'webhooks'), Textarea::make('userAttributes')->rows(2)->monospace())
                    ->instructions(t('List of additional attributes for the logged-in user that should be included in the webhook body.', category: 'webhooks').' '.t('(Place separate attributes on new lines.)', category: 'webhooks'))
                    ->visible(! $customPayload),
                Field::make(t('Extra Sender Attributes', category: 'webhooks'), Textarea::make('senderAttributes')->rows(2)->monospace())
                    ->instructions(t('List of additional attributes for the event’s subject that should be included in the webhook body.', category: 'webhooks').' '.t('(Place separate attributes on new lines.)', category: 'webhooks'))
                    ->visible(! $customPayload),
                Field::make(t('Extra Event Attributes', category: 'webhooks'), Textarea::make('eventAttributes')->rows(2)->monospace())
                    ->instructions(t('List of additional attributes for the event properties that should be included in the webhook body.', category: 'webhooks').' '.t('(Place separate attributes on new lines.)', category: 'webhooks'))
                    ->visible(! $customPayload),
                Field::make(t('Payload Template', category: 'webhooks'), Textarea::make('payloadTemplate')->rows(10)->monospace())
                    ->instructions(t('Enter a custom Twig template that outputs the payload body. An `event` variable will be present, set to the event that was triggered.', category: 'webhooks'))
                    ->visible($customPayload),
            ])->dependsOn(['method', 'customPayload'])->visible($hasBody),
        ]), new UiContext(
            values: $values,
            errors: session('errors')?->getMessages() ?? [],
            refreshable: true,
        ));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function filtersNode(string $eventClass, array $values): Group
    {
        $types = class_exists($eventClass)
            ? array_values(array_filter($this->webhooks->filterTypes(), fn (string $class) => $class::show($eventClass)))
            : [];

        // Filters excluded by an active filter can’t be used alongside it
        $excluded = [];

        foreach ($types as $class) {
            if (($values[$class] ?? '') !== '' && is_subclass_of($class, ExclusiveFilterInterface::class)) {
                array_push($excluded, ...$class::excludes());
            }
        }

        $children = [];

        foreach ($types as $class) {
            /** @var class-string<FilterInterface> $class */
            $children[] = Field::make(
                $class::displayName(),
                Choice::make(['filters', $class])
                    ->options([
                        ['label' => t('Any', category: 'webhooks'), 'value' => ''],
                        ['label' => t('Yes', category: 'webhooks'), 'value' => 'yes'],
                        ['label' => t('No', category: 'webhooks'), 'value' => 'no'],
                    ])
                    ->presentation(ChoicePresentation::Buttons)
                    ->withoutPlaceholder()
                    ->mode(in_array($class, $excluded, true) && ($values[$class] ?? '') === '' ? ControlMode::Disabled : ControlMode::Editable)
                    ->reactive(),
            );
        }

        if ($children === []) {
            $children[] = Callout::make('no-filters', t('No filters match this event.', category: 'webhooks'));
        }

        return Group::make('filters', [
            Heading::make('filters-heading', t('Event Filters', category: 'webhooks')),
            MarkdownContent::make('filters-instructions', t('Checks that can determine whether a webhook should be executed.', category: 'webhooks')),
            ...$children,
        ])->dependsOn(['event', 'filters']);
    }
}
