<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\NewsItem;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index()
    {
        $news = NewsItem::query()
            ->when(request('published'), fn ($q) => $q->where('published', request('published')))
            ->latest()
            ->paginate(20);

        return NewsResource::collection($news);
    }

    public function show($id)
    {
        $item = NewsItem::findOrFail($id);

        return new NewsResource($item);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $item = NewsItem::create($data);

        return (new NewsResource($item))->response()->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $item = NewsItem::findOrFail($id);
        $data = $this->validatePayload($request, $item);

        $item->update($data);

        return new NewsResource($item);
    }

    public function destroy($id)
    {
        $item = NewsItem::findOrFail($id);
        $item->delete();

        return response()->noContent();
    }

    protected function validatePayload(Request $request, ?NewsItem $item = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1024'],
            'content' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:1024'],
            'published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data = array_merge($data, [
            'image_url' => $data['imageUrl'] ?? $request->input('image_url'),
        ]);

        unset($data['imageUrl']);

        if (($data['published'] ?? false) && ! ($item?->published_at)) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        return $data;
    }
}
