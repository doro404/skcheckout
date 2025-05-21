document.getElementById('payment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.innerHTML = 'Gerando PIX...';

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Criar modal para exibir o QR Code
            const modalHtml = `
                <div class="modal fade" id="pixModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Pagamento PIX</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <p>Escaneie o QR Code abaixo para realizar o pagamento:</p>
                                <img src="data:image/png;base64,${data.qr_code_base64}" class="img-fluid mb-3">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" onclick="copyPixCode('${data.qr_code}')">
                                        Copiar Código PIX
                                    </button>
                                </div>
                                <small class="text-muted mt-3 d-block">
                                    O código PIX expira em 30 minutos
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Adicionar modal ao DOM e exibir
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('pixModal'));
            modal.show();

            // Iniciar verificação do status do pagamento
            checkPaymentStatus(data.payment_id);
        } else {
            alert('Erro ao gerar o PIX: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao processar o pagamento: ' + error.message);
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = 'Gerar PIX';
    });
});

function copyPixCode(code) {
    navigator.clipboard.writeText(code)
        .then(() => {
            alert('Código PIX copiado com sucesso!');
        })
        .catch(() => {
            alert('Erro ao copiar o código PIX');
        });
}

function checkPaymentStatus(paymentId) {
    const checkStatus = () => {
        fetch(`check_payment.php?payment_id=${paymentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'approved') {
                    window.location.href = 'success.php';
                } else if (data.status === 'pending') {
                    setTimeout(checkStatus, 5000); // Verificar novamente em 5 segundos
                } else {
                    window.location.href = 'failure.php';
                }
            })
            .catch(error => {
                console.error('Erro ao verificar status do pagamento:', error);
            });
    };

    checkStatus();
} 