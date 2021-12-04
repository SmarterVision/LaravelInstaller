<?php

namespace RachidLaasri\LaravelInstaller\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RachidLaasri\LaravelInstaller\Events\EnvironmentSaved;
use RachidLaasri\LaravelInstaller\Helpers\EnvironmentManager;
use Validator;

class EnvironmentController extends Controller
{
    /**
     * @var EnvironmentManager
     */
    protected $EnvironmentManager;

    /**
     * @param EnvironmentManager $environmentManager
     */
    public function __construct(EnvironmentManager $environmentManager)
    {
        $this->EnvironmentManager = $environmentManager;
    }

    /**
     * Display the Environment menu page.
     *
     * @return \Illuminate\View\View
     */
    public function environmentMenu()
    {
        return view('vendor.installer.environment');
    }

    /**
     * Display the Environment page.
     *
     * @return \Illuminate\View\View
     */
    public function environmentWizard()
    {
        $envConfig = $this->EnvironmentManager->getEnvContent();

        return view('vendor.installer.environment-wizard', compact('envConfig'));
    }

    /**
     * Processes the newly saved environment configuration (Form Wizard).
     *
     * @param Request $request
     * @param Redirector $redirect
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveWizard(Request $request, Redirector $redirect)
    {
        $rules = config('installer.environment.form.rules');
        $messages = [
            'environment_custom.required_if' => trans('installer_messages.environment.wizard.form.name_required'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return $redirect->route('LaravelInstaller::environmentWizard')->withInput()->withErrors($validator->errors());
        }

        $results = $this->EnvironmentManager->saveFileWizard($request);

        if (!$this->checkDatabaseConnection($request)) {
            $errors = $validator->errors()->add('database_connection', trans('installer_messages.environment.wizard.form.db_connection_failed'));
            return view('vendor.installer.environment-wizard', compact('errors'));
        }


        event(new EnvironmentSaved($request));

        // فيريفيكاسيون كود
        $itmId = "31658817"; // 31658817
        $token = "aVH71sVL6UA91XchRumA8AHY5tahMXBp";

        $code = $request->get('purchase_code');
        if (!preg_match("/^(\w{8})-((\w{4})-){3}(\w{12})$/", $code)) {
            $code = false;
            $errors = $validator->errors()->add('purchase_code', 'Not valid purchase code 3');
        } else {

            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => "https://api.envato.com/v3/market/author/sale?code={$code}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,

                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer {$token}",
                    "User-Agent: Verify Purchase Code"
                )
            ));
            $result = curl_exec($ch);
            if (isset($result) && isset(json_decode($result, true)['error'])) {
                $code = false;
                $errors = $validator->errors()->add('purchase_code', 'Not valid purchase code 1');
            } else {
                if (isset($result) && json_decode($result, true)['item']['id'] != $itmId) {
                    $code = false;
                    $errors = $validator->errors()->add('purchase_code', 'Not valid purchase code 2');
                }
            }
        }

        if (isset($errors) || !$code){
            return view('vendor.installer.environment-wizard', compact('errors'));
        }
        // فيريفيكاسيون كود

        return $redirect->route('LaravelInstaller::database')
            ->with(['results' => $results]);
    }

    /**
     * TODO: We can remove this code if PR will be merged: https://github.com/RachidLaasri/LaravelInstaller/pull/162
     * Validate database connection with user credentials (Form Wizard).
     *
     * @param Request $request
     * @return bool
     */
    private function checkDatabaseConnection(Request $request)
    {
        $connection = $request->input('database_connection');

        $settings = config("database.connections.$connection");

        config([
            'database' => [
                'default' => $connection,
                'connections' => [
                    $connection => array_merge($settings, [
                        'driver' => $connection,
                        'host' => $request->input('database_hostname'),
                        'port' => $request->input('database_port'),
                        'database' => $request->input('database_name'),
                        'username' => $request->input('database_username', ''),
                        'password' => $request->input('database_password', ''),
                    ]),
                ],
            ],
        ]);

        try {
            DB::purge($connection);
            DB::reconnect();
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
