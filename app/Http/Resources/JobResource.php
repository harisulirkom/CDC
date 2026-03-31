<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company' => $this->company,
            'companyProfile' => $this->company_profile,
            'location' => $this->location,
            'workMode' => $this->work_mode,
            'jobType' => $this->job_type,
            'category' => $this->category,
            'deadline' => $this->deadline?->toDateString(),
            'status' => $this->status,
            'summary' => $this->summary,
            'responsibilities' => $this->responsibilities ?? [],
            'qualifications' => $this->qualifications ?? [],
            'compensation' => $this->compensation,
            'benefits' => $this->benefits ?? [],
            'apply' => $this->apply,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
