<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\BaseRequest;
use App\Http\Resources\Management\ConfigResource;
use App\Models\Project;
use App\Transformers\ConfigTransformer;

class ConfigsController extends APIController
{
    /**
     * Project
     * @var \App\Models\Project
     */
    private $project;

    public function __construct(BaseRequest $request, Project $project, ConfigTransformer $transformer)
    {
        $this->project = $project;
        parent::__construct($request, $project->configs()->getModel(), $transformer);
    }

    /**
     * Get the resource
     *
     * @param  int $project_id
     * @param  int $id
     * @return \Dingo\Api\Http\Response
     */
    public function show($project_id, $id)
    {
        $model = $this->project->findConfig($project_id, $id);
        $this->authorize($model->project);

        $model->server_ids = $model->servers()->get()->pluck('id');

        return new ConfigResource($model);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Dingo\Api\Http\Response
     */
    public function store($project_id)
    {
        $this->validate(
            $this->request,
            $this->model->getValidationRules()
        );

        $project = $this->project->getUserModel($project_id);
        $this->authorize($project);

        $data = $this->request->all();
        $model = $this->model->newInstance($data);

        $model = \DB::transaction(function() use ($project, $model) {
            $project->configs()->save($model);
            $server_ids = $this->request->get('server_ids');
            if( !! $server_ids ) {
                $model->servers()->sync($server_ids);
            }
            $model->server_ids = $server_ids;
            return $model;
        });

        return new ConfigResource($model);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $project_id
     * @param  int  $id
     * @return \Dingo\Api\Http\Response
     */
    public function update($project_id, $id)
    {
        $this->validate(
            $this->request,
            $this->model->getValidationRules($id)
        );

        $model = $this->project->findConfig($project_id, $id);
        $this->authorize($model->project);

        $model = \DB::transaction(function() use ($model) {
            $model->update($this->request->all());
            if($server_ids = $this->request->server_ids) {
                $model->servers()->sync($server_ids);
            }
            $model->server_ids = $server_ids;
            return $model;
        });

        return new ConfigResource($model);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $project_id
     * @param  int  $id
     * @return \Dingo\Api\Http\Response
     */
    public function destroy($project_id, $id)
    {
        $model = $this->project->findConfig($project_id, $id);
        $this->authorize($model->project);

        if (!$model->delete()) {
            $this->response->error('Could not delete the configuration file.', 422);
        }
        return $this->response->array([
            'message'=>'Successfully deleted the config file.',
            'status_code'=>'200'
        ]);
    }

    /**
     * Get the relational options for the form
     *
     * @param  int|null $project_id
     * @return \Dingo\Api\Http\Response
     */
    public function options ($project_id=null)
    {
        $project = $this->project->findOrFail($project_id);
        $this->authorize('update', $project);

        $servers = $project->servers->pluck('name', 'id');
        return  $this->response->array(['options' => compact('servers')]);
    }
}
