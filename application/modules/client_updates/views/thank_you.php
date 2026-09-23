<?php $profile = client_update_profile(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Information Received | <?php echo html_escape($profile['business_name']); ?></title>
    <style>
        :root { --green: #173f22; --leaf: #2f6a3d; --sun: #f7a83b; --ink: #1e2921; --muted: #637168; --line: #d9e3da; --soft: #f4f8f3; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: var(--soft); font: 16px/1.5 Arial, Helvetica, sans-serif; }
        main { min-height: 100vh; padding: 44px 20px; display: grid; place-items: center; background: radial-gradient(circle at 80% 10%, rgba(247,168,59,.5), transparent 18rem), linear-gradient(135deg, #244d2c, #102d18); }
        .card { width: min(100%, 620px); padding: 38px; background: #fff; border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 22px 60px rgba(0,0,0,.22); text-align: center; }
        .logo { width: 96px; height: 96px; object-fit: contain; }
        .check { width: 52px; height: 52px; margin: 12px auto 20px; display: grid; place-items: center; color: #fff; background: var(--leaf); border-radius: 50%; font-size: 28px; font-weight: 800; }
        h1 { margin: 0; color: var(--green); font-size: 34px; line-height: 1.15; }
        p { color: var(--muted); }
        .reference { margin: 22px 0; padding: 13px; background: var(--soft); border: 1px solid var(--line); border-radius: 6px; }
        .reference strong { color: var(--green); letter-spacing: .04em; }
        .button { display: inline-block; margin-top: 10px; padding: 12px 18px; color: #172313; background: var(--sun); border-radius: 6px; font-weight: 800; text-decoration: none; }
        .help { margin-top: 24px; font-size: 14px; }
        .help a { color: var(--green); }
    </style>
</head>
<body>
<main>
    <section class="card" aria-labelledby="page-title">
<?php if ($profile['logo'] !== '') { ?>
        <img class="logo" src="<?php echo html_escape($profile['logo']); ?>" alt="<?php echo html_escape($profile['business_name']); ?> logo">
<?php } ?>
        <div class="check" aria-hidden="true">&#10003;</div>
        <h1 id="page-title">Thank you. We received your information.</h1>
        <p><?php echo html_escape(ucfirst($profile['contact_name'])); ?> will review your request before any client record is changed. We will contact you if anything needs clarification.</p>
<?php if ($reference !== '') { ?>
        <p class="reference">Confirmation: <strong><?php echo html_escape($reference); ?></strong></p>
<?php } ?>
        <a class="button" href="/">Return to <?php echo html_escape($profile['business_name']); ?></a>
<?php if ($profile['contact_phone'] !== '') { ?>
        <p class="help">Questions? Call <?php echo html_escape($profile['contact_name']); ?> at <a href="tel:<?php echo html_escape($profile['contact_tel']); ?>"><?php echo html_escape($profile['contact_phone']); ?></a>.</p>
<?php } ?>
    </section>
</main>
</body>
</html>
