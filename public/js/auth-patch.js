// This file patches the saveToDatabase function to support authenticated users
// Must be loaded AFTER the main app.js but BEFORE the page is fully loaded

// Store the original saveToDatabase function reference
// We'll override it after DOM is ready

document.addEventListener('DOMContentLoaded', function() {
    // Get reference to original saveToDatabase if it exists
    const originalSave = window.saveToDatabase;

    if (originalSave) {
        window.saveToDatabase = async function() {
            const text = editor.value;
            if (!text) {
                showToast('Nothing to save!');
                return;
            }

            const compressed = compress(text);
            const format = getActiveFormat();
            const expiresIn = document.getElementById('expiresIn')?.value;
            const password = document.getElementById('sharePassword').value;

            const payload = {
                content: compressed,
                format: format,
                browser_id: browserId
            };

            // Only include expires_in for guest users (not logged in)
            if (!currentUser && expiresIn) {
                payload.expires_in = expiresIn;
            }

            if (password) {
                payload.password = password;
            }

            try {
                const response = await fetch('/api/text-share', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    savedHashId = data.hash_id;
                    urlDisplay.textContent = data.url;

                    // Update browser URL without reload
                    history.replaceState(null, '', `/s/${data.hash_id}`);

                    // Reload history
                    loadHistory();

                    // Copy URL
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(data.url)
                            .then(() => showToast(data.is_permanent ? 'Saved permanently! URL copied.' : 'Saved! URL copied.'))
                            .catch(() => {
                                fallbackCopy(data.url);
                                showToast(data.is_permanent ? 'Saved permanently!' : 'Saved!');
                            });
                    } else {
                        fallbackCopy(data.url);
                        showToast(data.is_permanent ? 'Saved permanently!' : 'Saved!');
                    }
                } else {
                    showToast('Error saving: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                showToast('Error saving!');
            }
        };
    }
});
