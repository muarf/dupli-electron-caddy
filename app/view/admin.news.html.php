<?php
// Messages de succès
if (isset($_POST['titre']) && !isset($_POST['id2'])) {
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ <?php _e('common.success', [], false); ?> !</strong> <?php _e('admin.news.created_success', [], false); ?>
  </div>
  <?php
} elseif (isset($_POST['id2'])) {
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ <?php _e('common.success', [], false); ?> !</strong> <?php _e('admin.news.updated_success', [], false); ?>
  </div>
  <?php
} elseif (isset($_POST['id']) && isset($_POST['singlebutton'])) {
  ?>
  <div class="alert alert-success alert-dismissible fade in">
    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    <strong>✅ <?php _e('common.success', [], false); ?> !</strong> <?php _e('admin.news.deleted_success', [], false); ?>
  </div>
  <?php
}

// Formulaire d'édition
if (isset($_POST['id']) && !isset($_POST['singlebutton'])) {
  ?>
  <!-- Quill.js CSS -->
  <link href="js/quill/quill.snow.css" rel="stylesheet">
  <!-- Quill.js JS -->
  <script src="js/quill/quill.min.js"></script>

  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-primary">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-edit"></i> <?php _e('admin.news.edit_title', [], false); ?></h3>
            </div>
            <div class="panel-body">
              <form method="post" action="?admin&news" id="news-form-edit">
                <input type="hidden" value="<?= $new_edit->id ?>" name="id2" />
                <input type="hidden" name="texte" id="texte-hidden-edit" value="">

                <div class="form-group">
                  <label for="titre"><strong><?php _e('admin.news.title_label', [], false); ?> :</strong></label>
                  <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($new_edit->titre) ?>"
                    class="form-control input-lg" placeholder="<?php _e('admin.news.title_placeholder', [], false); ?>" required>
                </div>

                <div class="form-group">
                  <label for="editor-edit"><strong><?php _e('admin.news.content_label', [], false); ?> :</strong></label>
                  <div id="editor-edit" style="height: 400px; margin-bottom: 10px;"><?= $new_edit->news ?></div>
                </div>

                <div class="form-group">
                  <button type="submit" name="save" class="btn btn-primary btn-lg">
                    <i class="fa fa-save"></i> <?php _e('common.save_changes', [], false); ?>
                  </button>
                  <a href="?admin&news" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> <?php _e('admin.news.back_to_list', [], false); ?>
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <?php
}
// Formulaire de création
elseif ($_GET['news'] == "add") {
  ?>
  <!-- Quill.js CSS -->
  <link href="js/quill/quill.snow.css" rel="stylesheet">
  <!-- Quill.js JS -->
  <script src="js/quill/quill.min.js"></script>

  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-success">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-plus"></i> <?php _e('admin.news.create_title', [], false); ?></h3>
            </div>
            <div class="panel-body">
              <form method="post" action="?admin&news" id="news-form-create">
                <input type="hidden" name="texte" id="texte-hidden-create" value="">

                <div class="form-group">
                  <label for="titre"><strong><?php _e('admin.news.title_label', [], false); ?> :</strong></label>
                  <input type="text" id="titre" name="titre" value="" class="form-control input-lg"
                    placeholder="<?php _e('admin.news.title_placeholder', [], false); ?>" required>
                </div>

                <div class="form-group">
                  <label for="editor-create"><strong><?php _e('admin.news.content_label', [], false); ?> :</strong></label>
                  <div id="editor-create" style="height: 400px; margin-bottom: 10px;"></div>
                </div>

                <div class="form-group">
                  <button type="submit" name="save" class="btn btn-success btn-lg">
                    <i class="fa fa-plus"></i> <?php _e('admin.news.create_btn', [], false); ?>
                  </button>
                  <a href="?admin&news" class="btn btn-default btn-lg">
                    <i class="fa fa-arrow-left"></i> <?php _e('admin.news.back_to_list', [], false); ?>
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <?php
}
// Liste des news
else {
  ?>
  <div class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-12">
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title">
                <i class="fa fa-newspaper-o"></i> <?php _e('admin.news_management'); ?>
                <a href="?admin&news=add" class="btn btn-success btn-sm pull-right">
                  <i class="fa fa-plus"></i> <?php _e('common.add'); ?> <?php _e('admin.news_management'); ?>
                </a>
              </h3>
            </div>
            <div class="panel-body">
              <?php if (isset($news) && count($news) > 0): ?>
                <div class="table-responsive">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th width="15%"><i class="fa fa-calendar"></i> <?php _e('common.date', [], false); ?></th>
                        <th width="25%"><i class="fa fa-header"></i> <?php _e('admin.news.column_title', [], false); ?></th>
                        <th width="45%"><i class="fa fa-file-text"></i> <?php _e('admin.news.column_content', [], false); ?></th>
                        <th width="15%"><i class="fa fa-cogs"></i> <?php _e('common.actions', [], false); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 0; $i < count($news); $i++): ?>
                        <tr>
                          <td>
                            <span class="label label-info">
                              <i class="fa fa-clock-o"></i> <?= $news[$i]['temps'] ?>
                            </span>
                          </td>
                          <td>
                            <strong><?= htmlspecialchars($news[$i]['titre']) ?></strong>
                          </td>
                          <td>
                            <div class="text-muted">
                              <?= htmlspecialchars(substr(strip_tags($news[$i]['news']), 0, 100)) ?>
                              <?= strlen(strip_tags($news[$i]['news'])) > 100 ? '...' : '' ?>
                            </div>
                          </td>
                          <td>
                            <form method="post" style="display: inline;">
                              <input type="hidden" value="<?= $news[$i]['id'] ?>" name="id" />
                              <button type="submit" name="edit" class="btn btn-info btn-sm" title="<?php _e('common.edit', [], false); ?>">
                                <i class="fa fa-edit"></i>
                              </button>
                              <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteNews(this)"
                                title="<?php _e('common.delete', [], false); ?>">
                                <i class="fa fa-trash"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="text-center text-muted">
                  <i class="fa fa-newspaper-o fa-3x"></i>
                  <h4><?php _e('admin.news.no_news_title', [], false); ?></h4>
                  <p><?php _e('admin.news.no_news_help', [], false); ?></p>
                  <a href="?admin&news=add" class="btn btn-success btn-lg">
                    <i class="fa fa-plus"></i> <?php _e('admin.news.create_btn', [], false); ?>
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
}
?>
<script src="js/admin-news.js" defer></script>
