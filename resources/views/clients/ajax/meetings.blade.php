@include('customers.partials.contact-meetings', [
    'from' => 'client',
    'contactParam' => 'client_id',
    'contactId' => $client->id,
    'contactLabel' => 'Client',
])
