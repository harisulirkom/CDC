<?php

namespace App\Http\Controllers;

use App\Http\Resources\JobResource;
use App\Models\JobPosting;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index()
    {
        $query = JobPosting::query()->latest();

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $jobs = $query->paginate(20);

        return JobResource::collection($jobs);
    }

    public function show($id)
    {
        $job = JobPosting::findOrFail($id);

        return new JobResource($job);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $job = JobPosting::create($data);

        return (new JobResource($job))->response()->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $job = JobPosting::findOrFail($id);
        $data = $this->validatePayload($request, $job);

        // jika publish langsung
        if (($data['status'] ?? null) === 'published' && ! $job->published_at) {
            $data['published_at'] = now();
        }

        $job->update($data);

        return new JobResource($job);
    }

    public function destroy($id)
    {
        $job = JobPosting::findOrFail($id);
        $job->delete();

        return response()->noContent();
    }

    public function publish($id)
    {
        $job = JobPosting::findOrFail($id);
        $job->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return new JobResource($job);
    }

    public function close($id)
    {
        $job = JobPosting::findOrFail($id);
        $job->update(['status' => 'closed']);

        return new JobResource($job);
    }

    protected function validatePayload(Request $request, ?JobPosting $job = null): array
    {
        $deadlineRule = ['nullable', 'date'];
        if ($request->filled('deadline') && strlen($request->input('deadline')) === 10) {
            $deadlineRule[] = 'date_format:Y-m-d';
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'company_profile' => ['nullable', 'string', 'max:1024'],
            'location' => ['nullable', 'string', 'max:255'],
            'work_mode' => ['nullable', 'string', 'max:100'],
            'job_type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'deadline' => $deadlineRule,
            'status' => ['nullable', 'string', 'in:draft,published,closed'],
            'summary' => ['nullable', 'string'],
            'responsibilities' => ['nullable', 'array'],
            'qualifications' => ['nullable', 'array'],
            'compensation' => ['nullable', 'string', 'max:512'],
            'benefits' => ['nullable', 'array'],
            'apply' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'published' && ! ($job?->published_at)) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
