<div class="row g-3">
    <x-form.input name="session_idle_minutes" type="number" label="Sign out after inactivity (minutes)" :value="setting('session_idle_minutes')"
                  required col="col-md-6" min="5" max="480"
                  help="Protects patient data on shared computers. Typing and clicking count as activity; a warning appears one minute before sign-out." />
    <x-form.input name="backup_retention_days" type="number" label="Keep backups for (days)" :value="setting('backup_retention_days')"
                  required col="col-md-6" min="1" max="365" help="Older automatic backups are deleted after each new backup." />
</div>
<div class="alert alert-light border small mt-3 mb-0">
    Accounts lock for {{ config('emr.security.lockout_minutes') }} minutes after {{ config('emr.security.max_failed_logins') }} wrong passwords in a row;
    an administrator can unlock them from the staff profile.
</div>
