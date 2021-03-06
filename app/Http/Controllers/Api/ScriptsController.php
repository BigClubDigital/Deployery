<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\BaseRequest;
use App\Http\Resources\Management\ScriptResource;
use App\Models\Project;
use App\Transformers\ScriptTransformer;

class ScriptsController extends APIController
{

    /**
     * Project
     * @var App\Models\Project
     */
    private $projects;

    public function __construct(BaseRequest $request, Project $project, ScriptTransformer $transformer)
    {
        $this->projects = $project;
        parent::__construct($request, $project->scripts()->getModel(), $transformer);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Dingo\Api\Http\Response
     */

    public function show($project_id, $id)
    {
        $model = $this->projects->findScript($project_id, $id);
        $this->authorize($model->project);

        $model->server_ids = $model->servers()->get()->pluck('id');

        return new ScriptResource($model);
    }

    /**
     * Get the specific resource in storage.
     *
     * @param  int  $project_id
     * @return \Dingo\Api\Http\Response
     */
    public function store($project_id)
    {
        $this->validate(
            $this->request,
            $this->model->getValidationRules()
        );

        $project = $this->projects->findOrFail($project_id);
        $this->authorize($project);

        $data = $this->request->all();
        $model = $this->model->newInstance($data);

        $model = \DB::transaction(function() use ($project, $model) {
            $project->scripts()->save($model);
            if(($server_ids = $this->request->server_ids)) {
                $model->servers()->sync($server_ids);
            }
            $model->server_ids = $server_ids;
            return $model;
        });

        return new ScriptResource($model);
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
        $model = $this->projects->findScript($project_id, $id);
        $this->authorize($model->project);

        $model = \DB::transaction(function() use ($model) {
            $model->update($this->request->all());
            if(($server_ids = $this->request->server_ids)) {
                $model->servers()->sync($server_ids);
            }
            $model->server_ids = $server_ids;
            return $model;
        });

        return new ScriptResource($model);
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
        $model = $this->model->findOrFail($id);
        $this->authorize($model->project);

        if (!$model->delete()) {
            $this->response->error('Could not detete the model.', 422);
        }
        return $this->response->array([
            'message'=>'Successfully deleted the install script.',
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
        $servers = $this->projects->findOrFail($project_id)->servers->pluck('name', 'id');
        $deployments = $this->model->deployment_opts;
        $parsables = $this->model->parsable;

        return response()->json(
            ['options' => compact('servers','deployments','parsables')]
        );
    }
}
