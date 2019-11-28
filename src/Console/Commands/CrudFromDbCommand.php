<?php

namespace Sayeed\CrudFromDb\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\DetectsApplicationNamespace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as DoctrineDriver;

class CrudFromDbCommand extends Command
{
    use DetectsApplicationNamespace;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud:custom {--m|model=} {--c|connection=} {--f|force} {--a|auth}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crud from database or schema file';

    /**
     * The views that need to be exported.
     *
     * @var array
     */
    protected $views = [
        'crud/index.stub' => 'index.blade.php',
        'crud/create.stub' => 'create.blade.php',
        'crud/edit.stub' => 'edit.blade.php',
        'crud/view.stub' => 'view.blade.php',
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public $model;
    public $connection;
    public $force;
    public $auth;
    public function handle()
    {
        $this->model = $this->option('model');
        $this->force = $this->option('force');
        $this->auth = $this->option('auth');

        if (! $this->option('connection')) {
            $this->connection = 'mysql'; //its default
        } else {
            $this->connection = $this->option('connection');
        }

        $database_name = DB::connection($this->connection)->getDatabaseName();
        if (! $this->option('model')) {
            //$tables = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableNames();
            $tables = DB::connection($this->connection)->select('SHOW TABLES');
            $table_key = "Tables_in_".$database_name;
            foreach ($tables as $table) {
                $model_name = studly_case(str_singular($table->$table_key));
                $this->makeCompileModel($model_name, $database_name, $this->connection);
            }
        } else {
            $model_name = studly_case(str_singular($this->model));
            $this->makeCompileModel($model_name, $database_name, $this->connection);
        }
    }

    protected function makeCompileModel($model_name, $database_name, $connection_name)
    {
        /// Controller for Crud
        file_put_contents(
            app_path('Http/Controllers/'.$model_name.'Controller.php'),
            $this->compileControllerStub($model_name)
        );

        /// Route for Crud
        $route_file_content = file_get_contents(__DIR__.'/../../routes/web.php');
        $new_route_date = "\nRoute::resource('/".strtolower($model_name)."', 'App\Http\Controllers\\".$model_name."Controller');";
        if (! str_contains($route_file_content, $new_route_date)) {
            file_put_contents(
                __DIR__.'/../../routes/web.php',
                $new_route_date,
                FILE_APPEND
            );
        }

        /// Model for Crud
        if (! file_exists(app_path($model_name.'.php'))) {
            copy(
                __DIR__.'/../stubs/make/Model.stub',
                app_path($model_name.'.php')
            );

            file_put_contents(
                app_path($model_name.'.php'),
                $this->compileModelStub($model_name)
            );
        }

        /// Layout for Crud
        $this->exportLayout();

        /// make directory for curd files
        $this->createDirectories($model_name);

        /// make view file for curd
        $this->exportViews($model_name);
    }

    protected function compileControllerStub($model_name)
    {
        $crudController = str_replace(
            '{{namespace}}',
            $this->getAppNamespace(),
            file_get_contents(__DIR__.'/../stubs/make/controllers/CrudController.stub')
        );

        $auth_middleware = '';
        if ($this->option('auth')) {
            $auth_middleware = '$this->middleware(\'auth\');';
        }
        $crudController = str_replace(
            '{{auth}}',
            $auth_middleware,
            $crudController
        );

        return str_replace(
            '{{model}}',
            $model_name,
            $crudController
        );
    }

    protected function compileModelStub($model_name)
    {
        $crudModel = str_replace(
            '{{namespace}}',
            $this->getAppNamespace(),
            file_get_contents(__DIR__.'/../stubs/make/Model.stub')
        );

        return str_replace(
            '{{model}}',
            $model_name,
            $crudModel
        );
    }

    protected function createDirectories($model_name)
    {
        if (! is_dir(resource_path('views/layouts'))) {
            mkdir(resource_path('views/layouts'), 0755, true);
        }

        if (! is_dir(resource_path('views/'.str_plural(strtolower($model_name))))) {
            mkdir(resource_path('views/'.str_plural(strtolower($model_name))), 0755, true);
        }
    }

    protected function exportLayout()
    {
        if (file_exists(resource_path('views/layouts/crud-master.blade.php')) && ! $this->option('force')) {
            if (! $this->confirm("The [crud-master.blade.php] layout already exists. Do you want to replace it?")) {
                continue;
            }
        }

        copy(
            __DIR__.'/../stubs/make/views/layouts/crud-master.stub',
            resource_path('views/layouts/crud-master.blade.php')
        );
    }

    protected function exportViews($model_name)
    {
        foreach ($this->views as $key => $value) {
            if (file_exists(resource_path('views/'.str_plural(strtolower($model_name)).'/'.$value)) && ! $this->option('force')) {
                if (! $this->confirm("The [{$value}] view already exists. Do you want to replace it?")) {
                    continue;
                }
            }

            copy(
                __DIR__.'/../stubs/make/views/'.$key,
                resource_path('views/'.str_plural(strtolower($model_name)).'/'.$value)
            );
        }
    }
}