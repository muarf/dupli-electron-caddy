<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <h1 class="text-center"><?php _e('admin.email_management'); ?></h1>
        <hr>

        <!-- Messages de statut -->
        <?php if (isset($message)): ?>
          <div class="alert alert-<?= $message['type'] ?> alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
            <?= $message['text'] ?>
          </div>
        <?php endif; ?>

        <!-- Section Paramètres -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-info">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-cog"></i> <?php _e('common.settings'); ?></h3>
              </div>
              <div class="panel-body">
                <form method="post">
                  <div class="form-group">
                    <div class="checkbox">
                      <label>
                        <input type="checkbox" name="show_mailing_list" value="1" <?= (isset($show_mailing_list) && $show_mailing_list == '1') ? 'checked' : '' ?>>
                        <?php _e('admin.emails.show_list_on_home', [], false); ?>
                      </label>
                    </div>
                  </div>

                  <button type="submit" name="update_site_settings" class="btn btn-info">
                    <i class="fa fa-save"></i> <?php _e('admin.emails.save_settings', [], false); ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Section Liste des emails -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title">
                  <i class="fa fa-envelope"></i> <?php _e('admin.emails.list_title', [], false); ?> (<?= count($emails) ?>)
                </h3>
              </div>
              <div class="panel-body">
                <?php if (empty($emails)): ?>
                  <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> <?php _e('admin.emails.no_emails', [], false); ?>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-striped table-hover">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th><?php _e('admin.emails.email_address', [], false); ?></th>
                          <th><?php _e('common.actions', [], false); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($emails as $index => $email): ?>
                          <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                              <i class="fa fa-envelope"></i>
                              <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                            </td>
                            <td>
                              <form method="post" style="display: inline;">
                                <input type="hidden" name="delmail" value="<?= htmlspecialchars($email) ?>">
                                <button type="button" class="btn btn-danger btn-sm"
                                  onclick="confirmEmailAction(this, '<?= __js('admin.emails.confirm_delete') ?>')">
                                  <i class="fa fa-trash"></i> <?php _e('common.delete', [], false); ?>
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                  <!-- Actions en masse -->
                  <div class="row">
                    <div class="col-md-12">
                      <div class="alert alert-warning">
                        <strong><?php _e('common.warning', [], false); ?> :</strong>
                        <form method="post" style="display: inline;">
                          <button type="button" name="delete_all_emails" class="btn btn-warning btn-sm"
                            onclick="confirmEmailAction(this, '<?= __js('admin.emails.confirm_delete_all') ?>')">
                            <i class="fa fa-trash"></i> <?php _e('admin.emails.delete_all', [], false); ?>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Section Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> <?php _e('common.navigation', [], false); ?></h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> <?php _e('admin.emails.back_to_admin', [], false); ?>
                </a>
                <a href="?" class="btn btn-info" target="_blank">
                  <i class="fa fa-external-link"></i> <?php _e('admin.emails.view_home', [], false); ?>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  function confirmEmailAction(btn, message) {
    showAppModal({
      type: 'warning',
      title: 'Confirmation',
      message: message,
      confirm: true,
      onConfirm: function () {
        var $btn = $(btn);
        var $form = $btn.closest('form');
        if ($btn.attr('name')) {
          $form.append($('<input>').attr({
            type: 'hidden',
            name: $btn.attr('name'),
            value: $btn.val() || ''
          }));
        }
        $form.submit();
      }
    });
  }
</script>
