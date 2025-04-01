<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Access\Response;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function ($user) {
            if ($user->role === 'admin') return true;
        });
        Gate::define('update-comment', function ($user, $comment) {
            if ($user->id === $comment->user_id) {
                return Response::allow();
            } else {
                return Response::deny('Вы не являетесь автором комментария');
            };
        });
    }
}
