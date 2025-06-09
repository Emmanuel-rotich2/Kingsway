<!-- Confirm Delete Modal Component -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmDeleteMessage">Are you sure you want to delete this item?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <input type="hidden" name="delete_id" id="deleteItemId" value="">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showConfirmDelete(itemId, message = null, formAction = null) {
    // Get the modal element
    const modal = document.getElementById('confirmDeleteModal');
    
    // Set custom message if provided
    if (message) {
        document.getElementById('confirmDeleteMessage').textContent = message;
    }
    
    // Set the item ID in the hidden input
    document.getElementById('deleteItemId').value = itemId;
    
    // Set custom form action if provided
    if (formAction) {
        document.getElementById('deleteForm').action = formAction;
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}
</script>
