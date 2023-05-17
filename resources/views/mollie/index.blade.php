@extends('layouts.app')

@section('content')

    <div class="pagetitle">
        <div class="row">
            <div class="col-8">
                <h1>Billing</h1>
            </div>
            @if(isset($last_plan_info))
                <div class="col-8 mt-4">
                    <h5>Your remaining credits: @if(isset($credits) && $credits !== null && $credits !== false)
                            {{$credits}}
                        @else
                            {{$last_plan_info->credits}}
                        @endif</h5>
                </div>
            @endif
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <a href="{{route('consume.credits')}}" class="btn btn-primary">Consume Credits</a>
            </div>
        </div>

    </div>

@endsection
