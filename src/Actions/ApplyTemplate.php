<?php

namespace Komalnakrani\ClientPortal\Actions;

use Komalnakrani\ClientPortal\Portals;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Syncs phases and modules from a template onto portals, keeping each module's progress.
 */
class ApplyTemplate extends Action
{
    public $icon = 'copy-paste';

    public static function title()
    {
        return __('Apply template');
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry
            && $item->collectionHandle() === Portals::COLLECTION
            && ! $item->get('is_template');
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    public function confirmationText()
    {
        return __('Replace the phases of this portal with the template’s? Progress on modules that exist in both is kept.|Replace the phases of these :count portals with the template’s? Progress on modules that exist in both is kept.');
    }

    public function run($items, $values)
    {
        $template = EntryFacade::find(collect($values['template'])->first());

        if (! $template || ! $template->get('is_template')) {
            throw new \InvalidArgumentException(__('Choose a portal marked as a template.'));
        }

        $items->each(fn (Entry $portal) => Portals::applyTemplate($template, $portal));

        return trans_choice('Template applied to :count portal|Template applied to :count portals', $items->count());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function fieldItems()
    {
        return [
            'template' => [
                'type' => 'entries',
                'display' => __('Template'),
                'collections' => [Portals::COLLECTION],
                'max_items' => 1,
                'mode' => 'select',
                'validate' => 'required',
            ],
        ];
    }
}
