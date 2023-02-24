<?php

namespace RachidLaasri\LaravelInstaller\Middleware;

use Closure;

class canUpdate
{
    use \RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $version = $request->route("version");
//        dd($version);
        $updateEnabled = filter_var(config('installer.updaterEnabled'), FILTER_VALIDATE_BOOLEAN);
        switch ($updateEnabled) {
            case true:
                $canInstall = new canInstall;

                // if the application has not been installed,
                // redirect to the installer
                if (! $canInstall->alreadyInstalled()) {
                    return redirect()->route('LaravelInstaller::welcome');
                }

                if ($this->alreadyUpdated($version)) {
                    abort(404);
                }
                break;

            case false:
            default:
                abort(404);
                break;
        }

        return $next($request);
    }

    /**
     * If application is already updated.
     *
     * @return bool
     */
    public function alreadyUpdated($version)
    {
        $migrations = $this->getMigrations($version);
        $dbMigrations = $this->getExecutedMigrations();

        // If the count of migrations and dbMigrations is equal,
        // then the update as already been updated.
        if (count($migrations) == 0) {
            return true;
        }

        // Continue, the app needs an update
        return false;
    }
}
