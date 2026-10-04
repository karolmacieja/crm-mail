<?php

namespace App\Http\Controllers\Api\Settings;

use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactAccessRequest;
use App\Models\ContactCategory;
use App\Models\ContactShare;
use App\Models\ContactStatus;
use App\Models\CustomFieldTemplate;
use App\Models\Task;
use App\Models\TaskCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRM settings: the restaurant's editable dictionaries.
 *
 *   /settings/categories       contact categories (B2B, VIP, ...)
 *   /settings/statuses         contact statuses (Lead, Klient, ...)
 *   /settings/task-categories  task board columns (Follow-up, Oferty, ...)
 *   /settings/field-templates  suggested custom fields (Alergie, NIP, ...)
 *
 * Keys of statuses and task categories are immutable once created: contacts
 * and tasks store them. Deleting one that is in use requires `move_to`.
 * Everything is scoped to the user's group by GroupScope.
 */
class DictionaryController extends Controller
{
    public const COLORS = ['gray', 'red', 'orange', 'amber', 'yellow', 'green', 'emerald', 'sky', 'blue', 'indigo', 'purple', 'pink'];

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    public function store(Request $request, string $dictionary): JsonResponse
    {
        $class = $this->modelClass($dictionary);
        $data = $request->validate($this->rules($dictionary, creating: true));

        $item = DB::transaction(function () use ($class, $data) {
            $item = $class::create($data);
            if ($item instanceof ContactStatus && $item->is_default) {
                $this->makeOnlyDefault($item);
            }

            return $item;
        });

        return response()->json(['data' => $this->present($dictionary, $item->fresh())], 201);
    }

    public function update(Request $request, string $dictionary, int $id): JsonResponse
    {
        $item = $this->modelClass($dictionary)::findOrFail($id);
        $data = $request->validate($this->rules($dictionary, creating: false, ignoreId: $item->id));

        DB::transaction(function () use ($item, $data) {
            if ($item instanceof ContactCategory && array_key_exists('is_private', $data) && (bool) $data['is_private'] !== $item->is_private) {
                $this->switchPrivacy($item, (bool) $data['is_private']);
            }
            $item->update($data);
            if ($item instanceof ContactStatus && ($data['is_default'] ?? false)) {
                $this->makeOnlyDefault($item);
            }
        });

        return response()->json(['data' => $this->present($dictionary, $item->fresh())]);
    }

    public function destroy(Request $request, string $dictionary, int $id): JsonResponse
    {
        $class = $this->modelClass($dictionary);
        $item = $class::findOrFail($id);

        DB::transaction(function () use ($request, $dictionary, $item, $class) {
            if ($item instanceof ContactStatus || $item instanceof TaskCategory) {
                $this->reassignBeforeDelete($request, $dictionary, $item, $class);
            }
            if ($item instanceof ContactCategory && $item->is_private && $item->contacts()->exists()) {
                throw ValidationException::withMessages(['id' => [__('crm.personal.category_in_use')]]);
            }

            $item->delete(); // contact categories: contacts.category_id → NULL (FK)

            if ($item instanceof ContactStatus && $item->is_default) {
                ContactStatus::query()->ordered()->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(null, 204);
    }

    /** POST /settings/{dictionary}/reorder  { ids: [3, 1, 2] } */
    public function reorder(Request $request, string $dictionary): JsonResponse
    {
        $class = $this->modelClass($dictionary);
        $ids = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']])['ids'];

        DB::transaction(function () use ($class, $ids) {
            foreach (array_values($ids) as $position => $id) {
                $class::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['data' => $this->payload()[$this->payloadKey($dictionary)]]);
    }

    // ------------------------------------------------------------------

    /** @return class-string<Model> */
    private function modelClass(string $dictionary): string
    {
        return match ($dictionary) {
            'categories' => ContactCategory::class,
            'statuses' => ContactStatus::class,
            'task-categories' => TaskCategory::class,
            'field-templates' => CustomFieldTemplate::class,
            default => abort(404),
        };
    }

    private function payloadKey(string $dictionary): string
    {
        return str_replace('-', '_', $dictionary);
    }

    /** @return array<string, mixed> */
    private function rules(string $dictionary, bool $creating, ?int $ignoreId = null): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $groupId = request()->user()->group_id;
        $uniqueName = fn (string $table, string $column) => Rule::unique($table, $column)->where('group_id', $groupId)->ignore($ignoreId);

        return match ($dictionary) {
            'categories' => [
                'name' => [$required, 'string', 'max:60', $uniqueName('contact_categories', 'name')],
                'color' => ['sometimes', Rule::in(self::COLORS)],
                'icon' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
                'is_private' => ['sometimes', 'boolean'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ],
            'statuses' => [
                'name' => [$required, 'string', 'max:60', $uniqueName('contact_statuses', 'name')],
                'color' => ['sometimes', Rule::in(self::COLORS)],
                'is_default' => ['sometimes', 'boolean'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ],
            'task-categories' => [
                'name' => [$required, 'string', 'max:60', $uniqueName('task_categories', 'name')],
                'color' => ['sometimes', Rule::in(self::COLORS)],
                'icon' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ],
            'field-templates' => [
                'label' => [$required, 'string', 'max:100', $uniqueName('custom_field_templates', 'label')],
                'type' => ['sometimes', Rule::enum(CustomFieldType::class)],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ],
        };
    }

    /**
     * Statuses / task categories in use must hand their records to another entry.
     *
     * @param  class-string<Model>  $class
     */
    private function reassignBeforeDelete(Request $request, string $dictionary, Model $item, string $class): void
    {
        if ($class::query()->count() <= 1) {
            throw ValidationException::withMessages(['id' => [__('crm.settings.last_item')]]);
        }

        /** @var Builder $records */
        $records = $item instanceof ContactStatus
            ? Contact::query()->where('status', $item->key)
            : Task::query()->where('type', $item->key);
        $column = $item instanceof ContactStatus ? 'status' : 'type';
        $inUse = $records->count();

        if ($inUse === 0) {
            return;
        }

        $moveTo = $request->query('move_to');
        if (! is_string($moveTo) || $moveTo === $item->key || ! $class::query()->where('key', $moveTo)->exists()) {
            throw ValidationException::withMessages([
                'move_to' => [__('crm.settings.in_use', ['count' => $inUse])],
            ]);
        }

        $records->update([$column => $moveTo]);
    }

    /**
     * Private category on: its contacts become their owners' personal cards
     * (sharing removed). Off: only possible while nobody else keeps a card of
     * the same e-mail – shared cards are one per e-mail.
     */
    private function switchPrivacy(ContactCategory $category, bool $private): void
    {
        $contacts = Contact::query()->where('category_id', $category->id);

        if ($private) {
            $ids = (clone $contacts)->whereNotNull('user_id')->pluck('id');
            Contact::query()->whereKey($ids)->update(['personal_key' => DB::raw('user_id')]);
            ContactShare::query()->whereIn('contact_id', $ids)->delete();
            ContactAccessRequest::query()->whereIn('contact_id', $ids)->where('status', ContactAccessRequest::PENDING)
                ->update(['status' => ContactAccessRequest::DECLINED, 'decided_at' => now()]);

            return;
        }

        $duplicates = Contact::query()->whereIn('email', (clone $contacts)->select('email'))
            ->select('email')->groupBy('email')->havingRaw('COUNT(*) > 1')->get()->count();
        if ($duplicates > 0) {
            throw ValidationException::withMessages(['is_private' => [__('crm.personal.cannot_unprivate', ['count' => $duplicates])]]);
        }

        $contacts->update(['personal_key' => 0]);
    }

    private function makeOnlyDefault(ContactStatus $status): void
    {
        ContactStatus::query()->whereKeyNot($status->id)->update(['is_default' => false]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $statusCounts = Contact::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $taskCounts = Task::query()->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');

        return [
            'categories' => ContactCategory::query()->withCount('contacts')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($c) => $this->present('categories', $c)),
            'statuses' => ContactStatus::query()->ordered()->get()
                ->map(fn ($s) => $this->present('statuses', $s) + ['usage_count' => (int) ($statusCounts[$s->key] ?? 0)]),
            'task_categories' => TaskCategory::query()->ordered()->get()
                ->map(fn ($c) => $this->present('task-categories', $c) + ['usage_count' => (int) ($taskCounts[$c->key] ?? 0)]),
            'field_templates' => CustomFieldTemplate::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($f) => $this->present('field-templates', $f)),
            'colors' => self::COLORS,
        ];
    }

    /** @return array<string, mixed> */
    private function present(string $dictionary, Model $item): array
    {
        return match ($dictionary) {
            'categories' => [
                'id' => $item->id, 'name' => $item->name, 'slug' => $item->slug, 'color' => $item->color,
                'icon' => $item->icon, 'is_private' => (bool) $item->is_private, 'sort_order' => $item->sort_order,
                'usage_count' => $item->contacts_count ?? $item->contacts()->count(),
            ],
            'statuses' => [
                'id' => $item->id, 'key' => $item->key, 'name' => $item->name, 'color' => $item->color,
                'is_default' => $item->is_default, 'sort_order' => $item->sort_order,
            ],
            'task-categories' => [
                'id' => $item->id, 'key' => $item->key, 'name' => $item->name, 'color' => $item->color,
                'icon' => $item->icon, 'sort_order' => $item->sort_order,
            ],
            'field-templates' => [
                'id' => $item->id, 'label' => $item->label, 'type' => $item->type->value, 'sort_order' => $item->sort_order,
            ],
        };
    }
}
