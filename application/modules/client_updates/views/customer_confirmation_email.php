<?php $profile = client_update_profile(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>We received your information update</title>
</head>
<body style="margin:0;padding:24px;color:#1e2921;background:#f4f8f3;font:16px/1.5 Arial,Helvetica,sans-serif;">
<div style="max-width:620px;margin:0 auto;padding:28px;background:#ffffff;border:1px solid #d9e3da;border-radius:8px;">
    <h1 style="margin:0 0 18px;color:#173f22;font-size:26px;">We received your information.</h1>
    <p>Hi <?php echo html_escape($first_name); ?>,</p>
    <p>Thank you for sending your updated contact and property information to <?php echo html_escape($profile['business_name']); ?>.</p>
    <p style="padding:14px;color:#173f22;background:#f4f8f3;border-left:4px solid #2f6a3d;">
        Confirmation: <strong><?php echo html_escape($request['request_reference']); ?></strong>
    </p>
    <p><?php echo html_escape(ucfirst($profile['contact_name'])); ?> will review your submission before making any changes to your client account. We will contact you if anything needs clarification.</p>
    <p>Please keep this confirmation for your records.</p>
<?php if ($profile['contact_phone'] !== '') { ?>
    <p style="margin:24px 0 0;color:#637168;">
        Questions? Call <?php echo html_escape($profile['contact_name']); ?> at <a href="tel:<?php echo html_escape($profile['contact_tel']); ?>" style="color:#2f6a3d;"><?php echo html_escape($profile['contact_phone']); ?></a>.
    </p>
<?php } ?>
</div>
</body>
</html>
