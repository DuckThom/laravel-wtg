@extends('errors.master', ['pagetitle' => 'Error / 500'])

@section('title')
        <h3>Error 500: Internal Server Error</h3>
@stop

@section('content')
        <div class="alert alert-danger" role="alert">
                Er is een fout opgetreden waardoor de pagina niet kon worden geladen.<Br />
                Wij zijn er van op de hoogte er zullen het probleem zo spoedig mogelijk verhelpen.<br />

                {{ $exception }}
        </div>
@stop

@section('extraCSS')
        <style type="text/css">
                blockquote {
                        text-transform: capitalize;
                }
        </style>
@stop
