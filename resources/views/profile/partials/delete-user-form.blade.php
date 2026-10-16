<p class="text-muted mb-3">
    Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
</p>

<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
    Delete Account
</button>

<div class="modal fade" id="confirmUserDeletion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content tracker-modal">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')
                <div class="modal-header border-0">
                    <h5 class="modal-title">Are you sure you want to delete your account?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.</p>
                    <label class="tracker-form-label" for="delete_password">Password</label>
                    <input id="delete_password" name="password" type="password" class="form-control @error('password', 'userDeletion') is-invalid @enderror" placeholder="Password">
                    @error('password', 'userDeletion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn tracker-outline-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($errors->userDeletion->isNotEmpty())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new bootstrap.Modal(document.getElementById('confirmUserDeletion')).show();
            });
        </script>
    @endpush
@endif
