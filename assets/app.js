/**
 * Kişisel Ağ Günlüğü (nobody) - İstemci Etkileşimleri
 * Saf Vanilla JS - Harici bağımlılık yoktur.
 */
document.addEventListener('DOMContentLoaded', function () {
    // Silme ve onaylama işlemlerinde modal teyidi
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var message = el.getAttribute('data-confirm') || 'Bu işlemi gerçekleştirmek istediğinize emin misiniz?';
            if (!window.confirm(message)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // Yazdır / PDF işlemi (Özgeçmiş sayfası vb.)
    document.querySelectorAll('[data-action="print"]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    });
});
