<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Webhooks\Models\Group;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Creates, renames and deletes webhook groups.
 *
 * @since 4.0.0
 */
readonly class GroupsController
{
    use RespondsWithFlash;

    public function create(): CpScreenResponse
    {
        return $this->editScreen(new Group);
    }

    public function edit(int $groupId): CpScreenResponse
    {
        return $this->editScreen(Group::query()->findOrFail($groupId));
    }

    public function store(Request $request): Response
    {
        return $this->save($request, new Group);
    }

    public function update(Request $request, int $groupId): Response
    {
        return $this->save($request, Group::query()->findOrFail($groupId));
    }

    /**
     * Deletes a group. Its webhooks become ungrouped.
     */
    public function destroy(int $groupId): Response
    {
        Group::query()->whereKey($groupId)->delete();

        return $this->asSuccess(t('Group deleted.', category: 'webhooks'));
    }

    private function save(Request $request, Group $group): Response
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $isNew = ! $group->exists;
        $group->fill(['name' => trim($data['name'])])->save();

        return $this->asModelSuccess(
            $group,
            $isNew ? t('Group created.', category: 'webhooks') : t('Group renamed.', category: 'webhooks'),
            'group',
            redirect: route('webhooks.index'),
        );
    }

    private function editScreen(Group $group): CpScreenResponse
    {
        return new CpScreenResponse()
            ->title($group->exists ? $group->name : t('New group', category: 'webhooks'))
            ->selectedSubnavItem('manage')
            ->addCrumb(t('Webhooks', category: 'webhooks'), route('webhooks.index'))
            ->redirectUrl(route('webhooks.index'))
            ->ui(
                Ui::make([
                    Field::make(t('Name', category: 'webhooks'), Text::make('name')->autofocus())->required(),
                ]),
                new UiContext(values: ['name' => $group->name]),
                ['submit' => [
                    'method' => 'post',
                    'url' => $group->exists
                        ? route('webhooks.groups.update', ['groupId' => $group->id])
                        : route('webhooks.groups.store'),
                ]],
            );
    }
}
