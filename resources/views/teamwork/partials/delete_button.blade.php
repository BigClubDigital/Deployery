<form style="display: inline-block;" action="{{ route('teams.members.destroy', [$team, $user]) }}" method="post">
    {!! csrf_field() !!}
    <input type="hidden" name="_method" value="DELETE" />
    <button class="btn btn-danger btn-xs w-100-px"><i class="fa fa-boot"></i>Remove</button>
</form>