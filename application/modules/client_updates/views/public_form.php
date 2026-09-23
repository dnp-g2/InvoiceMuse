<?php $profile = client_update_profile(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update Your Client Information | <?php echo html_escape($profile['business_name']); ?></title>
    <meta name="description" content="Securely provide or confirm your contact and property information for <?php echo html_escape($profile['business_name']); ?>.">
<?php if ($turnstile_enabled) { ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php } ?>
    <style>
        :root {
            --green: #173f22;
            --leaf: #2f6a3d;
            --sun: #f7a83b;
            --ink: #1e2921;
            --muted: #637168;
            --line: #d9e3da;
            --soft: #f4f8f3;
            --error: #9f2525;
            --white: #fff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--soft);
            font: 16px/1.5 Arial, Helvetica, sans-serif;
        }
        a { color: var(--green); }
        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--line);
        }
        .nav {
            max-width: 980px;
            min-height: 78px;
            margin: 0 auto;
            padding: 10px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            color: var(--green);
            text-decoration: none;
        }
        .brand img { width: 58px; height: 58px; object-fit: contain; }
        .brand strong { display: block; font-size: 19px; line-height: 1.15; }
        .brand span { display: block; margin-top: 2px; color: var(--muted); font-size: 13px; }
        .back { font-weight: 700; text-decoration: none; white-space: nowrap; }
        .hero {
            padding: 54px 22px 92px;
            color: var(--white);
            background:
                radial-gradient(circle at 82% 10%, rgba(247, 168, 59, .62), transparent 20rem),
                linear-gradient(135deg, #244d2c, #102d18);
        }
        .hero-inner { max-width: 900px; margin: 0 auto; }
        .eyebrow {
            margin: 0 0 9px;
            color: #f9c77f;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1 { max-width: 720px; margin: 0; font-size: 46px; line-height: 1.08; }
        .hero p { max-width: 720px; margin: 16px 0 0; color: #e6efe6; font-size: 18px; }
        main { max-width: 900px; margin: -56px auto 64px; padding: 0 22px; }
        .card {
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 20px 55px rgba(26, 56, 32, .13);
        }
        .intro { padding: 28px 32px; border-bottom: 1px solid var(--line); }
        .intro h2 { margin: 0 0 8px; color: var(--green); font-size: 23px; }
        .intro p { margin: 0; color: var(--muted); }
        .required-note { margin-top: 8px !important; font-size: 14px; }
        form { padding: 8px 32px 32px; }
        fieldset { margin: 0; padding: 26px 0; border: 0; border-bottom: 1px solid var(--line); }
        fieldset:last-of-type { border-bottom: 0; }
        legend { width: 100%; margin: 0 0 18px; color: var(--green); font-size: 21px; font-weight: 800; }
        .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 6px; font-weight: 700; }
        .optional { color: var(--muted); font-size: 13px; font-weight: 400; }
        input[type="text"], input[type="tel"], input[type="email"] {
            width: 100%;
            min-height: 47px;
            padding: 10px 12px;
            color: var(--ink);
            background: var(--white);
            border: 1px solid #aebcaf;
            border-radius: 6px;
            font: inherit;
        }
        input:focus { outline: 3px solid rgba(247, 168, 59, .4); border-color: var(--green); }
        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 7px;
        }
        .check-row input { width: 19px; height: 19px; margin: 2px 0 0; accent-color: var(--leaf); }
        .check-row label { margin: 0; }
        #mailing-fields { margin-top: 20px; }
        .additional-address {
            margin-top: 20px;
            padding: 20px;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 7px;
        }
        .address-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .address-heading h3 { margin: 0; color: var(--green); font-size: 17px; }
        .secondary-button {
            min-height: 41px;
            padding: 8px 14px;
            color: var(--green);
            background: var(--white);
            border: 1px solid var(--leaf);
            border-radius: 6px;
            font: 700 14px Arial, Helvetica, sans-serif;
            cursor: pointer;
        }
        .secondary-button:hover { background: #edf5ec; }
        .remove-address { min-height: auto; padding: 5px 9px; color: #792020; border-color: #b86a6a; }
        .add-address { margin-top: 18px; }
        .address-limit { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .turnstile-wrap { margin: 0 0 20px; }
        .errors {
            margin: 22px 32px 0;
            padding: 16px 18px;
            color: #6f1515;
            background: #fff1f1;
            border: 1px solid #e5b1b1;
            border-radius: 7px;
        }
        .errors strong { display: block; margin-bottom: 6px; }
        .errors ul { margin: 0; padding-left: 21px; }
        .honeypot { position: absolute !important; left: -10000px !important; width: 1px !important; height: 1px !important; overflow: hidden !important; }
        .privacy {
            margin: 0 0 20px;
            padding: 17px 18px;
            color: var(--muted);
            background: var(--soft);
            border-left: 4px solid var(--leaf);
            font-size: 14px;
        }
        .submit {
            min-height: 49px;
            padding: 12px 21px;
            color: #172313;
            background: var(--sun);
            border: 0;
            border-radius: 6px;
            font: 800 16px Arial, Helvetica, sans-serif;
            cursor: pointer;
        }
        .submit:hover { background: #f5b657; }
        .help {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 18px;
            color: var(--muted);
            font-size: 14px;
        }
        .help p { margin: 0; }
        .help strong { color: var(--green); }
        footer { padding: 0 22px 34px; color: var(--muted); text-align: center; font-size: 13px; }
        [hidden] { display: none !important; }
        @media (max-width: 650px) {
            .nav { align-items: flex-start; }
            .brand span span { display: none; }
            .hero { padding-top: 40px; }
            h1 { font-size: 35px; }
            main { padding: 0 14px; }
            .intro, form { padding-left: 20px; padding-right: 20px; }
            .errors { margin-left: 20px; margin-right: 20px; }
            .fields { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .help { flex-direction: column; gap: 8px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <nav class="nav" aria-label="Page navigation">
        <a class="brand" href="/">
<?php if ($profile['logo'] !== '') { ?>
            <img src="<?php echo html_escape($profile['logo']); ?>" alt="<?php echo html_escape($profile['business_name']); ?> logo">
<?php } ?>
            <span><strong><?php echo html_escape($profile['business_name']); ?></strong><span>Client information update</span></span>
        </a>
        <a class="back" href="/">Back to website</a>
    </nav>
</header>
<section class="hero">
    <div class="hero-inner">
        <p class="eyebrow">Client records</p>
        <h1>Help us keep your information up to date.</h1>
        <p>Confirm your contact and property information so we can communicate with you about scheduling, estimates, invoices, and services.</p>
    </div>
</section>
<main>
    <div class="card">
        <div class="intro">
            <h2>Client Information Update</h2>
            <p>Please enter your current information below. <?php echo html_escape(ucfirst($profile['contact_name'])); ?> will review it before any account record is changed.</p>
            <p class="required-note">All fields are required unless marked optional.</p>
        </div>
<?php if (validation_errors() || $extra_errors !== []) { ?>
        <div class="errors" role="alert" aria-live="polite">
            <strong>Please correct the following:</strong>
            <ul>
                <?php echo validation_errors('<li>', '</li>'); ?>
<?php foreach ($extra_errors as $error) { ?>
                <li><?php echo html_escape($error); ?></li>
<?php } ?>
            </ul>
        </div>
<?php } ?>
        <form method="post" action="/update">
            <input type="hidden" name="<?php echo html_escape($this->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($this->security->get_csrf_hash()); ?>">
            <div class="honeypot" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <fieldset>
                <legend>Your contact information</legend>
                <div class="fields">
                    <div class="field full">
                        <label for="full_name">Full name</label>
                        <input id="full_name" name="full_name" type="text" maxlength="150" autocomplete="name" value="<?php echo html_escape(set_value('full_name')); ?>" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone number</label>
                        <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" value="<?php echo html_escape(set_value('phone')); ?>" required>
                    </div>
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" value="<?php echo html_escape(set_value('email')); ?>" required>
                    </div>
                </div>
            </fieldset>
            <fieldset>
                <legend>Service Address</legend>
                <div class="fields">
                    <div class="field full">
                        <label for="service_address_1">Street address</label>
                        <input id="service_address_1" name="service_address_1" type="text" maxlength="150" autocomplete="address-line1" value="<?php echo html_escape(set_value('service_address_1')); ?>" required>
                    </div>
                    <div class="field full">
                        <label for="service_address_2">Unit, building, or address line 2 <span class="optional">(optional)</span></label>
                        <input id="service_address_2" name="service_address_2" type="text" maxlength="150" autocomplete="address-line2" value="<?php echo html_escape(set_value('service_address_2')); ?>">
                    </div>
                    <div class="field">
                        <label for="service_city">City</label>
                        <input id="service_city" name="service_city" type="text" maxlength="100" autocomplete="address-level2" value="<?php echo html_escape(set_value('service_city')); ?>" required>
                    </div>
                    <div class="field">
                        <label for="service_state">State</label>
                        <input id="service_state" name="service_state" type="text" maxlength="100" autocomplete="address-level1" value="<?php echo html_escape(set_value('service_state')); ?>" required>
                    </div>
                    <div class="field">
                        <label for="service_zip">ZIP code</label>
                        <input id="service_zip" name="service_zip" type="text" maxlength="20" autocomplete="postal-code" inputmode="numeric" value="<?php echo html_escape(set_value('service_zip')); ?>" required>
                    </div>
                </div>
                <div id="additional-service-addresses">
<?php foreach ($additional_service_addresses as $index => $address) { ?>
                    <div class="additional-address">
                        <div class="address-heading">
                            <h3>Service Address <?php echo $index + 2; ?></h3>
                            <button class="secondary-button remove-address" type="button">Remove</button>
                        </div>
                        <div class="fields">
                            <div class="field full">
                                <label for="additional_address_<?php echo $index; ?>_address_1">Street address</label>
                                <input data-field="address_1" id="additional_address_<?php echo $index; ?>_address_1" name="additional_properties[<?php echo $index; ?>][address_1]" type="text" maxlength="150" value="<?php echo html_escape($address['address_1'] ?? ''); ?>" required>
                            </div>
                            <div class="field full">
                                <label for="additional_address_<?php echo $index; ?>_address_2">Unit, building, or address line 2 <span class="optional">(optional)</span></label>
                                <input data-field="address_2" id="additional_address_<?php echo $index; ?>_address_2" name="additional_properties[<?php echo $index; ?>][address_2]" type="text" maxlength="150" value="<?php echo html_escape($address['address_2'] ?? ''); ?>">
                            </div>
                            <div class="field">
                                <label for="additional_address_<?php echo $index; ?>_city">City</label>
                                <input data-field="city" id="additional_address_<?php echo $index; ?>_city" name="additional_properties[<?php echo $index; ?>][city]" type="text" maxlength="100" value="<?php echo html_escape($address['city'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label for="additional_address_<?php echo $index; ?>_state">State</label>
                                <input data-field="state" id="additional_address_<?php echo $index; ?>_state" name="additional_properties[<?php echo $index; ?>][state]" type="text" maxlength="100" value="<?php echo html_escape($address['state'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label for="additional_address_<?php echo $index; ?>_zip">ZIP code</label>
                                <input data-field="zip" id="additional_address_<?php echo $index; ?>_zip" name="additional_properties[<?php echo $index; ?>][zip]" type="text" maxlength="20" inputmode="numeric" value="<?php echo html_escape($address['zip'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
<?php } ?>
                </div>
                <button id="add-service-address" class="secondary-button add-address" type="button">Add another service address</button>
                <p class="address-limit">You can add up to 10 service addresses.</p>
            </fieldset>
            <fieldset>
                <legend>Mailing address</legend>
                <div class="check-row">
                    <input id="mailing_same_as_service" name="mailing_same_as_service" type="checkbox" value="1" aria-controls="mailing-fields" <?php echo set_checkbox('mailing_same_as_service', '1', $this->input->method() !== 'post'); ?>>
                    <label for="mailing_same_as_service">My mailing address is the same as my Service Address.</label>
                </div>
                <div id="mailing-fields" class="fields">
                    <div class="field full">
                        <label for="mailing_address_1">Mailing street address</label>
                        <input id="mailing_address_1" name="mailing_address_1" type="text" maxlength="150" value="<?php echo html_escape(set_value('mailing_address_1')); ?>">
                    </div>
                    <div class="field full">
                        <label for="mailing_address_2">Unit, building, or address line 2 <span class="optional">(optional)</span></label>
                        <input id="mailing_address_2" name="mailing_address_2" type="text" maxlength="150" value="<?php echo html_escape(set_value('mailing_address_2')); ?>">
                    </div>
                    <div class="field">
                        <label for="mailing_city">City</label>
                        <input id="mailing_city" name="mailing_city" type="text" maxlength="100" value="<?php echo html_escape(set_value('mailing_city')); ?>">
                    </div>
                    <div class="field">
                        <label for="mailing_state">State</label>
                        <input id="mailing_state" name="mailing_state" type="text" maxlength="100" value="<?php echo html_escape(set_value('mailing_state')); ?>">
                    </div>
                    <div class="field">
                        <label for="mailing_zip">ZIP code</label>
                        <input id="mailing_zip" name="mailing_zip" type="text" maxlength="20" inputmode="numeric" value="<?php echo html_escape(set_value('mailing_zip')); ?>">
                    </div>
                </div>
            </fieldset>
<?php if ($turnstile_enabled) { ?>
            <div class="turnstile-wrap">
                <div class="cf-turnstile" data-sitekey="<?php echo html_escape($turnstile_site_key); ?>" data-action="client_update" data-size="flexible"></div>
            </div>
<?php } ?>
            <p class="privacy">Your information will be used only for <?php echo html_escape($profile['business_name']); ?> business purposes, including customer records, scheduling, estimates, invoicing, and service-related communications.</p>
            <button class="submit" type="submit">Submit information update</button>
            <div class="help">
                <p>Need help? Contact <strong><?php echo html_escape($profile['contact_name']); ?></strong>.</p>
<?php if ($profile['contact_phone'] !== '') { ?>
                <p><a href="tel:<?php echo html_escape($profile['contact_tel']); ?>"><?php echo html_escape($profile['contact_phone']); ?></a></p>
<?php } ?>
            </div>
        </form>
    </div>
</main>
<footer>&copy; <?php echo date('Y'); ?> <?php echo html_escape($profile['business_name']); ?>. Your information is reviewed before account records are changed.</footer>
<script>
    (function () {
        var checkbox = document.getElementById('mailing_same_as_service');
        var fields = document.getElementById('mailing-fields');
        var required = ['mailing_address_1', 'mailing_city', 'mailing_state', 'mailing_zip'];
        function syncMailingAddress() {
            var same = checkbox.checked;
            fields.hidden = same;
            fields.querySelectorAll('input').forEach(function (input) { input.disabled = same; });
            required.forEach(function (id) { document.getElementById(id).required = !same; });
            checkbox.setAttribute('aria-expanded', same ? 'false' : 'true');
        }
        checkbox.addEventListener('change', syncMailingAddress);
        syncMailingAddress();

        var additionalAddresses = document.getElementById('additional-service-addresses');
        var addAddressButton = document.getElementById('add-service-address');

        function addressMarkup() {
            return '<div class="address-heading"><h3></h3><button class="secondary-button remove-address" type="button">Remove</button></div>' +
                '<div class="fields">' +
                '<div class="field full"><label>Street address</label><input data-field="address_1" type="text" maxlength="150" required></div>' +
                '<div class="field full"><label>Unit, building, or address line 2 <span class="optional">(optional)</span></label><input data-field="address_2" type="text" maxlength="150"></div>' +
                '<div class="field"><label>City</label><input data-field="city" type="text" maxlength="100" required></div>' +
                '<div class="field"><label>State</label><input data-field="state" type="text" maxlength="100" required></div>' +
                '<div class="field"><label>ZIP code</label><input data-field="zip" type="text" maxlength="20" inputmode="numeric" required></div>' +
                '</div>';
        }

        function syncAdditionalAddresses() {
            var groups = additionalAddresses.querySelectorAll('.additional-address');
            groups.forEach(function (group, index) {
                group.querySelector('h3').textContent = 'Service Address ' + (index + 2);
                group.querySelectorAll('input[data-field]').forEach(function (input) {
                    var field = input.getAttribute('data-field');
                    var id = 'additional_address_' + index + '_' + field;
                    input.id = id;
                    input.name = 'additional_properties[' + index + '][' + field + ']';
                    input.previousElementSibling.setAttribute('for', id);
                });
            });
            addAddressButton.disabled = groups.length >= 9;
            addAddressButton.textContent = groups.length >= 9 ? 'Maximum of 10 service addresses reached' : 'Add another service address';
        }

        addAddressButton.addEventListener('click', function () {
            if (additionalAddresses.querySelectorAll('.additional-address').length >= 9) {
                return;
            }
            var group = document.createElement('div');
            group.className = 'additional-address';
            group.innerHTML = addressMarkup();
            additionalAddresses.appendChild(group);
            syncAdditionalAddresses();
            group.querySelector('input').focus();
        });

        additionalAddresses.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-address')) {
                event.target.closest('.additional-address').remove();
                syncAdditionalAddresses();
                addAddressButton.focus();
            }
        });

        syncAdditionalAddresses();
    }());
</script>
</body>
</html>
