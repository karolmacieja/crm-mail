<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactCategoryResource;
use App\Models\ContactCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only for staff: categories drive filters and grouping in "Klienci".
 */
class ContactCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ContactCategoryResource::collection(
            ContactCategory::query()->withCount(['contacts' => fn ($q) => $q->visibleTo($request->user())])->orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
