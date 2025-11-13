// Função para alternar o status da campanha
document.querySelector('.toggle-switch input').addEventListener('change', function() {
    const statusIndicator = document.querySelector('.status-indicator');
    const statusText = document.querySelector('.status-text');

    if(this.checked) {
        statusIndicator.className = 'status-indicator active';
        statusText.textContent = 'Sua campanha está ATIVA';

        // Enviar para o servidor que a campanha foi ativada
        fetch('/api/campaign/activate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({userId: '123', status: 'active'})
        })
        .then(response => response.json())
        .then(data => {
            console.log('Campanha ativada:', data);
        });
    } else {
        statusIndicator.className = 'status-indicator inactive';
        statusText.textContent = 'Sua campanha está INATIVA';

        // Enviar para o servidor que a campanha foi desativada
        fetch('/api/campaign/deactivate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({userId: '123', status: 'inactive'})
        })
        .then(response => response.json())
        .then(data => {
            console.log('Campanha desativada:', data);
        });
    }
});

// Simulação de atualização de status em tempo real
function updateCampaignStatus() {
    fetch('/api/campaign/status?userId=123')
        .then(response => response.json())
        .then(data => {
            const switchInput = document.querySelector('.toggle-switch input');
            const statusIndicator = document.querySelector('.status-indicator');
            const statusText = document.querySelector('.status-text');

            if(data.status === 'active') {
                switchInput.checked = true;
                statusIndicator.className = 'status-indicator active';
                statusText.textContent = 'Sua campanha está ATIVA';
            } else {
                switchInput.checked = false;
                statusIndicator.className = 'status-indicator inactive';
                statusText.textContent = 'Sua campanha está INATIVA';
            }

            // Atualizar outras informações
            document.querySelector('.info-value:nth-of-type(1)').textContent = data.phoneNumber;
            document.querySelector('.info-value:nth-of-type(2)').textContent = data.phoneStatus;
            document.querySelector('.info-value:nth-of-type(3)').textContent = data.activeClients;
            document.querySelector('.info-value:nth-of-type(4)').textContent = data.lastUpdate;
        });
}

// Atualizar a cada 30 segundos
setInterval(updateCampaignStatus, 30000);

// Carregar dados iniciais
updateCampaignStatus();