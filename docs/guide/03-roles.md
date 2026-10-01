# Staff roles and what they can do

Every staff account has one or more **roles**. A role is a set of permissions. The administrator can adjust permissions (Administration → Roles & Permissions) and create new roles to suit the hospital. The sections below describe the roles as they are set up when the system is installed.

> **Super Admin** can do everything and cannot be restricted. Keep this role for one or two trusted people (usually the ICT officer and the Medical Director).

## Roles in plain language

{{role-summary}}

## Full permission table

The table lists every permission and the roles that have it. "✓" means the role has the permission.

{{permission-matrix}}

## Checks built into the system

Some rules protect patients and money no matter which roles someone has:

- **Two-person lab verification:** the person who enters lab results cannot also verify them (unless the hospital switches this off for small labs).
- **Purchase orders** must be approved by someone other than the person who raised them.
- **Supplier invoices** must be marked paid by someone other than the person who entered them.
- **Discounts, voids and payment reversals** need their own permissions and always require a reason.
- **Consultation notes** can only be edited by the doctor who wrote them, and only until signed; later corrections are added as dated addenda.
- The **Super Admin role** cannot be given through data import, and the last active Super Admin cannot be removed.
