// Gerenciador de transições entre páginas
document.addEventListener('DOMContentLoaded', function() {
    // Adiciona classe de transição ao conteúdo principal
    const mainContent = document.querySelector('.container');
    if (mainContent) {
        mainContent.classList.add('page-transition');
        setTimeout(() => {
            mainContent.classList.add('active');
        }, 100);
    }

    // Adiciona loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="text-center" style="justify-items: anchor-center;">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando...</div>
        </div>
    `;
    document.body.appendChild(loadingOverlay);

    // Funções para controlar o loading
    window.showLoading = function() {
        loadingOverlay.classList.add('active');
    };

    window.hideLoading = function() {
        loadingOverlay.classList.remove('active');
    };

    // Adiciona transição aos links do navbar
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Não aplica a transição para links externos ou com target="_blank"
            if (this.getAttribute('target') === '_blank' || 
                this.hostname !== window.location.hostname) {
                return;
            }

            e.preventDefault();
            const href = this.getAttribute('href');
            
            // Mostra o loading
            showLoading();

            // Faz a transição
            mainContent.classList.remove('active');
            
            // Carrega a nova página
            setTimeout(() => {
                window.location.href = href;
            }, 300);
        });
    });

    // Adiciona animações aos elementos da página
    const animateElements = document.querySelectorAll('.animate-on-load');
    animateElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.3s ease-out';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });
}); 