<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Support\EmailTemplateDefaults;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    private const ALLOWED_KEYS = ['alumni-blast'];

    public function show(string $key)
    {
        $key = $this->resolveKey($key);
        $defaults = $this->defaultsForKey($key);

        $template = EmailTemplate::firstOrCreate(
            ['key' => $key],
            ['subject' => $defaults['subject'], 'body' => $defaults['body']],
        );

        return response()->json([
            'key' => $template->key,
            'subject' => $template->subject,
            'body' => $template->body,
        ]);
    }

    public function update(Request $request, string $key)
    {
        $key = $this->resolveKey($key);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $defaults = $this->defaultsForKey($key);
        $template = EmailTemplate::firstOrCreate(
            ['key' => $key],
            ['subject' => $defaults['subject'], 'body' => $defaults['body']],
        );

        $template->subject = $data['subject'];
        $template->body = $data['body'];
        $template->save();

        return response()->json([
            'key' => $template->key,
            'subject' => $template->subject,
            'body' => $template->body,
        ]);
    }

    private function resolveKey(string $key): string
    {
        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            abort(404);
        }

        return $key;
    }

    private function defaultsForKey(string $key): array
    {
        if ($key === 'alumni-blast') {
            return EmailTemplateDefaults::alumniBlast();
        }

        return [
            'subject' => 'Template Email',
            'body' => 'Halo {nama},',
        ];
    }
}
