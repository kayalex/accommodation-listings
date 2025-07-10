<!-- Report Modal -->
<div id="reportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium leading-6 text-brand-gray mb-4">Report Listing</h3>
            <form id="reportForm" onsubmit="submitReport(event)" novalidate>
                <input type="hidden" name="report_listing" value="1">
                <input type="hidden" name="listing_id" id="report_listing_id">
                <input type="hidden" name="landlord_id" id="report_landlord_id">
                
                <div class="mb-4">
                    <label for="reason" class="block text-sm font-medium text-brand-gray mb-2">Reason for reporting</label>
                    <textarea 
                        id="reason" 
                        name="reason" 
                        rows="4" 
                        required
                        class="w-full px-3 py-2 border border-brand-light rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary"
                        placeholder="Please describe why you are reporting this listing..."></textarea>
                </div>

                <div id="reportMessage" class="mb-4 hidden">
                    <p class="text-sm font-medium"></p>
                </div>

                <div class="flex justify-end gap-3">
                    <button 
                        type="button"
                        onclick="closeReportModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 text-sm font-medium rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        id="submitReportBtn"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700">
                        Submit Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReportModal(listingId, landlordId) {
    document.getElementById('reportModal').classList.remove('hidden');
    document.getElementById('report_listing_id').value = listingId;
    document.getElementById('report_landlord_id').value = landlordId;
    // Reset form and message
    document.getElementById('reportForm').reset();
    document.getElementById('reportMessage').classList.add('hidden');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});

async function submitReport(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = document.getElementById('submitReportBtn');
    const messageDiv = document.getElementById('reportMessage');
    const messageText = messageDiv.querySelector('p');

    // Reset message
    messageDiv.classList.add('hidden');
    messageText.textContent = '';

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    try {
        const response = await fetch('/accommodation-listings/api/submit_report.php', {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        console.log('Raw server response:', text); // Debug log

        let data;
        try {
            data = JSON.parse(text);
            console.log('Parsed response:', data); // Debug log
        } catch (e) {
            console.error('JSON parse error:', e);
            throw new Error('Failed to process server response');
        }

        messageDiv.classList.remove('hidden');

        // Check for successful submission
        if (data.success) {
            console.log('Report submitted successfully');
            messageText.className = 'text-sm font-medium text-green-600';
            messageText.textContent = 'Report submitted successfully';
            form.reset();
            setTimeout(() => {
                closeReportModal();
            }, 2000);
            return;
        }

        // If we reach here, it's an error
        throw new Error(data.message || 'Failed to submit report');

    } catch (error) {
        console.error('Submission error:', error);
        messageDiv.classList.remove('hidden');
        messageText.className = 'text-sm font-medium text-red-600';
        messageText.textContent = 'An error occurred while submitting the report. Please try again.';
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Report';
    }
}

function submitReport(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const submitBtn = document.getElementById('submitReportBtn');
    const messageDiv = document.getElementById('reportMessage');
    const messageText = messageDiv.querySelector('p');

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    fetch('/accommodation-listings/api/submit_report.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        // Get the response text first
        const text = await response.text();
        
        // Try to parse it as JSON
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            console.error('Failed to parse JSON:', text);
            throw new Error('Server returned invalid JSON response');
        }
    })
    .then(data => {
        console.log('Server response:', data); // Debug log
        messageDiv.classList.remove('hidden');
        
        // Check both the success flag and for empty/null response
        if (data && data.success) {
            messageText.className = 'text-sm font-medium text-green-600';
            messageText.textContent = data.message || 'Report submitted successfully';
            
            // Reset the form
            form.reset();
            
            // Close modal after 2 seconds on success
            setTimeout(() => {
                closeReportModal();
            }, 2000);
        } else {
            throw new Error(data.message || 'Failed to submit report');
        }
    })
    .catch(error => {
        console.error('Error:', error); // Debug log
        messageDiv.classList.remove('hidden');
        messageText.className = 'text-sm font-medium text-red-600';
        messageText.textContent = error.message || 'An error occurred. Please try again.';
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Report';
    });
}
</script>
