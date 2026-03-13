// js/buscar.js
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchContainer = document.querySelector('.search-box');
    
    if (!searchInput) return;
    
    // Crear contenedor de resultados
    const resultsContainer = document.createElement('div');
    resultsContainer.className = 'search-results-container';
    searchContainer.appendChild(resultsContainer);
    
    let timeoutId = null;
    
    // Función para realizar la búsqueda completa
    function performSearch() {
        const term = searchInput.value.trim();
        if (term.length > 0) {
            window.location.href = 'busqueda.php?q=' + encodeURIComponent(term);
        }
    }
    
    // Evento para buscar al presionar Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });
    
    // Evento para el botón de búsqueda
    const searchButton = document.querySelector('.search-box button');
    if (searchButton) {
        searchButton.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Búsqueda en tiempo real (sugerencias)
    searchInput.addEventListener('input', function() {
        const term = this.value.trim();
        
        // Limpiar timeout anterior
        if (timeoutId) clearTimeout(timeoutId);
        
        // Ocultar resultados si el término es muy corto
        if (term.length < 2) {
            resultsContainer.classList.remove('show');
            return;
        }
        
        // Esperar 300ms antes de buscar (debounce)
        timeoutId = setTimeout(() => {
            fetch('api/buscar_ajax.php?q=' + encodeURIComponent(term))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarResultados(data.resultados, term);
                    } else {
                        mostrarError(term);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarError(term);
                });
        }, 300);
    });
    
    // Cerrar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!searchContainer.contains(e.target)) {
            resultsContainer.classList.remove('show');
        }
    });
    
    function mostrarResultados(resultados, term) {
        resultsContainer.innerHTML = '';
        
        if (resultados.length === 0) {
            resultsContainer.innerHTML = `
                <div class="search-result-item no-results">
                    <i class="fa-solid fa-search" style="color: #2b6cb0;"></i>
                    <span>No se encontraron resultados para "<strong style="color: #2b6cb0;">${escapeHtml(term)}</strong>"</span>
                </div>
            `;
            resultsContainer.classList.add('show');
            return;
        }
        
        // Mostrar resultados
        resultados.forEach(item => {
            const itemDiv = document.createElement('a');
            itemDiv.href = `producto.php?id=${item.id}`;
            itemDiv.className = 'search-result-item';
            itemDiv.innerHTML = `
                <div class="result-image">
                    <img src="${item.imagen}" alt="${escapeHtml(item.nombre)}" 
                         onerror="this.src='https://via.placeholder.com/50x50?text=NGS'">
                </div>
                <div class="result-info">
                    <div class="result-title">${item.nombre_resaltado || escapeHtml(item.nombre)}</div>
                    <div class="result-details">
                        <span class="result-brand">${escapeHtml(item.marca)} ${escapeHtml(item.modelo)}</span>
                        <span class="result-price">${item.precio_formato}</span>
                    </div>
                </div>
            `;
            resultsContainer.appendChild(itemDiv);
        });
        
        // Agregar enlace para ver todos los resultados
        const verTodos = document.createElement('a');
        verTodos.href = `busqueda.php?q=${encodeURIComponent(term)}`;
        verTodos.className = 'search-result-item view-all';
        verTodos.innerHTML = `
            <i class="fa-solid fa-arrow-right"></i>
            <span>Ver todos los resultados (${resultados.length}) para "<strong>${escapeHtml(term)}</strong>"</span>
        `;
        resultsContainer.appendChild(verTodos);
        
        resultsContainer.classList.add('show');
    }
    
    function mostrarError(term) {
        resultsContainer.innerHTML = `
            <div class="search-result-item no-results">
                <i class="fa-solid fa-exclamation-triangle" style="color: #ef4444;"></i>
                <span>Error al buscar. Intenta de nuevo.</span>
            </div>
        `;
        resultsContainer.classList.add('show');
        
        setTimeout(() => {
            resultsContainer.classList.remove('show');
        }, 2000);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});