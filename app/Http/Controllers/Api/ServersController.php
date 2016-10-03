<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ServerRequest;
use App\Jobs\ServerDeploy;
use App\Models\Project;
use App\Models\Server;
use App\Services\DeploymentProcess;
use App\Transformers\ServerTransformer;
use Illuminate\Support\Facades\Auth;

class ServersController extends APIController
{

    private $projects;

    /**
     * New APIController object
     *
     * @param Request     $request     Illuminate\Http\Request
     * @param Model       $model       Illuminate\Database\Eloquent\Model
     */
    public function __construct(ServerRequest $request, Project $project, ServerTransformer $transformer)
    {
        $this->projects = $project;
        parent::__construct($request, $project->servers()->getModel(), $transformer);
    }

    //----------------------------------------------------------
    // Resource Endpoints
    //-------------------------------------------------------

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $project_id
     * @return Dingo\Api\Http\Response
     */
    public function store($project_id)
    {
        $this->validate(
            $this->request,
            $this->model->getValidationRules()
        );

        $project = $this->project->getUserModel($project_id);

        $data = $this->request->all();
        $data["project_id"] = $project_id;

        $model = $this->model->newInstance($data);
        $project->servers()->save($model);

        $model->scripts()->sync($this->request->get('script_ids') ?: []);
        return $this->response->item($model, new $this->transformer);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $project_id
     * @param  int  $id
     * @return Dingo\Api\Http\Response
     */
    public function show($project_id, $id)
    {
        $model = $this->projects->findServer($project_id, $id);
        return $this->response->item($model, new $this->transformer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $project_id
     * @param  int  $id
     * @return Dingo\Api\Http\Response
     */
    public function update($project_id, $id)
    {
        $this->validate(
            $this->request,
            $model->getValidationRules($id)
        );
        $model = $this->projects->findServer($project_id, $id);

        $success = $model->update($this->request->all());
        $model->scripts()->sync($this->request->get('script_ids') ?: []);

        if ($model->update($this->request->all())) {
            return $this->response->item($model);
        }
        abort(500, "There was a problem updating {$model->name}");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $project_id
     * @param  int  $id
     * @return Dingo\Api\Http\Response
     */
    public function destroy($project_id, $id)
    {
        $model = $this->projects->findServer($project_id, $id);
        if (!$model->delete()) {
            $this->response->error('Could not detete the server.', 422);
        }

        return $this->response->array([
            'message'=>'Successfully deleted the server.',
            'status_code'=>'200'
        ]);
    }


    public function test($project_id, $id)
    {
        $server = $this->projects->findServer($project_id, $id);
        if ($server->validateConnection()) {
             return $this->response->array([
                'success'=> true,
                'message'=> $server->connection_status_message
             ]);
        }
        abort(412, $server->connection_status_message);
    }

    public function commit_details($project_id, $id)
    {
        $server = $this->projects->findServer($project_id, $id);
        $last_deployed_commit = $server->last_deployed_commit;
        $avaliable_commits = $server->commits;

        if ($server->validateConnection()) {
            return $this->response->array(compact('last_deployed_commit', 'avaliable_commits'));
        }
        abort(412, $server->connection_status_message);
    }

    //----------------------------------------------------------
    // Deployment  Endpoints
    //-------------------------------------------------------

    /**
     * Trigger Deployment from frontend
     *
     * @return JSON
     */
    public function deploy($project_id, $id)
    {
        // $this->validate($this->request, [
        //     'to' => 'required|string|max:40|min:6',
        // ]);

        $server = $this->projects->findServer($project_id, $id);

        $to = $this->request->get('to') ?: $server->newest_commit['hash'];
        $from = $this->request->get('from') ?: $server->last_deployed_commit;

        if (!$from) {
            $from = null;
        }

        return $this->response->array(
            $this->ququeDeployment($server, $to, $from)
        );
    }

    /**
     * Trigger Deployment from frontend
     *
     * @return JSON
     */
    public function webhook($webhook)
    {
        $server = $this->model
                       ->where('webhook', $this->request->url())
                       ->firstOrFail();

        // TODO: Figure out the sender from the request
        $sender = 'github';

        if (!$server->autodeploy) {
            return $this->response->error("Autodeploy is not enabled", 400);
        }

        $from = $server->last_deployed_commit;
        $to = $server->newest_commit;

        if ($from && $to) {
            return $this->response->array(
                $this->ququeDeployment($server, $to['hash'], $from, $sender)
            );
        }
        abort(400, "Missing required information");
    }

    //----------------------------------------------------------
    // Private
    //-------------------------------------------------------
    private function ququeDeployment(Server $server, string $to = null, string $from = null, $user_name = null)
    {
        if (!$user_name) {
            if ($user = Auth::user()) {
                $user_name = $user->username;
            } else {
                $user_name = $server->project->user->username;
            }
        }

        $deployment = (new ServerDeploy($server, $user_name, $from, $to))->onQueue('deployments');

        $this->dispatch($deployment);
        return [
            'message'=>'Queued deployment',
        ];
    }
}