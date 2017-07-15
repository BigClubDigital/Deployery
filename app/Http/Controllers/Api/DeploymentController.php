<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BaseRequest as Request;
use App\Jobs\ServerDeploy;
use App\Models\Project;
use App\Models\Server;
use Dingo\Api\Routing\Helpers;

class DeploymentController extends Controller
{
    use Helpers;

    /**
     * @var \App\Http\Requests\Request
     */
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
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
        $server = Project::findServer($project_id, $id);

        $this->validate($this->request, [
            'script_id' => 'sometimes|array',
            'script_ids.*' => 'exists:scripts,id',
        ]);

        $scriptIds = $this->request->get('script_ids', []);
        $oneOffs = $server->oneOffScripts()->whereIn('id', $scriptIds)->pluck('id')->values()->all();

        $this->authorize('deploy', $server->project);

        if ($this->request->get('deploy_entire_repo')) {
            $to = $server->newest_commit['hash'];
            $from = null;
        } else {
            $to = $this->request->get('to') ?: $server->newest_commit['hash'];
            $from = $this->request->get('from') ?: $server->last_deployed_commit;
        }

        return $this->response->array(
            $this->queueDeployment($server, $to, $from, $this->user()->full_name, $oneOffs)
        );
    }

    /**
     * Get details for the commit
     *
     * @param  integer $project_id Project ID
     * @param  integer $id         Server ID
     * @return \Dingo\Api\Http\Response|null
     */
    public function commitDetails($project_id, $id)
    {
        $server = Project::findServer($project_id, $id);
        $this->authorize('deploy', $server->project);

        $server->updateGitInfo();

        $last_deployed_commit = $server->last_deployed_commit;
        $avaliable_commits = $server->commits;
        $avaliable_scripts = $server->oneOffScripts->map(function($script){
            return ['id' => $script->id, 'description' => $script->description];
        })->values();

        if ($server->validateConnection()) {
            return $this->response->array(compact('last_deployed_commit', 'avaliable_commits', 'avaliable_scripts'));
        }
        abort(412, $server->present()->connection_status_message);
    }

    /**
     * Find a specific commit
     *
     * @param  integer $project_id Project ID
     * @param  integer $id         Server ID
     * @return \Dingo\Api\Http\Response|null
     */
    public function findCommit($project_id, $id)
    {
        # code...
    }

    /**
     * Trigger Deployment from frontend
     *
     * @return \Dingo\Api\Http\Response|null
     */
    public function webhook($webhook)
    {
        $server = Server::where('webhook', $this->request->url())
                        ->firstOrFail();

        list($agent,/*version*/) = explode('/', $this->request->header('User-Agent'), 2);
        $name = ucfirst($server->username);
        $sender = "{$name} [ {$agent} ]";

        if (!$server->autodeploy) {
            return $this->response->error("Autodeploy is not enabled", 404);
        }

        $server->updateGitInfo();

        $from = $server->last_deployed_commit;
        $to = $server->newest_commit['hash'];

        $response = $this->queueDeployment($server, $to, $from, $sender);

        return $this->response->array($response);
    }

    //----------------------------------------------------------
    // Private
    //-------------------------------------------------------

    /**
     * Add the deployment to the quque
     *
     * @param  Server      $server    Server being deployed to
     * @param  string|null $to        Commit getting deployed to
     * @param  string|null $from      Commite getting deployed from
     * @param  string|null $user_name User deploying
     *
     * @return array  Message
     */
    private function queueDeployment(Server $server, string $to = null, string $from = null, $user_name = null, $script_ids=[])
    {
        if ($user_name === '' || $user_name === null) {
            $user_name = $server->project->user->username;
        }

        $options = compact('script_ids');
        $deployment = (new ServerDeploy($server, $user_name, $from, $to, $options))->onQueue('deployments');

        $this->dispatch($deployment);


        return [
            'message'=>'Queued deployment',
            'from' => $from ?: "Beginning of time",
            'to' => $to ?: "Autodetecting"
        ];
    }

}