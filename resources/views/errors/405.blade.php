@extends('layouts.app')

@section('content')
<div class="content">
    <div class="title">That was a strange request.</div>
    <a class='btn btn-primary' href="{{ url()->previous() }}">Go back</a>

</div>
@endsection