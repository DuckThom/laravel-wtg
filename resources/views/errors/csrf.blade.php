@extends('errors.master')

@section('title')
        <h3>Error: Sessie verlopen</h3>
@stop

@section('content')
        <div class="alert alert-danger" role="alert">
                De sessie token is verlopen. Log opnieuw in en probeer het opnieuw.<br />
                <br />
                Mogelijke oorzaak is:<br />
                <br />
                <ul>
                        <li>Te lang inactief</li>
                </ul>
        </div>
@stop