<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\BaseRequest;
use App\Http\Resources\Management\ProjectResource;
use App\Jobs\RepositoryClone;
use App\Models\Project;
use App\Transformers\ProjectTransformer;

class ProjectsController extends APIController
{

    public function __construct(BaseRequest $request, Project $project, ProjectTransformer $transformer)
    {
        parent::__construct($request, $project, $transformer);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Dingo\Api\Http\Response
     */
    public function index()
    {
        \DB::enableQueryLog();
        $query = $this->model->with([
            'latest_history' => function($query){
                $query->with(['server' => function($q){
                    $q->select(['name', 'id']);
                }]);
            },
            'servers' => function($query){
                $query->select(['id', 'name', 'project_id']);
            }
        ])->findUserModels()
          ->order([
            'order' => 'latest_history.created_at',
            'direction' => 1,
        ]);

        $projects = $query->get();
        return ProjectResource::collection($projects);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @return \Dingo\Api\Http\Response
     */
    public function store()
    {
        $rules = $this->model->getValidationRules();
        $this->apiValidate($this->request, $rules);

        $model = $this->model->create($this->request->all());
        return new ProjectResource($model->load("servers", "configs", "scripts"));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Dingo\Api\Http\Response
     */
    public function show($id)
    {
        $model = $this->model->with([
            'servers',
            'configs' => function($query){
                $query->with(['servers' => function($query){
                    $query->select(['id', 'name']);
                }]);
            },
            'scripts'
        ])->findOrFail($id);

        $this->authorize($model);
        return new ProjectResource($model);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($id) {
        $model = $this->model->getUserModel($id);
        $this->authorize($model);

        $rules = $model->getValidationRules($id);
        $this->apiValidate($this->request, $rules);

        $model->update($this->request->all());

        return $this->response->array([
            "message" => "Successfully updated the project",
            "is_cloning" => $model->is_cloning,
            "status_code" => 200,
            "rules" => $rules
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Dingo\Api\Http\Response
     */
    public function destroy($id)
    {
        $model = $this->model->findOrFail($id);
        $this->authorize($model);

        $model->delete();
        return $this->response->array([
            'message' => "Successfully deleted the project",
            "status_code" => 200
        ]);
    }

    /**
     * Get General info about the project
     *
     * @param  int $id
     * @return \Dingo\Api\Http\Response
     */
    public function info($id)
    {
        $model = $this->model->findOrFail($id);
        $this->authorize('view', $model);

        $exists = $model->repo_exists;
        $is_cloning = $model->is_cloning;

        $history = $model->history()->first();
        $last = $history ? $history->created_at->toIso8601String(): 'Never Deployed';
        $server = $history ? $history->server->name : "";

        return $this->response->array([
            'deployments' => [
                'last' => [
                    'date' => $last,
                    'server' => $server,
                ],
                'count' => $model->history()->count()
            ],
            'repo' => [
                'size' => $model->getRepoSizeAttribute(true),
                'exists' => $exists,
            ],
            'status' => [
                'is_cloning' => $is_cloning,
                'is_deploying' => $model->is_deploying,
                'clone_failed' => (!$is_cloning && !$exists)
            ]
        ]);
    }

    /**
     * Get the Public Key for deployed to servers.
     *
     * @param  int $id
     * @return \Dingo\Api\Http\Response
     */
    public function pubkey($id)
    {
        $model = $this->model->findOrFail($id);
        $this->authorize('view', $model);

        return $this->response->array([
            'key' => $model->pubkey
        ]);
    }

    /**
     * Trigger a RepositoryClone event
     *
     * @param  int $id
     * @return \Dingo\Api\Http\Response
     */
    public function cloneRepo($id)
    {
        $model = $this->model->getUserModel($id);
        $this->authorize('update', $model);

        if(!file_exists($model->repoPath())){
            $repo = $this->request->get('repo');

            if($repo && $repo !== $model->repo) {
                $model->update(compact('repo'));
            }

            $clone = (new RepositoryClone($model))->onQueue('clones');
            $this->dispatch($clone);
        } else {
            abort(400, 'The repository already exists. No need to reclone.');
        }

        return $this->response->array([
            'message'=>'Trying to reclone the repo.',
            'status_code'=>'200'
        ]);
    }

}
