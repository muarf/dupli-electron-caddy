// ============================================================
// admin-aide.js — Gestion des aides (Quill.js editor + PDFs)
// Extrait de admin.aide.html.php
// ============================================================

/* global CONFIG, showAppModal, Quill */

// --- Init Quill.js ---
var quill = new Quill('#editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ color: [] }, { background: [] }],
      [{ list: 'ordered' }, { list: 'bullet' }],
      [{ align: [] }],
      ['link', 'image'],
      [{ 'custom-pdf': 'PDF' }],
      ['clean']
    ]
  },
  placeholder: CONFIG.translations.default_instructions
});

// Bouton PDF personnalisé
quill.getModule('toolbar').addHandler('custom-pdf', function () {
  showPdfInsertModal();
});

// Mettre à jour le champ caché avant soumission
document.getElementById('aide-form').addEventListener('submit', function () {
  document.getElementById('contenu_aide_hidden').value = quill.root.innerHTML;
});

// --- Quill helpers ---
function setQuillContent(html) {
  setTimeout(function () {
    if (quill && quill.root) {
      try {
        quill.setContents(quill.clipboard.convert(html));
      } catch (e) {
        quill.root.innerHTML = html;
      }
    } else {
      document.getElementById('editor').innerHTML = html;
    }
  }, 200);
}

function getDefaultContent(machine) {
  return (
    '<div class="alert alert-info">' +
    '  <p style="text-align: center;">Instructions pour ' + (machine || 'cette machine') + '...</p>' +
    '</div>' +
    '<div style="text-align: center;">' +
    '  <img src="img/compteur.png" style="width: 80%;">' +
    '</div>'
  );
}

// --- Aide CRUD ---
function editAide(id, machine) {
  fetch('?admin&aide&get_content=' + id)
    .then(function (r) { return r.text(); })
    .then(function (content) {
      document.getElementById('form-title').textContent = CONFIG.translations.edit_aide + ' ' + machine;
      document.getElementById('action').value = 'edit';
      document.getElementById('aide_id').value = id;
      document.getElementById('machine').value = machine;
      setQuillContent(content);
      document.getElementById('aide-form').scrollIntoView({ behavior: 'smooth' });
    })
    .catch(function (error) {
      console.error(CONFIG.translations.error_loading_content + ':', error);
      showAppModal({ type: 'danger', message: CONFIG.translations.error_loading_content });
    });
}

function loadExistingAide() {
  var machine = document.getElementById('machine').value;
  if (!machine) { resetForm(); return; }

  fetch('?admin&aide&get_aide_by_machine=' + encodeURIComponent(machine))
    .then(function (r) { return r.text(); })
    .then(function (content) {
      if (content && content.trim() !== '') {
        document.getElementById('form-title').textContent = CONFIG.translations.edit_aide + ' ' + machine;
        document.getElementById('action').value = 'edit';

        fetch('?admin&aide&get_aide_id=' + encodeURIComponent(machine))
          .then(function (r) { return r.text(); })
          .then(function (aideId) {
            if (aideId && aideId.trim() !== '') {
              document.getElementById('aide_id').value = aideId.trim();
            }
          });

        setQuillContent(content);
      } else {
        document.getElementById('form-title').textContent = CONFIG.translations.add_aide_for + ' ' + machine;
        document.getElementById('action').value = 'add';
        document.getElementById('aide_id').value = '';
        setQuillContent(getDefaultContent(machine));
      }
    })
    .catch(function (error) {
      console.error(CONFIG.translations.error_loading_aide + ':', error);
      document.getElementById('form-title').textContent = CONFIG.translations.add_aide_for + ' ' + machine;
      document.getElementById('action').value = 'add';
      document.getElementById('aide_id').value = '';
    });
}

function deleteAide(id, machine) {
  showAppModal({
    type: 'warning',
    title: 'Confirmation de suppression',
    message: CONFIG.translations.confirm_delete + ' ' + machine + ' ?',
    confirm: true,
    onConfirm: function () {
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '?admin&aide';

      var fields = { action: 'delete', aide_id: id };
      for (var key in fields) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
      }

      document.body.appendChild(form);
      form.submit();
    }
  });
}

function resetForm() {
  document.getElementById('form-title').textContent = CONFIG.translations.add_aide;
  document.getElementById('action').value = 'add';
  document.getElementById('aide_id').value = '';
  document.getElementById('machine').value = '';
  setQuillContent(getDefaultContent());
}

// --- PDF management ---
function uploadPdf() {
  var fileInput = document.getElementById('pdf-file-input');
  var file = fileInput.files[0];

  const trans = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
  if (!file) { showMessage(trans.select_pdf_file || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_aide.veuillez_s_lectionner_un_fichi'] || 'Veuillez sélectionner un fichier PDF.'), 'danger'); return; }
  if (file.type !== 'application/pdf') { showMessage(trans.select_valid_pdf || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_aide.veuillez_s_lectionner_un_fichi'] || 'Veuillez sélectionner un fichier PDF valide.'), 'danger'); return; }
  if (file.size > 10 * 1024 * 1024) { showMessage(trans.pdf_file_too_large || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_aide.le_fichier_est_trop_volumineux'] || 'Le fichier est trop volumineux (maximum 10MB).'), 'danger'); return; }

  var formData = new FormData();
  formData.append('pdf_file', file);
  formData.append('action', 'upload');

  showProgress(true);

  fetch('?upload_aide_pdf&action=upload', { method: 'POST', body: formData })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      showProgress(false);
      if (data.success) {
        showMessage(data.message, 'success');
        fileInput.value = '';
        loadPdfList();
      } else {
        showMessage(data.message, 'danger');
      }
    })
    .catch(function (error) {
      showProgress(false);
      showMessage('Erreur lors de l\'upload: ' + error.message, 'danger');
    });
}

function showProgress(show) {
  var progress = document.getElementById('upload-progress');
  progress.style.display = show ? 'block' : 'none';
  if (show) { progress.querySelector('.progress-bar').style.width = '100%'; }
}

function showMessage(message, type) {
  var messageDiv = document.getElementById('upload-message');
  messageDiv.className = 'alert alert-' + type;
  messageDiv.textContent = message;
  messageDiv.style.display = 'block';
  setTimeout(function () { messageDiv.style.display = 'none'; }, 5000);
}

function loadPdfList() {
  fetch('?upload_aide_pdf&action=list')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var pdfList = document.getElementById('pdf-list');

      if (data.success && data.pdfs.length > 0) {
        var html = '<table class="table table-striped table-hover">' +
          '<thead><tr>' +
          '<th>' + CONFIG.translations.pdf_name + '</th>' +
          '<th>' + CONFIG.translations.upload_date + '</th>' +
          '<th>' + CONFIG.translations.pdf_size + '</th>' +
          '<th>Actions</th>' +
          '</tr></thead><tbody>';

        data.pdfs.forEach(function (pdf) {
          var safeName = pdf.name.replace(/'/g, "\\'");
          var safeUrl = pdf.url.replace(/'/g, "\\'");
          var safeFilename = pdf.filename.replace(/'/g, "\\'");

          html += '<tr>' +
            '<td>' + pdf.name + '</td>' +
            '<td>' + pdf.upload_date + '</td>' +
            '<td>' + pdf.size + '</td>' +
            '<td>' +
            '<button class="btn btn-sm btn-success" onclick="insertPdf(\'' + safeUrl + '\', \'' + safeName + '\')">' +
            '<i class="fa fa-plus"></i> ' + CONFIG.translations.insert_pdf +
            '</button> ' +
            '<button class="btn btn-sm btn-danger" onclick="deletePdf(\'' + safeFilename + '\')">' +
            '<i class="fa fa-trash"></i> ' + CONFIG.translations.delete_pdf +
            '</button>' +
            '</td></tr>';
        });

        html += '</tbody></table>';
        pdfList.innerHTML = html;
      } else {
        pdfList.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> ' + CONFIG.translations.no_pdfs + '</div>';
      }
    })
    .catch(function () {
      document.getElementById('pdf-list').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des PDFs</div>';
    });
}

function insertPdf(url, name) {
  var range = quill.getSelection();
  if (range) {
    quill.insertText(range.index, '[PDF: ' + name + ']', 'link', url);
    quill.setSelection(range.index + name.length + 7);
  } else {
    var length = quill.getLength();
    quill.insertText(length - 1, '[PDF: ' + name + ']', 'link', url);
  }
  showMessage(CONFIG.translations.pdf_inserted, 'success');
}

function deletePdf(filename) {
  showAppModal({
    type: 'warning',
    title: 'Confirmation de suppression',
    message: CONFIG.translations.confirm_delete_pdf,
    confirm: true,
    onConfirm: function () {
      var formData = new FormData();
      formData.append('filename', filename);
      formData.append('action', 'delete');

      fetch('?upload_aide_pdf', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            showMessage('PDF supprimé avec succès.', 'success');
            loadPdfList();
          } else {
            showMessage(data.message, 'danger');
          }
        })
        .catch(function (error) {
          showMessage((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_aide.erreur_lors_de_la_suppression'] || 'Erreur lors de la suppression: ') + error.message, 'danger');
        });
    }
  });
}

// --- PDF insert modal (Quill toolbar) ---
function showPdfInsertModal() {
  var modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.id = 'pdfInsertModal';
  modal.innerHTML =
    '<div class="modal-dialog">' +
    '<div class="modal-content">' +
    '<div class="modal-header">' +
    '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
    '<h4 class="modal-title">' + CONFIG.translations.insert_pdf + '</h4>' +
    '</div>' +
    '<div class="modal-body" id="modal-pdf-list">' +
    '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Chargement...</div>' +
    '</div>' +
    '<div class="modal-footer">' +
    '<button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>' +
    '</div>' +
    '</div></div>';

  document.body.appendChild(modal);

  fetch('?upload_aide_pdf&action=list')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var modalBody = document.getElementById('modal-pdf-list');

      if (data.success && data.pdfs.length > 0) {
        var html = '<div class="list-group">';
        data.pdfs.forEach(function (pdf) {
          var safeName = pdf.name.replace(/'/g, "\\'");
          var safeUrl = pdf.url.replace(/'/g, "\\'");

          html += '<a href="#" class="list-group-item" onclick="insertPdfFromModal(\'' + safeUrl + '\', \'' + safeName + '\'); return false;">' +
            '<h5 class="list-group-item-heading"><i class="fa fa-file-pdf-o"></i> ' + pdf.name + '</h5>' +
            '<p class="list-group-item-text">Taille: ' + pdf.size + ' - Uploadé le: ' + pdf.upload_date + '</p>' +
            '</a>';
        });
        html += '</div>';
        modalBody.innerHTML = html;
      } else {
        modalBody.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> ' + CONFIG.translations.no_pdfs + '</div>';
      }
    })
    .catch(function () {
      document.getElementById('modal-pdf-list').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des PDFs</div>';
    });

  /* global $ */
  $(modal).modal('show');
  $(modal).on('hidden.bs.modal', function () {
    document.body.removeChild(modal);
  });
}

function insertPdfFromModal(url, name) {
  insertPdf(url, name);
  $('#pdfInsertModal').modal('hide');
}

// --- Init ---
document.addEventListener('DOMContentLoaded', function () {
  loadPdfList();
});
