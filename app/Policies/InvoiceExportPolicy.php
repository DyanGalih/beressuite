<?php

namespace Crater\Policies;

use Crater\Models\Invoice;
use Crater\Models\InvoiceExport;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class InvoiceExportPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        return BouncerFacade::can('view-invoice', Invoice::class)
            || BouncerFacade::can('create-invoice', Invoice::class);
    }

    public function view(User $user, InvoiceExport $export): bool
    {
        return $user->hasCompany($export->company_id)
            && (BouncerFacade::can('view-invoice', Invoice::class)
                || BouncerFacade::can('create-invoice', Invoice::class));
    }

    public function download(User $user, InvoiceExport $export): bool
    {
        return $this->view($user, $export);
    }
}
