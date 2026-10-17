<?php
// Inclure Quill.js
?>
<!-- Quill.js CSS -->
<link href="js/quill/quill.snow.css" rel="stylesheet">
<!-- Quill.js JS -->
<script src="js/quill/quill.min.js"></script>

<style>
  /* Style pour le bouton PDF personnalisé dans Quill.js */
  .ql-toolbar .ql-custom-pdf {
    background: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
  }

  .ql-toolbar .ql-custom-pdf:hover {
    background: #c82333;
  }

  .ql-toolbar .ql-custom-pdf:before {
    content: "PDF";
  }

  /* Style pour les liens PDF dans l'éditeur */
  .ql-editor a[href*=".pdf"] {
    color: #dc3545;
    font-weight: bold;
    text-decoration: underline;
  }

  .ql-editor a[href*=".pdf"]:before {
    content: "📄 ";
  }
</style>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin_aide.title'); ?></h1>
        <hr>

        <?php if (isset($message)): ?>
          <div class="alert alert-<?= $message['type'] ?>">
            <?= $message['text'] ?>
          </div>
        <?php endif; ?>

        <!-- Liste des aides existantes -->
        <div class="panel panel-primary">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_aide.existing_aides'); ?></h3>
          </div>
          <div class="panel-body">
            <?php if (isset($aides) && count($aides) > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th><?php _e('admin_aide.machine'); ?></th>
                      <th><?php _e('admin_aide.creation_date'); ?></th>
                      <th><?php _e('admin_aide.modification_date'); ?></th>
                      <th><?php _e('admin_aide.actions'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($aides as $aide): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($aide['machine']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($aide['date_creation'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($aide['date_modification'])) ?></td>
                        <td>
                          <button class="btn btn-sm btn-info"
                            onclick="editAide(<?= $aide['id'] ?>, '<?= addslashes($aide['machine']) ?>')"
                            data-aide-id="<?= $aide['id'] ?>">
                            <i class="fa fa-edit"></i> <?php _e('admin_aide.edit'); ?>
                          </button>
                          <button class="btn btn-sm btn-danger"
                            onclick="deleteAide(<?= $aide['id'] ?>, '<?= addslashes($aide['machine']) ?>')">
                            <i class="fa fa-trash"></i> <?php _e('admin_aide.delete'); ?>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> <?php _e('admin_aide.no_aides'); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Section Upload de PDFs -->
        <div class="panel panel-info">
          <div class="panel-heading">
            <h3 class="panel-title">
              <i class="fa fa-upload"></i>
              <?php _e('admin_aide.pdf_upload'); ?>
            </h3>
          </div>
          <div class="panel-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label><?php _e('admin_aide.select_pdf'); ?> :</label>
                  <input type="file" id="pdf-file-input" class="form-control" accept=".pdf" />
                  <small class="text-muted"><?php _e('admin_aide.pdf_format_hint', [], false); ?></small>
                </div>
                <button type="button" class="btn btn-primary" onclick="uploadPdf()">
                  <i class="fa fa-upload"></i> <?php _e('admin_aide.upload_pdf'); ?>
                </button>
              </div>
              <div class="col-md-6">
                <div id="upload-progress" class="progress" style="display: none;">
                  <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%"></div>
                </div>
                <div id="upload-message" class="alert" style="display: none;"></div>
              </div>
            </div>

            <!-- Liste des PDFs disponibles -->
            <hr>
            <h4><?php _e('admin_aide.uploaded_pdfs'); ?></h4>
            <div id="pdf-list" class="table-responsive">
              <div class="alert alert-info">
                <i class="fa fa-spinner fa-spin"></i> <?php _e('admin_aide.loading_pdfs', [], false); ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulaire d'ajout/modification -->
        <div class="panel panel-success">
          <div class="panel-heading">
            <h3 class="panel-title">
              <i class="fa fa-plus"></i>
              <span id="form-title"><?php _e('admin_aide.add_aide'); ?></span>
            </h3>
          </div>
          <div class="panel-body">
            <form method="POST" action="?admin&aide" id="aide-form">
              <input type="hidden" name="action" id="action" value="add">
              <input type="hidden" name="aide_id" id="aide_id" value="">
              <input type="hidden" name="contenu_aide" id="contenu_aide_hidden" value="">

              <div class="form-group">
                <label for="machine"><?php _e('admin_aide.machine'); ?> :</label>
                <select name="machine" id="machine" class="form-control" required onchange="loadExistingAide()">
                  <option value=""><?php _e('admin_aide.select_machine'); ?></option>
                  <?php if (isset($machines)): ?>
                    <?php foreach ($machines as $machine): ?>
                      <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="editor"><?php _e('admin_aide.help_content'); ?> :</label>
                <div id="editor" style="height: 300px; margin-bottom: 10px;">
                  <div class="alert alert-info">
                    <p style="text-align: center;"><?php _e('admin_aide.default_instructions'); ?></p>
                  </div>
                  <div style="text-align: center;">
                    <img src="img/compteur.png" style="width: 80%;">
                  </div>
                </div>
                <small class="text-muted">
                  <i class="fa fa-info-circle"></i>
                  <?php _e('admin_aide.help_instructions'); ?>
                </small>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-success">
                  <i class="fa fa-save"></i> <?php _e('admin_aide.save'); ?>
                </button>
                <button type="button" class="btn btn-default" onclick="resetForm()">
                  <i class="fa fa-refresh"></i> <?php _e('admin_aide.reset'); ?>
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Bouton retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?admin" class="btn btn-default btn-block">
              <i class="fa fa-arrow-left"></i> <?php _e('admin_aide.back_to_admin'); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  var CONFIG = {
    translations: {
      default_instructions: "<?php echo __js('admin_aide.default_instructions'); ?>",
      edit_aide: "<?php echo __js('admin_aide.edit_aide'); ?>",
      add_aide: "<?php echo __js('admin_aide.add_aide'); ?>",
      add_aide_for: "<?php echo __js('admin_aide.add_aide_for'); ?>",
      error_loading_content: "<?php echo __js('admin_aide.error_loading_content'); ?>",
      error_loading_aide: "<?php echo __js('admin_aide.error_loading_aide'); ?>",
      confirm_delete: "<?php echo __js('admin_aide.confirm_delete'); ?>",
      confirm_delete_pdf: "<?php echo __js('admin_aide.confirm_delete_pdf'); ?>",
      pdf_name: "<?php echo __js('admin_aide.pdf_name'); ?>",
      upload_date: "<?php echo __js('admin_aide.upload_date'); ?>",
      pdf_size: "<?php echo __js('admin_aide.pdf_size'); ?>",
      insert_pdf: "<?php echo __js('admin_aide.insert_pdf'); ?>",
      delete_pdf: "<?php echo __js('admin_aide.delete_pdf'); ?>",
      no_pdfs: "<?php echo __js('admin_aide.no_pdfs'); ?>",
      pdf_inserted: "<?php echo __js('admin_aide.pdf_inserted'); ?>"
    }
  };
</script>
<script src="js/admin-aide.js"></script>
