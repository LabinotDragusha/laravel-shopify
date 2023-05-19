@extends('layouts.app')

@section('content')

    <div class="container">
        <div class="row justify-content-md-center">
            <div class="card col-6">
                <div class="card-header">
                    <h4>Mollie Set Up</h4>
                </div>
                <div>
                    <div class="card-body mt-2">
                        <div class="form-group">
                            <form class="form-horizontal" method="POST" action="{{ route('mollie.saveKey') }}">
                                @csrf
                                <label class="control-label" for="mollie_api">Mollie API Key:</label>
                                @if($mollie_key == '')
                                    <input type="text" name="mollie_api" id="mollie_api" class="form-control col-md-4">
                                @else
                                    <div>
                                        <input type="text" name="mollie_api" id="mollie_api" class="form-control col-md-4" value="{{ $mollie_key }}">
                                        <strong>You have already connected you mollie account.</strong>
                                    </div>
                                @endif
                                <button class="btn btn-primary mt-3" type="submit">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
