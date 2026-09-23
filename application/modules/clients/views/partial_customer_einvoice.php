<?php $colClass = 'col-xs-12'; ?>
<?php
if ($req_einvoicing) {
?>
                <!-- eInvoicing panel -->
                <div class="<?php echo $colClass; ?>">
                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">
                            e-<?php _trans('invoicing'); ?>
<?php
// Panel eInvoicing checks
$title_tip = ' data-toggle="tooltip" data-placement="bottom" title="' . trans('edit'); // Tooltip helper ! Need add: . '"'

// For eInvoicing panel client (users)
$nb_users    = count($req_einvoicing->users);
$me          = $req_einvoicing->users[$_SESSION['user_id']]->show_table;
$nb          = $req_einvoicing->show_table;
$ln          = 'user' . (($nb ?: $nb_users) > 1 ? 's' : ''); // tweak 1 on more nb_users no ok
$user_toggle = ($req_einvoicing->show_table ? ($me ? 'danger' : 'warning') : 'default') . ' ' . ($me ? '" aria-expanded="true' : '" collapsed" aria-expanded="false');
// For eInvoicing panel User(s) table
$class_checks     = ['fa fa-lg fa-check-square-o text-success', 'fa fa-lg fa-edit text-warning', 'fa fa-lg fa-square-o text-danger']; // Checkboxe icons
$base             = 'address_1 zip city country company tax_code vat_id'; // Field names
$keys             = explode(' ', $base); // to array
$lang             = explode(' ', strtr($base, ['_1' => '']));
$user_fields_nook = ($req_einvoicing->clients[$client->client_id]->einvoicing_empty_fields > 0 && ($client->client_einvoicing_version ?? '') != '');
// eInvoicing button toggle users table checking
if (($client->client_einvoicing_active ?? 0) && ! $user_fields_nook) {
?>
                            <span class="pull-right cursor-pointer btn btn-xs btn-default alert-<?php echo $user_toggle; ?>"
                                  data-toggle="collapse" data-target=".einvoice-users-check"
                                  onclick="switch_fa_toggle('einvoice_users_check_fa_toggle')"
                            >
                                <i class="fa fa-<?php echo $nb ? ($me ? 'ban' : 'warning') : 'check-square-o text-success'; ?>"></i>
                                <span data-toggle="tooltip" data-placement="bottom" title="<?php echo '🗸 ' . ($nb_users - $nb) . '/' . $nb_users . ' ' . trans('user' . ($nb_users > 1 ? 's' : '')); ?>">
                                    <?php echo ($nb ?: $nb_users) . ' ' . trans($ln); ?>
                                </span>
                                <i id="einvoice_users_check_fa_toggle" class="fa fa-toggle-<?php echo $me ? 'on' : 'off'; ?> fa-margin"></i>
                            </span>
<?php
} // End if eInvoicing button toggle users table checking
?>
                        </div>
                        <div class="panel-body table-content">

                            <table class="table no-margin">
                                <tr>
                                    <th>e-<?php _htmlsc(trans('invoice') . ' ' . trans('version') . ' (' . trans('send')); ?>)</th>
                                    <td><?php
                                        // Handle missing einvoicing fields for pre-1.6.3 databases
                                        $einvoicing_version = $client->client_einvoicing_version ?? '';
                                        echo (($client->client_einvoicing_active ?? 0) && $einvoicing_version) ? get_xml_full_name($einvoicing_version) : trans('none');
                                    ?></td>
                                </tr>
                            </table>

<?php
// eInvoicing panel Client checks table
if (($client->client_einvoicing_active ?? 0) && $user_fields_nook) {
?>
                            <div class="alert alert-warning small" style="margin: 0px 10px 10px;">
                                <table>
                                    <tr>
                                        <td><i class="fa fa-exclamation-triangle fa-2x"></i>&emsp;</td>
                                        <td><?php _trans('einvoicing_no_creation_hint'); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <table class="table no-margin" id="client_einvoice_checks">
                                <thead class="einvoice-client-checks-lists">
                                    <tr><th><?php _trans('required_fields'); ?> (<?php _trans('client'); ?>)</th></tr>
                                </thead>
                                <tbody class="einvoice-client-checks-lists">
                                    <tr><td>
<?php
    $reqs = []; // init ! important
    if ($req_einvoicing->clients[$client->client_id]->einvoicing_empty_fields) {
        foreach ($keys as $l => $key) {
            if ($req_einvoicing->clients[$client->client_id]->{$key}) {
                $reqs[] = '<i class="' . $class_checks[$req_einvoicing->clients[$client->client_id]->{$key}] . '"></i>'
                        . anchor(
                            '/clients/form/' . $client->client_id . '#client_' . $key,
                            trans($lang[$l]),
                            $title_tip . ' #' . trans($lang[$l]) . ' (' . mb_trim(trans('field')) . ')"'
                        ); // ! Need add: "
            }
        }
    }
    // Show fields in Errors
?>
                                        <span><?php echo implode(', ', $reqs); ?></span>

                                    </td></tr>
                                </tbody>
                            </table>
<?php
} else {
    // Client ok! Show check fields user(s)
?>
                            <table class="einvoice-users-check table no-margin collapse<?php
                                   echo $req_einvoicing->users[$_SESSION['user_id']]->einvoicing_empty_fields ? ' in" aria-expanded="true' : '" aria-expanded="false'; ?>"
                            >
                                <thead class="einvoice-users-check-lists">
                                    <tr><th colspan="3"><?php _trans('required_fields'); ?> (<?php _trans('user' . ($nb_users > 1 ? 's' : '')); ?>)</th></tr>
                                    <tr><th><?php _trans('user'); ?></th><th class="text-nowrap">e-<?php _trans('invoice'); ?></th><th><?php _trans('errors'); ?></th></tr>
                                </thead>
<?php
    // eInvoicing panel User(s) checks table
    foreach ($req_einvoicing->users as $uid => $user) {
        $ok = ! $user->einvoicing_empty_fields; // or ->show_table (inverse)
        $tx = $ok ? 'success' : ($_SESSION['user_id'] == $uid ? 'danger' : 'warning');
?>
                                <tbody class="einvoice-user-check-lists">
                                    <tr class="text-<?php echo $tx; ?>">
                                        <td class="te te-1">
                                            <i class="fa fa-fw fa-user"></i>
                                            <span><?php echo anchor('/users/form/' . $uid, $user->user_name); ?></span>
                                        </td>
                                        <td><i class="<?php echo $class_checks[$ok ? 0 : 2]; ?>"></i><?php _trans($ok ? 'yes' : 'no'); ?></td>
                                        <td>
<?php
        $reqs = []; // Re init ! important
        if ($user->einvoicing_empty_fields) {
            $reqs = []; // reuse
            foreach ($keys as $l => $key) {
                if ($user->{$key}) {
                    $reqs[] = '<span class="text-nowrap"><i class="' . $class_checks[$user->{$key}] . '"></i>'
                            . anchor(
                                '/users/form/' . $uid . '#user_' . $key,
                                trans($lang[$l]),
                                // ! Need add: "
                                $title_tip . ' #' . trans($lang[$l]) . ' (' . mb_trim(trans('field')) . ' ' . htmlsc($user->user_name) . ')"'
                            )
                            . '</span>';
                }
            }
        }
        // Show Ok or Errors
        $reqs = $reqs === [] ? trans('no') : implode(', ', $reqs);
?>
                                            <span><?php echo $reqs; ?></span>
                                        </td>
                                    </tr>
                                </tbody>
<?php
    } // End foreach users
?>
                            </table>
<?php
} // End if client ok
?>

                        </div>
                    </div>
                </div>
                <!-- /eInvoicing panel -->
<?php
}
?>