@extends('layouts.app')

@section('content')

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">{{ isset($customer) ? 'Edit Customer' : 'Add Customer' }}</h1>
            <p class="text-muted mb-0">{{ isset($customer) ? 'Update the customer details' : 'Create a new customer' }}</p>
        </div>
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">&larr; Back to Customers</a>
    </div>

    <div class="card">
        <div class="card-body">
            @include('customers.ajax.create-customer')
        </div>
    </div>
</div>

@endsection
