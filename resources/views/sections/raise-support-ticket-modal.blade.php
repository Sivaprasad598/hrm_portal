<!-- Raise Support Ticket Modal -->
<div id="raiseSupportTicketModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Raise Support Ticket</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <h2 class="text-xl font-weight-bold text-dark mb-2">Choose Your Support Option</h2>
                        <p class="text-muted">Select the support service that best fits your needs</p>
                    </div>

                    <!-- Support Options -->
                    <div class="row">
                        <!-- Envato Support Card -->
                        <div class="col-md-6 mb-4">
                            <div class="card border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="https://cdn.worldvectorlogo.com/logos/envato.svg" alt="Envato" class="h-8 w-8 object-contain mr-3" style="height: 32px; width: 32px;">
                                        <div>
                                            <h5 class="font-weight-bold text-dark mb-1">Envato Regular Support</h5>
                                            <p class="text-muted small mb-0">Included with your purchase</p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-success mr-2"></i>
                                                <span class="text-muted small">Response time: 24-48 working hours</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-success mr-2"></i>
                                                <span class="text-muted small">Email & forum support</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-success mr-2"></i>
                                                <span class="text-muted small">General documentation and guides</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-success mr-2"></i>
                                                <span class="text-muted small">Community forum access</span>
                                            </div>
                                        </div>
                                    </div>

                                    <a href="https://froiden.freshdesk.com/support/tickets/new" target="_blank"
                                       class="btn btn-secondary btn-sm">
                                        <i class="fa fa-external-link-alt mr-1"></i>
                                        Raise Ticket
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Priority Support Card -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-primary" style="background: linear-gradient(135deg, #f8f9ff 0%, #e8f2ff 100%);">
                                <div class="card-body">
                                    <div class="position-relative">
                                        <span class="badge badge-primary position-absolute" style="top: 0; right: 0;">
                                            Recommended
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center mb-3">
                                        <img src="https://envato.froid.works/logo-froiden.png" alt="Froiden" class="" style="height: 32px;">
                                        <div>
                                            <h5 class="font-weight-bold text-dark mb-1">Priority Support</h5>
                                            <p class="text-muted small mb-0">Premium enhancement service</p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">Response time: 4 working hours</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">WhatsApp support</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">One-on-one Zoom consultations</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">Code discussion with developer</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">Dedicated support team</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa fa-check text-primary mr-2"></i>
                                                <span class="text-primary font-weight-medium small">Priority queue access</span>
                                            </div>
                                        </div>
                                    </div>

                                    <a href="https://envato.froid.works/priority-support?purchase_code={{ global_setting()->purchase_code }}&utm_source=Arowana_app&utm_campaign=priority_support" target="_blank"
                                       class="btn btn-primary btn-sm">
                                        <i class="fa fa-plus mr-1"></i>
                                        Know More
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('app.close')</button>
            </div>
        </div>
    </div>
</div>
