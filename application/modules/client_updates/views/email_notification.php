<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client information update</title>
</head>
<body style="margin:0;padding:24px;color:#1e2921;background:#f4f8f3;font:16px/1.5 Arial,Helvetica,sans-serif;">
<div style="max-width:680px;margin:0 auto;padding:28px;background:#ffffff;border:1px solid #d9e3da;border-radius:8px;">
    <h1 style="margin:0 0 8px;color:#173f22;font-size:26px;">Client information update</h1>
    <p style="margin:0 0 24px;color:#637168;">
        Confirmation <strong><?php echo html_escape($request['request_reference']); ?></strong><br>
        Submitted <?php echo html_escape($request['submitted_at']); ?> UTC
    </p>

    <h2 style="margin:24px 0 8px;color:#173f22;font-size:19px;">Contact information</h2>
    <table role="presentation" style="width:100%;border-collapse:collapse;">
        <tr><th style="padding:7px;text-align:left;border-bottom:1px solid #d9e3da;">Name</th><td style="padding:7px;border-bottom:1px solid #d9e3da;"><?php echo html_escape($request['full_name']); ?></td></tr>
        <tr><th style="padding:7px;text-align:left;border-bottom:1px solid #d9e3da;">Phone</th><td style="padding:7px;border-bottom:1px solid #d9e3da;"><?php echo html_escape($request['phone']); ?></td></tr>
        <tr><th style="padding:7px;text-align:left;border-bottom:1px solid #d9e3da;">Email</th><td style="padding:7px;border-bottom:1px solid #d9e3da;"><?php echo html_escape($request['email']); ?></td></tr>
    </table>

    <h2 style="margin:24px 0 8px;color:#173f22;font-size:19px;">Service Address</h2>
    <p style="margin:0;">
        <?php echo html_escape($request['service_address_1']); ?><br>
<?php if ($request['service_address_2']) { ?>
        <?php echo html_escape($request['service_address_2']); ?><br>
<?php } ?>
        <?php echo html_escape($request['service_city'] . ', ' . $request['service_state'] . ' ' . $request['service_zip']); ?>
    </p>

<?php foreach ($additional_service_addresses as $index => $address) { ?>
    <h2 style="margin:24px 0 8px;color:#173f22;font-size:19px;">Service Address <?php echo $index + 2; ?></h2>
    <p style="margin:0;">
        <?php echo html_escape($address['address_1']); ?><br>
<?php if ($address['address_2'] !== '') { ?>
        <?php echo html_escape($address['address_2']); ?><br>
<?php } ?>
        <?php echo html_escape($address['city'] . ', ' . $address['state'] . ' ' . $address['zip']); ?>
    </p>
<?php } ?>

    <h2 style="margin:24px 0 8px;color:#173f22;font-size:19px;">Mailing address</h2>
<?php if ((int) $request['mailing_same_as_service'] === 1) { ?>
    <p style="margin:0;">Same as Service Address</p>
<?php } else { ?>
    <p style="margin:0;">
        <?php echo html_escape($request['mailing_address_1']); ?><br>
<?php if ($request['mailing_address_2']) { ?>
        <?php echo html_escape($request['mailing_address_2']); ?><br>
<?php } ?>
        <?php echo html_escape($request['mailing_city'] . ', ' . $request['mailing_state'] . ' ' . $request['mailing_zip']); ?>
    </p>
<?php } ?>

    <p style="margin:28px 0 0;">
        <a href="<?php echo html_escape($review_url); ?>" style="display:inline-block;padding:11px 16px;color:#ffffff;background:#2f6a3d;border-radius:5px;text-decoration:none;font-weight:bold;">Review this request</a>
    </p>
</div>
</body>
</html>
