@extends('layouts.app')

@section('content')

    <div class="form-group">
        <form class="form-horizontal" method="POST" action="{{ route('mollie.saveKey') }}">
            @csrf
            <label class="control-label" for="mollie_api">Mollie API Key:</label>
            <input type="text" name="mollie_api" id="mollie_api" class="form-control col-md-4">
            <button class="btn btn-primary mt-3" type="submit">Save</button>
        </form>

    </div>

@endsection
