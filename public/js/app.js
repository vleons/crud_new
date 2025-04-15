// Обработка выбора файла для загрузки
document.addEventListener('DOMContentLoaded', function() {
    // Для всех форм загрузки файлов
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Файл не выбран';
            const fileDisplay = this.nextElementSibling?.nextElementSibling;
            if (fileDisplay) fileDisplay.textContent = fileName;
        });
    });

    // Подтверждение удаления
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите удалить этот препарат?')) {
                e.preventDefault();
            }
        });
    });

    // Поиск продуктов
    const searchForm = document.querySelector('.search-box');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = this.querySelector('input').value.trim();
            window.location.href = `/search?q=${encodeURIComponent(query)}`;
        });
    }
});