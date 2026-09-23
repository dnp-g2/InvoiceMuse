<div class="col-xs-12 col-md-8 col-md-offset-2">

    <div class="panel panel-default">
        <div class="panel-heading">
            <?php _trans('updatecheck'); ?>
        </div>
        <div class="panel-body">

            <div class="form-group">
                <label for="invoicemuse_version"><?php _trans('current_version'); ?></label>
                <input type="text" id="invoicemuse_version" class="form-control" value="<?php echo html_escape(INVOICEMUSE_VERSION); ?>" readonly="readonly">
            </div>
            <div class="form-group">
                <label for="database_schema_version"><?php _trans('database_schema_version'); ?></label>
                <input type="text" id="database_schema_version" class="form-control" value="<?php echo html_escape(get_setting('current_version')); ?>" readonly="readonly">
            </div>
            <div class="alert alert-info no-margin">
                <?php _trans('updatecheck_not_configured'); ?>
            </div>

        </div>
    </div>

</div>
