<?php

namespace App\Http\Controllers;

use App\Http\Resources\PopupBannerResource;
use App\Models\PopupBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PopupBannerController extends Controller
{
    public function active()
    {
        $banner = PopupBanner::query()
            ->currentlyActive()
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->first();

        if (!$banner) {
            return response()->json(['data' => null]);
        }

        return new PopupBannerResource($banner);
    }

    public function image(PopupBanner $popupBanner)
    {
        abort_unless($popupBanner->image_path, 404);
        abort_unless(Storage::disk('public')->exists($popupBanner->image_path), 404);

        return response()->file(Storage::disk('public')->path($popupBanner->image_path), [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function index()
    {
        $banners = PopupBanner::query()
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return PopupBannerResource::collection($banners);
    }

    public function store(Request $request)
    {
        $data = $this->validatedPayload($request);

        $banner = DB::transaction(function () use ($request, $data) {
            if (!empty($data['is_active'])) {
                PopupBanner::query()->update(['is_active' => false]);
            }

            $data['created_by'] = $request->user()?->id;
            $data['updated_by'] = $request->user()?->id;
            $data['image_path'] = $this->storeImage($request);

            return PopupBanner::create($data);
        });

        return (new PopupBannerResource($banner))->response()->setStatusCode(201);
    }

    public function show(PopupBanner $popupBanner)
    {
        return new PopupBannerResource($popupBanner);
    }

    public function update(Request $request, PopupBanner $popupBanner)
    {
        $data = $this->validatedPayload($request, $popupBanner);

        DB::transaction(function () use ($request, $popupBanner, $data) {
            if (!empty($data['is_active'])) {
                PopupBanner::query()
                    ->whereKeyNot($popupBanner->id)
                    ->update(['is_active' => false]);
            }

            if ($request->boolean('remove_image') && $popupBanner->image_path) {
                Storage::disk('public')->delete($popupBanner->image_path);
                $data['image_path'] = null;
            }

            $newImagePath = $this->storeImage($request);
            if ($newImagePath) {
                if ($popupBanner->image_path) {
                    Storage::disk('public')->delete($popupBanner->image_path);
                }
                $data['image_path'] = $newImagePath;
            }

            $data['updated_by'] = $request->user()?->id;
            $popupBanner->update($data);
        });

        return new PopupBannerResource($popupBanner->refresh());
    }

    public function destroy(PopupBanner $popupBanner)
    {
        if ($popupBanner->image_path) {
            Storage::disk('public')->delete($popupBanner->image_path);
        }

        $popupBanner->delete();

        return response()->noContent();
    }

    protected function validatedPayload(Request $request, ?PopupBanner $banner = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => [$banner ? 'nullable' : 'required_without:image_url', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_url' => [$banner ? 'nullable' : 'required_without:image', 'url', 'max:1024'],
            'link_url' => ['nullable', 'url', 'max:1024'],
            'button_label' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'remove_image' => ['nullable', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
        ]);

        unset($data['image'], $data['remove_image']);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    protected function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('popup-banners', 'public');
    }
}
