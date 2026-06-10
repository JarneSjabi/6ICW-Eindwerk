

function saveAllSettings() {
    const settings = {};
    const inputs = document.querySelectorAll('[data-key]');
    
    inputs.forEach(input => {
        const key = input.getAttribute('data-key');
        let value = input.value;
        
        
        const type = input.type === 'checkbox' ? 'boolean' : 
                    input.tagName === 'TEXTAREA' ? 'json' : 'string';
        
        
        if (type === 'boolean') {
            value = input.checked ? '1' : '0';
        } else if (type === 'number') {
            value = parseFloat(value) || 0;
        } else if (type === 'json') {
            try {
                JSON.parse(value); 
            } catch (e) {
                showAlert('Ongeldig JSON formaat voor ' + key, 'error');
                return;
            }
        }
        
        settings[key] = {
            value: value,
            type: type
        };
    });
    
    fetch('?ajax=setting&action=bulk_update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ settings: settings })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showAlert('Instellingen opgeslagen', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.message || data.error, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showAlert('Fout bij opslaan instellingen', 'error');
    });
}

