<aside class="sidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        @if (setting('logo'))
            <img src="{{ asset(setting('logo')) }}" alt="Logo">
        @else
            <span class="avatar bg-brand"><i class="bi bi-hospital"></i></span>
        @endif
        <span class="brand-name">{{ setting('hospital_short_name') ?: setting('hospital_name') }}</span>
    </a>

    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="sidebar-heading">Clinical</div>
        @can('patients.view')
            <a href="{{ route('patients.index') }}" class="sidebar-link {{ request()->routeIs('patients.*') ? 'active' : '' }}">
                <i class="bi bi-person-vcard"></i> Patients
            </a>
        @endcan
        @can('appointments.view')
            <a href="{{ route('appointments.index') }}" class="sidebar-link {{ request()->routeIs('appointments.*') ? 'active' : '' }}">
                <i class="bi bi-calendar-check"></i> Appointments
            </a>
        @endcan
        @can('queue.view')
            <a href="{{ route('queue.index') }}" class="sidebar-link {{ request()->routeIs('queue.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i> Clinic Queue
            </a>
        @endcan
        @can('vitals.record')
            <a href="{{ route('vitals.worklist') }}" class="sidebar-link {{ request()->routeIs('vitals.*') ? 'active' : '' }}">
                <i class="bi bi-heart-pulse"></i> Nursing / Triage
            </a>
        @endcan
        @can('admissions.view')
            <a href="{{ route('inpatients.index') }}" class="sidebar-link {{ request()->routeIs('inpatients.*') ? 'active' : '' }}">
                <i class="bi bi-hospital"></i> Inpatients / Wards
            </a>
        @endcan
        @can('maternity.view')
            <a href="{{ route('maternity.index') }}" class="sidebar-link {{ request()->routeIs('maternity.*') ? 'active' : '' }}">
                <i class="bi bi-person-heart"></i> Maternity
            </a>
        @endcan
        @can('immunization.record')
            <a href="{{ route('immunizations.index') }}" class="sidebar-link {{ request()->routeIs('immunizations.*') ? 'active' : '' }}">
                <i class="bi bi-shield-plus"></i> Immunization
            </a>
        @endcan
        @can('theatre.view')
            <a href="{{ route('theatre.index') }}" class="sidebar-link {{ request()->routeIs('theatre.*') ? 'active' : '' }}">
                <i class="bi bi-scissors"></i> Theatre
            </a>
        @endcan
        @can('lab.process')
            <a href="{{ route('lab.index') }}" class="sidebar-link {{ request()->routeIs('lab.*') ? 'active' : '' }}">
                <i class="bi bi-droplet-half"></i> Laboratory
            </a>
        @endcan
        @can('radiology.process')
            <a href="{{ route('radiology.index') }}" class="sidebar-link {{ request()->routeIs('radiology.*') ? 'active' : '' }}">
                <i class="bi bi-radioactive"></i> Radiology
            </a>
        @endcan
        @can('pharmacy.dispense')
            <a href="{{ route('pharmacy.index') }}" class="sidebar-link {{ request()->routeIs('pharmacy.*') ? 'active' : '' }}">
                <i class="bi bi-capsule"></i> Pharmacy
            </a>
        @endcan
        @can('inventory.view')
            <a href="{{ route('inventory.index') }}" class="sidebar-link {{ request()->routeIs('inventory.*', 'suppliers.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i> Drug Inventory
            </a>
        @endcan

        @canany(['requisitions.create', 'stores.view', 'purchasing.manage', 'purchasing.approve', 'payables.manage'])
            <div class="sidebar-heading">Stores & Procurement</div>
        @endcanany
        @can('stores.view')
            <a href="{{ route('stores.index') }}" class="sidebar-link {{ request()->routeIs('stores.*') ? 'active' : '' }}">
                <i class="bi bi-boxes"></i> General Store
            </a>
        @endcan
        @canany(['requisitions.create', 'stores.view'])
            <a href="{{ route('requisitions.index') }}" class="sidebar-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}">
                <i class="bi bi-clipboard-check"></i> Requisitions
            </a>
        @endcanany
        @canany(['purchasing.manage', 'purchasing.approve'])
            <a href="{{ route('purchasing.index') }}" class="sidebar-link {{ request()->routeIs('purchasing.*') ? 'active' : '' }}">
                <i class="bi bi-cart-check"></i> Purchase Orders
            </a>
        @endcanany
        @can('payables.manage')
            <a href="{{ route('invoices.index') }}" class="sidebar-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                <i class="bi bi-journal-text"></i> Supplier Invoices
            </a>
        @endcan

        @canany(['billing.view', 'billing.prices', 'billing.claims', 'claims.preauth', 'reports.operational', 'reports.clinical', 'reports.financial', 'reports.staff'])
            <div class="sidebar-heading">Finance & Reports</div>
        @endcanany
        @can('billing.view')
            <a href="{{ route('billing.index') }}" class="sidebar-link {{ request()->routeIs('billing.index', 'billing.account', 'billing.receipt', 'billing.invoice') ? 'active' : '' }}">
                <i class="bi bi-cash-coin"></i> Billing & Cashier
            </a>
        @endcan
        @can('billing.prices')
            <a href="{{ route('billing.prices') }}" class="sidebar-link {{ request()->routeIs('billing.prices*') ? 'active' : '' }}">
                <i class="bi bi-tags"></i> Price List
            </a>
        @endcan
        @can('billing.claims')
            <a href="{{ route('billing.claims') }}" class="sidebar-link {{ request()->routeIs('billing.claims*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-medical"></i> Insurance Claims
            </a>
        @endcan
        @can('claims.preauth')
            <a href="{{ route('preauth.index') }}" class="sidebar-link {{ request()->routeIs('preauth.*') ? 'active' : '' }}">
                <i class="bi bi-shield-check"></i> PA Codes
            </a>
        @endcan
        @canany(['reports.operational', 'reports.clinical', 'reports.financial', 'reports.staff'])
            <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        @endcanany

        @canany(['settings.manage', 'audit.view', 'users.view', 'departments.manage', 'roles.manage', 'insurance.manage', 'clinics.manage', 'catalog.manage', 'wards.manage', 'system.manage'])
            <div class="sidebar-heading">Administration</div>
            @can('users.view')
                <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Staff
                </a>
            @endcan
            @can('departments.manage')
                <a href="{{ route('admin.departments.index') }}" class="sidebar-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3"></i> Departments
                </a>
            @endcan
            @can('roles.manage')
                <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                    <i class="bi bi-key"></i> Roles &amp; Permissions
                </a>
            @endcan
            @can('clinics.manage')
                <a href="{{ route('admin.clinics.index') }}" class="sidebar-link {{ request()->routeIs('admin.clinics.*') ? 'active' : '' }}">
                    <i class="bi bi-door-open"></i> Clinics
                </a>
            @endcan
            @can('wards.manage')
                <a href="{{ route('admin.wards.index') }}" class="sidebar-link {{ request()->routeIs('admin.wards.*') ? 'active' : '' }}">
                    <i class="bi bi-grid-3x3-gap"></i> Wards & Beds
                </a>
            @endcan
            @can('catalog.manage')
                <a href="{{ route('admin.catalogs.index', 'lab-tests') }}" class="sidebar-link {{ request()->routeIs('admin.catalogs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-medical"></i> Clinical Catalogues
                </a>
            @endcan
            @can('insurance.manage')
                <a href="{{ route('admin.insurance.index') }}" class="sidebar-link {{ request()->routeIs('admin.insurance.*') ? 'active' : '' }}">
                    <i class="bi bi-shield-plus"></i> Insurance / HMOs
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('admin.settings.edit') }}" class="sidebar-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear"></i> Hospital Settings
                </a>
            @endcan
            @can('system.manage')
                <a href="{{ route('admin.system.index') }}" class="sidebar-link {{ request()->routeIs('admin.system.*') ? 'active' : '' }}">
                    <i class="bi bi-heart-pulse-fill"></i> System Health & Backups
                </a>
            @endcan
            @can('integrations.manage')
                <a href="{{ route('admin.integrations.index') }}" class="sidebar-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}">
                    <i class="bi bi-plug"></i> Integrations
                </a>
            @endcan
            @can('data.import')
                <a href="{{ route('admin.imports.index') }}" class="sidebar-link {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}">
                    <i class="bi bi-cloud-upload"></i> Data Import
                </a>
            @endcan
            @can('sms.manage')
                <a href="{{ route('admin.sms.index') }}" class="sidebar-link {{ request()->routeIs('admin.sms.*') ? 'active' : '' }}">
                    <i class="bi bi-chat-dots"></i> SMS Messages
                </a>
            @endcan
            @can('audit.view')
                <a href="{{ route('admin.audit.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                    <i class="bi bi-shield-check"></i> Audit Log
                </a>
            @endcan
        @endcanany
    </nav>
</aside>
