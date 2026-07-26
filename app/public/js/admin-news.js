// ============================================================
// admin-news.js -- Gestion des News (Admin)
// Extrait de admin.news.html.php
// ============================================================

/* global Quill, showAppModal */

window.confirmDeleteNews = function (btn) {
    showAppModal({
        type: 'danger',
        title: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_news.supprimer_la_news'] || 'Supprimer la news ?'),
        message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_news.tes_vous_s_r_de_vouloir_suppr'] || 'Êtes-vous sûr de vouloir supprimer cette news ?'),
        confirm: true,
        onConfirm: function () {
            const form = btn.form;
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'singlebutton';
            hiddenInput.value = '';
            form.appendChild(hiddenInput);
            form.submit();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    // Édition de news
    const editorEditEl = document.getElementById('editor-edit');
    const formEdit = document.getElementById('news-form-edit');
    if (editorEditEl && formEdit) {
        const quillEdit = new Quill('#editor-edit', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            },
            placeholder: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_news.r_digez_le_contenu_de_votre_ne'] || 'Rédigez le contenu de votre news...')
        });

        quillEdit.on('text-change', () => {
            const content = quillEdit.root.innerHTML;
            const hidden = document.getElementById('texte-hidden-edit');
            if (hidden) hidden.value = content;
        });

        formEdit.addEventListener('submit', () => {
            const content = quillEdit.root.innerHTML;
            const hidden = document.getElementById('texte-hidden-edit');
            if (hidden) hidden.value = content;
        });
    }

    // Création de news
    const editorCreateEl = document.getElementById('editor-create');
    const formCreate = document.getElementById('news-form-create');
    if (editorCreateEl && formCreate) {
        const quillCreate = new Quill('#editor-create', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            },
            placeholder: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_news.r_digez_le_contenu_de_votre_ne'] || 'Rédigez le contenu de votre news...')
        });

        quillCreate.on('text-change', () => {
            const content = quillCreate.root.innerHTML;
            const hidden = document.getElementById('texte-hidden-create');
            if (hidden) hidden.value = content;
        });

        formCreate.addEventListener('submit', () => {
            const content = quillCreate.root.innerHTML;
            const hidden = document.getElementById('texte-hidden-create');
            if (hidden) hidden.value = content;
        });
    }
});
