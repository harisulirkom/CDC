<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use App\Models\Alumni;
use App\Models\Response;
use App\Models\Questionnaire;
use App\Models\Question;
use App\Policies\AlumniPolicy;
use App\Policies\ResponsePolicy;
use App\Policies\QuestionnairePolicy;
use App\Policies\QuestionPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Alumni::class, AlumniPolicy::class);
        Gate::policy(Response::class, ResponsePolicy::class);
        Gate::policy(Questionnaire::class, QuestionnairePolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);

        RateLimiter::for('login', function ($request) {
            $email = (string) $request->input('email', '');
            return Limit::perMinute(5)->by($request->ip() . '|' . strtolower($email));
        });

        RateLimiter::for('public', function ($request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('submit', function ($request) {
            if (app()->environment('local')) {
                return Limit::perMinute(1000000)->by($request->ip());
            }
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('export', function ($request) {
            $userId = optional($request->user())->id ?: 'guest';
            return Limit::perMinute(20)->by($request->ip() . '|' . $userId);
        });
    }
}
