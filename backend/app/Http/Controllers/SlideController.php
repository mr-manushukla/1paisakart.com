<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Homepage hero slides. Stored as one JSON row in `settings` — a handful of
 * banners doesn't earn its own table.
 *
 * index() is public; the rest are admin-only via the route group.
 */
class SlideController extends Controller
{
    private const KEY = 'hero_slides';

    /** Saved slides, or the config fallback if an admin has never saved. */
    public static function slides(): array
    {
        $saved = json_decode((string) Setting::get(self::KEY), true);

        // An explicit empty array means "no slider" — only a missing row falls back.
        return is_array($saved) ? $saved : config('slides.defaults');
    }

    public function index(): array
    {
        return ['data' => static::slides(), 'gradients' => config('slides.gradients')];
    }

    public function update(Request $request): array
    {
        $data = $request->validate([
            'slides' => ['present', 'array', 'max:8'],
            'slides.*.eyebrow' => ['nullable', 'string', 'max:60'],
            'slides.*.heading' => ['required', 'string', 'max:120'],
            'slides.*.text' => ['nullable', 'string', 'max:300'],
            'slides.*.cta_label' => ['nullable', 'string', 'max:40'],
            // Internal paths only — the CTA is a <RouterLink>, and this keeps the
            // homepage from becoming an open redirect.
            'slides.*.cta_to' => ['nullable', 'string', 'max:200', 'regex:#^/#'],
            'slides.*.gradient' => ['required', Rule::in(config('slides.gradients'))],
            'slides.*.image' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#'],
        ], [
            'slides.*.cta_to.regex' => 'The link must be a path on this site, starting with "/".',
            'slides.*.image.regex' => 'The image must be an uploaded file or an http(s) URL.',
        ]);

        Setting::put(self::KEY, json_encode(array_values($data['slides'])));

        return ['message' => 'Slides saved.', 'data' => static::slides()];
    }

    /** Upload one slide image and hand back its public URL. */
    public function uploadImage(Request $request): array
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'], // 4 MB
        ]);

        $file = $request->file('image');
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        Storage::disk('uploads')->putFileAs('slides', $file, $name);

        return ['url' => rtrim(config('filesystems.disks.uploads.url'), '/')."/slides/{$name}"];
    }
}
