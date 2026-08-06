<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Services\DynamicFieldService;
use Illuminate\Http\JsonResponse;

class DynamicFieldController extends Controller
{
    public function forCategory(AssetCategory $category, DynamicFieldService $fields): JsonResponse
    {
        return response()->json($fields->resolveForCategory($category->id)->values());
    }
}
