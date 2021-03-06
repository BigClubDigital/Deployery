<?php

namespace App\Http\Controllers\Teamwork;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mpociot\Teamwork\Exceptions\UserNotInTeamException;

class TeamController extends Controller
{

    private $teamModel;

    public function __construct()
    {
        $this->middleware('auth');

        $Team = config('teamwork.team_model');
        $this->teamModel = new $Team();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->can('joinTeams', Team::class)) {
            $teams = Team::all();
        } else {
            $teams = $user->teams;
        }
        return view('teamwork.index')
            ->withTeams($teams);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('teamwork.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $team = $this->teamModel->create([
            'name' => $request->name,
            'owner_id' => $request->user()->getKey()
        ]);
        $request->user()->attachTeam($team);

        return redirect(route('teams.index'));
    }

    /**
     * Switch to the given team.
     *
     * @param  int $id
     * @param  string $type

     * @return \Illuminate\Http\Response
     */
    public function switchTeam($id, $type='')
    {
        $team = $this->teamModel->findOrFail($id);
        try {
            auth()->user()->switchTeam($team);
        } catch ( UserNotInTeamException $e ) {
            abort(403);
        }

        switch ($type) {
            case 'menu':
                return redirect('/');
            default:
                break;
        }

        return redirect(route('teams.index'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $team = $this->teamModel->findOrFail($id);

        if (!auth()->user()->isOwnerOfTeam($team)) {
            abort(403);
        }

        return view('teamwork.edit')->withTeam($team);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $team = $this->teamModel->findOrFail($id);
        $team->name = $request->name;
        $team->save();

        return redirect(route('teams.index'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $team = $this->teamModel->findOrFail($id);
        if (!auth()->user()->isOwnerOfTeam($team)) {
            abort(403);
        }

        $team->delete();

        $userModel = config('teamwork.user_model');
        $userModel::where('current_team_id', $id)
                    ->update(['current_team_id' => null]);

        return redirect(route('teams.index'));
    }

}